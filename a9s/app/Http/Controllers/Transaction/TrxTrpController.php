<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;
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
use App\Models\MySql\ExtraMoneyTrx;
use App\PS\PSPotonganTrx;
use App\PS\PSTripSupirKernet;

class TrxTrpController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'trx_trp';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function index(Request $request, $download = false,$custome_info = "")
  {
    if(!$download)
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.views','trp_trx.ticket.views','trp_trx.report.views']);

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

          $list_to_like_uj = [
            ["uj_asst_opt","asst_opt"],
            ["uj_xto","xto"],
          ];
          foreach ($list_to_like_uj as $key => $v) {
            if (isset($like_lists[$v[0]])) {
              $q->orWhereIn('id_uj', function($q2)use($like_lists,$v) {
                $q2->from('is_uj')
                ->select('id')->where($v[1],'like',$like_lists[$v[0]]);          
              });
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
          if(array_search($value['key'],['uj_asst_opt','uj_xto'])!==false){
            $model_query = MyLib::queryOrderP1($model_query,"uj","id_uj",$value['key'],$filter_model[$value['key']]["sort_type"],"is_uj");
          } else{
            $model_query = $model_query->orderBy($value['key'], $filter_model[$value['key']]["sort_type"]);
            if (count($first_row) > 0) {
              $sort_symbol = $filter_model[$value['key']]["sort_type"] == "desc" ? "<=" : ">=";
              $model_query = $model_query->where($value['key'],$sort_symbol,$first_row[$value['key']]);
            }
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
          }else if(array_search($key,['uj_asst_opt','uj_xto'])!==false){
            MyLib::queryCheckP1Dif("uj",$value,$key,$q,'is_uj',"id_uj");
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

    if($filter_status=="pv_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->whereNotNull("pv_no");
    }

    // if($filter_status=="ticket_done"){
    //   $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where(function ($q){
    //       $q->orWhere(function ($q1){
    //         $q1->where("jenis","TBS")->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_no");
    //       });
    //       $q->orWhere(function ($q1){
    //         $q1->where("jenis","TBSK")->whereNotNull("ticket_b_no");
    //       });
    //       $q->orWhere(function ($q1){
    //         $q1->whereIn("jenis",["CPO","PK"])->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_in_at")->whereNotNull("ticket_b_out_at")->where("ticket_b_bruto",">",1)->where("ticket_b_tara",">",1)->where("ticket_b_netto",">",1);
    //       });
    //   });
    // }

    // if($filter_status=="ticket_not_done"){
    //   $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where(function ($q){
    //       $q->orWhere(function ($q1){
    //         $q1->where("jenis","TBS")->where(function($q2){
    //           $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_no");
    //         });
    //       });
    //       $q->orWhere(function ($q1){
    //         $q1->where("jenis","TBSK")->whereNull("ticket_b_no");
    //       });
    //       $q->orWhere(function ($q1){
    //         $q1->whereIn("jenis",["CPO","PK"])->where(function($q2){
    //           $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_in_at")->orWhereNull("ticket_b_out_at")->orWhereNull("ticket_b_bruto")->orWhereNull("ticket_b_tara")->orWhereNull("ticket_b_netto");
    //         });
    //       });
    //   });
    // }

    if($filter_status=="ticket_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where('val_ticket',1);
    }

    if($filter_status=="ticket_not_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where('val_ticket',0);
    }

    if($filter_status=="pv_not_done"){
      $model_query = $model_query->where("deleted",0)->whereNull("pv_no")->where("req_deleted",0);
    }

    if($filter_status=="ritase_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where('ritase_val',1);
    }

    if($filter_status=="mandor_trx_unverified"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where('val',1)->where('val1',0);
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    if($filter_status=="req_deleted"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",1);
    }

    if($download && $custome_info=='plus_uj_details'){
      $model_query = $model_query->with('uj_details');
    }

    $model_query = $model_query->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','potongan','uj','salary_paid','trx_absens'=>function($q) {
      $q->select('id','trx_trp_id','created_at','updated_at')->where("status","B");
    }])->get();

    $resources = TrxTrpResource::collection($model_query);

    if ($filter_status == "pv_not_done") {
      $resources = $resources->map(function ($resource) {
        $newResource = collect($resource);
        $return = self::permit_continue_trx($resource,true);
        // $supir_absen = TrxTrp::where(function($q)use($resource){
        //     $q->where("supir_id",$resource['supir_id']);
        //     $q->orWhere("kernet_id",$resource['supir_id']);
        // })
        // ->where("deleted",0)
        // ->where("req_deleted",0)
        // ->orderBy("tanggal","desc")
        // ->orderBy("id","desc")
        // // ->where("tanggal",">=","2025-10-01")
        // ->where(
        //   function ($q){
        //     $q->whereNull('ritase_leave_at');
        //     $q->orWhereNull('ritase_arrive_at');
        //     $q->orWhereNull('ritase_return_at');
        //     $q->orWhereNull('ritase_till_at');
        //   }
        // )->get();
                
        // if(count($supir_absen) > 1 && !$supir_absen[1]->ritase_val2)
        // array_push($return,$supir_absen[1]->id);
      
        // if($resource['kernet_id']){
        //     $kernet_absen=TrxTrp::where(function($q)use($resource){
        //         $q->where("supir_id",$resource['kernet_id']);
        //         $q->orWhere("kernet_id",$resource['kernet_id']);
        //     })
        //     ->where("deleted",0)
        //     ->where("req_deleted",0)
        //     ->orderBy("tanggal","desc")
        //     ->orderBy("id","desc")
        //     // ->where("tanggal",">=","2025-10-01")
        //     ->where(
        //       function ($q){
        //         $q->whereNull('ritase_leave_at');
        //         $q->orWhereNull('ritase_arrive_at');
        //         $q->orWhereNull('ritase_return_at');
        //         $q->orWhereNull('ritase_till_at');
        //       }
        //     )->get();
            
        //     if(count($kernet_absen) > 1 && !$kernet_absen[1]->ritase_val2)
        //     array_push($return,$kernet_absen[1]->id);
        // }
        $newResource['absen_not_done'] = $return; // Assign the modified values to the new array
        return $newResource; // Return the new array
      });
    }

    return response()->json([
      "data" => $resources,
    ], 200);
  }

  public function show(TrxTrpRequest $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.view','trp_trx.ticket.view']);

    $model_query = TrxTrp::with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','uj','trx_absens'=>function ($q){
      $q->select("created_at","id","trx_trp_id","updated_at");
      $q->where("status","B");
    },'potongan'])->find($request->id);
    return response()->json([
      "data" => new TrxTrpResource($model_query),
    ], 200);
  }

  public function fullView(TrxTrpRequest $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.view','trp_trx.ticket.view']);

    $model_query = TrxTrp::with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','ritase_val_by','ritase_val1_by','ritase_val2_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','uj','uj_details','uj_details2'
    ,'extra_money_trxs'=>function ($q){
      $q->with(['employee','extra_money','payment_method','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by']);
    },'potongan'])->find($request->id);

    $data = new TrxTrpResource($model_query);

    return response()->json([
      // "data" => new TrxTrpResource($model_query),
      "data" => $data,
    ], 200);
  }

  public function mandorGetVerifyTrx(TrxTrpRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.ritase.views');

    $model_query = TrxTrp::where("deleted",0)->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','val_ticket_by','deleted_by','req_deleted_by','trx_absens','uj','uj_details'])->find($request->id);
    return response()->json([
      "data" => new TrxTrpResource($model_query),
    ], 200);
  }

  public function mandorGetVerifySet(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.val1');

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

      if($model_query->val1){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }
  
      self::permit_continue_trx($model_query);

      $SYSOLD                     = clone($model_query);
      $model_query->val1 = 1;
      $model_query->val1_user = $this->admin_id;
      $model_query->val1_at = $t_stamp;

      $model_query->save();
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 

      MyLog::sys("trx_trp",$model_query->id,"approve",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses validasi data berhasil",
        "val1"=>$model_query->val1,
        "val1_user"=>$model_query->val1_user,
        "val1_at"=>$model_query->val1_at,
        "val1_by"=>$model_query->val1_user ? new IsUserResource(IsUser::find($model_query->val1_user)) : null,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      return response()->json([
        "getCode" => $e->getCode(),
        "line" => $e->getLine(),
        "message" => $e->getMessage(),
      ], 400);
      return response()->json([
        "message" => "Proses ubah data gagal",
      ], 400);
    }

  }

  public function store(TrxTrpRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.create');

    $t_stamp = date("Y-m-d H:i:s");

    DB::beginTransaction();
    try {
      $supir_dt =\App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where('id',$request->supir_id)->available()->verified()->first();
      if(!$supir_dt)
      throw new \Exception("Supir tidak terdaftar",1);

      if($request->kernet_id){
        $kernet_dt =\App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where('id',$request->kernet_id)->available()->verified()->first();
        if(!$kernet_dt)
        throw new \Exception("Kernet tidak terdaftar",1);
      }

      if($request->supir_id == $request->kernet_id && $request->supir_id != 1)
      throw new \Exception("Supir Dan Kernet Tidak Boleh Orang Yang Sama",1);

      if(in_array($request->payment_method_id , [2,3,4,5])){
        if(!$supir_dt->rek_no && $supir_dt->id != 1)
        throw new \Exception("Tidak ada no rekening supir",1);

        if(isset($kernet_dt) && !$kernet_dt->rek_no && $kernet_dt->id != 1)
        throw new \Exception("Tidak ada no rekening kernet",1);
      }

      $model_query                  = new TrxTrp();      
      $model_query->tanggal         = $request->tanggal;

      $ujalan = \App\Models\MySql\Ujalan::where("id",$request->id_uj)
      ->where("jenis",$request->jenis)
      ->where("deleted",0)
      ->with(['details2'=>function ($q){
        $q->whereIn('xfor',['Supir','Kernet']);
      }])
      ->lockForUpdate()
      ->first();

      if(!$ujalan) 
      throw new \Exception("Silahkan Isi Data Ujalan Dengan Benar",1);

      if($ujalan->asst_opt=="DENGAN KERNET" && !isset($kernet_dt))
      throw new \Exception("KERNET HARUS DIPILIH",1);

      $is_uj_dinas_supir =  false;
      $is_uj_dinas_kernet =  false;

      foreach ($ujalan->details2 as $kd => $vd) {
        if($vd->xfor=='Supir' && str_contains(strtolower($vd->ac_account_name), 'dinas') ){
          $is_uj_dinas_supir = true;
        }

        if($vd->xfor=='Kernet' && str_contains(strtolower($vd->ac_account_name), 'dinas') ){
          $is_uj_dinas_kernet = true;
        }
      }

      if($is_uj_dinas_supir && $supir_dt->workers_from == env('APP_NAME') ){
        throw new \Exception("Supir bukan dari pabrik lain",1);
      }

      if(!$is_uj_dinas_supir && $supir_dt->workers_from != env('APP_NAME') ){
        throw new \Exception("Supir berasal dari pabrik lain",1);
      }

      if(isset($kernet_dt)){
        if($is_uj_dinas_kernet && $kernet_dt->workers_from == env('APP_NAME')){
          throw new \Exception("Kernet bukan dari pabrik lain",1);
        }

        if(!$is_uj_dinas_kernet && $kernet_dt->workers_from != env('APP_NAME') ){
          throw new \Exception("Kernet berasal dari pabrik lain",1);
        }
      }

      $model_query->id_uj               = $ujalan->id;
      $model_query->jenis               = $request->jenis;
      $model_query->xto                 = $ujalan->xto;
      $model_query->tipe                = $ujalan->tipe;
      $model_query->amount              = $ujalan->harga;

      if($ujalan->transition_from){
        $model_query->transition_target = $ujalan->transition_from;
        $model_query->transition_type   = "From";
      }

      $model_query->supir_id          = $supir_dt->id;
      $model_query->supir             = $supir_dt->name;
      $model_query->supir_rek_no      = $supir_dt->rek_no;
      $model_query->supir_rek_name    = $supir_dt->rek_name;

      if(isset($kernet_dt)){
        $model_query->kernet_id       = $kernet_dt->id;
        $model_query->kernet          = $kernet_dt->name;
        $model_query->kernet_rek_no   = $kernet_dt->rek_no;
        $model_query->kernet_rek_name = $kernet_dt->rek_name;  
      }

      $model_query->payment_method_id = $request->payment_method_id;
      $model_query->note_for_remarks  = $request->note_for_remarks;
      $model_query->no_pol            = $request->no_pol;      
      $model_query->created_at        = $t_stamp;
      $model_query->created_user      = $this->admin_id;

      $model_query->updated_at        = $t_stamp;
      $model_query->updated_user      = $this->admin_id;

      $model_query->save();


      $ptg_trx_dt=[];
      if($supir_dt->id!=1 && $supir_dt->potongan){
        array_push($ptg_trx_dt,[
          "employee_id" => $supir_dt->id,
          "user_id"     => $this->admin_id,
        ]);
      }

      if(isset($kernet_dt) && $kernet_dt->id!=1 && $kernet_dt->potongan){
        array_push($ptg_trx_dt,[
          "employee_id" => $kernet_dt->id,
          "user_id"     => $this->admin_id,
        ]);
      }

      if(count($ptg_trx_dt) > 0)
      PSPotonganTrx::trpTrxInsert($model_query->id,$ptg_trx_dt);
    
      MyLog::sys("trx_trp",$model_query->id,"insert");

      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      // return response()->json([
      //   "message" => $e->getMessage(),
      //   "code" => $e->getCode(),
      //   "line" => $e->getLine(),
      // ], 400);
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      if ($e->getCode() == 422) {
        return response()->json(json_decode($e->getMessage()), 422);
      }
      return response()->json([
        "message" => "Proses tambah data gagal",
      ], 400);
    }
  }

  public function update(TrxTrpRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.modify');
    
    $t_stamp = date("Y-m-d H:i:s");
    $online_status=$request->online_status;

    DB::beginTransaction();
    try {
      $supir_dt =\App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where('id',$request->supir_id)->available()->verified()->first();
      if(!$supir_dt)
      throw new \Exception("Supir tidak terdaftar",1);

      if($request->kernet_id){
        $kernet_dt =\App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where('id',$request->kernet_id)->available()->verified()->first();
        if(!$kernet_dt)
        throw new \Exception("Kernet tidak terdaftar",1);
      }

      if($request->supir_id == $request->kernet_id && $request->supir_id != 1)
      throw new \Exception("Supir Dan Kernet Tidak Boleh Orang Yang Sama",1);

      if(in_array($request->payment_method_id ,[2,3,4,5])){
        if(!$supir_dt->rek_no && $supir_dt->id != 1)
        throw new \Exception("Tidak ada no rekening supir",1);

        if(isset($kernet_dt) && !$kernet_dt->rek_no && $kernet_dt->id != 1)
        throw new \Exception("Tidak ada no rekening kernet",1);
      }

      $model_query             = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);
      // if($model_query->val1==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
      // throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      if($model_query->req_deleted==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Tidak Dapat Di Ubah",1);


      if($model_query->val==0){
        
        $model_query->tanggal         = $request->tanggal;
  
        $ujalan = \App\Models\MySql\Ujalan::where("id",$request->id_uj)
        ->where("jenis",$request->jenis)
        ->where("deleted",0)
        ->lockForUpdate()
        ->first();
  
        if(!$ujalan) 
        throw new \Exception("Silahkan Isi Data Ujalan Dengan Benar",1);
  
        if($ujalan->xto!=$request->xto)
        throw new \Exception("Silahkan Isi Tipe Ujalan Dengan Benar",1);
  
        $model_query->id_uj           = $ujalan->id;
        $model_query->jenis           = $request->jenis;
        $model_query->xto             = $ujalan->xto;
        $model_query->tipe            = $ujalan->tipe;
        $model_query->amount          = $ujalan->harga;

        if($ujalan->transition_from){
          $model_query->transition_target = $ujalan->transition_from;
          $model_query->transition_type   = "From";
        }else{
          $model_query->transition_target = null;
          $model_query->transition_type   = null;
        }
        
        $prev_supir_id = $model_query->supir_id;
        if($prev_supir_id != null && $prev_supir_id!=1 && $prev_supir_id != $supir_dt->id){
          throw new \Exception("Supir sudah tidak boleh di ubah",1);
        }

        $model_query->supir_id          = $supir_dt->id;
        $model_query->supir             = $supir_dt->name;
        $model_query->supir_rek_no      = $supir_dt->rek_no;
        $model_query->supir_rek_name    = $supir_dt->rek_name;
  

        $prev_kernet_id = $model_query->kernet_id;
        if(isset($kernet_dt)){
          if($prev_kernet_id != null && $prev_kernet_id!=1 && $prev_kernet_id != $kernet_dt->id){
            throw new \Exception("Kernet sudah tidak boleh di ubah",1);
          }
          $model_query->kernet_id       = $kernet_dt->id;
          $model_query->kernet          = $kernet_dt->name;
          $model_query->kernet_rek_no   = $kernet_dt->rek_no;
          $model_query->kernet_rek_name = $kernet_dt->rek_name;  
        }else{
          if($prev_kernet_id != null){
            throw new \Exception("Kernet sudah tidak boleh di kosong",1);
          }
        }
        $model_query->payment_method_id = $request->payment_method_id;
        $model_query->note_for_remarks  = $request->note_for_remarks;
        $model_query->no_pol            = $request->no_pol;
      }
      
      // if($online_status=="true"){
      //   if($model_query->pvr_id==null){
      //     if($request->cost_center_code){  
      //       $list_cost_center = DB::connection('sqlsrv')->table("AC_CostCenterNames")
      //       ->select('CostCenter','Description')
      //       ->where('CostCenter',$request->cost_center_code)
      //       ->first();
      //       if(!$list_cost_center)
      //       throw new \Exception(json_encode(["cost_center_code"=>["Cost Center Code Tidak Ditemukan"]]), 422);
          
      //       $model_query->cost_center_code = $list_cost_center->CostCenter;
      //       $model_query->cost_center_desc = $list_cost_center->Description;
      //     }else{
      //       $model_query->cost_center_code = null;
      //       $model_query->cost_center_desc = null;
      //     } 
      //   }
      // }else{
      //   if($request->cost_center_code)
      //   throw new \Exception("Pengisian cost center harus dalam mode online", 1);
      // }

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->save();

      $ptg_trx_dt=[];



      if(($prev_supir_id == null || $prev_supir_id == 1 ) && $supir_dt->id!=1 && $supir_dt->potongan){
        array_push($ptg_trx_dt,[
          "employee_id" => $supir_dt->id,
          "user_id"     => $this->admin_id,
        ]);
      }

      if(($prev_kernet_id == null || $prev_kernet_id == 1) && isset($kernet_dt) && $kernet_dt->id!=1 && $kernet_dt->potongan){
        array_push($ptg_trx_dt,[
          "employee_id" => $kernet_dt->id,
          "user_id"     => $this->admin_id,
        ]);
      }

      if(count($ptg_trx_dt) > 0)
      PSPotonganTrx::trpTrxInsert($model_query->id,$ptg_trx_dt);

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"update",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      if ($e->getCode() == 422) {
        return response()->json(json_decode($e->getMessage()), 422);
      }
      return response()->json([
        "message" => "Proses ubah data gagal",
      ], 400);
    }
  }

  public function delete(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.remove');

    DB::beginTransaction();

    try {
      $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      
      if($model_query->req_deleted==1  || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Di (Permintaan) Hapus",1);

      if($model_query->pvr_id!=null)
      throw new \Exception("Harap Lakukan Permintaan Penghapusan Terlebih Dahulu",1);

      if($model_query->received_payment==1) 
      throw new \Exception("Pembayaran Sudah Dilakukan. Harap Lakukan Permintaan Penghapusan Terlebih Dahulu",1);

      if(in_array(1,[$model_query->val2,$model_query->val3,$model_query->val4,$model_query->val5,$model_query->val6,$model_query->val_ticket]) || $model_query->req_deleted==1  || $model_query->deleted==1) 
      throw new \Exception("Unvalidasi terlebih dahulu untuk menghapus kecuali kasir dan mandor",1);

      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $SYSOLD                     = clone($model_query);

      $t_stamp                      = date("Y-m-d H:i:s");
      $model_query->deleted         = 1;
      $model_query->deleted_user    = $this->admin_id;
      $model_query->deleted_at      = $t_stamp;
      $model_query->deleted_reason  = $deleted_reason;
      $model_query->save();

      PSPotonganTrx::trpTrxDelete($model_query->id,[
        "deleted_user"    => $this->admin_id,
        "deleted_at"      => $t_stamp,
        "deleted_reason"  => $deleted_reason,
      ]);

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"delete",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message"         => "Proses Hapus data berhasil",
        "deleted"         => $model_query->deleted,
        "deleted_user"    => $model_query->deleted_user,
        "deleted_by"      => $model_query->deleted_user ? new IsUserResource(IsUser::find($model_query->deleted_user)) : null,
        "deleted_at"      => $model_query->deleted_at,
        "deleted_reason"  => $model_query->deleted_reason,
      ], 200);
    } catch (\Exception  $e) {
      DB::rollback();
      if ($e->getCode() == "23000")
        return response()->json([
          "message" => "Data tidak dapat dihapus, data terkait dengan data yang lain nya",
        ], 400);

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
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  }

  public function reqDelete(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.request_remove');

    DB::beginTransaction();

    try {
      $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      
      if(in_array(1,[$model_query->val_ticket]))
      throw new \Exception("Data Sudah Divalidasi (Tiket) Dan Tidak Dapat Di Hapus",1);

      if($model_query->deleted==1 || $model_query->req_deleted==1 )
      throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

      if($model_query->pvr_id==null)
      throw new \Exception("Harap Lakukan Penghapusan",1);

      $req_deleted_reason = $request->req_deleted_reason;
      if(!$req_deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $SYSOLD                     = clone($model_query);

      $model_query->req_deleted = 1;
      $model_query->req_deleted_user = $this->admin_id;
      $model_query->req_deleted_at = date("Y-m-d H:i:s");
      $model_query->req_deleted_reason = $req_deleted_reason;
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"req_delete",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses Permintaan Hapus data berhasil",
        "req_deleted"=>$model_query->req_deleted,
        "req_deleted_user"=>$model_query->req_deleted_user,
        "req_deleted_by"=>$model_query->req_deleted_user ? new IsUserResource(IsUser::find($model_query->req_deleted_user)) : null,
        "req_deleted_at"=>$model_query->req_deleted_at,
        "req_deleted_reason"=>$model_query->req_deleted_reason,
      ], 200);
    } catch (\Exception  $e) {
      DB::rollback();
      if ($e->getCode() == "23000")
        return response()->json([
          "message" => "Data tidak dapat dihapus, data terkait dengan data yang lain nya",
        ], 400);

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
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  }

  public function approveReqDelete(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.approve_request_remove');

    $time = microtime(true);
    $mSecs = sprintf('%03d', ($time - floor($time)) * 1000);
    $t_stamp_ms = date("Y-m-d H:i:s").".".$mSecs;

    DB::beginTransaction();

    try {
      $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      
      if(in_array(1,[$model_query->val_ticket]))
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      if($model_query->deleted==1 )
      throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

      if($model_query->pvr_id=="" || $model_query->pvr_id==null)
      throw new \Exception("Harap Lakukan Penghapusan",1);

      $reason_adder = $request->reason_adder;
      $deleted_reason = $model_query->req_deleted_reason.($reason_adder?" | ".$reason_adder:"");
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $SYSOLD                     = clone($model_query);

      $t_stamp                      = date("Y-m-d H:i:s");
      $model_query->deleted         = 1;
      $model_query->deleted_user    = $this->admin_id;
      $model_query->deleted_at      = $t_stamp;
      $model_query->deleted_reason  = $deleted_reason;

      PSPotonganTrx::trpTrxDelete($model_query->id,[
        "deleted_user"    => $this->admin_id,
        "deleted_at"      => $t_stamp,
        "deleted_reason"  => $deleted_reason,
      ]);

      // if($model_query->pvr_no){
      //   DB::connection('sqlsrv')->table('FI_APRequest')
      //   ->where("VoucherNo",$model_query->pvr_no)->update([
      //     "Void" => 1,
      //     "VoidBy" => $this->admin->the_user->username,
      //     "VoidDateTime" => $t_stamp_ms,
      //     "VoidReason" => $deleted_reason
      //   ]);
      // }

      // if($model_query->pv_no){
      //   DB::connection('sqlsrv')->table('FI_Arap')
      //   ->where("VoucherNo",$model_query->pv_no)->update([
      //     "Void" => 1,
      //     "VoidBy" => $this->admin->the_user->username,
      //     "VoidDateTime" => $t_stamp_ms,
      //     "VoidReason" => $deleted_reason
      //   ]);
      // }
      
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"req_app_delete",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses Hapus data berhasil",
        "deleted"=>$model_query->deleted,
        "deleted_user"=>$model_query->deleted_user,
        "deleted_by"=>$model_query->deleted_user ? new IsUserResource(IsUser::find($model_query->deleted_user)) : null,
        "deleted_at"=>$model_query->deleted_at,
        "deleted_reason"=>$model_query->deleted_reason,
      ], 200);
    } catch (\Exception  $e) {
      DB::rollback();
      if ($e->getCode() == "23000")
        return response()->json([
          "message" => "Data tidak dapat dihapus, data terkait dengan data yang lain nya",
        ], 400);

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
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  }

  public function previewFile(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.preview_file');

    set_time_limit(0);

    $trx_trp = TrxTrp::with('uj')->find($request->id);

    if($trx_trp->val==0)
    return response()->json([
      "message" => "Mandor harus Validasi Terlebih Dahulu",
    ], 400);


    $supir_id   = $trx_trp->supir_id;
    $kernet_id  = $trx_trp->kernet_id;
    $ttl_ps     = 0;
    $ttl_pk     = 0;

    $ptg_ps_ids = "";
    $ptg_pk_ids = "";
    foreach ($trx_trp->potongan as $k => $v) {
      if($v->potongan_mst->employee_id == $supir_id){
        $ttl_ps+=$v->nominal_cut;
        $ptg_ps_ids.="#".$v->potongan_mst->id." ";
      }

      if($v->potongan_mst->employee_id == $kernet_id){
        $ttl_pk+=$v->nominal_cut;
        $ptg_pk_ids.="#".$v->potongan_mst->id." ";
      }
    }
    
    $ujalan = \App\Models\MySql\Ujalan::where("id",$trx_trp->id_uj)->first();
    $details = \App\Models\MySql\UjalanDetail::where("id_uj",$trx_trp->id_uj)->orderBy("ordinal","asc")->get();
    // $total = 0;

    // foreach ($details as $key => $value) {
    //   $total += $value["qty"] * $value["harga"];
    // }

    $sendData = [
      "id"            => $trx_trp->id,
      "id_uj"         => $trx_trp->id_uj,
      "no_pol"        => $trx_trp->no_pol,
      "payment"       => $trx_trp->payment_method_id,
      "payment_name"  => $trx_trp->payment_method->name,
      "supir"         => $trx_trp->supir,
      "supir_rek_no"  => $trx_trp->supir_rek_no,
      "supir_sim_name"=> $trx_trp->employee_s->sim_name,
      "ttl_ps"        => $ttl_ps,
      "ptg_ps_ids"    => $ptg_ps_ids,
      "kernet"        => $trx_trp->kernet,
      "kernet_rek_no" => $trx_trp->kernet_rek_no,
      "ttl_pk"        => $ttl_pk,
      "ptg_pk_ids"    => $ptg_pk_ids,
      "tanggal"       => $trx_trp->tanggal,
      "created_at"    => $trx_trp->created_at,
      "asal"          => env("app_name"),
      "xto"           => $trx_trp->xto,
      "jenis"         => $trx_trp->jenis,
      "tipe"          => $trx_trp->tipe,
      "info"          => $trx_trp->uj->asst_opt,
      "details"       => $details,
      "total"         => $ujalan->harga,
      "is_transition" => $trx_trp->transition_type=='From',
      "user_1"        => $this->admin->the_user->username,
    ];   
    
    // $date = new \DateTime();
    // $filename = $date->format("YmdHis");
    // Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
    // $pdf = PDF::loadView('pdf.trx_trp_ujalan', $sendData)->setPaper('a4', 'portrait');
    
    $html = view("html.trx_trp_ujalan",$sendData);
  
    // $mime = MyLib::mime("pdf");
    // $bs64 = base64_encode($pdf->download($filename . "." . $mime["ext"]));
  
    $result = [
      // "contentType" => $mime["contentType"],
      // "data" => $bs64,
      // "dataBase64" => $mime["dataBase64"] . $bs64,
      // "filename" => $filename . "." . $mime["ext"],
      "html"=>$html->render()
    ];
    return $result;
  }

  public function previewQRCode(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.preview_file');

    set_time_limit(0);

    $trx_trp = TrxTrp::with('uj')->find($request->id);

    if($trx_trp->val==0)
    return response()->json([
      "message" => "Mandor harus Validasi Terlebih Dahulu",
    ], 400);

    $sendData = [
      "id"            => $trx_trp->id,
      "id_uj"         => $trx_trp->id_uj,
      "no_pol"        => $trx_trp->no_pol,
      "payment"       => $trx_trp->payment_method_id,
      "payment_name"  => $trx_trp->payment_method->name,
      "supir"         => $trx_trp->supir,
      "supir_rek_no"  => $trx_trp->supir_rek_no,
      "supir_sim_name"=> $trx_trp->employee_s->sim_name,
      "kernet"        => $trx_trp->kernet,
      "kernet_rek_no" => $trx_trp->kernet_rek_no,
      "tanggal"       => $trx_trp->tanggal,
      "created_at"    => $trx_trp->created_at,
      "asal"          => env("app_name"),
      "xto"           => $trx_trp->xto,
      "jenis"         => $trx_trp->jenis,
      "tipe"          => $trx_trp->tipe,
      "info"          => $trx_trp->uj->asst_opt,
      "is_transition" => $trx_trp->transition_type=='From',
    ];   
    
    $html = view("html.trx_trp_ujalan_qr",$sendData);
  
    $result = [
      "html"=>$html->render()
    ];
    return $result;
  }

  public function previewFileBT(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.preview_file');

    set_time_limit(0);

    $trx_trp = TrxTrp::find($request->id);

    if($trx_trp->val1==0)
    return response()->json([
      "message" => "Mandor harus Validasi Terlebih Dahulu",
    ], 400);


    $supir_id   = $trx_trp->supir_id;
    $kernet_id  = $trx_trp->kernet_id;
    $ttl_ps     = 0;
    $ttl_pk     = 0;

    $supir_remarks  = "UJ#".$trx_trp->id;
    $kernet_remarks = "UJ#".$trx_trp->id;

    $ps_trip_supir_kernet = PSTripSupirKernet::fn_supir_kernet_for_transfer($trx_trp);

    $supir_money = $ps_trip_supir_kernet['supir_money']+$ps_trip_supir_kernet['supir_extra_money_trx_money']-$ps_trip_supir_kernet['supir_potongan_trx_money'];
    $kernet_money = $ps_trip_supir_kernet['kernet_money']+$ps_trip_supir_kernet['kernet_extra_money_trx_money']-$ps_trip_supir_kernet['kernet_potongan_trx_money'];

    $xfor_supir_exists = $ps_trip_supir_kernet['supir_money'] > 0;
    $xfor_kernet_exists = $ps_trip_supir_kernet['kernet_money'] > 0;

    $sem_remarks = $ps_trip_supir_kernet['supir_extra_money_trx_ids']; 
    $kem_remarks = $ps_trip_supir_kernet['kernet_extra_money_trx_ids']; 


    // $supir_money = 0;
    // $kernet_money = 0;
    // foreach ($trx_trp->uj_details2 as $key => $val) {
    //   if($val->xfor=='Kernet'){
    //     $kernet_money+=$val->amount*$val->qty;
    //   }else{
    //     $supir_money+=$val->amount*$val->qty;
    //   }
    // }

    // $ptg_ps_ids = "";
    // $ptg_pk_ids = "";
    // foreach ($trx_trp->potongan as $k => $v) {
    //   if($v->potongan_mst->employee_id == $supir_id){
    //     $ttl_ps+=$v->nominal_cut;
    //     $ptg_ps_ids.="#".$v->potongan_mst->id." ";
    //   }

    //   if($v->potongan_mst->employee_id == $kernet_id){
    //     $ttl_pk+=$v->nominal_cut;
    //     $ptg_pk_ids.="#".$v->potongan_mst->id." ";
    //   }
    // }


    // $supir_money -= $ttl_ps;
    // $kernet_money -= $ttl_pk;

    // $sem_remarks="";
    // $kem_remarks="";
    // foreach ($trx_trp->extra_money_trxs as $k => $emt) {
    //   if($emt->employee_id == $supir_id){
    //     $supir_money+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
    //     if($sem_remarks=="")
    //       $sem_remarks="EM#".$emt->id;
    //     else
    //       $sem_remarks.=",".$emt->id;
    //   }

    //   if($emt->employee_id == $kernet_id){
    //     $kernet_money+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
    //     if($kem_remarks=="")
    //       $kem_remarks="EM#".$emt->id;
    //     else
    //       $kem_remarks.=",".$emt->id;
    //   }
    // }

    if($sem_remarks!="") $supir_remarks.=",".$sem_remarks;
    if($kem_remarks!="") $kernet_remarks.=",".$kem_remarks;

  
    $sendData = [
      "id"            => $trx_trp->id,
      "id_uj"         => $trx_trp->id_uj,
      "logo"          => File::exists(files_path("/duitku.png")) ? "data:image/png;base64,".base64_encode(File::get(files_path("/duitku.png"))) :"",

      "ref_no0"       => $trx_trp->duitku_supir_disburseId,
      "supir"         => $trx_trp->supir,
      "supir_rek_no"  => $trx_trp->supir_rek_no,
      "nominal0"      => $supir_money,
      "tanggal_supir" => $trx_trp->rp_supir_at,
      "supir_remarks" => $supir_remarks,

      "ref_no1"       => $trx_trp->duitku_kernet_disburseId,
      "kernet"        => $trx_trp->kernet,
      "kernet_rek_no" => $trx_trp->kernet_rek_no,
      "nominal1"      => $kernet_money,
      "tanggal_kernet" => $trx_trp->rp_kernet_at,
      "kernet_remarks" => $kernet_remarks,

      
    ];   
    $html = view("html.trx_trp_ujalan_bt",$sendData);  
    $result = [
      "html"=>$html->render()
    ];
    return $result;
  }

  public function reportRawExcel(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.download_file');

    set_time_limit(0);
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    

    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {
      $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      array_push($newDetails,$value);
    }

    $filter_model = json_decode($request->filter_model,true);
    $tanggal = $filter_model['tanggal'];    


    $date_from=date("d-m-Y",strtotime($tanggal['value_1']));
    $date_to=date("d-m-Y",strtotime($tanggal['value_2']));

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-trx_trp'."[".$date_from."*".$date_to."]";

    $mime=MyLib::mime("xlsx");
    // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.trx_trp_raw'), $mime["exportType"]));


    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }

  public function reportExcelWUj(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.download_file');

    set_time_limit(0);
    $callGet = $this->index($request, true,"plus_uj_details");
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    

    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {

      $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      array_push($newDetails,$value);
    }

    $filter_model = json_decode($request->filter_model,true);
    $tanggal = $filter_model['tanggal'];    


    $date_from=date("d-m-Y",strtotime($tanggal['value_1']));
    $date_to=date("d-m-Y",strtotime($tanggal['value_2']));

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-trx_trp'."[".$date_from."*".$date_to."]";

    $mime=MyLib::mime("xlsx");
    // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.trx_trp_w_uj'), $mime["exportType"]));


    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }

  // public function reportPVPDF(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.download_file');

  //   set_time_limit(0);
  //   $callGet = $this->index($request, true);
  //   if ($callGet->getStatusCode() != 200) return $callGet;
  //   $ori = json_decode(json_encode($callGet), true)["original"];
    
  //   $newDetails = [];
  //   foreach ($ori["data"] as $key => $value) {
  //     $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
  //     $value['amount']=number_format((float)$value["amount"], 0,',','.');
  //     $value['pv_total']=number_format((float)$value["pv_total"], 0,',','.');
  //     $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
  //     array_push($newDetails,$value);
  //   }

  //   $filter_model = json_decode($request->filter_model,true);
  //   $tanggal = $filter_model['tanggal'];    

  //   $date = new \DateTime();
  //   $filename = $date->format("YmdHis");
  //   Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
  //   $pdf = PDF::loadView('pdf.trx_trp_pv', ["data"=>$newDetails,"info"=>[
  //     "from"=>date("d-m-Y",strtotime($tanggal['value_1'])),
  //     "to"=>date("d-m-Y",strtotime($tanggal['value_2'])),
  //     "now"=>date("d-m-Y H:i:s"),
  //   ]])->setPaper('a4', 'landscape');


  //   $mime = MyLib::mime("pdf");
  //   $bs64 = base64_encode($pdf->download($filename . "." . $mime["ext"]));

  //   $result = [
  //     "contentType" => $mime["contentType"],
  //     "data" => $bs64,
  //     "dataBase64" => $mime["dataBase64"] . $bs64,
  //     "filename" => $filename . "." . $mime["ext"],
  //   ];
  //   return $result;
  // }

  // public function reportPVExcel(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.download_file');

  //   set_time_limit(0);
  //   $callGet = $this->index($request, true);
  //   if ($callGet->getStatusCode() != 200) return $callGet;
  //   $ori = json_decode(json_encode($callGet), true)["original"];
    

  //   $newDetails = [];

  //   foreach ($ori["data"] as $key => $value) {

  //     $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
  //     $value['amount']=$value["amount"];
  //     $value['pv_total']=$value["pv_total"];
  //     $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
  //     array_push($newDetails,$value);
  //   }

  //   $filter_model = json_decode($request->filter_model,true);
  //   $tanggal = $filter_model['tanggal'];    

  //   $date_from=date("d-m-Y",strtotime($tanggal['value_1']));
  //   $date_to=date("d-m-Y",strtotime($tanggal['value_2']));

  //   $date = new \DateTime();
  //   $filename=$date->format("YmdHis").'-trx_trp'."[".$date_from."_".$date_to."]";

  //   $mime=MyLib::mime("xlsx");
  //   // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
  //   $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.trx_trp_pv'), $mime["exportType"]));

  //   $result = [
  //     "contentType" => $mime["contentType"],
  //     "data" => $bs64,
  //     "dataBase64" => $mime["dataBase64"] . $bs64,
  //     "filename" => $filename . "." . $mime["ext"],
  //   ];
  //   return $result;
  // }

  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.val','trp_trx.val1','trp_trx.val2','trp_trx.val3','trp_trx.val4','trp_trx.val5','trp_trx.val6']);

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
      $model_query = TrxTrp::lockForUpdate()->find($request->id);
      if($model_query->val && $model_query->val1 && $model_query->val2 && $model_query->val3 && $model_query->val4 && $model_query->val5 && $model_query->val6){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }
      $SYSOLD                     = clone($model_query);

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.val',true) && !$model_query->val){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.val1',true) && !$model_query->val1){
        self::permit_continue_trx($model_query);
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.val2',true) && !$model_query->val2){
        $model_query->val2 = 1;
        $model_query->val2_user = $this->admin_id;
        $model_query->val2_at = $t_stamp;
      }
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.val3',true) && !$model_query->val3){
        $model_query->val3 = 1;
        $model_query->val3_user = $this->admin_id;
        $model_query->val3_at = $t_stamp;
      }
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.val4',true) && !$model_query->val4){
        $model_query->val4 = 1;
        $model_query->val4_user = $this->admin_id;
        $model_query->val4_at = $t_stamp;
      }
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.val5',true) && !$model_query->val5){
        if($model_query->val4==0)
        throw new \Exception("Data Perlu Divalidasi oleh Staff Logistik terlebih dahulu",1);
        $model_query->val5 = 1;
        $model_query->val5_user = $this->admin_id;
        $model_query->val5_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.val6',true) && !$model_query->val6){
        if($model_query->val5==0)
        throw new \Exception("Data Perlu Divalidasi oleh SPV Logistik terlebih dahulu",1);
        $model_query->val6 = 1;
        $model_query->val6_user = $this->admin_id;
        $model_query->val6_at = $t_stamp;
      }

      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"approve",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses validasi data berhasil",
        "val"=>$model_query->val,
        "val_user"=>$model_query->val_user,
        "val_at"=>$model_query->val_at,
        "val_by"=>$model_query->val_user ? new IsUserResource(IsUser::find($model_query->val_user)) : null,
        "val1"=>$model_query->val1,
        "val1_user"=>$model_query->val1_user,
        "val1_at"=>$model_query->val1_at,
        "val1_by"=>$model_query->val1_user ? new IsUserResource(IsUser::find($model_query->val1_user)) : null,
        "val2"=>$model_query->val2,
        "val2_user"=>$model_query->val2_user,
        "val2_at"=>$model_query->val2_at,
        "val2_by"=>$model_query->val2_user ? new IsUserResource(IsUser::find($model_query->val2_user)) : null,
        "val3"=>$model_query->val3,
        "val3_user"=>$model_query->val3_user,
        "val3_at"=>$model_query->val3_at,
        "val3_by"=>$model_query->val3_user ? new IsUserResource(IsUser::find($model_query->val3_user)) : null,
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

  public function unvalidasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.unval1','trp_trx.unval2','trp_trx.unval3','trp_trx.unval4','trp_trx.unval5','trp_trx.unval6']);

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
      $model_query = TrxTrp::lockForUpdate()->find($request->id);
      if($model_query->pvr_no || $model_query->received_payment){
        throw new \Exception("Data sudah tidak bisa di unvalidasi",1);
      }

      if(!$model_query->val1 && !$model_query->val2 && !$model_query->val3 && !$model_query->val4 && !$model_query->val5 && !$model_query->val6){
        throw new \Exception("Data Sudah Terunvalidasi Sepenuhnya",1);
      }


      // if($model_query->duitku_supir_disburseId!==null && $request->confirm==0){
      //   throw new \Exception("need_confirm",1);
      // }

      $SYSOLD                     = clone($model_query);

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.unval4',true) && $model_query->val4){
        $model_query->val4 = 0;
      }
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.unval6',true) && $model_query->val6){
        if($model_query->val4==1)
        throw new \Exception("Minta Staff Logistik untuk unvalidasi terlebih dahulu",1);

        $model_query->val6 = 0;
      }
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.unval5',true) && $model_query->val5){
        // if($model_query->val6==1)
        // throw new \Exception("Minta Manager Logistik untuk unvalidasi terlebih dahulu",1);
        if($model_query->val4==1)
        throw new \Exception("Minta Staff Logistik untuk unvalidasi terlebih dahulu",1);

        $model_query->val5 = 0;
      }      
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.unval3',true) && $model_query->val3){
        if($model_query->val5==1)
        throw new \Exception("Minta Supervisor Logistik untuk unvalidasi terlebih dahulu",1);
        $model_query->val3 = 0;
      }
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.unval2',true) && $model_query->val2){
        if($model_query->val3==1)
        throw new \Exception("Minta Marketing untuk unvalidasi terlebih dahulu",1);
        $model_query->val2 = 0;
      }
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.unval1',true) && $model_query->val1){
        if($model_query->val2==1)
        throw new \Exception("Minta KTU/W untuk unvalidasi terlebih dahulu",1);
        $model_query->val1 = 0;
      }
      
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"unapprove",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses unvalidasi data berhasil",
        "val"=>$model_query->val,
        "val_user"=>$model_query->val_user,
        "val_at"=>$model_query->val_at,
        "val_by"=>$model_query->val_user ? new IsUserResource(IsUser::find($model_query->val_user)) : null,
        "val1"=>$model_query->val1,
        "val1_user"=>$model_query->val1_user,
        "val1_at"=>$model_query->val1_at,
        "val1_by"=>$model_query->val1_user ? new IsUserResource(IsUser::find($model_query->val1_user)) : null,
        "val2"=>$model_query->val2,
        "val2_user"=>$model_query->val2_user,
        "val2_at"=>$model_query->val2_at,
        "val2_by"=>$model_query->val2_user ? new IsUserResource(IsUser::find($model_query->val2_user)) : null,
        "val3"=>$model_query->val3,
        "val3_user"=>$model_query->val3_user,
        "val3_at"=>$model_query->val3_at,
        "val3_by"=>$model_query->val3_user ? new IsUserResource(IsUser::find($model_query->val3_user)) : null,
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
        $status_code = 400;
        if($e->getMessage()=="need_confirm"){
          $status_code = 200;
        }
        return response()->json([
          "message" => $e->getMessage(),
        ], $status_code);
      }
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      return response()->json([
        "message" => "Proses unvalidasi data gagal",
      ], 400);
    }

  }

  // public function valTicket(Request $request){
  //   MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.val_ticket']);

  //   $rules = [
  //     'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
  //   ];

  //   $messages = [
  //     'id.required' => 'ID tidak boleh kosong',
  //     'id.exists' => 'ID tidak terdaftar',
  //   ];

  //   $validator = Validator::make($request->all(), $rules, $messages);

  //   if ($validator->fails()) {
  //     throw new ValidationException($validator);
  //   }

  //   $t_stamp = date("Y-m-d H:i:s");
  //   DB::beginTransaction();
  //   try {
  //     $model_query = TrxTrp::lockForUpdate()->find($request->id);
  //     if($model_query->val_ticket){
  //       throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
  //     }
  //     $SYSOLD                     = clone($model_query);

  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.val_ticket',true) && !$model_query->val_ticket){
  //       $model_query->val_ticket = 1;
  //       $model_query->val_ticket_user = $this->admin_id;
  //       $model_query->val_ticket_at = $t_stamp;
  //       $model_query->updated_user = $this->admin_id;
  //       $model_query->updated_at = $t_stamp;
  //     }
  //     $model_query->save();
  //     $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
  //     MyLog::sys("trx_trp",$request->id,"approve ticket",$SYSNOTE);

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses validasi data berhasil",
  //       "val_ticket"=>$model_query->val_ticket,
  //       "val_ticket_user"=>$model_query->val_ticket_user,
  //       "val_ticket_at"=>$model_query->val_ticket_at,
  //       "val_ticket_by"=>$model_query->val_ticket_user ? new IsUserResource(IsUser::find($model_query->val_ticket_user)) : null,
  //       "updated_at"=>$model_query->updated_at,
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     // return response()->json([
  //     //   "getCode" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     //   "message" => $e->getMessage(),
  //     // ], 400);
  //     return response()->json([
  //       "message" => "Proses ubah data gagal",
  //     ], 400);
  //   }

  // }

  // public function unvalTicket(Request $request){
  //   MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.unval_ticket']);

  //   $rules = [
  //     'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
  //   ];

  //   $messages = [
  //     'id.required' => 'ID tidak boleh kosong',
  //     'id.exists' => 'ID tidak terdaftar',
  //   ];

  //   $validator = Validator::make($request->all(), $rules, $messages);

  //   if ($validator->fails()) {
  //     throw new ValidationException($validator);
  //   }

  //   $t_stamp = date("Y-m-d H:i:s");
  //   DB::beginTransaction();
  //   try {
  //     $model_query = TrxTrp::lockForUpdate()->find($request->id);
  //     // if($model_query->val_ticket){
  //     //   throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
  //     // }
  //     $SYSOLD                     = clone($model_query);

  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.unval_ticket',true) && $model_query->val_ticket){
  //       $model_query->val_ticket = 0;
  //       // $model_query->val_ticket_user = $this->admin_id;
  //       // $model_query->val_ticket_at = $t_stamp;
  //       $model_query->updated_user = $this->admin_id;
  //       $model_query->updated_at = $t_stamp;
  //     }
  //     $model_query->save();

  //     $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
  //     MyLog::sys("trx_trp",$request->id,"unvalidasi ticket",$SYSNOTE);

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses validasi data berhasil",
  //       "val_ticket"=>$model_query->val_ticket,
  //       "val_ticket_user"=>$model_query->val_ticket_user,
  //       "val_ticket_at"=>$model_query->val_ticket_at,
  //       "val_ticket_by"=>$model_query->val_ticket_user ? new IsUserResource(IsUser::find($model_query->val_ticket_user)) : null,
  //       "updated_at"=>$model_query->updated_at,
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     // return response()->json([
  //     //   "getCode" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     //   "message" => $e->getMessage(),
  //     // ], 400);
  //     return response()->json([
  //       "message" => "Proses ubah data gagal",
  //     ], 400);
  //   }

  // }

  public function validasis(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.val','trp_trx.val1','trp_trx.val2','trp_trx.val3','trp_trx.val4','trp_trx.val5','trp_trx.val6']);

    $ids = json_decode($request->ids, true);
    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_querys = TrxTrp::lockForUpdate()->whereIn("id",$ids)->get();
      $valList = [];
      $SYSNOTES = [];
      foreach ($model_querys as $key => $v) {
        $SYSOLD                     = clone($v);
        $hadChange = 0;
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val',true) && !$v->val){
          $v->val = 1;
          $v->val_user = $this->admin_id;
          $v->val_at = $t_stamp;
          $hadChange++;
        }
  
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val1',true) && !$v->val1){
          self::permit_continue_trx($v);
          $v->val1 = 1;
          $v->val1_user = $this->admin_id;
          $v->val1_at = $t_stamp;
          $hadChange++;
        }
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val2',true) && !$v->val2){
          $v->val2 = 1;
          $v->val2_user = $this->admin_id;
          $v->val2_at = $t_stamp;
          $hadChange++;
        }
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val3',true) && !$v->val3){
          $v->val3 = 1;
          $v->val3_user = $this->admin_id;
          $v->val3_at = $t_stamp;
          $hadChange++;
        }
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val4',true) && !$v->val4){
          $v->val4 = 1;
          $v->val4_user = $this->admin_id;
          $v->val4_at = $t_stamp;
          $hadChange++;
        }
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val5',true) && !$v->val5){
          if($v->val4==0)
          throw new \Exception("Data Perlu Divalidasi oleh Staff Logistik terlebih dahulu",1);
          $v->val5 = 1;
          $v->val5_user = $this->admin_id;
          $v->val5_at = $t_stamp;
          $hadChange++;
        }
  
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val6',true) && !$v->val6){
          if($v->val5==0)
          throw new \Exception("Data Perlu Divalidasi oleh SPV Logistik terlebih dahulu",1);
          $v->val6 = 1;
          $v->val6_user = $this->admin_id;
          $v->val6_at = $t_stamp;
          $hadChange++;
        }

        if($hadChange>0){
          $v->updated_user = $this->admin_id;
          $v->updated_at = $t_stamp;

          $v->save();
          array_push($valList,[
            "id"=>$v->id,
            "val"=>$v->val,
            "val_user"=>$v->val_user,
            "val_at"=>$v->val_at,
            "val_by"=>$v->val_user ? new IsUserResource(IsUser::find($v->val_user)) : null,
            "val1"=>$v->val1,
            "val1_user"=>$v->val1_user,
            "val1_at"=>$v->val1_at,
            "val1_by"=>$v->val1_user ? new IsUserResource(IsUser::find($v->val1_user)) : null,
            "val2"=>$v->val2,
            "val2_user"=>$v->val2_user,
            "val2_at"=>$v->val2_at,
            "val2_by"=>$v->val2_user ? new IsUserResource(IsUser::find($v->val2_user)) : null,
            "val3"=>$v->val3,
            "val3_user"=>$v->val3_user,
            "val3_at"=>$v->val3_at,
            "val3_by"=>$v->val3_user ? new IsUserResource(IsUser::find($v->val3_user)) : null,
            "val4"=>$v->val4,
            "val4_user"=>$v->val4_user,
            "val4_at"=>$v->val4_at,
            "val4_by"=>$v->val4_user ? new IsUserResource(IsUser::find($v->val4_user)) : null,
            "val5"=>$v->val5,
            "val5_user"=>$v->val5_user,
            "val5_at"=>$v->val5_at,
            "val5_by"=>$v->val5_user ? new IsUserResource(IsUser::find($v->val5_user)) : null,
            "val6"=>$v->val6,
            "val6_user"=>$v->val6_user,
            "val6_at"=>$v->val6_at,
            "val6_by"=>$v->val6_user ? new IsUserResource(IsUser::find($v->val6_user)) : null,
            "updated_at"=>$v->updated_at,
          ]);
          $SYSNOTE = MyLib::compareChange($SYSOLD,$v); 
          array_push($SYSNOTES,$SYSNOTE);
        }
      }

      MyLog::sys($this->syslog_db,null,"validasis",implode(",",$SYSNOTES));

      $nids = array_map(function($x) {
        return $x['id'];        
      },$valList);

      // MyLog::sys("trx_trp",null,"val_tickets",implode(",",$nids));

      DB::commit();
      return response()->json([
        "message" => "Proses validasi data berhasil",
        "val_lists"=>$valList
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
        "message" => "Proses validasi data gagal",
      ], 400);
    }

  }

  // public function valTickets(Request $request){
  //   MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.val_ticket']);

  //   $ids = json_decode($request->ids, true);
  //   $t_stamp = date("Y-m-d H:i:s");
  //   DB::beginTransaction();
  //   try {
  //     $model_querys = TrxTrp::lockForUpdate()->whereIn("id",$ids)->get();
  //     $valList = [];
  //     $SYSNOTES = [];
  //     foreach ($model_querys as $key => $v) {
  //       if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.val_ticket',true) && !$v->val_ticket){
  //         $SYSOLD                     = clone($v);
  //         $v->val_ticket = 1;
  //         $v->val_ticket_user = $this->admin_id;
  //         $v->val_ticket_at = $t_stamp;
  //         $v->updated_user = $this->admin_id;
  //         $v->updated_at = $t_stamp;
  //         $v->save();
  //         array_push($valList,[
  //           "id"=>$v->id,
  //           "val_ticket"=>$v->val_ticket,
  //           "val_ticket_user"=>$v->val_ticket_user,
  //           "val_ticket_at"=>$v->val_ticket_at,
  //           "val_ticket_by"=>$v->val_ticket_user ? new IsUserResource(IsUser::find($v->val_ticket_user)) : null,
  //           "updated_at"=>$v->updated_at,
  //         ]);
  //         $SYSNOTE = MyLib::compareChange($SYSOLD,$v); 
  //         array_push($SYSNOTES,$SYSNOTE);
  //       }
  //     }

  //     MyLog::sys($this->syslog_db,null,"val_tickets",implode(",",$SYSNOTES));

  //     $nids = array_map(function($x) {
  //       return $x['id'];        
  //     },$valList);

  //     // MyLog::sys("trx_trp",null,"val_tickets",implode(",",$nids));

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses validasi data berhasil",
  //       "val_lists"=>$valList
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     // return response()->json([
  //     //   "getCode" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     //   "message" => $e->getMessage(),
  //     // ], 400);
  //     return response()->json([
  //       "message" => "Proses validasi data gagal",
  //     ], 400);
  //   }

  // }

  // public function unvalTickets(Request $request){
  //   MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.unval_ticket']);

  //   $ids = json_decode($request->ids, true);
  //   $t_stamp = date("Y-m-d H:i:s");
  //   DB::beginTransaction();
  //   try {
  //     $model_querys = TrxTrp::lockForUpdate()->whereIn("id",$ids)->get();
  //     $valList = [];
  //     $SYSNOTES =[];
  //     foreach ($model_querys as $key => $v) {
  //       if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.unval_ticket',true) && $v->val_ticket){
  //         $SYSOLD                     = clone($v);
  //         $v->val_ticket = 0;
  //         // $v->val_ticket_user = $this->admin_id;
  //         // $v->val_ticket_at = $t_stamp;
  //         $v->updated_user = $this->admin_id;
  //         $v->updated_at = $t_stamp;
  //         $v->save();
  //         array_push($valList,[
  //           "id"=>$v->id,
  //           "val_ticket"=>$v->val_ticket,
  //           "val_ticket_user"=>$v->val_ticket_user,
  //           "val_ticket_at"=>$v->val_ticket_at,
  //           "val_ticket_by"=>$v->val_ticket_user ? new IsUserResource(IsUser::find($v->val_ticket_user)) : null,
  //           "updated_at"=>$v->updated_at,
  //         ]);
  //         $SYSNOTE = MyLib::compareChange($SYSOLD,$v); 
  //         array_push($SYSNOTES,$SYSNOTE);
  //       }
  //     }

  //     MyLog::sys($this->syslog_db,null,"unval_tickets",implode(",",$SYSNOTES));

  //     $nids = array_map(function($x) {
  //       return $x['id'];        
  //     },$valList);

  //     // MyLog::sys("trx_trp",null,"unval_tickets",implode(",",$nids));

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses unvalidasi data berhasil",
  //       "val_lists"=>$valList
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     // return response()->json([
  //     //   "getCode" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     //   "message" => $e->getMessage(),
  //     // ], 400);
  //     return response()->json([
  //       "message" => "Proses unvalidasi data gagal",
  //     ], 400);
  //   }

  // }

  // public function clearTickets(Request $request){
  //   MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.val_ticket']);

  //   $ids = json_decode($request->ids, true);
  //   $t_stamp = date("Y-m-d H:i:s");
  //   DB::beginTransaction();
  //   try {
  //     $model_querys = TrxTrp::lockForUpdate()->whereIn("id",$ids)->where('val_ticket',0)->get();
  //     $clearList = [];
  //     $SYSNOTES =[];

  //     foreach ($model_querys as $key => $v) {
  //       if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.val_ticket',true) && !$v->val_ticket){
  //         $SYSOLD                     = clone($v);
  //         $v->ticket_a_id = null;
  //         $v->ticket_a_no = null;
  //         $v->ticket_b_id = null;
  //         $v->ticket_b_no = null;
  //         $v->updated_user = $this->admin_id;
  //         $v->updated_at = $t_stamp;

  //         $v->save();
  //         array_push($clearList,[
  //           "id"=>$v->id,
  //           "ticket_a_id"=>$v->ticket_a_id,
  //           "ticket_a_no"=>$v->ticket_a_no,
  //           "ticket_b_id"=>$v->ticket_b_id,
  //           "ticket_b_no"=>$v->ticket_b_no,
  //           "updated_at"=>$v->updated_at,
  //         ]);
  //         $SYSNOTE = MyLib::compareChange($SYSOLD,$v); 
  //         array_push($SYSNOTES,$SYSNOTE);
  //       }
  //     }

  //     MyLog::sys($this->syslog_db,null,"clear_tickets",implode(",",$SYSNOTES));

  //     $nids = array_map(function($x) {
  //       return $x['id'];        
  //     },$clearList);

  //     // MyLog::sys("trx_trp",null,"clear_tickets",implode(",",$nids));

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses clear tiket berhasil",
  //       "clear_lists"=>$clearList
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     // return response()->json([
  //     //   "getCode" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     //   "message" => $e->getMessage(),
  //     // ], 400);
  //     return response()->json([
  //       "message" => "Proses clear tiket gagal",
  //     ], 400);
  //   }

  // }

  public function genPersen($a,$b){
    $a = (float)$a;
    $b = (float)$b;
    
    $diff=(float)($b-$a);
    $bigger = $diff > 0 ? $b  : $a;

    if($bigger==0) return [$diff,0];

    // return [$diff , $diff / $a * 100];
    if($a!=0)
    return [$diff , $diff / $a * 100];
    else
    return [$diff , $diff];
  }

  public function doGenPVR(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.generate_pvr');
    $rules = [
      // 'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
      'online_status' => "required",
    ];

    $messages = [
      // 'id.required' => 'ID tidak boleh kosong',
      // 'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }
    $online_status=$request->online_status;
    if($online_status!="true")
    return response()->json([
      "message" => "Mode Harus Online",
    ], 400);

    $miniError="";
    $id="";
    try {
      $trx_trps = TrxTrp::where(function($q1){$q1->where('pvr_had_detail',0)->orWhereNull("pvr_id");})->whereNull("pv_id")->where("req_deleted",0)->where("deleted",0)->where('val',1)->where('val1',1)->where('val2',1)
      ->where(function ($q) {
        $q->where(function ($q1){
          $q1->where("payment_method_id",1);       
          $q1->where("received_payment",0);                  
        });

        $q->orWhere(function ($q1){
          $q1->whereIn("payment_method_id",[2,3,4,5]);
          $q1->where("received_payment",1);                 
        });
      })->get();      
      if(count($trx_trps)==0){
        throw new \Exception("Semua PVR sudah terisi",1);
      }
      $changes=[];
      foreach ($trx_trps as $key => $tt) {
        $id=$tt->id;
        $callGet = $this->genPVR($id);
        array_push($changes,$callGet);
      }
      if(count($changes)>0){
        $ids = array_map(function ($x) {
          return $x["id"];
        },$changes);
        MyLog::sys("trx_trp",null,"generate_pvr",implode(",",$ids));
      }
      return response()->json($changes, 200);
    } catch (\Exception $e) {
      if(isset($changes) && count($changes)>0){
        $ids = array_map(function ($x) {
          return $x["id"];
        },$changes);
        MyLog::sys("trx_trp",null,"generate_pvr",implode(",",$ids));
      }

      if ($e->getCode() == 1) {
        if($id!=""){
          $miniError.="Trx-".$id.".";
        }
        $miniError.="PVR Batal Dibuat: ".$e->getMessage();
      }else{
        if($id!=""){
          $miniError.="Trx-".$id.".";
        }
        $miniError.="PVR Batal Dibuat. Akses Jaringan Gagal";
        // $miniError.="PVR Batal Dibuat. Akses Jaringan Gagal->".$e->getLine().$e->getCode().$e->getMessage();
      }
      return response()->json([
        "message" => $miniError,
      ], 400);
    }
  }

  public function genPVR($trx_trp_id){

    $t_stamp = date("Y-m-d H:i:s");

    $time = microtime(true);
    $mSecs = sprintf('%03d', ($time - floor($time)) * 1000);
    $t_stamp_ms = date("Y-m-d H:i:s",strtotime($t_stamp)).".".$mSecs;

    $trx_trp = TrxTrp::where("id",$trx_trp_id)->first();
    if(!$trx_trp){
      throw new \Exception("Karna Transaksi tidak ditemukan",1);
    }

    if($trx_trp->pvr_had_detail==1) throw new \Exception("Karna PVR sudah selesai dibuat",1);
    // if($trx_trp->cost_center_code==null) throw new \Exception("Cost Center Code belum diisi",1);
    if($trx_trp->pv_id!=null) throw new \Exception("Karna PV sudah diisi",1);

    if($trx_trp->cost_center_code==null){
      $trx_trp->cost_center_code = '112';
      $trx_trp->cost_center_desc = 'Transport';      
  
      $get_cost_center = DB::connection('sqlsrv')->table("AC_CostCenterNames")
      ->select('CostCenter','Description')
      ->where('CostCenter','like', '112%')
      ->where('Description','like', '%'.trim($trx_trp->no_pol))
      ->first();
  
      if($get_cost_center){
        $trx_trp->cost_center_code = $get_cost_center->CostCenter;
        $trx_trp->cost_center_desc = $get_cost_center->Description;
      }
    }
      
    $supir = $trx_trp->supir;
    $no_pol = $trx_trp->no_pol;
    $kernet = $trx_trp->kernet;
    $associate_name="(S) ".$supir.($kernet?" (K) ".$kernet." ":" (Tanpa Kernet) ").$no_pol; // max 80char

    $ujalan=Ujalan::where("id",$trx_trp->id_uj)->first();

    $arrRemarks = [];
    array_push($arrRemarks,"#".$trx_trp->id.($trx_trp->transition_type=="From" ?" (P) " : " ").$associate_name.".");
    array_push($arrRemarks,"BIAYA UANG JALAN ".$trx_trp->jenis." ".env("app_name")."-".$trx_trp->xto." P/".date("d-m-y",strtotime($trx_trp->tanggal))).".";


    // $arr=[1];
    
    // if($trx_trp->jenis=="PK" && env("app_name")!="SMP")
    // $arr=[1,2];
    

    $ujalan_details = UjalanDetail::where("id_uj",$trx_trp->id_uj)->where("for_remarks",1)->orderBy("ordinal","asc")->get();
    if(count($ujalan_details)==0)
    throw new \Exception("Detail Ujalan Harus diisi terlebih dahulu",1);
    
    foreach ($ujalan_details as $key => $v) {
      array_push($arrRemarks,$v->xdesc." ".number_format($v->qty, 0,',','.')."x".number_format($v->harga, 0,',','.')."=".number_format($v->qty*$v->harga, 0,',','.').";");
    }

    if($ujalan->note_for_remarks!=null){
      $note_for_remarks_arr = preg_split('/\r\n|\r|\n/', $ujalan->note_for_remarks);
      $arrRemarks = array_merge($arrRemarks,$note_for_remarks_arr);
    }

    if($trx_trp->note_for_remarks!=null){
      $note_for_remarks_arr = preg_split('/\r\n|\r|\n/', $trx_trp->note_for_remarks);
      $arrRemarks = array_merge($arrRemarks,$note_for_remarks_arr);
    }
    
    $remarks = implode(chr(10),$arrRemarks);

    $ujalan_details2 = \App\Models\MySql\UjalanDetail2::where("id_uj",$trx_trp->id_uj)->get();
    if(count($ujalan_details2)==0)
    throw new \Exception("Detail PVR Harus diisi terlebih dahulu",1);

    if(strlen($associate_name)>80){
      $associate_name = substr($associate_name,0,80);
    }

    // $bank_account_code=env("PVR_BANK_ACCOUNT_CODE");
    $bank_account_code=$trx_trp->payment_method->account_code;
    
    $bank_acccount_db = DB::connection('sqlsrv')->table('FI_BankAccounts')
    ->select('BankAccountID')
    ->where("bankaccountcode",$bank_account_code)
    ->first();
    if(!$bank_acccount_db) throw new \Exception("Bank account code tidak terdaftar ,segera infokan ke tim IT",1);

    $bank_account_id = $bank_acccount_db->BankAccountID;
    
    // @VoucherID INT = 0,
    $voucher_no = "(AUTO)";
    $voucher_type = "TRP";
    $voucher_date = date("Y-m-d");

    $income_or_expense = 1;
    $currency_id = 1;
    $payment_method="Cash";
    $check_no=$bank_name=$account_no= '';
    $check_due_date= null;

    $sql = \App\Models\MySql\UjalanDetail2::selectRaw('SUM(qty*amount) as total')->where("id_uj",$trx_trp->id_uj)->first();
    $amount_paid = $sql->total; // call from child

    $ps_trip_supir_kernet = PSTripSupirKernet::fn_supir_kernet_for_transfer($trx_trp);
    $supir_money  = $ps_trip_supir_kernet['supir_money'];
    $kernet_money = $ps_trip_supir_kernet['kernet_money'];
    $ttl_ps       = $ps_trip_supir_kernet['supir_potongan_trx_money'];
    $ttl_pk       = $ps_trip_supir_kernet['kernet_potongan_trx_money'];


    // Start Potongan! CHECK Potongan
    // $supir_money = 0;
    // $kernet_money = 0;
    // $ttl_ps     = 0;
    // $ttl_pk     = 0;

    // foreach ($trx_trp->potongan as $k => $v) {
    //   if($v->potongan_mst->employee_id == $trx_trp->supir_id){
    //     $ttl_ps+=$v->nominal_cut;
    //   }

    //   if($v->potongan_mst->employee_id == $trx_trp->kernet_id){
    //     $ttl_pk+=$v->nominal_cut;
    //   }
    // }

    // // Done Potongan! GET Total Potongan Supir Kernet
    // foreach ($ujalan_details2 as $key => $v) {
    //   // Start Potongan! SET Total Uang Yang diterima Supir Kernet
    //   if($v->xfor=='Kernet'){
    //     $kernet_money+=$v->amount*$v->qty;
    //   }else{
    //     $supir_money+=$v->amount*$v->qty;
    //   }
    //   // Done Potongan! GET Total Uang Yang diterima Supir Kernet
    // }

    // Start Potongan! KURANGI Adm Qty Apabila Uang Tf 0 
    $no_adm_s = 0;
    $no_adm_k = 0;

    if($supir && $supir_money - $ttl_ps < 0)
    throw new \Exception("Supir. Potongan Melebih Uang Yang Akan Diterima",1);
    if($kernet && $kernet_money - $ttl_pk < 0)
    throw new \Exception("Kernet. Potongan Melebih Uang Yang Akan Diterima",1);

    if($supir && $supir_money - $ttl_ps == 0) $no_adm_s++;
    if($kernet && $kernet_money - $ttl_pk == 0) $no_adm_k++;

    // Admin Cost & Variable
    if($trx_trp->payment_method->id==2 || $trx_trp->payment_method->id==3){
      $adm_cost = $trx_trp->payment_method->id==2 ? 2500 : 5000;
      // $adm_cost = 5000;
      $adm_qty=0;
      if($supir){
        $adm_qty += (1-$no_adm_s);
      }
      if($kernet){
        $adm_qty += (1-$no_adm_k);
      }
      $amount_paid += ($adm_cost * $adm_qty);
    }

    if($trx_trp->payment_method->id==4 || $trx_trp->payment_method->id==5){
      $adm_qty=0;
      // // $adm_cost = 2500;
      // $adm_cost = $trx_trp->payment_method->id==4 ? 2500 : 6500;

      // $supir_is_not_mandiri = 0;
      // $kernet_is_not_mandiri = 0;
      // if($trx_trp->employee_s->bank->code != 'Mandiri'){
      //   $supir_is_not_mandiri=1;
      // }

      // if($trx_trp->employee_k){
      //   if($trx_trp->employee_k->bank->code != 'Mandiri'){
      //     $kernet_is_not_mandiri=1;
      //   }
      // }

      // $adm_qty=0;
      // if($supir){
      //   $remain = ($supir_is_not_mandiri - $no_adm_s)>=0?($supir_is_not_mandiri - $no_adm_s):0;
      //   $adm_qty += $remain;
      // }
      // if($kernet){
      //   $remain = ($kernet_is_not_mandiri - $no_adm_k)>=0?($kernet_is_not_mandiri - $no_adm_k):0;
      //   $adm_qty += $remain;
      // }
      // $amount_paid += ($adm_cost * $adm_qty);
    }

    $exclude_in_ARAP = 0;
    $login_name = $this->admin->the_user->username;
    $expense_or_revenue_type_id=0;
    $confidential=1;
    $PVR_source = 'gtsource'; // digenerate melalui program
    $PVR_source_id = $trx_trp_id; //ambil id trx
      // DB::select("exec USP_FI_APRequest_Update(0,'(AUTO)','TRP',1,1,1,0,)",array($ts,$param2));
    $VoucherID = -1;

    $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
    ->select('VoucherID','VoucherNo','AmountPaid')
    ->where("PVRSource",$PVR_source)
    ->where("PVRSourceID",$trx_trp->id)
    ->where("Void",0)
    ->first();

    if(!$pvr){
      // $myData = DB::connection('sqlsrv')->update("SET NOCOUNT ON;exec USP_FI_APRequest_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
      $myData = DB::connection('sqlsrv')->update("exec USP_FI_APRequest_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
      @VoucherDate=:voucher_date,@IncomeOrExpense=:income_or_expense,@CurrencyID=:currency_id,@AssociateName=:associate_name,
      @BankAccountID=:bank_account_id,@PaymentMethod=:payment_method,@CheckNo=:check_no,@CheckDueDate=:check_due_date,
      @BankName=:bank_name,@AmountPaid=:amount_paid,@AccountNo=:account_no,@Remarks=:remarks,@ExcludeInARAP=:exclude_in_ARAP,
      @LoginName=:login_name,@ExpenseOrRevenueTypeID=:expense_or_revenue_type_id,@Confidential=:confidential,
      @PVRSource=:PVR_source,@PVRSourceID=:PVR_source_id",[
        ":voucher_no"=>$voucher_no,
        ":voucher_type"=>$voucher_type,
        ":voucher_date"=>$voucher_date,
        ":income_or_expense"=>$income_or_expense,
        ":currency_id"=>$currency_id,
        ":associate_name"=>$associate_name,
        ":bank_account_id"=>$bank_account_id,
        ":payment_method"=>$payment_method,
        ":check_no"=>$check_no,
        ":check_due_date"=>$check_due_date,
        ":bank_name"=>$bank_name,
        ":amount_paid"=>$amount_paid,
        ":account_no"=>$account_no,
        ":remarks"=>$remarks,
        ":exclude_in_ARAP"=>$exclude_in_ARAP,
        ":login_name"=>$login_name,
        ":expense_or_revenue_type_id"=>$expense_or_revenue_type_id,
        ":confidential"=>$confidential,
        ":PVR_source"=>$PVR_source,
        ":PVR_source_id"=>$PVR_source_id,
      ]);
      if(!$myData)
      throw new \Exception("Data yang diperlukan tidak terpenuhi",1);
    
      $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
      ->select('VoucherID','VoucherNo','AmountPaid')
      ->where("PVRSource",$PVR_source)
      ->where("PVRSourceID",$trx_trp->id)
      ->where("Void",0)
      ->first();
      if(!$pvr)
      throw new \Exception("Akses Ke Jaringan Gagal",1);
    }

    $trx_trp->pvr_id = $pvr->VoucherID;
    $trx_trp->pvr_no = $pvr->VoucherNo;
    $trx_trp->pvr_total = $pvr->AmountPaid;
    $trx_trp->save();
    
    $d_voucher_id = $pvr->VoucherID;
    $d_voucher_extra_item_id = 0;
    $d_type = 0;

    $pvr_detail= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
    ->select('VoucherID')
    ->where("VoucherID",$d_voucher_id)
    ->get();

    if(count($pvr_detail)==0 || count($pvr_detail) < count($ujalan_details2)){
      $start = count($pvr_detail);
      foreach ($ujalan_details2 as $key => $v) {
        if($key < $start){ continue; }
        $d_description = $v->description;
        $d_amount = $v->qty * $v->amount;
        $d_account_id = $v->ac_account_id;
        $d_dept = $trx_trp->cost_center_code;
        $d_qty=$v->qty;
        $d_unit_price=$v->amount;
        $details = DB::connection('sqlsrv')->update("exec 
        USP_FI_APRequestExtraItems_Update @VoucherID=:d_voucher_id,
        @VoucherExtraItemID=:d_voucher_extra_item_id,
        @Description=:d_description,@Amount=:d_amount,
        @AccountID=:d_account_id,@TypeID=:d_type,
        @Department=:d_dept,@LoginName=:login_name,
        @Qty=:d_qty,@UnitPrice=:d_unit_price",[
          ":d_voucher_id"=>$d_voucher_id,
          ":d_voucher_extra_item_id"=>$d_voucher_extra_item_id,
          ":d_description"=>$d_description,
          ":d_amount"=>$d_amount,
          ":d_account_id"=>$d_account_id,
          ":d_type"=>$d_type,
          ":d_dept"=>$d_dept,
          ":login_name"=>$login_name,
          ":d_qty"=>$d_qty,
          ":d_unit_price"=>$d_unit_price
        ]);
      }
    }


    // End Potongan! Jangan Sertakan Biaya Admin jika Adm Qty 0 
    if($adm_qty > 0 && in_array($trx_trp->payment_method->id,[2,3,4,5])){
      $admin_cost_code=env("PVR_ADMIN_COST");
  
      $admin_cost_db = DB::connection('sqlsrv')->table('ac_accounts')
      ->select('AccountID')
      ->where('isdisabled',0)
      ->where("AccountCode",$admin_cost_code)
      ->first();
      if(!$admin_cost_db) throw new \Exception("GL account code tidak terdaftar ,segera infokan ke tim IT",1);

      $adm_cost_id = $admin_cost_db->AccountID;
      $adm_fee_exists= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
      ->select('VoucherID')
      ->where("VoucherID",$d_voucher_id)
      ->where("AccountID",$adm_cost_id)
      ->first();

      if(!$adm_fee_exists){
        $d_description  = "Biaya Admin";
        $d_account_id   = $adm_cost_id;
        $d_dept         = '112';
        $d_qty          = $adm_qty;
        $d_unit_price   = $adm_cost;
        $d_amount       = $d_qty * $d_unit_price;
  
        DB::connection('sqlsrv')->update("exec 
        USP_FI_APRequestExtraItems_Update @VoucherID=:d_voucher_id,
        @VoucherExtraItemID=:d_voucher_extra_item_id,
        @Description=:d_description,@Amount=:d_amount,
        @AccountID=:d_account_id,@TypeID=:d_type,
        @Department=:d_dept,@LoginName=:login_name,
        @Qty=:d_qty,@UnitPrice=:d_unit_price",[
          ":d_voucher_id"=>$d_voucher_id,
          ":d_voucher_extra_item_id"=>$d_voucher_extra_item_id,
          ":d_description"=>$d_description,
          ":d_amount"=>$d_amount,
          ":d_account_id"=>$d_account_id,
          ":d_type"=>$d_type,
          ":d_dept"=>$d_dept,
          ":login_name"=>$login_name,
          ":d_qty"=>$d_qty,
          ":d_unit_price"=>$d_unit_price
        ]);
      }
    }

    $tocheck = DB::connection('sqlsrv')->table('FI_APRequest')->where("VoucherID",$d_voucher_id)->first();

    if(!$tocheck)
    throw new \Exception("Voucher Tidak terdaftar",1);

    $checked2 = IsUser::where("id",$trx_trp->val1_user)->first();
    if(!$checked2)
    throw new \Exception("User Tidak terdaftar",1);

    if($tocheck->Checked==0){
      DB::connection('sqlsrv')->update("exec USP_FI_APRequest_DoCheck @VoucherID=:voucher_no,
      @CheckedBy=:login_name",[
        ":voucher_no"=>$d_voucher_id,
        ":login_name"=>$login_name,
      ]);
    }

    if($tocheck->Approved==0){
      DB::connection('sqlsrv')->update("exec USP_FI_APRequest_DoApprove @VoucherID=:voucher_no,
      @ApprovedBy=:login_name",[
        ":voucher_no"=>$d_voucher_id,
        ":login_name"=>$checked2->username,
      ]);
    }

    $trx_trp->pvr_had_detail = 1;
    $trx_trp->save();

    return [
      "message" => "PVR berhasil dibuat",
      "id"=>$trx_trp->id,
      "pvr_id" => $trx_trp->pvr_id,
      "pvr_no" => $trx_trp->pvr_no,
      "pvr_total" => $trx_trp->pvr_total,
      "pvr_had_detail" => $trx_trp->pvr_had_detail,
      "updated_at"=>$t_stamp
    ];
  }


  public function doGenPV(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.generate_pv');
    $rules = [
      // 'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
      'online_status' => "required",
    ];

    $messages = [
      // 'id.required' => 'ID tidak boleh kosong',
      // 'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }
    $online_status=$request->online_status;
    if($online_status!="true")
    return response()->json([
      "message" => "Mode Harus Online",
    ], 400);

    $miniError="";
    $id="";
    try {
      $trx_trps = TrxTrp::where(function($q1){$q1->where('pv_complete',0)->orWhereNull("pv_id");})->whereNotNull("pvr_id")->where("req_deleted",0)->where("deleted",0)->where('val',1)->where('val1',1)->where('val2',1)
      ->where(function ($q) {
        $q->where(function ($q1){
          $q1->where("payment_method_id",1);       
          $q1->where("received_payment",0);                  
        });

        $q->orWhere(function ($q1){
          $q1->whereIn("payment_method_id",[2,3,4,5]);
          $q1->where("received_payment",1);                 
        });
      })->get();      
      if(count($trx_trps)==0){
        throw new \Exception("Semua PV sudah terisi",1);
      }
      $changes=[];
      foreach ($trx_trps as $key => $tt) {
        $id=$tt->id;
        $callGet = $this->genPV($id);
        array_push($changes,$callGet);
      }
      if(count($changes)>0){
        $ids = array_map(function ($x) {
          return $x["id"];
        },$changes);
        MyLog::sys("trx_trp",null,"generate_pv",implode(",",$ids));
      }
      return response()->json($changes, 200);
    } catch (\Exception $e) {
      if(isset($changes) && count($changes)>0){
        $ids = array_map(function ($x) {
          return $x["id"];
        },$changes);
        MyLog::sys("trx_trp",null,"generate_pv",implode(",",$ids));
      }

      if ($e->getCode() == 1) {
        if($id!=""){
          $miniError.="Trx-".$id.".";
        }
        $miniError.="PV Batal Dibuat: ".$e->getMessage();
      }else{
        if($id!=""){
          $miniError.="Trx-".$id.".";
        }
        $miniError.="PV Batal Dibuat. Akses Jaringan Gagal";
      }
      return response()->json([
        "message" => $miniError,
      ], 400);
    }
  }

  public function genPV($trx_trp_id){

    $t_stamp = date("Y-m-d H:i:s");

    $trx_trp = TrxTrp::where("id",$trx_trp_id)->first();
    if(!$trx_trp){
      throw new \Exception("Karna Transaksi tidak ditemukan",1);
    }

    if($trx_trp->pv_complete==1) throw new \Exception("Karna PV sudah selesai dibuat",1);
    if($trx_trp->pvr_id==null) throw new \Exception("Karna PVR Masih Kosong",1);
    if($trx_trp->pvr_had_detail==0) throw new \Exception("Karna PVR Masih Belum Selesai",1);

    $pvr_dt = DB::connection('sqlsrv')->table('FI_APRequest')
    ->select('BankAccountID','AmountPaid','VoucherID','AssociateName','Remarks','VoucherType','IncomeOrExpense','CurrencyID','PaymentMethod','CheckNo','CheckDueDate','BankName','AccountNo','ExcludeInARAP','ExpenseOrRevenueTypeID','Confidential')
    ->where("VoucherID",$trx_trp->pvr_id)
    ->first();

    if(!$pvr_dt) throw new \Exception("PVR tidak terdaftar ,segera infokan ke tim IT",1);
    
    $voucher_no = "(AUTO)";
    $voucher_date = date("Y-m-d");

    $login_name = $this->admin->the_user->username;
    $sourceVoucherId = $trx_trp->pvr_id; //ambil pvr id trx

    $pv= DB::connection('sqlsrv')->table('FI_ARAP')
    ->select('VoucherID','VoucherDate','VoucherNo','AmountPaid','SourceVoucherId')
    ->where("SourceVoucherId",$sourceVoucherId)
    ->where("Void",0)
    ->first();

    if(!$pv){
      $myData = DB::connection('sqlsrv')->update("exec USP_FI_ARAP_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
      @VoucherDate=:voucher_date,@IncomeOrExpense=:income_or_expense,@CurrencyID=:currency_id,@AssociateName=:associate_name,
      @BankAccountID=:bank_account_id,@PaymentMethod=:payment_method,@CheckNo=:check_no,@CheckDueDate=:check_due_date,
      @BankName=:bank_name,@AmountPaid=:amount_paid,@AccountNo=:account_no,@Remarks=:remarks,@ExcludeInARAP=:exclude_in_ARAP,
      @LoginName=:login_name,@ExpenseOrRevenueTypeID=:expense_or_revenue_type_id,@Confidential=:confidential,
      @SourceVoucherId=:sourceVoucherId",[
        ":voucher_no"                 => $voucher_no,
        ":voucher_type"               => $pvr_dt->VoucherType,
        ":voucher_date"               => $voucher_date,
        ":income_or_expense"          => $pvr_dt->IncomeOrExpense,
        ":currency_id"                => $pvr_dt->CurrencyID,
        ":associate_name"             => $pvr_dt->AssociateName,
        ":bank_account_id"            => $pvr_dt->BankAccountID,
        ":payment_method"             => $pvr_dt->PaymentMethod,
        ":check_no"                   => $pvr_dt->CheckNo,
        ":check_due_date"             => $pvr_dt->CheckDueDate,
        ":bank_name"                  => $pvr_dt->BankName,
        ":amount_paid"                => $pvr_dt->AmountPaid,
        ":account_no"                 => $pvr_dt->AccountNo,
        ":remarks"                    => $pvr_dt->Remarks,
        ":exclude_in_ARAP"            => $pvr_dt->ExcludeInARAP,
        ":login_name"                 => $login_name,
        ":expense_or_revenue_type_id" => $pvr_dt->ExpenseOrRevenueTypeID,
        ":confidential"               => $pvr_dt->Confidential,
        ":sourceVoucherId"            => $sourceVoucherId,
      ]);
      if(!$myData)
      throw new \Exception("Data yang diperlukan tidak terpenuhi",1);
    
      $pv= DB::connection('sqlsrv')->table('FI_ARAP')
      ->select('VoucherID','VoucherDate','VoucherNo','AmountPaid','SourceVoucherId')
      ->where("SourceVoucherId",$sourceVoucherId)
      ->where("Void",0)
      ->first();
      if(!$pv)
      throw new \Exception("Akses Ke Jaringan Gagal",1);
    }

    $trx_trp->pv_id       = $pv->VoucherID;
    $trx_trp->pv_datetime = $pv->VoucherDate;
    $trx_trp->pv_no       = $pv->VoucherNo;
    $trx_trp->pv_total    = $pv->AmountPaid;
    $trx_trp->save();
    
    $d_voucher_id       = $pv->VoucherID;
    $d_voucher_extra_item_id = 0;

    $pvr_detail= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
    ->select('VoucherID','VoucherExtraItemID','Description','Amount','AccountID','TypeID','Department','Qty','UnitPrice')
    ->where("VoucherID",$pvr_dt->VoucherID)
    ->get();


    $pv_detail= DB::connection('sqlsrv')->table('FI_ARAPExtraItems')
    ->select('VoucherID')
    ->where("VoucherID",$d_voucher_id)
    ->get();

    if(count($pv_detail)==0 || count($pv_detail) < count($pvr_detail)){
      $start = count($pv_detail);
      foreach ($pvr_detail as $key => $v) {
        if($key < $start){ continue; }
        $details = DB::connection('sqlsrv')->update("exec 
        USP_FI_ARAPExtraItems_Update @VoucherID=:d_voucher_id,
        @VoucherExtraItemID=:d_voucher_extra_item_id,
        @Description=:d_description,@Amount=:d_amount,
        @AccountID=:d_account_id,@TypeID=:d_type,
        @Department=:d_dept,@LoginName=:login_name,
        @Qty=:d_qty,@UnitPrice=:d_unit_price,@PVRVoucherExtraItemID=:pvr_voucher_extra_item_id",[
          ":d_voucher_id"               => $d_voucher_id,
          ":d_voucher_extra_item_id"    => $d_voucher_extra_item_id,
          ":d_description"              => $v->Description,
          ":d_amount"                   => $v->Amount,
          ":d_account_id"               => $v->AccountID,
          ":d_type"                     => $v->TypeID,
          ":d_dept"                     => $v->Department,
          ":login_name"                 => $login_name,
          ":d_qty"                      => $v->Qty,
          ":d_unit_price"               => $v->UnitPrice,
          ":pvr_voucher_extra_item_id"  => $v->VoucherExtraItemID
        ]);
      }
    }

    if($pv){
      DB::connection('sqlsrv')->update("exec 
      USP_FI_ARAP_UpdateAmountAllocated @VoucherID=:d_voucher_id",[
        ":d_voucher_id"               => $d_voucher_id,
      ]);

      DB::connection('sqlsrv')->update("exec 
      USP_FI_ARAPSources_Update @VoucherID=:d_voucher_id, @PVRVoucherID=:d_pvr_voucher_id",[
        ":d_voucher_id"               => $d_voucher_id,
        ":d_pvr_voucher_id"           => $pvr_dt->VoucherID,
      ]);

      DB::connection('sqlsrv')->update("exec 
      USP_FI_ARAP_BatchPostingToGL @VoucherID=:d_voucher_id, @LoginName=:login_name",[
        ":d_voucher_id"               => $d_voucher_id,
        ":login_name"                 => $login_name,
      ]);
    }

    $trx_trp->pv_complete = 1;
    $trx_trp->save();

    return [
      "message"     => "PVR berhasil dibuat",
      "id"          => $trx_trp->id,
      "pv_id"       => $trx_trp->pv_id,
      "pv_no"       => $trx_trp->pv_no,
      "pv_datetime" => $trx_trp->pv_datetime,
      "pv_total"    => $trx_trp->pv_total,
      "pv_complete" => $trx_trp->pv_complete,
      "updated_at"  => $t_stamp
    ];
  }

  public function doUpdatePV(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.get_pv');
    $rules = [
      'online_status' => "required",
    ];

    $messages = [
      'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }
    $online_status=$request->online_status;
    if($online_status!="true")
    return response()->json([
      "message" => "Mode Harus Online",
    ], 400);

    $miniError="";
    try {
      $t_stamp = date("Y-m-d H:i:s");
      $trx_trps = TrxTrp::whereNotNull("pvr_id")->where('pvr_had_detail',1)->whereNull("pv_id")->where("deleted",0)->where("req_deleted",0)->get();
      if(count($trx_trps)==0){
        throw new \Exception("Semua PVR yang ada ,PV ny sudah terisi",1);
      }

      $pvr_nos=$trx_trps->pluck('pvr_no');
      // $pvr_nos=['KPN/PV-R/2404/0951','KPN/PV-R/2404/1000'];
      $get_data_pvs = DB::connection('sqlsrv')->table('FI_ARAPINFO')
      ->selectRaw('fi_arap.VoucherID,fi_arap.VoucherDate,Sources,fi_arap.VoucherNo,FI_APRequest.PVRSourceID,fi_arap.AmountPaid')
      ->join('fi_arap',function ($join){
        $join->on("fi_arap.VoucherID","FI_ARAPINFO.VoucherID");        
      })
      ->join('FI_APRequest',function ($join){
        $join->on("FI_APRequest.VoucherNo","FI_ARAPINFO.Sources");        
      })
      ->whereIn("sources",$pvr_nos)
      ->get();

      $get_data_pvs=MyLib::objsToArray($get_data_pvs);
      $changes=[];
      foreach ($get_data_pvs as $key => $v) {
        $ud_trx_trp=TrxTrp::where("id", $v["PVRSourceID"])->where("pvr_no", $v["Sources"])->first();
        if(!$ud_trx_trp) continue;
        $ud_trx_trp->pv_id=$v["VoucherID"];
        $ud_trx_trp->pv_no=$v["VoucherNo"];
        $ud_trx_trp->pv_total=$v["AmountPaid"];
        $ud_trx_trp->pv_datetime=$v["VoucherDate"];
        $ud_trx_trp->pv_complete=1;
        $ud_trx_trp->updated_at=$t_stamp;
        $ud_trx_trp->save();
        array_push($changes,[
          "id"=>$ud_trx_trp->id,
          "pv_id"=>$ud_trx_trp->pv_id,
          "pv_no"=>$ud_trx_trp->pv_no,
          "pv_total"=>$ud_trx_trp->pv_total,
          "pv_datetime"=>$ud_trx_trp->pv_datetime,
          "pv_complete"=>$ud_trx_trp->pv_complete,
          "updated_at"=>$t_stamp
        ]);
      }

      if(count($changes)==0)
      throw new \Exception("PV Tidak ada yang di Update",1);

      $ids = array_map(function ($x) {
        return $x["id"];
      }, $changes);
      MyLog::sys("trx_trp",null,"update_pv",implode(",",$ids));

      return response()->json([
        "message" => "PV Berhasil di Update",
        "data" => $changes,
      ], 200);
      
    } catch (\Exception $e) {
      if ($e->getCode() == 1) {
        $miniError="PV Batal Update: ".$e->getMessage();
      }else{
        $miniError="PV Batal Update. Akses Jaringan Gagal";
      }
      return response()->json([
        "message" => $miniError,
      ], 400);
    }
  }

  public function delete_absen(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.absen.remove');

    $ids = json_decode($request->ids, true);


    $rules = [
      
      // 'details'                          => 'required|array',
      'details.*.id'               => 'required|exists:\App\Models\MySql\TrxAbsen,id',
    ];

    $messages = [
      'details.required' => 'Item harus di isi',
      'details.array' => 'Format Pengambilan Barang Salah',
    ];

    // // Replace :index with the actual index value in the custom error messages
    foreach ($ids as $k => $v) {
      $messages["details.{$k}.id_uj.required"]          = "Baris #" . ($k + 1) . ". ID tidak boleh kosong.";
      $messages["details.{$k}.id_uj.exists"]            = "Baris #" . ($k + 1) . ". ID harus diisi";
    }

    $validator = Validator::make(['details' => $ids], $rules, $messages);

    // Check if validation fails
    if ($validator->fails()) {
      foreach ($validator->messages()->all() as $k => $v) {
        throw new MyException(["message" => $v], 400);
      }
    }


    DB::beginTransaction();

    try {
      $all_id = array_map(function ($x){
        return $x['id'];
      },$ids);

      $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      
      if($model_query->val4==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      $model_query = TrxAbsen::whereIn("id",$all_id)->where("status","B")->lockForUpdate()->delete();

      MyLog::sys("trx_absen",null,"delete",implode(",",$all_id));

      DB::commit();
      return response()->json([
        "message" => "Proses Hapus data berhasil",
      ], 200);
    } catch (\Exception  $e) {
      DB::rollback();
      if ($e->getCode() == "23000")
        return response()->json([
          "message" => "Data tidak dapat dihapus, data terkait dengan data yang lain nya",
        ], 400);

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
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  }


  public static function permit_continue_trx($trx_trp, $return = false) {
    
    $supir_absen=TrxTrp::where(function($q)use($trx_trp){
      $q->where("supir_id",$trx_trp->supir_id);
      $q->orWhere("kernet_id",$trx_trp->supir_id);
    })
    ->whereIn("jenis",['TBS','TBSK','CPO','PK','LAIN'])
    ->where("deleted",0)
    ->where("req_deleted",0)
    ->orderBy("tanggal","desc")
    ->orderBy("id","desc")
    ->where("tanggal",">=","2025-10-01")
    ->where(
      function ($q){
        $q->whereNull('ritase_leave_at');
        $q->orWhereNull('ritase_arrive_at');
        $q->orWhereNull('ritase_return_at');
        $q->orWhereNull('ritase_till_at');
      }
    )
    ->where("id","!=",$trx_trp->id)
    ->where("id","<",$trx_trp->id)
    ->pluck('id')->toArray();
    
    if(count($supir_absen) > 1 && !$return)
    throw new \Exception("Absen Supir Belum Selesai [ID:".implode(",",$supir_absen)."]",1);

    $res = $supir_absen;

    if($trx_trp->kernet_id){
      $kernet_absen=TrxTrp::where(function($q)use($trx_trp){
          $q->where("supir_id",$trx_trp->kernet_id);
          $q->orWhere("kernet_id",$trx_trp->kernet_id);
      })
      ->whereIn("jenis",['TBS','TBSK','CPO','PK','LAIN'])
      ->where("deleted",0)
      ->where("req_deleted",0)
      ->orderBy("tanggal","desc")
      ->orderBy("id","desc")
      ->where("tanggal",">=","2025-10-01")
      // ->where(
      //   function ($q){
      //     $q->whereNull('ritase_leave_at');
      //     $q->orWhereNull('ritase_arrive_at');
      //     $q->orWhereNull('ritase_return_at');
      //     $q->orWhereNull('ritase_till_at');
      //     $q->orWhere("ritase_val2",0);
      //   }
      // )
      ->where("ritase_val2",0)
      ->where("id","!=",$trx_trp->id)
      ->where("id","<",$trx_trp->id)
      ->pluck('id')->toArray();
      
      $res+=$kernet_absen;
      // return $supir_absen;
      if(count($kernet_absen) > 1 && !$return)
      throw new \Exception("Absen Kernet Belum Selesai [ID:".implode(",",$kernet_absen)."]",1);
    }

    if($return){
      return array_unique($res);
    }
  }

  public function LinkToExtraMoneyTrx(Request $request)
  {
    DB::beginTransaction();
    try {
      $model_query = new TrxTrp();

      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->whereIn('payment_method_id',[2,3,4,5])->where('val',1)->where('val1',1)->where('val2',1)->where(function ($q){
        $q->where('val5',1)->orWhere('val6',1);
      })->where('received_payment',0)->whereDoesntHave('fin_payment_req_dtl')->get();
      $update=0;
      $syslogs=[];
      foreach ($model_query as $key => $mq) {
        $all_id=[];
        $emts =ExtraMoneyTrx::where(function ($q)use($mq){
          $q->where("employee_id",$mq->supir_id)->orWhere("employee_id",$mq->kernet_id);
        })->whereIn("payment_method_id",[2,3,4,5])->where("received_payment",0)->where("deleted",0)->where("req_deleted",0)
        ->where('val1',1)->where('val2',1)->where('val3',1)->where('val4',1)->where('val5',1)->where('val6',1)
        ->whereNull("trx_trp_id")->get();

        foreach ($emts as $kemt => $emt) {
          $emt->trx_trp_id = $mq->id;
          $emt->update();
          $update++;
          array_push($all_id,$emt->id);
        }

        if(count($all_id)>0){
          array_push($syslogs,"trx_trp_id:".$mq->id."->".implode(",",$all_id));
        }
      }
      MyLog::sys("extra_money",null,"linktotrxtrp",implode("|",$syslogs));
      DB::commit();
      if($update==0){
        return response()->json([
          "message" => "Extra Money Tidak ada yang Dihubungkan",
        ], 400);  
      }
      return response()->json([
        "message" => "Extra Money Berhasil Dihubungkan",
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
        "message" => "Extra Money Gagal Dihubungkan",
      ], 400);
    }
  }


  // public function indexold(Request $request, $download = false)
  // {

  //   //======================================================================================================
  //   // Pembatasan Data hanya memerlukan limit dan offset
  //   //======================================================================================================

  //   $limit = 100; // Limit +> Much Data
  //   if (isset($request->limit)) {
  //     if ($request->limit <= 250) {
  //       $limit = $request->limit;
  //     } else {
  //       throw new MyException(["message" => "Max Limit 250"]);
  //     }
  //   }

  //   $offset = isset($request->offset) ? (int) $request->offset : 0; // example offset 400 start from 401

  //   //======================================================================================================
  //   // Jika Halaman Ditentutkan maka $offset akan disesuaikan
  //   //======================================================================================================
  //   if (isset($request->page)) {
  //     $page =  (int) $request->page;
  //     $offset = ($page * $limit) - $limit;
  //   }


  //   //======================================================================================================
  //   // Init Model
  //   //======================================================================================================
  //   $model_query = new TrxTrp();
  //   if (!$download) {
  //     $model_query = $model_query->offset($offset)->limit($limit);
  //   }

  //   $first_row=[];
  //   if($request->first_row){
  //     $first_row 	= json_decode($request->first_row, true);
  //   }

  //   //======================================================================================================
  //   // Model Sorting | Example $request->sort = "username:desc,role:desc";
  //   //======================================================================================================
    

  //   if ($request->sort) {
  //     $sort_lists = [];

  //     $sorts = explode(",", $request->sort);
  //     foreach ($sorts as $key => $sort) {
  //       $side = explode(":", $sort);
  //       $side[1] = isset($side[1]) ? $side[1] : 'ASC';
  //       $sort_symbol = $side[1] == "desc" ? "<=" : ">=";
  //       $sort_lists[$side[0]] = $side[1];
  //     }

  //     if (isset($sort_lists["id"])) {
  //       $model_query = $model_query->orderBy("id", $sort_lists["id"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("id",$sort_symbol,$first_row["id"]);
  //       }
  //     }

  //     if (isset($sort_lists["xto"])) {
  //       $model_query = $model_query->orderBy("xto", $sort_lists["xto"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("xto",$sort_symbol,$first_row["xto"]);
  //       }
  //     }

  //     if (isset($sort_lists["tipe"])) {
  //       $model_query = $model_query->orderBy("tipe", $sort_lists["tipe"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("tipe",$sort_symbol,$first_row["tipe"]);
  //       }
  //     }

  //     if (isset($sort_lists["pv_no"])) {
  //       $model_query = $model_query->orderBy("pv_no", $sort_lists["pv_no"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("pv_no",$sort_symbol,$first_row["pv_no"]);
  //       }
  //     }

  //     if (isset($sort_lists["ticket_a_no"])) {
  //       $model_query = $model_query->orderBy("ticket_a_no", $sort_lists["ticket_a_no"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("ticket_a_no",$sort_symbol,$first_row["ticket_a_no"]);
  //       }
  //     }

  //     if (isset($sort_lists["ticket_b_no"])) {
  //       $model_query = $model_query->orderBy("ticket_b_no", $sort_lists["ticket_b_no"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("ticket_b_no",$sort_symbol,$first_row["ticket_b_no"]);
  //       }
  //     }

  //     if (isset($sort_lists["supir"])) {
  //       $model_query = $model_query->orderBy("supir", $sort_lists["supir"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("supir",$sort_symbol,$first_row["supir"]);
  //       }
  //     }

  //     if (isset($sort_lists["kernet"])) {
  //       $model_query = $model_query->orderBy("kernet", $sort_lists["kernet"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("kernet",$sort_symbol,$first_row["kernet"]);
  //       }
  //     }

  //     if (isset($sort_lists["no_pol"])) {
  //       $model_query = $model_query->orderBy("no_pol", $sort_lists["no_pol"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("no_pol",$sort_symbol,$first_row["no_pol"]);
  //       }
  //     }

  //     if (isset($sort_lists["tanggal"])) {
  //       $model_query = $model_query->orderBy("tanggal", $sort_lists["tanggal"])->orderBy('id','DESC');
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("tanggal",$sort_symbol,$first_row["tanggal"])->orderBy('id','DESC');
  //       }
  //     }

  //     if (isset($sort_lists["cost_center_code"])) {
  //       $model_query = $model_query->orderBy("cost_center_code", $sort_lists["cost_center_code"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("cost_center_code",$sort_symbol,$first_row["cost_center_code"]);
  //       }
  //     }
  //     if (isset($sort_lists["cost_center_desc"])) {
  //       $model_query = $model_query->orderBy("cost_center_desc", $sort_lists["cost_center_desc"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("cost_center_desc",$sort_symbol,$first_row["cost_center_desc"]);
  //       }
  //     }
  //     if (isset($sort_lists["pvr_id"])) {
  //       $model_query = $model_query->orderBy("pvr_id", $sort_lists["pvr_id"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("pvr_id",$sort_symbol,$first_row["pvr_id"]);
  //       }
  //     }
  //     if (isset($sort_lists["pvr_no"])) {
  //       $model_query = $model_query->orderBy("pvr_no", $sort_lists["pvr_no"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("pvr_no",$sort_symbol,$first_row["pvr_no"]);
  //       }
  //     }

      
  //     // if (isset($sort_lists["tipe"])) {
  //     //   $model_query = $model_query->orderBy("tipe", $sort_lists["tipe"]);
  //     //   if (count($first_row) > 0) {
  //     //     $model_query = $model_query->where("tipe",$sort_symbol,$first_row["tipe"]);
  //     //   }
  //     // }

  //     // if (isset($sort_lists["jenis"])) {
  //     //   $model_query = $model_query->orderBy("jenis", $sort_lists["jenis"]);
  //     //   if (count($first_row) > 0) {
  //     //     $model_query = $model_query->where("jenis",$sort_symbol,$first_row["jenis"]);
  //     //   }
  //     // }
      

  //   } else {
  //     $model_query = $model_query->orderBy('tanggal', 'DESC')->orderBy('id','DESC');
  //   }
  //   //======================================================================================================
  //   // Model Filter | Example $request->like = "username:%username,role:%role%,name:role%,";
  //   //======================================================================================================

  //   if ($request->like) {
  //     $like_lists = [];

  //     $likes = explode(",", $request->like);
  //     foreach ($likes as $key => $like) {
  //       $side = explode(":", $like);
  //       $side[1] = isset($side[1]) ? $side[1] : '';
  //       $like_lists[$side[0]] = $side[1];
  //     }

  //     if(count($like_lists) > 0){
  //       $model_query = $model_query->where(function ($q)use($like_lists){
            
  //         if (isset($like_lists["id"])) {
  //           $q->orWhere("id", "like", $like_lists["id"]);
  //         }
    
  //         if (isset($like_lists["xto"])) {
  //           $q->orWhere("xto", "like", $like_lists["xto"]);
  //         }
    
  //         if (isset($like_lists["tipe"])) {
  //           $q->orWhere("tipe", "like", $like_lists["tipe"]);
  //         }

  //         if (isset($like_lists["jenis"])) {
  //           $q->orWhere("jenis", "like", $like_lists["jenis"]);
  //         }

  //         if (isset($like_lists["pv_no"])) {
  //           $q->orWhere("pv_no", "like", $like_lists["pv_no"]);
  //         }

  //         if (isset($like_lists["ticket_a_no"])) {
  //           $q->orWhere("ticket_a_no", "like", $like_lists["ticket_a_no"]);
  //         }

  //         if (isset($like_lists["ticket_b_no"])) {
  //           $q->orWhere("ticket_b_no", "like", $like_lists["ticket_b_no"]);
  //         }

  //         if (isset($like_lists["supir"])) {
  //           $q->orWhere("supir", "like", $like_lists["supir"]);
  //         }
  //         if (isset($like_lists["kernet"])) {
  //           $q->orWhere("kernet", "like", $like_lists["kernet"]);
  //         }
  //         if (isset($like_lists["no_pol"])) {
  //           $q->orWhere("no_pol", "like", $like_lists["no_pol"]);
  //         }
  //         if (isset($like_lists["tanggal"])) {
  //           $q->orWhere("tanggal", "like", $like_lists["tanggal"]);
  //         }
  //         if (isset($like_lists["cost_center_code"])) {
  //           $q->orWhere("cost_center_code", "like", $like_lists["cost_center_code"]);
  //         }
  //         if (isset($like_lists["cost_center_desc"])) {
  //           $q->orWhere("cost_center_desc", "like", $like_lists["cost_center_desc"]);
  //         }
  //         if (isset($like_lists["pvr_id"])) {
  //           $q->orWhere("pvr_id", "like", $like_lists["pvr_id"]);
  //         }
  //         if (isset($like_lists["pvr_no"])) {
  //           $q->orWhere("pvr_no", "like", $like_lists["pvr_no"]);
  //         }
  //         if (isset($like_lists["transition_target"])) {
  //           $q->orWhere("transition_target", "like", $like_lists["transition_target"]);
  //         }
    
  //         // if (isset($like_lists["requested_name"])) {
  //         //   $q->orWhereIn("requested_by", function($q2)use($like_lists) {
  //         //     $q2->from('is_users')
  //         //     ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
  //         //   });
  //         // }
    
  //         // if (isset($like_lists["confirmed_name"])) {
  //         //   $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
  //         //     $q2->from('is_users')
  //         //     ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
  //         //   });
  //         // }
  //       });        
  //     }

      
  //   }

  //   // ==============
  //   // Model Filter
  //   // ==============
  //   if($request->date_from || $request->date_to){
  //     $date_from = $request->date_from;
  //     if(!$date_from)
  //     throw new MyException([ "date_from" => ["Date From harus diisi"] ], 422);

  //     if(!strtotime($date_from))
  //     throw new MyException(["date_from"=>["Format Date From Tidak Cocok"]], 422);
      
  //     $date_to = $request->date_to;
  //     if(!$date_to)
  //     throw new MyException([ "date_to" => ["Date To harus diisi"] ], 422);

  //     if(!strtotime($date_to))
  //     throw new MyException(["date_to"=>["Format Date To Tidak Cocok"]], 422);
      
  //     $model_query = $model_query->whereBetween("tanggal",[$request->date_from,$request->date_to]);
  //   }

  //   $filter_status = $request->filter_status;
    
  //   // if(in_array($this->role,["Finance","Accounting"])){
  //   //   $filter_status = "pv_done";
  //   // }

  //   // if(in_array($this->role,["Marketing","MIS"])){
  //   //   $filter_status = "ticket_done";
  //   // }

  //   if($filter_status=="pv_done"){
  //     $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->whereNotNull("pv_no");
  //   }

  //   if($filter_status=="ticket_done"){
  //     $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where(function ($q){
  //         $q->orWhere(function ($q1){
  //           $q1->where("jenis","TBS")->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_no");
  //         });
  //         $q->orWhere(function ($q1){
  //           $q1->where("jenis","TBSK")->whereNotNull("ticket_b_no");
  //         });
  //         $q->orWhere(function ($q1){
  //           $q1->whereIn("jenis",["CPO","PK"])->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_in_at")->whereNotNull("ticket_b_out_at")->where("ticket_b_bruto",">",1)->where("ticket_b_tara",">",1)->where("ticket_b_netto",">",1);
  //         });
  //     });
  //   }

  //   if($filter_status=="ticket_not_done"){
  //     $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where(function ($q){
  //         $q->orWhere(function ($q1){
  //           $q1->where("jenis","TBS")->whereNull("ticket_a_no")->whereNull("ticket_b_no");
  //         });
  //         $q->orWhere(function ($q1){
  //           $q1->where("jenis","TBSK")->whereNull("ticket_b_no");
  //         });
  //         $q->orWhere(function ($q1){
  //           $q1->whereIn("jenis",["CPO","PK"])->whereNull("ticket_a_no")->whereNull("ticket_b_in_at")->whereNull("ticket_b_out_at")->whereNull("ticket_b_bruto")->whereNull("ticket_b_tara")->whereNull("ticket_b_netto");
  //         });
  //     });
  //   }

  //   if($filter_status=="pv_not_done"){
  //     $model_query = $model_query->where("deleted",0)->whereNull("pv_no")->where("req_deleted",0);
  //   }

  //   if($filter_status=="ritase_done"){
  //     $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where('ritase_val',1);
  //   }

  //   if($filter_status=="mandor_trx_unverified"){
  //     $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where('val',1)->where('val1',0);
  //   }

  //   if($filter_status=="deleted"){
  //     $model_query = $model_query->where("deleted",1);
  //   }

  //   if($filter_status=="req_deleted"){
  //     $model_query = $model_query->where("deleted",0)->where("req_deleted",1);
  //   }

  //   $model_query = $model_query->with(['val_by','val1_by','val2_by','deleted_by','req_deleted_by','trx_absens'=>function($q) {
  //     $q->select('id','trx_trp_id','created_at','updated_at');
  //   }])->get();

  //   return response()->json([
  //     "data" => TrxTrpResource::collection($model_query),
  //   ], 200);
  // }
}
