<?php

namespace App\Http\Controllers\ExtraMoney;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;

use App\Exceptions\MyException;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\ExtraMoneyTrx;
use App\Models\MySql\IsUser;
use App\Models\MySql\TrxAbsen;
use App\Models\MySql\Ujalan;
use App\Models\MySql\UjalanDetail;

use App\Http\Requests\MySql\ExtraMoneyTrxRequest;
use App\Http\Requests\MySql\ExtraMoneyTrxTicketRequest;

use App\Http\Resources\MySql\ExtraMoneyTrxResource;
use App\Http\Resources\MySql\IsUserResource;

use App\Exports\MyReport;
use App\Helpers\TrfDuitku;
use App\Models\MySql\Employee;

class ExtraMoneyTrxTransferController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();
  }

  public function checkGATimeout(){
    if($this->admin->the_user->ga_timeout==null){
      throw new MyException(["message"=>"Forbidden"],400);
    }

    if(strtotime($this->admin->the_user->ga_timeout) <= strtotime(date("Y-m-d H:i:s.v"))){
      IsUser::where("id",$this->admin->the_user)->update(["ga_timeout"=>null]);
      throw new MyException(["message"=>"Timeout"],400);
    }    
  }
  public function index(Request $request, $download = false)
  {
    if(!$download)
    MyAdmin::checkMultiScope($this->permissions, ['extra_money_trx.transfer.views']);

    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 100; // Limit +> Much Data
    if (isset($request->limit)) {
      if ($request->limit <= 250) {
        $limit = $request->limit;
      } else {
        throw new MyException(["message" => "Max Limit 250"]);
      }
    }

    $offset = isset($request->offset) ? (int) $request->offset : 0; // example offset 400 start from 401

    //======================================================================================================
    // Jika Halaman Ditentutkan maka $offset akan disesuaikan
    //======================================================================================================
    if (isset($request->page)) {
      $page =  (int) $request->page;
      $offset = ($page * $limit) - $limit;
    }


    //======================================================================================================
    // Init Model
    //======================================================================================================
    $model_query = new ExtraMoneyTrx();
    if (!$download) {
      $model_query = $model_query->offset($offset)->limit($limit);
    }

    $first_row=[];
    if($request->first_row){
      $first_row 	= json_decode($request->first_row, true);
    }

    //======================================================================================================
    // Model Filter | Example $request->like = "username:%username,role:%role%,name:role%,";
    //======================================================================================================

    if ($request->like) {
      $like_lists = [];

      $likes = explode(",", $request->like);
      foreach ($likes as $key => $like) {
        $side = explode(":", $like);
        $side[1] = isset($side[1]) ? $side[1] : '';
        $like_lists[$side[0]] = $side[1];
      }

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists){
          
          $list_to_like = ["id","xto","tipe",
          "jenis","pv_no","employee","no_pol","tanggal",
          "cost_center_code","cost_center_desc","pvr_id","pvr_no","transition_target"];
    
          foreach ($list_to_like as $key => $v) {
            if (isset($like_lists[$v])) {
              $q->orWhere($v, "like", $like_lists[$v]);
            }
          }
    
          // if (isset($like_lists["requested_name"])) {
          //   $q->orWhereIn("requested_by", function($q2)use($like_lists) {
          //     $q2->from('is_users')
          //     ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
          //   });
          // }
    
          // if (isset($like_lists["confirmed_name"])) {
          //   $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
          //     $q2->from('is_users')
          //     ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
          //   });
          // }
        });        
      }

      
    }

    //======================================================================================================
    // Model Sorting And Filtering
    //======================================================================================================

    $fm_sorts=[];
    if($request->filter_model){
      $filter_model = json_decode($request->filter_model,true);
  
      foreach ($filter_model as $key => $value) {
        if($value["sort_priority"] && $value["sort_type"]){
          array_push($fm_sorts,[
            "key"    =>$key,
            "priority"=>$value["sort_priority"],
          ]);
        }
      }

      if(count($fm_sorts)>0){
        usort($fm_sorts, function($a, $b) {return (int)$a['priority'] - (int)$b['priority'];});
        foreach ($fm_sorts as $key => $value) {
          $model_query = $model_query->orderBy($value['key'], $filter_model[$value['key']]["sort_type"]);
          if (count($first_row) > 0) {
            $sort_symbol = $filter_model[$value['key']]["sort_type"] == "desc" ? "<=" : ">=";
            $model_query = $model_query->where($value['key'],$sort_symbol,$first_row[$value['key']]);
          }
        }
      }

      $model_query = $model_query->where(function ($q)use($filter_model,$request){

        foreach ($filter_model as $key => $value) {
          if(!isset($value['type'])) continue;

          if(array_search($key,['status'])!==false){
            // if(array_search($value['type'],['string','number'])!==false && $value['value_1']){

            //   if($value["operator"]=='exactly_same'){
            //     $q->Where($key, $value["value_1"]);
            //   }
  
            //   if($value["operator"]=='exactly_not_same'){
            //     $q->Where($key,"!=", $value["value_1"]);
            //   }
  
            //   if($value["operator"]=='same'){
            //     $v_val1=explode(",",$value["value_1"]);
            //     $q->where(function ($q1)use($filter_model,$v_val1,$key){
            //       foreach ($v_val1 as $k1 => $v1) {
            //         $q1->orwhere($key,"like", '%'.$v1.'%');
            //       }
            //     });
            //   }
  
            //   if($value["operator"]=='not_same'){
            //     $v_val1=explode(",",$value["value_1"]);
            //     $q->where(function ($q1)use($filter_model,$v_val1,$key){
            //       foreach ($v_val1 as $k1 => $v1) {
            //         $q1->orwhere($key,"not like", '%'.$v1.'%');
            //       }
            //     });
            //   }
  
            //   if($value["operator"]=='more_then'){
            //     $q->Where($key,">", $value["value_1"]);
            //   }
              
            //   if($value["operator"]=='more_and'){
            //     $q->Where($key,">=", $value["value_1"]);
            //   }
  
            //   if($value["operator"]=='less_then'){
            //     $q->Where($key,"<", $value["value_1"]);
            //   }
  
            //   if($value["operator"]=='less_and'){
            //     $q->Where($key,"<=", $value["value_1"]);
            //   }
            // }
  
            // if(array_search($value['type'],['date','datetime'])!==false){
            //   if($value['value_1'] || $value['value_2']){
            //     $date_from = $value['value_1'];
            //     if(!$date_from)
            //     throw new MyException([ "message" => "Date From pada ".$value['label']." harus diisi" ], 400);
          
            //     if(!strtotime($date_from))
            //     throw new MyException(["message"=>"Format Date pada ".$value['label']." From Tidak Cocok"], 400);

              
            //     $date_to = $value['value_2'];                
            //     if(!$date_to)
            //     throw new MyException([ "message" => "Date To pada ".$value['label']." harus diisi" ], 400);
              
            //     if(!strtotime($date_to))
            //     throw new MyException(["message"=>"Format Date To pada ".$value['label']." Tidak Cocok"], 400);
            
            //     $date_from = date($value['type']=='datetime'?"Y-m-d H:i:s.v" :"Y-m-d",strtotime($date_from));
            //     $date_to = date($value['type']=='datetime'?"Y-m-d H:i:s.v" :"Y-m-d",strtotime($date_to));
            //     // throw new MyException(["message"=>"Format Date To pada ".$date_to." Tidak Cocok".$request->_TimeZoneOffset], 400);

            //     $q->whereBetween($key,[$date_from,$date_to]);
            //   }
            // }

            if(array_search($value['type'],['select'])!==false && $value['value_1']){

              if(array_search($key,['status'])!==false){
                $r_val = $value['value_1'];
                if($value["operator"]=='exactly_same'){
                }else {
                  if($r_val=='Undone'){
                    $r_val='Done';
                  }else{
                    $r_val='Undone';
                  };
                }

                // if($r_val=='Done'){
                //   $q->where("deleted",0)->where("req_deleted",0)->whereNotNull("pv_no")->Where(function ($q1){
                //       $q1->orWhere(function ($q2){
                //         $q2->where("jenis","TBS")->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_no");
                //       });
                //       $q1->orWhere(function ($q2){
                //         $q2->where("jenis","TBSK")->whereNotNull("ticket_b_no");
                //       });
                //       $q1->orWhere(function ($q2){
                //         $q2->whereIn("jenis",["CPO","PK"])->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_in_at")->whereNotNull("ticket_b_out_at")->where("ticket_b_bruto",">",1)->where("ticket_b_tara",">",1)->where("ticket_b_netto",">",1);
                //       });
                //   });
                // }else{
                //   $q->where("deleted",0)->where("req_deleted",0)->whereNull("pv_no")->Where(function ($q1){
                //       $q1->orWhere(function ($q2){
                //         $q2->where("jenis","TBS")->where(function($q2){
                //           $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_no");
                //         });
                //       });
                //       $q1->orWhere(function ($q2){
                //         $q2->where("jenis","TBSK")->whereNull("ticket_b_no");
                //       });
                //       $q1->orWhere(function ($q2){
                //         $q2->whereIn("jenis",["CPO","PK"])->where(function($q2){
                //           $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_in_at")->orWhereNull("ticket_b_out_at")->orWhereNull("ticket_b_bruto")->orWhereNull("ticket_b_tara")->orWhereNull("ticket_b_netto");
                //         });
                //       });
                //   });
                // }
              }
            }
          }else{
            MyLib::queryCheck($value,$key,$q);
          }
        }
        
         
       
        // if (isset($like_lists["requested_name"])) {
        //   $q->orWhereIn("requested_by", function($q2)use($like_lists) {
        //     $q2->from('is_users')
        //     ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
        //   });
        // }
  
        // if (isset($like_lists["confirmed_name"])) {
        //   $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
        //     $q2->from('is_users')
        //     ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
        //   });
        // }
      });  
    }
    
    if(!$request->filter_model || count($fm_sorts)==0){
      $model_query = $model_query->orderBy('tanggal', 'DESC')->orderBy('id','DESC');
    }

    $filter_status = $request->filter_status;
    
    $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->whereIn('payment_method_id',[2,3])->where('val1',1)->where('val2',1)->where('val3',1)->where('received_payment',0)->whereNull('trx_trp_id');

    $model_query = $model_query->with(['val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','deleted_by','req_deleted_by','payment_method','extra_money','employee'])->get();

    return response()->json([
      "data" => ExtraMoneyTrxResource::collection($model_query),
    ], 200);
  }

  public function show(ExtraMoneyTrxRequest $request)
  {
    $this->checkGATimeout();
    MyAdmin::checkMultiScope($this->permissions, ['extra_money_trx.transfer.view']);

    $model_query = ExtraMoneyTrx::where("req_deleted",0)->where("deleted",0)->with(['val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','deleted_by','req_deleted_by','payment_method','extra_money','employee'])->find($request->id);
    return response()->json([
      "data" => new ExtraMoneyTrxResource($model_query),
    ], 200);
  }

  public function validasiAndTransfer(Request $request){
    $this->checkGATimeout();

    MyAdmin::checkMultiScope($this->permissions, ['extra_money_trx.transfer.do_transfer']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\ExtraMoneyTrx,id",
    ];

    $messages = [
      'id.required' => 'ID tidak boleh kosong',
      'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query = ExtraMoneyTrx::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->cost_center_code==""){
      //   throw new \Exception("Minta Kasir Untuk Memasukkan Cost Center Code Terlebih Dahulu",1);
      // }

      if($model_query->val1==0){
        throw new \Exception("Data Perlu Divalidasi oleh kasir terlebih dahulu",1);
      }

      if($model_query->val2==0){
        throw new \Exception("Data Perlu Divalidasi oleh mandor terlebih dahulu",1);
      }

      if($model_query->val3==0){
        throw new \Exception("Data Perlu Divalidasi oleh W/KTU terlebih dahulu",1);
      }

      // if(($model_query->jenis=='CPO' || $model_query->jenis=='PK') && $model_query->val3==0){
      //   throw new \Exception("Data Perlu Divalidasi oleh marketing terlebih dahulu",1);
      // }

      if($model_query->received_payment == 1){
        throw new \Exception("Pembayaran sudah selesai",1);
      }

      if($model_query->trx_trp_id){
        throw new \Exception("Pembayaran harus melalui transfer uang jalan",1);
      }

      $SYSOLD                     = clone($model_query);
      

      $employee_id   = $model_query->employee_id;
      $ttl_ps     = 0;
      $ttl_pk     = 0;

      $em = $model_query->extra_money;
      $employee_money = $em->nominal * $em->qty;

      if($employee_money < MyLib::$min_transfer)
      throw new \Exception("Nilai yang akan di transfer kurang dari nilai minimal transfer".$employee_money,1);

      if($model_query->employee_id){
        $employee = Employee::exclude(['attachment_1','attachment_2'])->with('bank')->find($model_query->employee_id);
        if(!$employee->bank || !$employee->bank->code_duitku)
        throw new \Exception("Bank Belum Memiliki code duitku",1);

        if(!$employee->rek_no)
        throw new \Exception("Pekerja Belum Memiliki rekening",1);
      }


      if(isset($employee) && $employee_money > 0){
        if(!$model_query->duitku_employee_disburseId){
          $result = TrfDuitku::generate_invoice($employee->bank->code_duitku,$employee->rek_no,$employee_money,"EM#".$model_query->id,'',$model_query->payment_method_id);
          if($result){
            $model_query->duitku_employee_disburseId   = $result['disburseId'];
            $model_query->duitku_employee_inv_res_code = $result['responseCode'];
            $model_query->duitku_employee_inv_res_desc = $result['responseDesc'];
          }
          // MyLog::logging($result);
        }
        
        if($model_query->duitku_employee_disburseId && $model_query->duitku_employee_inv_res_code == "00" && $model_query->duitku_employee_trf_res_code!="00"){
          $result = TrfDuitku::generate_transfer($model_query->duitku_employee_disburseId,$employee->bank->code_duitku,$employee->rek_no,$employee_money,"EM#".$model_query->id,'',$model_query->payment_method_id);
          if($result){
            $model_query->duitku_employee_trf_res_code = $result['responseCode'];
            $model_query->duitku_employee_trf_res_desc = $result['responseDesc'];
          }
          // MyLog::logging($result);
        }

        if($model_query->duitku_employee_disburseId && $model_query->duitku_employee_trf_res_code=="00" && !$model_query->rp_employee_user){
          $model_query->rp_employee_user = $this->admin_id;               
          $model_query->rp_employee_at   = $t_stamp;               
        }
      }


      if($model_query->duitku_employee_trf_res_code=="00"){
        $model_query->received_payment=1;


        if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.val4',true)){
          $model_query->val4 = 1;
          $model_query->val4_user = $this->admin_id;
          $model_query->val4_at = $t_stamp;
        }

        if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.val5',true)){
          $model_query->val5 = 1;
          $model_query->val5_user = $this->admin_id;
          $model_query->val5_at = $t_stamp;
        }

        if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.val6',true)){
          $model_query->val6 = 1;
          $model_query->val6_user = $this->admin_id;
          $model_query->val6_at = $t_stamp;
        }

        $model_query->employee_rek_no   = $employee->rek_no;
        $model_query->employee_rek_name = $employee->rek_name;
      }

      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("extra_money_trx",$model_query->id,"transfer",$SYSNOTE);

      DB::commit();

      $error=[];
      if($model_query->duitku_employee_inv_res_code!=="00"){
        $msg = TrfDuitku::info_inv($model_query->duitku_employee_inv_res_code);
        if($msg==""){
          array_push($error,"Pekerja:Unknown Error");
        }else{
          array_push($error,"Pekerja:".$msg);
        }
      }

      if($model_query->duitku_employee_trf_res_code!=="00"){
        $msg = TrfDuitku::info_inv($model_query->duitku_employee_trf_res_code);
        if($msg==""){
          array_push($error,"Pekerja:Unknown Error");
        }else{
          array_push($error,"Pekerja:".$msg);
        }
      }

      if($error){
        return response()->json([
          "message" => implode(",",$error),
        ], 400);
      }

      return response()->json([
        "message" => "Proses validasi data berhasil",
        "val4"=>$model_query->val4,
        "val4_user"=>$model_query->val4_user,
        "val4_at"=>$model_query->val4_at,
        "val4_by"=>$model_query->val4_user ? new IsUserResource(IsUser::find($model_query->val4_user)) : null,
        "val5"=>$model_query->val5,
        "val5_user"=>$model_query->val5_user,
        "val5_at"=>$model_query->val5_at,
        "val5_by"=>$model_query->val5_user ? new IsUserResource(IsUser::find($model_query->val5_user)) : null,
        "val6"=>$model_query->val6,
        "val6_user"=>$model_query->val6_user,
        "val6_at"=>$model_query->val6_at,
        "val6_by"=>$model_query->val6_user ? new IsUserResource(IsUser::find($model_query->val6_user)) : null,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      return response()->json([
        "message" => "Proses ubah data gagal",
      ], 400);
    }

  }

}