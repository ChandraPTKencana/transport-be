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
use App\Models\MySql\SalaryBonus;

use App\Models\MySql\TrxAbsen;
use App\Models\MySql\Ujalan;
use App\Models\MySql\UjalanDetail;

use App\Http\Requests\MySql\TrxTrpRequest;
use App\Http\Requests\MySql\TrxTrpTicketRequest;

use App\Http\Resources\MySql\TrxTrpResource;
use App\Http\Resources\MySql\IsUserResource;

use App\Exports\MyReport;
use App\Models\MySql\TempData;
use App\Models\MySql\Vehicle;
use App\PS\PSPotonganTrx;
use PHPUnit\Framework\Constraint\Count;

class TrxTrpTicketController extends Controller
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

  public function index(Request $request, $download = false)
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
          if(array_search($value['key'],['uj_asst_opt'])!==false){
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
          }else if(array_search($key,['uj_asst_opt'])!==false){
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

    if($filter_status=="ticket_done"){
      // $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where(function ($q){
      //     $q->orWhere(function ($q1){
      //       $q1->where("jenis","TBS")->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_no");
      //     });
      //     $q->orWhere(function ($q1){
      //       $q1->where("jenis","TBSK")->whereNotNull("ticket_b_no");
      //     });
      //     $q->orWhere(function ($q1){
      //       $q1->whereIn("jenis",["CPO","PK"])->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_in_at")->whereNotNull("ticket_b_out_at")->where("ticket_b_bruto",">",1)->where("ticket_b_tara",">",1)->where("ticket_b_netto",">",1);
      //     });
      //     $q->orWhereNotNull("ticket_note");
      // })->where('val_ticket',1);
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)
      ->where('val_ticket',1);
    }

    if($filter_status=="ticket_not_done"){
      // $model_query = $model_query->where("deleted",0)->where("req_deleted",0)
      // ->where(function ($q){
      //     $q->orWhere(function ($q1){
      //       $q1->where("jenis","TBS")->where(function($q2){
      //         $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_no");
      //       });
      //     });
      //     $q->orWhere(function ($q1){
      //       $q1->where("jenis","TBSK")->whereNull("ticket_b_no");
      //     });
      //     $q->orWhere(function ($q1){
      //       $q1->whereIn("jenis",["CPO","PK"])->where(function($q2){
      //         $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_in_at")->orWhereNull("ticket_b_out_at")->orWhereNull("ticket_b_bruto")->orWhereNull("ticket_b_tara")->orWhereNull("ticket_b_netto");
      //       });
      //     });
      //     // $q->orWhere('val_ticket',0);
      // })->where('val_ticket',0);

      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)
      ->where('val_ticket',0);
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

    $model_query = $model_query->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','uj','salary_paid','trx_absens'=>function($q) {
      $q->select('id','trx_trp_id','created_at','updated_at')->where("status","B");
    }])->get();

    return response()->json([
      "data" => TrxTrpResource::collection($model_query),
    ], 200);
  }

  public function show(TrxTrpRequest $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.view','trp_trx.ticket.view']);

    $model_query = TrxTrp::with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','uj','trx_absens'=>function ($q){
      $q->where("status","B");
    },'potongan'])->find($request->id);
    return response()->json([
      "data" => new TrxTrpResource($model_query),
    ], 200);
  }

  public function updateTicket(TrxTrpTicketRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.modify');
    
    $t_stamp = date("Y-m-d H:i:s");

    $transition_target = $request->transition_target;
    if($transition_target==env("app_name") || !in_array($transition_target,MyLib::$list_pabrik)){
      $transition_target="";
    }

    DB::beginTransaction();
    try {
      $model_query             = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD                  = clone($model_query);
      
      if($model_query->val_ticket==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);


      if($request->ticket_a_id){

        $get_data_ticket = $this->getTicketA("sqlsrv",$request);

        if(!$get_data_ticket && $transition_target!="") 
        $get_data_ticket = $this->getTicketA($transition_target,$request);            
        
        if(!$get_data_ticket) 
        throw new \Exception("Data Ticket tidak terdaftar",1);

        if(\App\Models\MySql\TrxTrp::where("ticket_a_id",$get_data_ticket->TicketID)
        ->where("ticket_a_no",$get_data_ticket->TicketNo)
        ->where("id","!=",$model_query->id)->first())
        throw new \Exception("Data Ticket telah digunakan",1);

        $model_query->ticket_a_id         = $request->ticket_a_id;
        $model_query->ticket_a_no         = $get_data_ticket->TicketNo;
        $model_query->ticket_a_bruto      = $get_data_ticket->Bruto;
        $model_query->ticket_a_tara       = $get_data_ticket->Tara;
        $model_query->ticket_a_netto      = $get_data_ticket->Bruto - $get_data_ticket->Tara;
        $model_query->ticket_a_ori_bruto  = $get_data_ticket->OriginalBruto;
        $model_query->ticket_a_ori_tara   = $get_data_ticket->OriginalTara;
        $model_query->ticket_a_ori_netto  = $get_data_ticket->OriginalBruto - $get_data_ticket->OriginalTara;
        $model_query->ticket_a_supir      = $get_data_ticket->NamaSupir;
        $model_query->ticket_a_no_pol     = $get_data_ticket->VehicleNo;
        $model_query->ticket_a_in_at      = $get_data_ticket->DateTimeIn;
        $model_query->ticket_a_out_at     = $get_data_ticket->DateTimeOut;       
      }else{
        $model_query->ticket_a_id         = null;
        $model_query->ticket_a_no         = null;
        $model_query->ticket_a_bruto      = null;
        $model_query->ticket_a_tara       = null;
        $model_query->ticket_a_netto      = null;
        $model_query->ticket_a_ori_bruto  = null;
        $model_query->ticket_a_ori_tara   = null;
        $model_query->ticket_a_ori_netto  = null;
        $model_query->ticket_a_supir      = null;
        $model_query->ticket_a_no_pol     = null;
        $model_query->ticket_a_in_at      = null;
        $model_query->ticket_a_out_at     = null;
      }

      if($request->ticket_b_id){

        $get_data_ticket = $this->getTicketB('sqlsrv',$request);

        if(!$get_data_ticket && $transition_target!="")
        $get_data_ticket = $this->getTicketB($transition_target,$request);

        if(!$get_data_ticket) 
        throw new \Exception("Data Ticket tidak terdaftar",1);

        if(\App\Models\MySql\TrxTrp::where("ticket_b_id",$get_data_ticket->TicketID)
        ->where("ticket_b_no",$get_data_ticket->TicketNo)
        ->where("id","!=",$model_query->id)->first())
        throw new \Exception("Data Ticket telah digunakan",1);

        $model_query->ticket_b_id         = $request->ticket_b_id;
        $model_query->ticket_b_no         = $get_data_ticket->TicketNo;
        $model_query->ticket_b_bruto      = $get_data_ticket->Bruto;
        $model_query->ticket_b_tara       = $get_data_ticket->Tara;
        $model_query->ticket_b_netto      = $get_data_ticket->Bruto - $get_data_ticket->Tara;
        $model_query->ticket_b_ori_bruto  = $get_data_ticket->OriginalBruto;
        $model_query->ticket_b_ori_tara   = $get_data_ticket->OriginalTara;
        $model_query->ticket_b_ori_netto  = $get_data_ticket->OriginalBruto - $get_data_ticket->OriginalTara;
        $model_query->ticket_b_supir      = $get_data_ticket->NamaSupir;
        $model_query->ticket_b_no_pol     = $get_data_ticket->VehicleNo;
        $model_query->ticket_b_in_at      = $get_data_ticket->DateTimeIn;
        $model_query->ticket_b_out_at     = $get_data_ticket->DateTimeOut;
      }else{
        $model_query->ticket_b_id         = null;
        $model_query->ticket_b_no         = null;
        $model_query->ticket_b_bruto      = MyLib::emptyStrToNull($request->ticket_b_bruto);
        $model_query->ticket_b_tara       = MyLib::emptyStrToNull($request->ticket_b_tara);
        $model_query->ticket_b_netto      = MyLib::emptyStrToNull($request->ticket_b_bruto - $request->ticket_b_tara);
        $model_query->ticket_b_ori_bruto  = null;
        $model_query->ticket_b_ori_tara   = null;
        $model_query->ticket_b_ori_netto  = null;
        $model_query->ticket_b_supir      = null;
        $model_query->ticket_b_no_pol     = null;
        $model_query->ticket_b_in_at      = MyLib::emptyStrToNull($request->ticket_b_in_at);
        $model_query->ticket_b_out_at     = MyLib::emptyStrToNull($request->ticket_b_out_at);
      }

      if($request->transition_target){
        if($model_query->transition_type=="From" && $model_query->transition_target !== $request->transition_target){
          throw new \Exception("Peralihan Sudah Terkunci",1);
        }else if($model_query->transition_type=="From" && $model_query->transition_target == $request->transition_target){

        }else{
          $model_query->transition_target = $request->transition_target;
          $model_query->transition_type   = "To";
        }
      }else{
        $model_query->transition_target   = null;
        $model_query->transition_type     = null;
      }

      $model_query->ticket_note     = $request->ticket_note;
      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"update",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at"=>$t_stamp
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
      
      if(in_array(1,[$model_query->val2,$model_query->val3,$model_query->val4,$model_query->val5,$model_query->val6,$model_query->val_ticket]))
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      if($model_query->deleted==1 )
      throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

      if($model_query->pvr_id=="" || $model_query->pvr_id==null)
      throw new \Exception("Harap Lakukan Penghapusan",1);

      $deleted_reason = $model_query->req_deleted_reason;
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


  public function getTicketA($connection_name,$request){
    $get_data_ticket = DB::connection($connection_name)->table('palm_tickets')
    ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo','DateTimeIn','DateTimeOut','OriginalBruto','OriginalTara')
    ->where("TicketID",$request->ticket_a_id)
    ->where("TicketNo",$request->ticket_a_no);
    if($request->jenis=="CPO"){
      $get_data_ticket =$get_data_ticket->where('ProductName',"CPO");
    }else if($request->jenis=="PK"){
      $get_data_ticket =$get_data_ticket->where('ProductName',"KERNEL");
    }else{ 
      $get_data_ticket =$get_data_ticket->where('ProductName',"MTBS");
    }
    $get_data_ticket =$get_data_ticket->first();
    
    return $get_data_ticket;
  }

  public function getTicketB($connection_name,$request){
    $get_data_ticket = DB::connection($connection_name)->table('palm_tickets')
    ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo','DateTimeIn','DateTimeOut','OriginalBruto','OriginalTara')
    ->where("TicketID",$request->ticket_b_id)
    ->where("TicketNo",$request->ticket_b_no);

    // if($request->jenis!=="TBS" || $connection_name!=='sqlsrv'){
    //   $get_data_ticket=$get_data_ticket->whereIn('ProductName',["RTBS","TBS"]);
    // }else {
    //   $get_data_ticket=$get_data_ticket->whereIn('ProductName',["RTBS","TBS"]);
    // }

    $get_data_ticket=$get_data_ticket->whereIn('ProductName',["RTBS","TBS"]);

    $get_data_ticket=$get_data_ticket->first();
    
    return $get_data_ticket;
  }

  public function valTicket(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.val_ticket']);

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
    $tanggal = date("Y-m-d");

    DB::beginTransaction();
    try {
      $model_query = TrxTrp::lockForUpdate()->find($request->id);
      if($model_query->val_ticket){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }
      $SYSOLD                     = clone($model_query);

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.val_ticket',true) && !$model_query->val_ticket){
        $model_query->val_ticket = 1;
        $model_query->val_ticket_user = $this->admin_id;
        $model_query->val_ticket_at = $t_stamp;
        $model_query->updated_user = $this->admin_id;
        $model_query->updated_at = $t_stamp;

        $reason_cut = "";
        $gen_salary_bonus = false;

        if(in_array($model_query->jenis,['CPO','PK']) && $model_query->tanggal >= '2025-08-01'){
          
          if(!$model_query->ritase_leave_at || !$model_query->ritase_till_at){
            throw new \Exception("#".$model_query->id." Gambar Belum Lengkap",1);
          }

          if(!$model_query->ritase_val || !$model_query->ritase_val1 || !$model_query->ritase_val2){
            throw new \Exception("#".$model_query->id." Absensi Harus Tervalidasi Sepenuhnya untuk melakukan validasi tiket",1);
          }

          $nonorinet = $model_query->ticket_b_netto - $model_query->ticket_a_netto;
          // if($nonorinet<0) $nonorinet*=-1;
          $pembanding = $model_query->ticket_a_netto;
          
          $model_query->batas_persen_susut = -0.3;
          if(round(($nonorinet/$pembanding)*100,2) < $model_query->batas_persen_susut ){
            $gen_salary_bonus = true;
            $reason_cut .= "Susut.";
          }

          // $totalHours = MyLib::hoursDiff($model_query->ritase_leave_at,$model_query->ritase_till_at);
          // if($totalHours>72){
          //   $gen_salary_bonus = true;
          //   $reason_cut .= "Melewati waktu maksimal 72 Jam (".$totalHours.").";
          // }
          
        }
        // else if(in_array($model_query->jenis,['TBS']) && $model_query->tanggal >= '2025-08-01'){
        //   $orinet = $model_query->ticket_b_ori_netto - $model_query->ticket_a_ori_netto;
        //   // if($orinet<0) $orinet*=-1;
        //   $pembanding = $model_query->ticket_a_ori_netto;

        //   if($model_query->uj->batas_persen_susut == null) 
        //   throw new \Exception("Batas Persen Susut Belum Di Tentukan",1);

        //   $model_query->batas_persen_susut = $model_query->uj->batas_persen_susut;
        //   if(round(($orinet/$pembanding)*100,2) < $model_query->batas_persen_susut){
        //     $gen_salary_bonus = true;
        //   }
        // }

        if($gen_salary_bonus){
          $salary_bonuses = SalaryBonus::where("trx_trp_id",$model_query->id)->get();
          if(count($salary_bonuses)>0){
            foreach ($salary_bonuses as $key => $sb) {
              if($sb->deleted){
                $sb->deleted      = 0;
                $sb->deleted_user = null;
                $sb->note         = "Sumber Potongan Dari Validasi Tiket [".$model_query->id."] : ".$reason_cut;
                $sb->save();
                MyLog::sys("salary_bonus",$sb->id,"update","Undelete from ticket[".$model_query->id."]");
              }
            }
          }else{              
            if($model_query->supir_id){
              $model_query2                  = new SalaryBonus();
              $model_query2->tanggal         = $model_query->tanggal;
              $model_query2->type            = 'BonusTrip';
              $model_query2->employee_id     = $model_query->supir_id;
              $model_query2->nominal         = $model_query->uj->bonus_trip_supir*-1;
              $model_query2->note            = "Sumber Potongan Dari Validasi Tiket [".$model_query->id."] : ".$reason_cut;
              $model_query2->trx_trp_id      = $model_query->id;
  
              $model_query2->created_at      = $t_stamp;
              $model_query2->created_user    = 1;
  
              $model_query2->updated_at      = $t_stamp;
              $model_query2->updated_user    = 1;

              $model_query2->val1            = 1;
              $model_query2->val1_user       = 1;
              $model_query2->val1_at         = $t_stamp;
              $model_query2->val2            = 1;
              $model_query2->val2_user       = 1;
              $model_query2->val2_at         = $t_stamp;
              $model_query2->val3            = 1;
              $model_query2->val3_user       = 1;
              $model_query2->val3_at         = $t_stamp;

              $model_query2->save();

              MyLog::sys("salary_bonus",$model_query2->id,"insert","Source ticket[".$model_query->id."]->valtickets");
            }

            if($model_query->kernet_id){
              $model_query2                  = new SalaryBonus();
              $model_query2->tanggal         = $model_query->tanggal;
              $model_query2->type            = 'BonusTrip';
              $model_query2->employee_id     = $model_query->kernet_id;
              $model_query2->nominal         = $model_query->uj->bonus_trip_kernet*-1;
              $model_query2->note            = "Sumber Potongan Dari Validasi Tiket [".$model_query->id."] : ".$reason_cut;
              $model_query2->trx_trp_id      = $model_query->id;
  
              $model_query2->created_at      = $t_stamp;
              $model_query2->created_user    = 1;
  
              $model_query2->updated_at      = $t_stamp;
              $model_query2->updated_user    = 1;

              $model_query2->val1            = 1;
              $model_query2->val1_user       = 1;
              $model_query2->val1_at         = $t_stamp;
              $model_query2->val2            = 1;
              $model_query2->val2_user       = 1;
              $model_query2->val2_at         = $t_stamp;
              $model_query2->val3            = 1;
              $model_query2->val3_user       = 1;
              $model_query2->val3_at         = $t_stamp;
              $model_query2->save();

              MyLog::sys("salary_bonus",$model_query2->id,"insert","Source ticket[".$model_query->id."]->valtickets");
            }
          }
        }
      }
      $model_query->save();
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"approve ticket",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses validasi data berhasil",
        "val_ticket"=>$model_query->val_ticket,
        "val_ticket_user"=>$model_query->val_ticket_user,
        "val_ticket_at"=>$model_query->val_ticket_at,
        "val_ticket_by"=>$model_query->val_ticket_user ? new IsUserResource(IsUser::find($model_query->val_ticket_user)) : null,
        "updated_at"=>$model_query->updated_at,
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

  public function unvalTicket(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.unval_ticket']);

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
      if($model_query->salary_paid_id != null){
        throw new \Exception("Data Tidak Bisa Di unvalidasi lagi karna sudah memiliki Salary Paid ID",1);
      }
      $SYSOLD                     = clone($model_query);

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.unval_ticket',true) && $model_query->val_ticket){
        $model_query->val_ticket = 0;
        // $model_query->val_ticket_user = $this->admin_id;
        // $model_query->val_ticket_at = $t_stamp;
        $model_query->updated_user = $this->admin_id;
        $model_query->updated_at = $t_stamp;

        $salary_bonuses = SalaryBonus::where("trx_trp_id",$model_query->id)->get();
        if(count($salary_bonuses)>0){
          foreach ($salary_bonuses as $key => $sb) {
            if($sb->deleted==0){
              $sb->deleted = 1;
              $sb->deleted_at = $t_stamp;
              $sb->deleted_user = 1;
              $sb->deleted_reason = "Unval Ticket";
              $sb->save();
              MyLog::sys("salary_bonus",$sb->id,"update","Delete from ticket[".$model_query->id."]");
            }
          }
        }

      }
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"unvalidasi ticket",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses validasi data berhasil",
        "val_ticket"=>$model_query->val_ticket,
        "val_ticket_user"=>$model_query->val_ticket_user,
        "val_ticket_at"=>$model_query->val_ticket_at,
        "val_ticket_by"=>$model_query->val_ticket_user ? new IsUserResource(IsUser::find($model_query->val_ticket_user)) : null,
        "updated_at"=>$model_query->updated_at,
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

  public function valTickets(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.val_ticket']);

    $ids = json_decode($request->ids, true);
    $t_stamp = date("Y-m-d H:i:s");
    $tanggal = date("Y-m-d");
    DB::beginTransaction();
    try {
      $model_querys = TrxTrp::lockForUpdate()->whereIn("id",$ids)->with('uj')->get();
      $valList = [];
      $SYSNOTES = [];

      foreach ($model_querys as $key => $v) {
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.val_ticket',true) && !$v->val_ticket){
          $SYSOLD                     = clone($v);
          $v->val_ticket = 1;
          $v->val_ticket_user = $this->admin_id;
          $v->val_ticket_at = $t_stamp;
          $v->updated_user = $this->admin_id;
          $v->updated_at = $t_stamp;

          $reason_cut = "";
          $gen_salary_bonus = false;

          if(in_array($v->jenis,['CPO','PK']) && $v->tanggal >= '2025-08-01'){

            if(!$v->ritase_leave_at || !$v->ritase_till_at){
              throw new \Exception("#".$v->id." Gambar Belum Lengkap",1);
            }

            if(!$v->ritase_val || !$v->ritase_val1 || !$v->ritase_val2){
              throw new \Exception("#".$v->id." Absensi Harus Tervalidasi Sepenuhnya untuk melakukan validasi tiket",1);
            }
            
            $nonorinet = $v->ticket_b_netto - $v->ticket_a_netto ;
            // if($nonorinet<0) $nonorinet*=-1;
            $pembanding = $v->ticket_a_netto;
            
            $v->batas_persen_susut = -0.3;
            if(round(($nonorinet/$pembanding)*100,2) < $v->batas_persen_susut ){
              $gen_salary_bonus = true;
              $reason_cut .= "Susut.";
            }

            // $totalHours = MyLib::hoursDiff($v->ritase_leave_at,$v->ritase_till_at);
            // if($totalHours>72){
            //   $gen_salary_bonus = true;
            //   $reason_cut .= "Melewati waktu maksimal 72 Jam (".$totalHours.").";
            // }
          }
          // else if(in_array($v->jenis,['TBS']) && $v->tanggal >= '2025-08-01'){
          //   $orinet = $v->ticket_b_ori_netto - $v->ticket_a_ori_netto;
          //   // if($orinet<0) $orinet*=-1;
          //   $pembanding = $v->ticket_a_ori_netto;

          //   if($v->uj->batas_persen_susut == null) 
          //   throw new \Exception("Batas Persen Susut Belum Di Tentukan",1);

          //   $v->batas_persen_susut = $v->uj->batas_persen_susut;
          //   if(round(($orinet/$pembanding)*100,2) < $v->batas_persen_susut){
          //     $gen_salary_bonus = true;
          //   }
          // }

          if($gen_salary_bonus){
            $salary_bonuses = SalaryBonus::where("trx_trp_id",$v->id)->get();
            if(count($salary_bonuses)>0){
              foreach ($salary_bonuses as $key => $sb) {
                if($sb->deleted){
                  $sb->deleted      = 0;
                  $sb->deleted_user = null;
                  $sb->note         = "Sumber Potongan Dari Validasi Tiket [".$v->id."] : ".$reason_cut;
                  $sb->save();
                  MyLog::sys("salary_bonus",$sb->id,"update","Undelete from ticket[".$v->id."]");
                }
              }
            }else{              
              if($v->supir_id){
                $model_query                  = new SalaryBonus();
                $model_query->tanggal         = $v->tanggal;
                $model_query->type            = 'BonusTrip';
                $model_query->employee_id     = $v->supir_id;
                $model_query->nominal         = $v->uj->bonus_trip_supir*-1;
                $model_query->note            = "Sumber Potongan Dari Validasi Tiket [".$v->id."] : ".$reason_cut;
                $model_query->trx_trp_id      = $v->id;
    
                $model_query->created_at      = $t_stamp;
                $model_query->created_user    = 1;
    
                $model_query->updated_at      = $t_stamp;
                $model_query->updated_user    = 1;

                $model_query->val1            = 1;
                $model_query->val1_user       = 1;
                $model_query->val1_at         = $t_stamp;
                $model_query->val2            = 1;
                $model_query->val2_user       = 1;
                $model_query->val2_at         = $t_stamp;
                $model_query->val3            = 1;
                $model_query->val3_user       = 1;
                $model_query->val3_at         = $t_stamp;


                $model_query->save();

                MyLog::sys("salary_bonus",$model_query->id,"insert","Source ticket[".$v->id."]>valtickets");
              }
  
              if($v->kernet_id){
                $model_query                  = new SalaryBonus();
                $model_query->tanggal         = $v->tanggal;
                $model_query->type            = 'BonusTrip';
                $model_query->employee_id     = $v->kernet_id;
                $model_query->nominal         = $v->uj->bonus_trip_kernet*-1;
                $model_query->note            = "Sumber Potongan Dari Validasi Tiket [".$v->id."] : ".$reason_cut;
                $model_query->trx_trp_id      = $v->id;
    
                $model_query->created_at      = $t_stamp;
                $model_query->created_user    = 1;
    
                $model_query->updated_at      = $t_stamp;
                $model_query->updated_user    = 1;

                $model_query->val1            = 1;
                $model_query->val1_user       = 1;
                $model_query->val1_at         = $t_stamp;
                $model_query->val2            = 1;
                $model_query->val2_user       = 1;
                $model_query->val2_at         = $t_stamp;
                $model_query->val3            = 1;
                $model_query->val3_user       = 1;
                $model_query->val3_at         = $t_stamp;

                $model_query->save();

                MyLog::sys("salary_bonus",$model_query->id,"insert","Source ticket[".$v->id."]->valtickets");
              }
            }
          }

          $v->save();
          array_push($valList,[
            "id"=>$v->id,
            "val_ticket"=>$v->val_ticket,
            "val_ticket_user"=>$v->val_ticket_user,
            "val_ticket_at"=>$v->val_ticket_at,
            "val_ticket_by"=>$v->val_ticket_user ? new IsUserResource(IsUser::find($v->val_ticket_user)) : null,
          ]);
          $SYSNOTE = MyLib::compareChange($SYSOLD,$v); 
          array_push($SYSNOTES,$SYSNOTE);
        }
      }

      MyLog::sys($this->syslog_db,null,"val_tickets",implode(",",$SYSNOTES));

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
        "message" => "Proses ubah data gagal",
      ], 400);
    }

  }

  public function unvalTickets(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.unval_ticket']);

    $ids = json_decode($request->ids, true);
    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_querys = TrxTrp::lockForUpdate()->whereIn("id",$ids)->get();
      $valList = [];
      $SYSNOTES =[];
      foreach ($model_querys as $key => $v) {
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.unval_ticket',true) && $v->val_ticket){
          if($v->salary_paid_id != null){
            throw new \Exception("Data #".$v->id." Tidak Bisa Di unvalidasi lagi karna sudah memiliki Salary Paid ID",1);
          }
          $SYSOLD                     = clone($v);
          $v->val_ticket = 0;
          // $v->val_ticket_user = $this->admin_id;
          // $v->val_ticket_at = $t_stamp;
          $v->updated_user = $this->admin_id;
          $v->updated_at = $t_stamp;

          $salary_bonuses = SalaryBonus::where("trx_trp_id",$v->id)->get();
          if(count($salary_bonuses)>0){
            foreach ($salary_bonuses as $key => $sb) {
              if($sb->deleted==0){
                $sb->deleted = 1;
                $sb->deleted_at = $t_stamp;
                $sb->deleted_user = 1;
                $sb->deleted_reason = "Unval Tickets";
                $sb->save();
                MyLog::sys("salary_bonus",$sb->id,"update","Multi Delete from ticket[".$v->id."]");
              }
            }
          }

          $v->save();
          array_push($valList,[
            "id"=>$v->id,
            "val_ticket"=>$v->val_ticket,
            "val_ticket_user"=>$v->val_ticket_user,
            "val_ticket_at"=>$v->val_ticket_at,
            "val_ticket_by"=>$v->val_ticket_user ? new IsUserResource(IsUser::find($v->val_ticket_user)) : null,
            "updated_at"=>$v->updated_at,
          ]);
          $SYSNOTE = MyLib::compareChange($SYSOLD,$v); 
          array_push($SYSNOTES,$SYSNOTE);
        }
      }

      MyLog::sys($this->syslog_db,null,"unval_tickets",implode(",",$SYSNOTES));

      $nids = array_map(function($x) {
        return $x['id'];        
      },$valList);

      // MyLog::sys("trx_trp",null,"unval_tickets",implode(",",$nids));

      DB::commit();
      return response()->json([
        "message" => "Proses unvalidasi data berhasil",
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
        "message" => "Proses unvalidasi data gagal",
      ], 400);
    }

  }

  public function clearTickets(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.val_ticket']);

    $ids = json_decode($request->ids, true);
    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_querys = TrxTrp::lockForUpdate()->whereIn("id",$ids)->where('val_ticket',0)->get();
      $clearList = [];
      $SYSNOTES =[];

      foreach ($model_querys as $key => $v) {
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.val_ticket',true) && !$v->val_ticket){
          $SYSOLD                     = clone($v);
          $v->ticket_a_id         = null;
          $v->ticket_a_no         = null;
          $v->ticket_a_bruto      = null;
          $v->ticket_a_tara       = null;
          $v->ticket_a_netto      = null;
          $v->ticket_a_ori_bruto  = null;
          $v->ticket_a_ori_tara   = null;
          $v->ticket_a_ori_netto  = null;
          $v->ticket_a_supir      = null;
          $v->ticket_a_no_pol     = null;
          $v->ticket_a_in_at      = null;
          $v->ticket_a_out_at     = null;
          $v->ticket_b_id         = null;
          $v->ticket_b_no         = null;
          $v->ticket_b_bruto      = null;
          $v->ticket_b_tara       = null;
          $v->ticket_b_netto      = null;
          $v->ticket_b_ori_bruto  = null;
          $v->ticket_b_ori_tara   = null;
          $v->ticket_b_ori_netto  = null;
          $v->ticket_b_supir      = null;
          $v->ticket_b_no_pol     = null;
          $v->ticket_b_in_at      = null;
          $v->ticket_b_out_at     = null;
          $v->updated_user        = $this->admin_id;
          $v->updated_at          = $t_stamp;

          $v->save();
          array_push($clearList,[
            "id"=>$v->id,
            "ticket_a_id"     =>$v->ticket_a_id,
            "ticket_a_no"     =>$v->ticket_a_no,
            "ticket_a_bruto"  =>$v->ticket_a_bruto,
            "ticket_a_tara"   =>$v->ticket_a_tara,
            "ticket_a_netto"  =>$v->ticket_a_netto,
            "ticket_a_supir"  =>$v->ticket_a_supir,
            "ticket_a_no_pol" =>$v->ticket_a_no_pol,
            "ticket_a_in_at"  =>$v->ticket_a_in_at,
            "ticket_a_out_at" =>$v->ticket_a_out_at,
            "ticket_b_id"     =>$v->ticket_b_id,
            "ticket_b_no"     =>$v->ticket_b_no,
            "ticket_b_bruto"  =>$v->ticket_b_bruto,
            "ticket_b_tara"   =>$v->ticket_b_tara,
            "ticket_b_netto"  =>$v->ticket_b_netto,
            "ticket_b_supir"  =>$v->ticket_b_supir,
            "ticket_b_no_pol" =>$v->ticket_b_no_pol,
            "ticket_b_in_at"  =>$v->ticket_b_in_at,
            "ticket_b_out_at" =>$v->ticket_b_out_at,
            "updated_at"      =>$v->updated_at,
          ]);
          $SYSNOTE = MyLib::compareChange($SYSOLD,$v); 
          array_push($SYSNOTES,$SYSNOTE);
        }
      }

      MyLog::sys($this->syslog_db,null,"clear_tickets",implode(",",$SYSNOTES));

      $nids = array_map(function($x) {
        return $x['id'];        
      },$clearList);

      // MyLog::sys("trx_trp",null,"clear_tickets",implode(",",$nids));

      DB::commit();
      return response()->json([
        "message" => "Proses clear tiket berhasil",
        "clear_lists"=>$clearList
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
        "message" => "Proses clear tiket gagal",
      ], 400);
    }

  }

  public function genPersen($a,$b){
    $a = (float)$a;
    $b = (float)$b;
    
    $diff=(float)($b-$a);
    $bigger = $diff > 0 ? $b  : $a;

    if($bigger==0) return [$diff,0];

    // return [$diff , $diff / $bigger * 100];
    if($a!=0)
    return [$diff , $diff / $a * 100];
    else
    return [$diff , $diff];
  }
  
  public function doUpdateTicket(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.get_ticket');
    $t_stamp = date("Y-m-d H:i:s");
    
    $miniError="";
    $SYSNOTES=[];
    $changes=[];

    $dkey = "vehiclesAllowedUpdateTicket";
    $lists = json_decode($request->vehicles, true);
    $pabriks = json_decode($request->pabriks, true);
    
    $vehicles = Vehicle::whereIn("id",$lists)->where("deleted",0)->get();

    if(count($vehicles) != count($lists))
    throw new MyException(["message" => "No Pol Tidak Valid"], 400);

    $dval = MyLib::emptyStrToNull(json_encode($lists));
    $model_query = TempData::where('dkey',$dkey)->first();
    if(!$model_query){
      $model_query                  = new TempData();
      $model_query->dkey            = $dkey;
      $model_query->dval            = $dval;
      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
    }else{
      $SYSOLD                       = clone($model_query);
      if($model_query->dval         != $dval){
        $model_query->dval          = $dval;
        $model_query->updated_at    = $t_stamp;
        $model_query->updated_user  = $this->admin_id;
      }
    }
    $model_query->save();

    if(isset($SYSOLD)){
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      if($SYSNOTE)
        MyLog::sys("temp_data_mst",$model_query->id,"update",$SYSNOTE);
    }else{
      MyLog::sys("temp_data_mst",$model_query->id,"insert");
    }

    try {
      $vehicles = Vehicle::whereIn("id",$lists)->where('deleted',0)->get();
      foreach ($vehicles as $k => $vehicle) {

        $empty_one = TrxTrp::where("no_pol",$vehicle->no_pol)->where("deleted",0)->where("req_deleted",0)
        ->where(function ($q){
          $q->orWhere(function ($q1){
            $q1->where("jenis","TBS")->where(function($q2){
              $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_no");
            });
          });
          $q->orWhere(function ($q1){
            $q1->where("jenis","TBSK")->whereNull("ticket_b_no");
          });
          $q->orWhere(function ($q1){
            $q1->whereIn("jenis",["CPO","PK"])->where(function($q2){
              $q2->whereNull("ticket_a_no");
            });
          });
        })->where('val_ticket',0)->orderBy('tanggal','asc')->orderBy("created_at","asc")->first();
  
        if($empty_one){
          $empty_one = $empty_one->toArray();

          // $created_at = $empty_one['created_at'];
          // $created_at = date("Y-m-d H:i:s", strtotime($created_at));
          // $all_afters = TrxTrp::where("no_pol",$vehicle->no_pol)->where("created_at",">=",$created_at)->where('deleted',0)->where('req_deleted',0)->get();

          // if(in_array($empty_one['jenis'],['CPO','PK'])){
          //   $created_at = $empty_one['tanggal'];
          // }


          $created_at = $empty_one['tanggal'];
          $all_afters = TrxTrp::where("no_pol",$vehicle->no_pol)->where("tanggal",">=",$created_at)->where('deleted',0)->where('req_deleted',0)->orderBy('tanggal','asc')->orderBy('created_at','asc')->get();

          $no_pol = preg_replace('/\s+/', '', $vehicle->no_pol);
          $get_data_tickets = DB::connection('sqlsrv')->table('palm_tickets')
          ->select('VehicleNo','DateTimeIn','TicketNo','Void',"TicketID","Bruto","Tara","OriginalBruto","OriginalTara","NamaSupir","DateTimeOut")
          ->whereRaw("REPLACE(VehicleNo, ' ', '')='".$no_pol."'")->where('DateTimeIn',">",$created_at);

          if(in_array($empty_one['jenis'],['PK','CPO'])){
            $get_data_tickets = $get_data_tickets->where('TicketNo','not like','%-%');
          }else{
            $get_data_tickets = $get_data_tickets->where('Void',0);
          }
          $get_data_tickets = $get_data_tickets->orderBy("DateTimeIn","asc")->get();

          $get_data_tickets = MyLib::objsToArray($get_data_tickets);

          foreach ($pabriks as $kp => $vp) {
            if(array_search($vp,MyLib::$list_pabrik)!==false){
              $get_data_ticketsp = DB::connection($vp)->table('palm_tickets')
              ->select('VehicleNo','DateTimeIn','TicketNo','Void',"TicketID","Bruto","Tara","OriginalBruto","OriginalTara","NamaSupir","DateTimeOut")
              ->whereRaw("REPLACE(VehicleNo, ' ', '')='".$no_pol."'")->where('DateTimeIn',">",$created_at);
    
              if(in_array($empty_one['jenis'],['PK','CPO'])){
                $get_data_ticketsp = $get_data_ticketsp->where('TicketNo','not like','%-%');
              }else{
                $get_data_ticketsp = $get_data_ticketsp->where('Void',0);
              }
              $get_data_ticketsp = $get_data_ticketsp->orderBy("DateTimeIn","asc")->get();

              $get_data_ticketsp = MyLib::objsToArray($get_data_ticketsp);

              foreach ($get_data_ticketsp as $kgp => $vgp) {
                $insert=0;
                foreach ($get_data_tickets as $kg => $vg) {

                  if($vgp["DateTimeIn"] < $vg["DateTimeIn"]){
                    $insert=1;
                    array_splice($get_data_tickets,$kg,0,[$vgp]);
                    break;                    
                  }
                }
                
                if($insert==0){
                  array_push($get_data_tickets,$vgp);
                }
              }
            }
          }

          foreach ($all_afters as $key => $af) {
            if(!$af->ticket_a_no && !$af->ticket_b_no && $af->ticket_note && $af->val_ticket == 1)
            continue;
            // MyLog::logging($get_data_tickets[0]);
            // MyLog::logging($af);
            if($key==0){
              if(count($get_data_tickets) == 0)
              break;

              $gdt = $get_data_tickets[0];
              if(TrxTrp::where('ticket_b_no',$gdt['TicketNo'])->orWhere('ticket_a_no',$gdt['TicketNo'])->first()){
                array_shift( $get_data_tickets );

                if(count($get_data_tickets) == 0)
                break;
  
                $gdt = $get_data_tickets[0];
                if(TrxTrp::where('ticket_b_no',$gdt['TicketNo'])->orWhere('ticket_a_no',$gdt['TicketNo'])->first()){
                  array_shift( $get_data_tickets );
                }
              }
            }

            if(count($get_data_tickets) == 0)
            break;

            if($af['jenis']=="TBS"){
              $gdt = $get_data_tickets[0];
              $tn = explode("/",$gdt['TicketNo']);
              if($tn[0]==env("app_name")){

                // if(!$af['ticket_a_no']){
                //   break;
                // }

                // if($af['transition_type']!='To'){
                  
                  if($af['ticket_b_no'] && $af['ticket_b_no'] !== $gdt['TicketNo']){
                    break;
                  }elseif($af['ticket_b_no'] && $af['ticket_b_no'] == $gdt['TicketNo']){
                    array_shift( $get_data_tickets );
                  }elseif (!$af['ticket_b_no']) {
  
                    $trx_trp = TrxTrp::where("id",$af->id)->first();
                    $SYSOLD  = clone($trx_trp);
                    try {
                      $trx_trp->ticket_b_id         = $gdt['TicketID'];
                      $trx_trp->ticket_b_no         = $gdt['TicketNo'];
                      $trx_trp->ticket_b_bruto      = (int)$gdt['Bruto'];
                      $trx_trp->ticket_b_tara       = (int)$gdt['Tara'];
                      $trx_trp->ticket_b_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
                      $trx_trp->ticket_b_ori_bruto  = (int)$gdt['OriginalBruto'];
                      $trx_trp->ticket_b_ori_tara   = (int)$gdt['OriginalTara'];
                      $trx_trp->ticket_b_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
                      $trx_trp->ticket_b_supir      = $gdt['NamaSupir'];
                      $trx_trp->ticket_b_no_pol     = $gdt['VehicleNo'];
                      $trx_trp->ticket_b_in_at      = $gdt['DateTimeIn'];
                      $trx_trp->ticket_b_out_at     = $gdt['DateTimeOut'];
                      $trx_trp->save();
                      array_shift( $get_data_tickets );
                      array_push( $SYSNOTES ,"Details:".$af->id );
                      array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));
  
                      array_push($changes,[
                        "id"                  => $trx_trp->id,
                        "ticket_a_id"         => $trx_trp->ticket_a_id,
                        "ticket_a_no"         => $trx_trp->ticket_a_no,
                        "ticket_a_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_bruto,
                        "ticket_a_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_tara,
                        "ticket_a_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_netto,
                        "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                        "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                        "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                        "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
                        "ticket_b_id"         => $trx_trp->ticket_b_id,
                        "ticket_b_no"         => $trx_trp->ticket_b_no,
                        "ticket_b_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_bruto,
                        "ticket_b_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_tara,
                        "ticket_b_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_netto,
                        "ticket_b_supir"      => $trx_trp->ticket_b_supir,
                        "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
                        "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
                        "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
                        "updated_at"          => $t_stamp
                      ]);
                    } catch (\Throwable $th) {
                      break;
                    }
  
                  }

                // }

                if(count($get_data_tickets) == 0)
                break;

                if($key==0){    
                  $gdt = $get_data_tickets[0];
                  if(TrxTrp::where('ticket_b_no',$gdt['TicketNo'])->orWhere('ticket_a_no',$gdt['TicketNo'])->first()){
                    array_shift( $get_data_tickets );
                  }
                }

                
                $gdt = $get_data_tickets[0];
                $tn = explode("/",$gdt['TicketNo']);

                if($tn[0]==env("app_name"))
                break;

                // if($af['transition_type']!='To'){
                  if($af['ticket_a_no'] && $af['ticket_a_no'] !== $gdt['TicketNo']){
                    continue;
                    // break;
                  }elseif($af['ticket_a_no'] && $af['ticket_a_no'] == $gdt['TicketNo']){
                    array_shift( $get_data_tickets );
                  }elseif (!$af['ticket_a_no']) {
                    $trx_trp = TrxTrp::where("id",$af->id)->first();
                    $SYSOLD  = clone($trx_trp);
                    try {
                      $trx_trp->ticket_a_id         = $gdt['TicketID'];
                      $trx_trp->ticket_a_no         = $gdt['TicketNo'];
                      $trx_trp->ticket_a_bruto      = (int)$gdt['Bruto'];
                      $trx_trp->ticket_a_tara       = (int)$gdt['Tara'];
                      $trx_trp->ticket_a_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
                      $trx_trp->ticket_a_ori_bruto  = (int)$gdt['OriginalBruto'];
                      $trx_trp->ticket_a_ori_tara   = (int)$gdt['OriginalTara'];
                      $trx_trp->ticket_a_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
                      $trx_trp->ticket_a_supir      = $gdt['NamaSupir'];
                      $trx_trp->ticket_a_no_pol     = $gdt['VehicleNo'];
                      $trx_trp->ticket_a_in_at      = $gdt['DateTimeIn'];
                      $trx_trp->ticket_a_out_at     = $gdt['DateTimeOut'];
                      $trx_trp->save();
                      array_shift( $get_data_tickets );
                      array_push( $SYSNOTES ,"Details:".$af->id );
                      array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));
  
                      array_push($changes,[
                        "id"                  => $trx_trp->id,
                        "ticket_a_id"         => $trx_trp->ticket_a_id,
                        "ticket_a_no"         => $trx_trp->ticket_a_no,
                        "ticket_a_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_bruto,
                        "ticket_a_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_tara,
                        "ticket_a_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_netto,
                        "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                        "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                        "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                        "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
                        "ticket_b_id"         => $trx_trp->ticket_b_id,
                        "ticket_b_no"         => $trx_trp->ticket_b_no,
                        "ticket_b_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_bruto,
                        "ticket_b_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_tara,
                        "ticket_b_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_netto,
                        "ticket_b_supir"      => $trx_trp->ticket_b_supir,
                        "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
                        "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
                        "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
                        "updated_at"          => $t_stamp
                      ]);
                    } catch (\Throwable $th) {
                      break;
                    }
                  }
                // }

              }else{

                // if($af['transition_type']!='From'){
                  if($af['ticket_a_no'] && $af['ticket_a_no'] !== $gdt['TicketNo']){
                    break;
                  }elseif($af['ticket_a_no'] && $af['ticket_a_no'] == $gdt['TicketNo']){
                    array_shift( $get_data_tickets );
                  }elseif (!$af['ticket_a_no']) {
                    $trx_trp = TrxTrp::where("id",$af->id)->first();
                    $SYSOLD  = clone($trx_trp);
                    try {
                      $trx_trp->ticket_a_id         = $gdt['TicketID'];
                      $trx_trp->ticket_a_no         = $gdt['TicketNo'];
                      $trx_trp->ticket_a_bruto      = (int)$gdt['Bruto'];
                      $trx_trp->ticket_a_tara       = (int)$gdt['Tara'];
                      $trx_trp->ticket_a_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
                      $trx_trp->ticket_a_ori_bruto  = (int)$gdt['OriginalBruto'];
                      $trx_trp->ticket_a_ori_tara   = (int)$gdt['OriginalTara'];
                      $trx_trp->ticket_a_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
                      $trx_trp->ticket_a_supir      = $gdt['NamaSupir'];
                      $trx_trp->ticket_a_no_pol     = $gdt['VehicleNo'];
                      $trx_trp->ticket_a_in_at      = $gdt['DateTimeIn'];
                      $trx_trp->ticket_a_out_at     = $gdt['DateTimeOut'];
                      $trx_trp->save();
                      array_shift( $get_data_tickets );
                      array_push( $SYSNOTES ,"Details:".$af->id );
                      array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));
  
                      array_push($changes,[
                        "id"                  => $trx_trp->id,
                        "ticket_a_id"         => $trx_trp->ticket_a_id,
                        "ticket_a_no"         => $trx_trp->ticket_a_no,
                        "ticket_a_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_bruto,
                        "ticket_a_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_tara,
                        "ticket_a_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_netto,
                        "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                        "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                        "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                        "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
                        "ticket_b_id"         => $trx_trp->ticket_b_id,
                        "ticket_b_no"         => $trx_trp->ticket_b_no,
                        "ticket_b_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_bruto,
                        "ticket_b_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_tara,
                        "ticket_b_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_netto,
                        "ticket_b_supir"      => $trx_trp->ticket_b_supir,
                        "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
                        "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
                        "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
                        "updated_at"          => $t_stamp
                      ]);
                    } catch (\Throwable $th) {
                      break;
                    }
                  }
                // }

                if(count($get_data_tickets) == 0)
                break;
              
                if($key==0){    
                  $gdt = $get_data_tickets[0];
                  if(TrxTrp::where('ticket_b_no',$gdt['TicketNo'])->orWhere('ticket_a_no',$gdt['TicketNo'])->first()){
                    array_shift( $get_data_tickets );
                  }
                }
                
                $gdt = $get_data_tickets[0];
                $tn = explode("/",$gdt['TicketNo']);

                if($tn[0]!=env("app_name"))
                break;

                // if($af['transition_type']!='To'){
                  if($af['ticket_b_no'] && $af['ticket_b_no'] !== $gdt['TicketNo']){
                    break;
                  }elseif($af['ticket_b_no'] && $af['ticket_b_no'] == $gdt['TicketNo']){
                    array_shift( $get_data_tickets );
                  }elseif (!$af['ticket_b_no']) {
                    $trx_trp = TrxTrp::where("id",$af->id)->first();
                    $SYSOLD  = clone($trx_trp);
                    try {
                      $trx_trp->ticket_b_id         = $gdt['TicketID'];
                      $trx_trp->ticket_b_no         = $gdt['TicketNo'];
                      $trx_trp->ticket_b_bruto      = (int)$gdt['Bruto'];
                      $trx_trp->ticket_b_tara       = (int)$gdt['Tara'];
                      $trx_trp->ticket_b_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
                      $trx_trp->ticket_b_ori_bruto  = (int)$gdt['OriginalBruto'];
                      $trx_trp->ticket_b_ori_tara   = (int)$gdt['OriginalTara'];
                      $trx_trp->ticket_b_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
                      $trx_trp->ticket_b_supir      = $gdt['NamaSupir'];
                      $trx_trp->ticket_b_no_pol     = $gdt['VehicleNo'];
                      $trx_trp->ticket_b_in_at      = $gdt['DateTimeIn'];
                      $trx_trp->ticket_b_out_at     = $gdt['DateTimeOut'];
                      $trx_trp->save();
                      array_shift( $get_data_tickets );
                      array_push( $SYSNOTES ,"Details:".$af->id );
                      array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));
  
                      array_push($changes,[
                        "id"                  => $trx_trp->id,
                        "ticket_a_id"         => $trx_trp->ticket_a_id,
                        "ticket_a_no"         => $trx_trp->ticket_a_no,
                        "ticket_a_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_bruto,
                        "ticket_a_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_tara,
                        "ticket_a_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_netto,
                        "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                        "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                        "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                        "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
                        "ticket_b_id"         => $trx_trp->ticket_b_id,
                        "ticket_b_no"         => $trx_trp->ticket_b_no,
                        "ticket_b_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_bruto,
                        "ticket_b_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_tara,
                        "ticket_b_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_netto,
                        "ticket_b_supir"      => $trx_trp->ticket_b_supir,
                        "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
                        "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
                        "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
                        "updated_at"          => $t_stamp
                      ]);
                    } catch (\Throwable $th) {
                      break;
                    }
                  }
                // }

              }

            }elseif($af['jenis']=="TBSK") {
              $gdt = $get_data_tickets[0];
              $tn = explode("/",$gdt['TicketNo']);
              if($tn[0]!==env("app_name")){
                break;
              }
              if($af['transition_type']!='To'){                
                if($af['ticket_b_no'] && $af['ticket_b_no'] !== $gdt['TicketNo']){
                  break;
                }elseif($af['ticket_b_no'] && $af['ticket_b_no'] == $gdt['TicketNo']){
                  array_shift( $get_data_tickets );
                }elseif (!$af['ticket_b_no']) {
                    $trx_trp = TrxTrp::where("id",$af->id)->first();
                    $SYSOLD  = clone($trx_trp);
                    try {
                      $trx_trp->ticket_b_id         = $gdt['TicketID'];
                      $trx_trp->ticket_b_no         = $gdt['TicketNo'];
                      $trx_trp->ticket_b_bruto      = (int)$gdt['Bruto'];
                      $trx_trp->ticket_b_tara       = (int)$gdt['Tara'];
                      $trx_trp->ticket_b_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
                      $trx_trp->ticket_b_ori_bruto  = (int)$gdt['OriginalBruto'];
                      $trx_trp->ticket_b_ori_tara   = (int)$gdt['OriginalTara'];
                      $trx_trp->ticket_b_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
                      $trx_trp->ticket_b_supir      = $gdt['NamaSupir'];
                      $trx_trp->ticket_b_no_pol     = $gdt['VehicleNo'];
                      $trx_trp->ticket_b_in_at      = $gdt['DateTimeIn'];
                      $trx_trp->ticket_b_out_at     = $gdt['DateTimeOut'];
                      $trx_trp->save();
                      array_shift( $get_data_tickets );
                      array_push( $SYSNOTES ,"Details:".$af->id );
                      array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));
  
                      array_push($changes,[
                        "id"                  => $trx_trp->id,
                        "ticket_a_id"         => $trx_trp->ticket_a_id,
                        "ticket_a_no"         => $trx_trp->ticket_a_no,
                        "ticket_a_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_bruto,
                        "ticket_a_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_tara,
                        "ticket_a_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_netto,
                        "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                        "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                        "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                        "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
                        "ticket_b_id"         => $trx_trp->ticket_b_id,
                        "ticket_b_no"         => $trx_trp->ticket_b_no,
                        "ticket_b_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_bruto,
                        "ticket_b_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_tara,
                        "ticket_b_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_netto,
                        "ticket_b_supir"      => $trx_trp->ticket_b_supir,
                        "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
                        "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
                        "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
                        "updated_at"          => $t_stamp
                      ]);
                    } catch (\Throwable $th) {
                      break;
                    }
                }
              }
            }elseif($af['jenis']=="CPO" || $af['jenis']=="PK") {
              $gdt = $get_data_tickets[0];
              $tn = explode("/",$gdt['TicketNo']);
              if($tn[0]!==env("app_name")){
                break;
              }
              if($af['ticket_a_no'] && $af['ticket_a_no'] !== $gdt['TicketNo']){
                break;
              }elseif($af['ticket_a_no'] && $af['ticket_a_no'] == $gdt['TicketNo']){
                array_shift( $get_data_tickets );
              }elseif (!$af['ticket_a_no']) {
                $trx_trp = TrxTrp::where("id",$af->id)->first();
                  $SYSOLD  = clone($trx_trp);
                  try {
                    $trx_trp->ticket_a_id         = $gdt['TicketID'];
                    $trx_trp->ticket_a_no         = $gdt['TicketNo'];
                    $trx_trp->ticket_a_bruto      = (int)$gdt['Bruto'];
                    $trx_trp->ticket_a_tara       = (int)$gdt['Tara'];
                    $trx_trp->ticket_a_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
                    $trx_trp->ticket_a_ori_bruto  = (int)$gdt['OriginalBruto'];
                    $trx_trp->ticket_a_ori_tara   = (int)$gdt['OriginalTara'];
                    $trx_trp->ticket_a_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
                    $trx_trp->ticket_a_supir      = $gdt['NamaSupir'];
                    $trx_trp->ticket_a_no_pol     = $gdt['VehicleNo'];
                    $trx_trp->ticket_a_in_at      = $gdt['DateTimeIn'];
                    $trx_trp->ticket_a_out_at     = $gdt['DateTimeOut'];
                    $trx_trp->save();
                    array_shift( $get_data_tickets );
                    array_push( $SYSNOTES ,"Details:".$af->id );
                    array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));

                    array_push($changes,[
                      "id"                  => $trx_trp->id,
                      "ticket_a_id"         => $trx_trp->ticket_a_id,
                      "ticket_a_no"         => $trx_trp->ticket_a_no,
                      "ticket_a_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_bruto,
                      "ticket_a_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_tara,
                      "ticket_a_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_a_netto,
                      "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                      "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                      "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                      "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,

                      "ticket_b_id"         => $trx_trp->ticket_b_id,
                      "ticket_b_no"         => $trx_trp->ticket_b_no,
                      "ticket_b_bruto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_bruto,
                      "ticket_b_tara"       => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_tara,
                      "ticket_b_netto"      => (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : $trx_trp->ticket_b_netto,
                      "ticket_b_supir"      => $trx_trp->ticket_b_supir,
                      "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
                      "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
                      "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
                      "updated_at"          => $t_stamp
                    ]);
                  } catch (\Throwable $th) {
                    break;
                  }
              }
            }        
          }
        }
      }


      MyLog::sys("trx_trp",null,"update ticket",implode("\n",$SYSNOTES));      

      if(count($changes)==0)
      throw new \Exception("Ticket Tidak ada yang di Update",1);

      $ids = array_map(function ($x) {
        return $x["id"];
      }, $changes);
      MyLog::sys("trx_trp",null,"do_update_ticket",implode(",",$ids));

      return response()->json([
        "message" => "Ticket Berhasil di Update",
        "data" => $changes,
      ], 200);
      
    } catch (\Exception $e) {
      if ($e->getCode() == 1) {
        $miniError="Ticket Batal Update: ".$e->getMessage();
      }else{
        $miniError="Ticket Batal Update. Akses Jaringan Gagal";
      }
      return response()->json([
        "message" => $miniError,
        "e"=>$e->getMessage(),
        "line" => $e->getLine(),
      ], 400);
    }
  }

  public function ticketOver(Request $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.views']);

    $pabrik = strtoupper($request->pabrik);
    if(array_search($pabrik,MyLib::$list_pabrik)===false){
      throw new MyException(["message" => "Nama Pabrik Tidak Terdaftar"]);
    }
    $pabrik = strtolower($pabrik);


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
    $table1 = DB::table('trx_trp')->selectRaw("concat('A') as jenis, ticket_a_no as ticket_no")->whereNotNull("ticket_a_no")->where("tanggal",">=","2025-10-01");

    $table2 = DB::table('trx_trp')->selectRaw("concat('B') as jenis, ticket_b_no as ticket_no")->whereNotNull("ticket_b_no")->where("tanggal",">=","2025-10-01");

    $final = $table1->unionAll($table2);

    $querySql = $final->toSql();
     
    $model_query = DB::table(DB::raw("($querySql) as a"))->mergeBindings($final);
     
    //Now you can do anything u like:
     
    // $model_query = $model_query->selectRaw("jenis, ticket_no,count(*) as lebih")->groupBy('ticket_no','jenis')->having('lebih',">",1)->offset($offset)->limit($limit)->get(); 
    // $model_query = $model_query->selectRaw("jenis, ticket_no,count(*) as lebih")->groupBy('ticket_no','jenis')->having('lebih',">",1)->get();

    
    if(strtolower(env("app_name"))==$pabrik){
        $model_query = $model_query->selectRaw("jenis, ticket_no,count(*) as lebih")->groupBy('ticket_no','jenis')->having('lebih',">",1)->get();
    }else{
        $model_query = $model_query->selectRaw("jenis, ticket_no,concat('1') as lebih")->groupBy('ticket_no','jenis')->get();
    }

    if(strtolower(env("app_name"))!=$pabrik){
      $pabrik = "ms_".$pabrik;

      $table3 = DB::connection($pabrik)->table('trx_trp')->selectRaw("concat('A') as jenis, ticket_a_no as ticket_no")->whereNotNull("ticket_a_no")->where("tanggal",">=","2025-10-01");
      $table4 = DB::connection($pabrik)->table('trx_trp')->selectRaw("concat('B') as jenis, ticket_b_no as ticket_no")->whereNotNull("ticket_b_no")->where("tanggal",">=","2025-10-01");
  
      $final = $table3->unionAll($table4);

      $querySql = $final->toSql();
      
      $model_query2 = DB::connection($pabrik)->table(DB::raw("($querySql) as a"))->mergeBindings($final);
      
      $model_query2 = $model_query2->selectRaw("ticket_no")->groupBy('ticket_no')->pluck("ticket_no")->toArray();

      $mq = $model_query->toArray();
      $model_query = [];
      foreach ($mq as $k => $v) {

          $lebih = $v->lebih;
          if(array_search( $v->ticket_no, $model_query2) === false){
          }else{
            $lebih+=1;
          }
          
          array_push($model_query,[
            "jenis"=>$v->jenis,
            "ticket_no"=>$v->ticket_no,
            "lebih"=>$lebih,
          ]);
      }
      
      // $final = $final->unionAll($table3)->unionAll($table4);
      $model_query = array_filter($model_query,function ($x){
          return $x['lebih'] >= 2;
      });
    }

    return response()->json([
      "data" => $model_query,
    ], 200);    
    // $model_query = new TrxTrp();
    
    // $model_query = $model_query->offset($offset)->limit($limit);
    // $model_query = $model_query->get();

    // return response()->json([
    //   "data" => TrxTrpResource::collection($model_query),
    // ], 200);
  }

  public function downloadExcel(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx_ticket.download_file');

    set_time_limit(0);
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    

    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {
      $ticket_a_bruto = (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : (float)$value["ticket_a_bruto"];
      $ticket_b_bruto = (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : (float)$value["ticket_b_bruto"];
      $ticket_a_tara = (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : (float)$value["ticket_a_tara"];
      $ticket_b_tara = (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : (float)$value["ticket_b_tara"];
      $ticket_a_netto = (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : (float)$value["ticket_a_netto"];
      $ticket_b_netto = (!MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)) ? 0 : (float)$value["ticket_b_netto"];
      list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($ticket_a_bruto,$ticket_b_bruto);
      list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($ticket_a_tara,$ticket_b_tara);
      list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($ticket_a_netto,$ticket_b_netto);

      $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
      $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
      $value['ticket_a_bruto']=$ticket_a_bruto;
      $value['ticket_b_bruto']=$ticket_b_bruto;
      $value['ticket_b_a_bruto']=$ticket_b_a_bruto;
      $value['ticket_b_a_bruto_persen']=$ticket_b_a_bruto_persen;
      $value['ticket_a_tara']=$ticket_a_tara;
      $value['ticket_b_tara']=$ticket_b_tara;
      $value['ticket_b_a_tara']=$ticket_b_a_tara;
      $value['ticket_b_a_tara_persen']=$ticket_b_a_tara_persen;
      $value['ticket_a_netto']=$ticket_a_netto;
      $value['ticket_b_netto']=$ticket_b_netto;
      $value['ticket_b_a_netto']=$ticket_b_a_netto;
      $value['ticket_b_a_netto_persen']=$ticket_b_a_netto_persen;
      $value['amount']=$value["amount"];
      // $value['pv_total']=$value["pv_total"];
      // $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
      array_push($newDetails,$value);
    }

    // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

    $filter_model = json_decode($request->filter_model,true);
    $tanggal = $filter_model['tanggal'];    


    $date_from=date("d-m-Y",strtotime($tanggal['value_1']));
    $date_to=date("d-m-Y",strtotime($tanggal['value_2']));

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-trx_trp_ticket'."[".$date_from."*".$date_to."]";

    $mime=MyLib::mime("xlsx");
    // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.trx_trp_ticket'), $mime["exportType"]));


    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
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
