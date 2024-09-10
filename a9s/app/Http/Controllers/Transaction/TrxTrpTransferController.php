<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;

use App\Exceptions\MyException;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\TrxTrp;
use App\Models\MySql\IsUser;
use App\Models\MySql\TrxAbsen;
use App\Models\MySql\Ujalan;
use App\Models\MySql\UjalanDetail;

use App\Http\Requests\MySql\TrxTrpRequest;
use App\Http\Requests\MySql\TrxTrpTicketRequest;

use App\Http\Resources\MySql\TrxTrpResource;
use App\Http\Resources\MySql\IsUserResource;

use App\Exports\MyReport;
use App\Helpers\TrfDuitku;
use App\Models\MySql\Employee;
use App\PS\PSPotonganTrx;

class TrxTrpTransferController extends Controller
{
  private $admin;
  private $role;
  private $admin_id;
  private $permissions;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->role = $this->admin->the_user->hak_akses;
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
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.transfer.views']);

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
    $model_query = new TrxTrp();
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
          "jenis","pv_no","ticket_a_no","ticket_b_no","supir","kernet","no_pol","tanggal",
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

                if($r_val=='Done'){
                  $q->where("deleted",0)->where("req_deleted",0)->whereNotNull("pv_no")->Where(function ($q1){
                      $q1->orWhere(function ($q2){
                        $q2->where("jenis","TBS")->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_no");
                      });
                      $q1->orWhere(function ($q2){
                        $q2->where("jenis","TBSK")->whereNotNull("ticket_b_no");
                      });
                      $q1->orWhere(function ($q2){
                        $q2->whereIn("jenis",["CPO","PK"])->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_in_at")->whereNotNull("ticket_b_out_at")->where("ticket_b_bruto",">",1)->where("ticket_b_tara",">",1)->where("ticket_b_netto",">",1);
                      });
                  });
                }else{
                  $q->where("deleted",0)->where("req_deleted",0)->whereNull("pv_no")->Where(function ($q1){
                      $q1->orWhere(function ($q2){
                        $q2->where("jenis","TBS")->where(function($q2){
                          $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_no");
                        });
                      });
                      $q1->orWhere(function ($q2){
                        $q2->where("jenis","TBSK")->whereNull("ticket_b_no");
                      });
                      $q1->orWhere(function ($q2){
                        $q2->whereIn("jenis",["CPO","PK"])->where(function($q2){
                          $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_in_at")->orWhereNull("ticket_b_out_at")->orWhereNull("ticket_b_bruto")->orWhereNull("ticket_b_tara")->orWhereNull("ticket_b_netto");
                        });
                      });
                  });
                }
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
    
    // if(in_array($this->role,["Finance","Accounting"])){
    //   $filter_status = "pv_done";
    // }

    // if(in_array($this->role,["Marketing","MIS"])){
    //   $filter_status = "ticket_done";
    // }

    $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where('payment_method_id',2)->where('val4',1)->where('received_payment',0);

    $model_query = $model_query->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','trx_absens'=>function($q) {
      $q->select('id','trx_trp_id','created_at','updated_at')->where("status","B");
    }])->get();

    return response()->json([
      "data" => TrxTrpResource::collection($model_query),
    ], 200);
  }

  public function show(TrxTrpRequest $request)
  {
    $this->checkGATimeout();
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.transfer.view']);

    $model_query = TrxTrp::where("deleted",0)->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','uj_details','potongan'])->find($request->id);
    return response()->json([
      "data" => new TrxTrpResource($model_query),
    ], 200);
  }

  public function validasiAndTransfer(Request $request){
    $this->checkGATimeout();

    MyAdmin::checkScope($this->permissions, 'trp_trx.val5');

    $rules = [
      'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
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
      $model_query = TrxTrp::find($request->id);
      // if($model_query->cost_center_code==""){
      //   throw new \Exception("Minta Kasir Untuk Memasukkan Cost Center Code Terlebih Dahulu",1);
      // }

      if($model_query->val==0){
        throw new \Exception("Data Perlu Divalidasi oleh kasir terlebih dahulu",1);
      }

      if($model_query->val1==0){
        throw new \Exception("Data Perlu Divalidasi oleh mandor terlebih dahulu",1);
      }

      if($model_query->val2==0){
        throw new \Exception("Data Perlu Divalidasi oleh W/KTU terlebih dahulu",1);
      }

      // if(($model_query->jenis=='CPO' || $model_query->jenis=='PK') && $model_query->val3==0){
      //   throw new \Exception("Data Perlu Divalidasi oleh marketing terlebih dahulu",1);
      // }

      if($model_query->receive_payment == 1){
        throw new \Exception("Pembayaran sudah selesai",1);
      }


      $supir_id   = $model_query->supir_id;
      $kernet_id  = $model_query->kernet_id;
      $ttl_ps     = 0;
      $ttl_pk     = 0;

      $supir_money = 0;
      $kernet_money = 0;
      foreach ($model_query->uj_details2 as $key => $val) {
        if($val->xfor=='Kernet'){
          $kernet_money+=$val->amount*$val->qty;
        }else{
          $supir_money+=$val->amount*$val->qty;
        }
      }

      $ptg_ps_ids = "";
      $ptg_pk_ids = "";
      foreach ($model_query->potongan as $k => $v) {
        if($v->potongan_mst->employee_id == $supir_id){
          $ttl_ps+=$v->nominal_cut;
          $ptg_ps_ids.="#".$v->potongan_mst->id." ";
        }
  
        if($v->potongan_mst->employee_id == $kernet_id){
          $ttl_pk+=$v->nominal_cut;
          $ptg_pk_ids.="#".$v->potongan_mst->id." ";
        }
      }
      
      $supir_money -= $ttl_ps;
      $kernet_money -= $ttl_pk;

      if($model_query->supir_id){
        $supir = Employee::with('bank')->find($model_query->supir_id);
        if(!$supir->bank || !$supir->bank->code_duitku)
        throw new \Exception("Bank Belum Memiliki code duitku",1);

        if(!$supir->rek_no)
        throw new \Exception("Supir Belum Memiliki rekening",1);

        if($supir_money==0)
        throw new \Exception("Cek kembali master uang jalan detail PVR apakah xfor tidak ada berisi supir atau semua berisikan kernet",1);
      }

      if($model_query->kernet_id){
        $kernet = Employee::with('bank')->find($model_query->kernet_id);
        if(!$kernet->bank || !$kernet->bank->code_duitku)
        throw new \Exception("Bank Belum Memiliki code duitku",1);

        if(!$kernet->rek_no)
        throw new \Exception("Supir Belum Memiliki rekening",1);

        if($kernet_money==0)
        throw new \Exception("Cek kembali master uang jalan detail PVR apakah xfor tidak ada berisi kernet atau semua berisikan kernet",1);
      }


      if(isset($supir)){
        if(!$model_query->duitku_supir_disburseId){
          $result = TrfDuitku::generate_invoice($supir->bank->code_duitku,$supir->rek_no,$supir_money,"UJ#".$model_query->id);
          if($result){
            $model_query->duitku_supir_disburseId   = $result['disburseId'];
            $model_query->duitku_supir_inv_res_code = $result['responseCode'];
            $model_query->duitku_supir_inv_res_desc = $result['responseDesc'];
          }
        }
        
        if($model_query->duitku_supir_disburseId && $model_query->duitku_supir_trf_res_code!="00"){
          $result = TrfDuitku::generate_transfer($model_query->duitku_supir_disburseId,$supir->bank->code_duitku,$supir->rek_no,$supir_money,"UJ#".$model_query->id);
          if($result){
            $model_query->duitku_supir_trf_res_code = $result['responseCode'];
            $model_query->duitku_supir_trf_res_desc = $result['responseDesc'];
          }
        }
      }

      if(isset($kernet)){
        if(!$model_query->duitku_kernet_disburseId){
          $result = TrfDuitku::generate_invoice($kernet->bank->code_duitku,$kernet->rek_no,$kernet_money,"UJ#".$model_query->id);
          if($result){
            $model_query->duitku_kernet_disburseId   = $result['disburseId'];
            $model_query->duitku_kernet_inv_res_code = $result['responseCode'];
            $model_query->duitku_kernet_inv_res_desc = $result['responseDesc'];
            MyLog::logging($result,"disbust");
          }
        }
        
        if($model_query->duitku_kernet_disburseId && $model_query->duitku_kernet_trf_res_code!="00"){
          $result = TrfDuitku::generate_transfer($model_query->duitku_kernet_disburseId,$kernet->bank->code_duitku,$kernet->rek_no,$kernet_money,"UJ#".$model_query->id);
          if($result){
            $model_query->duitku_kernet_trf_res_code = $result['responseCode'];
            $model_query->duitku_kernet_trf_res_desc = $result['responseDesc'];
            MyLog::logging($result,"disbust");
          }
        }
      }


      if((!isset($kernet) && $model_query->duitku_supir_trf_res_code=="00") || (isset($kernet) && $model_query->duitku_supir_trf_res_code=="00" && $model_query->duitku_kernet_trf_res_code=="00")){
        $model_query->received_payment=1;

        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val5',true)){
          $model_query->val5 = 1;
          $model_query->val5_user = $this->admin_id;
          $model_query->val5_at = $t_stamp;
        }
      }

      $model_query->save();

      MyLog::sys("trx_trp",$model_query->id,"transfer");

      DB::commit();

      $error=[];
      if($model_query->duitku_supir_inv_res_code!=="00"){
        $msg = TrfDuitku::info_inv($model_query->duitku_supir_inv_res_code);
        if($msg==""){
          array_push($error,"Supir:Unknown Error");
        }else{
          array_push($error,"Supir:".$msg);
        }
      }

      if(isset($kernet) && $model_query->duitku_kernet_inv_res_code!=="00"){
        $msg = TrfDuitku::info_inv($model_query->duitku_kernet_inv_res_code);
        if($msg==""){
          array_push($error,"Kernet:Unknown Error");
        }else{
          array_push($error,"Kernet:".$msg);
        }
      }

      if($error){
        return response()->json([
          "message" => implode(",",$error),
        ], 400);
      }

      return response()->json([
        "message" => "Proses validasi data berhasil",
        "val5"=>$model_query->val5,
        "val5_user"=>$model_query->val5_user,
        "val5_at"=>$model_query->val5_at,
        "val5_by"=>$model_query->val5_user ? new IsUserResource(IsUser::find($model_query->val5_user)) : null,
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