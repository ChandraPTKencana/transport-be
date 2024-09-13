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
use App\Models\MySql\TempData;
use App\Models\MySql\Vehicle;
use App\PS\PSPotonganTrx;
use PHPUnit\Framework\Constraint\Count;

class TrxTrpTicketController extends Controller
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

    if($filter_status=="pv_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->whereNotNull("pv_no");
    }

    if($filter_status=="ticket_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where(function ($q){
          $q->orWhere(function ($q1){
            $q1->where("jenis","TBS")->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_no");
          });
          $q->orWhere(function ($q1){
            $q1->where("jenis","TBSK")->whereNotNull("ticket_b_no");
          });
          $q->orWhere(function ($q1){
            $q1->whereIn("jenis",["CPO","PK"])->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_in_at")->whereNotNull("ticket_b_out_at")->where("ticket_b_bruto",">",1)->where("ticket_b_tara",">",1)->where("ticket_b_netto",">",1);
          });
      })->where('val_ticket',1);
    }

    if($filter_status=="ticket_not_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where(function ($q){
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
              $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_in_at")->orWhereNull("ticket_b_out_at")->orWhereNull("ticket_b_bruto")->orWhereNull("ticket_b_tara")->orWhereNull("ticket_b_netto");
            });
          });
          $q->orWhere('val_ticket',0);
      });
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

    $model_query = $model_query->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','trx_absens'=>function($q) {
      $q->select('id','trx_trp_id','created_at','updated_at')->where("status","B");
    }])->get();

    return response()->json([
      "data" => TrxTrpResource::collection($model_query),
    ], 200);
  }

  public function show(TrxTrpRequest $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.view','trp_trx.ticket.view']);

    $model_query = TrxTrp::with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','trx_absens'=>function ($q){
      $q->where("status","B");
    },'potongan'])->find($request->id);
    return response()->json([
      "data" => new TrxTrpResource($model_query),
    ], 200);
  }

  public function mandorGetVerifyTrx(TrxTrpRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.ritase.views');

    $model_query = TrxTrp::where("deleted",0)->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val_ticket_by','deleted_by','req_deleted_by','trx_absens','uj_details'])->find($request->id);
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
      if($model_query->cost_center_code==""){
        throw new \Exception("Minta Kasir Untuk Memasukkan Cost Center Code Terlebih Dahulu",1);
      }

      if($model_query->val==0){
        throw new \Exception("Data Perlu Divalidasi oleh kasir terlebih dahulu",1);
      }

      if($model_query->val1){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }
  
      $model_query->val1 = 1;
      $model_query->val1_user = $this->admin_id;
      $model_query->val1_at = $t_stamp;

      $model_query->save();

      MyLog::sys("trx_trp",$model_query->id,"approve");

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

  // public function store(TrxTrpRequest $request)
  // {
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.create');

  //   $t_stamp = date("Y-m-d H:i:s");
  //   $online_status=$request->online_status;

  //   DB::beginTransaction();
  //   try {
  //     $supir_dt =\App\Models\MySql\Employee::where('id',$request->supir_id)->available()->verified()->first();
  //     if(!$supir_dt)
  //     throw new \Exception("Supir tidak terdaftar",1);

  //     if($request->kernet_id){
  //       $kernet_dt =\App\Models\MySql\Employee::where('id',$request->kernet_id)->available()->verified()->first();
  //       if(!$kernet_dt)
  //       throw new \Exception("Kernet tidak terdaftar",1);
  //     }

  //     if($request->supir_id == $request->kernet_id && $request->supir_id != 1)
  //     throw new \Exception("Supir Dan Kernet Tidak Boleh Orang Yang Sama",1);

  //     if($request->payment_method_id == 2){
  //       if(!$supir_dt->rek_no && $supir_dt->id != 1)
  //       throw new \Exception("Tidak ada no rekening supir",1);

  //       if(isset($kernet_dt) && !$kernet_dt->rek_no && $kernet_dt->id != 1)
  //       throw new \Exception("Tidak ada no rekening kernet",1);
  //     }

  //     // if(TrxTrp::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
  //     // throw new \Exception("List sudah terdaftar");

  //     $model_query                  = new TrxTrp();      
  //     $model_query->tanggal         = $request->tanggal;

  //     $rejenis = ($request->jenis=="TBSK" ? "TBS" : $request->jenis );
  //     $ujalan = \App\Models\MySql\Ujalan::where("id",$request->id_uj)
  //     ->where("jenis",$rejenis)
  //     ->where("deleted",0)
  //     ->lockForUpdate()
  //     ->first();

  //     if(!$ujalan) 
  //     throw new \Exception("Silahkan Isi Data Ujalan Dengan Benar",1);

  //     $model_query->id_uj               = $ujalan->id;
  //     $model_query->jenis               = $request->jenis;
  //     $model_query->xto                 = $ujalan->xto;
  //     $model_query->tipe                = $ujalan->tipe;
  //     $model_query->amount              = $ujalan->harga;

  //     if($ujalan->transition_from){
  //       $model_query->transition_target = $ujalan->transition_from;
  //       $model_query->transition_type   = "From";
  //     }
      
  //     if($online_status=="true"){
  //       if($request->cost_center_code){
  //         $list_cost_center = DB::connection('sqlsrv')->table("AC_CostCenterNames")
  //         ->select('CostCenter','Description')
  //         ->where('CostCenter',$request->cost_center_code)
  //         ->first();
  //         if(!$list_cost_center)
  //         throw new \Exception(json_encode(["cost_center_code"=>["Cost Center Code Tidak Ditemukan"]]), 422);
        
  //         $model_query->cost_center_code = $list_cost_center->CostCenter;
  //         $model_query->cost_center_desc = $list_cost_center->Description;
  //       }
  //     }

  //     $model_query->supir_id          = $supir_dt->id;
  //     $model_query->supir             = $supir_dt->name;
  //     $model_query->supir_rek_no      = $supir_dt->rek_no;
  //     $model_query->supir_rek_name    = $supir_dt->rek_name;

  //     if(isset($kernet_dt)){
  //       $model_query->kernet_id       = $kernet_dt->id;
  //       $model_query->kernet          = $kernet_dt->name;
  //       $model_query->kernet_rek_no   = $kernet_dt->rek_no;
  //       $model_query->kernet_rek_name = $kernet_dt->rek_name;  
  //     }

  //     $model_query->payment_method_id = $request->payment_method_id;
  //     $model_query->no_pol            = $request->no_pol;      
  //     $model_query->created_at        = $t_stamp;
  //     $model_query->created_user      = $this->admin_id;

  //     $model_query->updated_at        = $t_stamp;
  //     $model_query->updated_user      = $this->admin_id;

  //     $model_query->save();


  //     $ptg_trx_dt=[];
  //     if($supir_dt->id!=1 && $supir_dt->potongan){
  //       array_push($ptg_trx_dt,[
  //         "_source"     => "TRX_TRP",
  //         "employee_id" => $supir_dt->id,
  //         "user_id"     => $this->admin_id,
  //         "trx_trp_id"  => $model_query->id,
  //       ]);
  //     }

  //     if(isset($kernet_dt) && $kernet_dt->id!=1 && $kernet_dt->potongan){
  //       array_push($ptg_trx_dt,[
  //         "_source"     => "TRX_TRP",
  //         "employee_id" => $kernet_dt->id,
  //         "user_id"     => $this->admin_id,
  //         "trx_trp_id"  => $model_query->id,
  //       ]);
  //     }

  //     if(count($ptg_trx_dt) > 0)
  //     PSPotonganTrx::insertData($ptg_trx_dt);
    
  //     MyLog::sys("trx_trp",$model_query->id,"insert");

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses tambah data berhasil",
  //       "id"=>$model_query->id,
  //       "created_at" => $t_stamp,
  //       "updated_at" => $t_stamp,
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     // return response()->json([
  //     //   "message" => $e->getMessage(),
  //     //   "code" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     // ], 400);
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     if ($e->getCode() == 422) {
  //       return response()->json(json_decode($e->getMessage()), 422);
  //     }
  //     return response()->json([
  //       "message" => "Proses tambah data gagal",
  //     ], 400);
  //   }
  // }

  // public function update(TrxTrpRequest $request)
  // {
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.modify');
    
  //   $t_stamp = date("Y-m-d H:i:s");
  //   $online_status=$request->online_status;

  //   DB::beginTransaction();
  //   try {
  //     $supir_dt =\App\Models\MySql\Employee::where('id',$request->supir_id)->available()->verified()->first();
  //     if(!$supir_dt)
  //     throw new \Exception("Supir tidak terdaftar",1);

  //     if($request->kernet_id){
  //       $kernet_dt =\App\Models\MySql\Employee::where('id',$request->kernet_id)->available()->verified()->first();
  //       if(!$kernet_dt)
  //       throw new \Exception("Kernet tidak terdaftar",1);
  //     }

  //     if($request->supir_id == $request->kernet_id && $request->supir_id != 1)
  //     throw new \Exception("Supir Dan Kernet Tidak Boleh Orang Yang Sama",1);

  //     if($request->payment_method_id == 2){
  //       if(!$supir_dt->rek_no && $supir_dt->id != 1)
  //       throw new \Exception("Tidak ada no rekening supir",1);

  //       if(isset($kernet_dt) && !$kernet_dt->rek_no && $kernet_dt->id != 1)
  //       throw new \Exception("Tidak ada no rekening kernet",1);
  //     }

  //     $model_query             = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
  //     $SYSOLD      = clone($model_query);
  //     // if($model_query->val1==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
  //     // throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

  //     if($model_query->req_deleted==1 || $model_query->deleted==1) 
  //     throw new \Exception("Data Sudah Tidak Dapat Di Ubah",1);


  //     if($model_query->val==0){
        
  //       $model_query->tanggal         = $request->tanggal;
  
  //       $rejenis = ($request->jenis=="TBSK" ? "TBS" : $request->jenis );
  //       $ujalan = \App\Models\MySql\Ujalan::where("id",$request->id_uj)
  //       ->where("jenis",$rejenis)
  //       ->where("deleted",0)
  //       ->lockForUpdate()
  //       ->first();
  
  //       if(!$ujalan) 
  //       throw new \Exception("Silahkan Isi Data Ujalan Dengan Benar",1);
  
  //       if($ujalan->xto!=$request->xto)
  //       throw new \Exception("Silahkan Isi Tipe Ujalan Dengan Benar",1);
  
  //       $model_query->id_uj           = $ujalan->id;
  //       $model_query->jenis           = $request->jenis;
  //       $model_query->xto             = $ujalan->xto;
  //       $model_query->tipe            = $ujalan->tipe;
  //       $model_query->amount          = $ujalan->harga;

  //       if($ujalan->transition_from){
  //         $model_query->transition_target = $ujalan->transition_from;
  //         $model_query->transition_type   = "From";
  //       }else{
  //         $model_query->transition_target = null;
  //         $model_query->transition_type   = null;
  //       }
        
  //       $prev_supir_id = $model_query->supir_id;
  //       if($prev_supir_id != null && $prev_supir_id!=1 && $prev_supir_id != $supir_dt->id){
  //         throw new \Exception("Supir sudah tidak boleh di ubah",1);
  //       }

  //       $model_query->supir_id          = $supir_dt->id;
  //       $model_query->supir             = $supir_dt->name;
  //       $model_query->supir_rek_no      = $supir_dt->rek_no;
  //       $model_query->supir_rek_name    = $supir_dt->rek_name;
  

  //       $prev_kernet_id = $model_query->kernet_id;
  //       if(isset($kernet_dt)){
  //         if($prev_kernet_id != null && $prev_kernet_id!=1 && $prev_kernet_id != $kernet_dt->id){
  //           throw new \Exception("Kernet sudah tidak boleh di ubah",1);
  //         }
  //         $model_query->kernet_id       = $kernet_dt->id;
  //         $model_query->kernet          = $kernet_dt->name;
  //         $model_query->kernet_rek_no   = $kernet_dt->rek_no;
  //         $model_query->kernet_rek_name = $kernet_dt->rek_name;  
  //       }else{
  //         if($prev_kernet_id != null){
  //           throw new \Exception("Kernet sudah tidak boleh di kosong",1);
  //         }
  //       }
  //       $model_query->payment_method_id = $request->payment_method_id;
  //       $model_query->no_pol            = $request->no_pol;
  //     }
      
  //     if($online_status=="true"){
  //       if($model_query->pvr_id==null){
  //         if($request->cost_center_code){  
  //           $list_cost_center = DB::connection('sqlsrv')->table("AC_CostCenterNames")
  //           ->select('CostCenter','Description')
  //           ->where('CostCenter',$request->cost_center_code)
  //           ->first();
  //           if(!$list_cost_center)
  //           throw new \Exception(json_encode(["cost_center_code"=>["Cost Center Code Tidak Ditemukan"]]), 422);
          
  //           $model_query->cost_center_code = $list_cost_center->CostCenter;
  //           $model_query->cost_center_desc = $list_cost_center->Description;
  //         }else{
  //           $model_query->cost_center_code = null;
  //           $model_query->cost_center_desc = null;
  //         } 
  //       }
  //     }else{
  //       if($request->cost_center_code)
  //       throw new \Exception("Pengisian cost center harus dalam mode online", 1);
  //     }

  //     $model_query->updated_at      = $t_stamp;
  //     $model_query->updated_user    = $this->admin_id;
  //     $model_query->save();

  //     $ptg_trx_dt=[];



  //     if(isset($prev_supir_id) && ($prev_supir_id == null || $prev_supir_id == 1 ) && $supir_dt->id!=1 && $supir_dt->potongan){
  //       array_push($ptg_trx_dt,[
  //         "_source"     => "TRX_TRP",
  //         "employee_id" => $supir_dt->id,
  //         "user_id"     => $this->admin_id,
  //         "trx_trp_id"  => $model_query->id,
  //       ]);
  //     }

  //     if(isset($prev_kernet_id) && ($prev_kernet_id == null || $prev_kernet_id == 1) && isset($kernet_dt) && $kernet_dt->id!=1 && $kernet_dt->potongan){
  //       array_push($ptg_trx_dt,[
  //         "_source"     => "TRX_TRP",
  //         "employee_id" => $kernet_dt->id,
  //         "user_id"     => $this->admin_id,
  //         "trx_trp_id"  => $model_query->id,
  //       ]);
  //     }

  //     if(count($ptg_trx_dt) > 0)
  //     PSPotonganTrx::insertData($ptg_trx_dt);

  //     $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
  //     MyLog::sys("trx_trp",$request->id,"update",$SYSNOTE);

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses ubah data berhasil",
  //       "updated_at" => $t_stamp,
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     // return response()->json([
  //     //   "getCode" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     //   "message" => $e->getMessage(),
  //     // ], 400);
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     if ($e->getCode() == 422) {
  //       return response()->json(json_decode($e->getMessage()), 422);
  //     }
  //     return response()->json([
  //       "message" => "Proses ubah data gagal",
  //     ], 400);
  //   }
  // }

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

  // public function delete(Request $request)
  // {
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.remove');

  //   DB::beginTransaction();

  //   try {
  //     $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
  //     // if($model_query->requested_by != $this->admin_id){
  //     //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
  //     // }
  //     if (!$model_query) {
  //       throw new \Exception("Data tidak terdaftar", 1);
  //     }
      
  //     if(in_array(1,[$model_query->val2,$model_query->val3,$model_query->val4,$model_query->val5,$model_query->val_ticket]) || $model_query->req_deleted==1  || $model_query->deleted==1) 
  //     throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

  //     if($model_query->pvr_id!="" || $model_query->pvr_id!=null)
  //     throw new \Exception("Harap Lakukan Permintaan Penghapusan Terlebih Dahulu",1);

  //     $deleted_reason = $request->deleted_reason;
  //     if(!$deleted_reason)
  //     throw new \Exception("Sertakan Alasan Penghapusan",1);

  //     $t_stamp                      = date("Y-m-d H:i:s");
  //     $model_query->deleted         = 1;
  //     $model_query->deleted_user    = $this->admin_id;
  //     $model_query->deleted_at      = $t_stamp;
  //     $model_query->deleted_reason  = $deleted_reason;
  //     $model_query->save();

  //     PSPotonganTrx::deletePotongan([
  //       "_source"         => "TRX_TRP",
  //       "trx_trp_id"      => $model_query->id,
  //       "deleted_user"    => $this->admin_id,
  //       "deleted_at"      => $t_stamp,
  //       "deleted_reason"  => $deleted_reason,
  //     ]);

  //     MyLog::sys("trx_trp",$request->id,"delete");

  //     DB::commit();
  //     return response()->json([
  //       "message"         => "Proses Hapus data berhasil",
  //       "deleted"         => $model_query->deleted,
  //       "deleted_user"    => $model_query->deleted_user,
  //       "deleted_by"      => $model_query->deleted_user ? new IsUserResource(IsUser::find($model_query->deleted_user)) : null,
  //       "deleted_at"      => $model_query->deleted_at,
  //       "deleted_reason"  => $model_query->deleted_reason,
  //     ], 200);
  //   } catch (\Exception  $e) {
  //     DB::rollback();
  //     if ($e->getCode() == "23000")
  //       return response()->json([
  //         "message" => "Data tidak dapat dihapus, data terkait dengan data yang lain nya",
  //       ], 400);

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
  //       "message" => "Proses hapus data gagal",
  //     ], 400);
  //     //throw $th;
  //   }
  // }

  // public function reqDelete(Request $request)
  // {
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.request_remove');

  //   DB::beginTransaction();

  //   try {
  //     $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
  //     // if($model_query->requested_by != $this->admin_id){
  //     //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
  //     // }
  //     if (!$model_query) {
  //       throw new \Exception("Data tidak terdaftar", 1);
  //     }
      
  //     if(in_array(1,[$model_query->val2,$model_query->val3,$model_query->val4,$model_query->val5,$model_query->val_ticket]))
  //     throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

  //     if($model_query->deleted==1 || $model_query->req_deleted==1 )
  //     throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

  //     if($model_query->pvr_id=="" || $model_query->pvr_id==null)
  //     throw new \Exception("Harap Lakukan Penghapusan",1);

  //     $req_deleted_reason = $request->req_deleted_reason;
  //     if(!$req_deleted_reason)
  //     throw new \Exception("Sertakan Alasan Penghapusan",1);

  //     $model_query->req_deleted = 1;
  //     $model_query->req_deleted_user = $this->admin_id;
  //     $model_query->req_deleted_at = date("Y-m-d H:i:s");
  //     $model_query->req_deleted_reason = $req_deleted_reason;
  //     $model_query->save();

  //     MyLog::sys("trx_trp",$request->id,"delete","Request Delete (Void)");

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses Permintaan Hapus data berhasil",
  //       "req_deleted"=>$model_query->req_deleted,
  //       "req_deleted_user"=>$model_query->req_deleted_user,
  //       "req_deleted_by"=>$model_query->req_deleted_user ? new IsUserResource(IsUser::find($model_query->req_deleted_user)) : null,
  //       "req_deleted_at"=>$model_query->req_deleted_at,
  //       "req_deleted_reason"=>$model_query->req_deleted_reason,
  //     ], 200);
  //   } catch (\Exception  $e) {
  //     DB::rollback();
  //     if ($e->getCode() == "23000")
  //       return response()->json([
  //         "message" => "Data tidak dapat dihapus, data terkait dengan data yang lain nya",
  //       ], 400);

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
  //       "message" => "Proses hapus data gagal",
  //     ], 400);
  //     //throw $th;
  //   }
  // }

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
      
      if(in_array(1,[$model_query->val2,$model_query->val3,$model_query->val4,$model_query->val5,$model_query->val_ticket]))
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      if($model_query->deleted==1 )
      throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

      if($model_query->pvr_id=="" || $model_query->pvr_id==null)
      throw new \Exception("Harap Lakukan Penghapusan",1);

      $deleted_reason = $model_query->req_deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $t_stamp                      = date("Y-m-d H:i:s");
      $model_query->deleted         = 1;
      $model_query->deleted_user    = $this->admin_id;
      $model_query->deleted_at      = $t_stamp;
      $model_query->deleted_reason  = $deleted_reason;

      PSPotonganTrx::deletePotongan([
        "_source"         => "TRX_TRP",
        "trx_trp_id"      => $model_query->id,
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
      MyLog::sys("trx_trp",$request->id,"delete","Approve Request Delete (Void)");

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

    if($request->jenis!=="TBS" || $connection_name!=='sqlsrv'){
      $get_data_ticket=$get_data_ticket->whereIn('ProductName',["RTBS","TBS"]);
    }else {
      $get_data_ticket=$get_data_ticket->whereIn('ProductName',["RTBS","TBS"]);
    }

    $get_data_ticket=$get_data_ticket->first();
    
    return $get_data_ticket;
  }

  // public function previewFile(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.preview_file');

  //   set_time_limit(0);

  //   $trx_trp = TrxTrp::find($request->id);

  //   if($trx_trp->val1==0)
  //   return response()->json([
  //     "message" => "Mandor harus Validasi Terlebih Dahulu",
  //   ], 400);


  //   $supir_id   = $trx_trp->supir_id;
  //   $kernet_id  = $trx_trp->kernet_id;
  //   $ttl_ps     = 0;
  //   $ttl_pk     = 0;

  //   $ptg_ps_ids = "";
  //   $ptg_pk_ids = "";
  //   foreach ($trx_trp->potongan as $k => $v) {
  //     if($v->potongan_mst->employee_id == $supir_id){
  //       $ttl_ps+=$v->nominal_cut;
  //       $ptg_ps_ids.="#".$v->potongan_mst->id." ";
  //     }

  //     if($v->potongan_mst->employee_id == $kernet_id){
  //       $ttl_pk+=$v->nominal_cut;
  //       $ptg_pk_ids.="#".$v->potongan_mst->id." ";
  //     }
  //   }
    
  //   $ujalan = \App\Models\MySql\Ujalan::where("id",$trx_trp->id_uj)->first();
  //   $details = \App\Models\MySql\UjalanDetail::where("id_uj",$trx_trp->id_uj)->orderBy("ordinal","asc")->get();
  //   // $total = 0;

  //   // foreach ($details as $key => $value) {
  //   //   $total += $value["qty"] * $value["harga"];
  //   // }

  //   $sendData = [
  //     "id"            => $trx_trp->id,
  //     "id_uj"         => $trx_trp->id_uj,
  //     "no_pol"        => $trx_trp->no_pol,
  //     "payment"       => $trx_trp->payment_method_id,
  //     "payment_name"  => $trx_trp->payment_method->name,
  //     "supir"         => $trx_trp->supir,
  //     "supir_rek_no"  => $trx_trp->supir_rek_no,
  //     "ttl_ps"        => $ttl_ps,
  //     "ptg_ps_ids"    => $ptg_ps_ids,
  //     "kernet"        => $trx_trp->kernet,
  //     "kernet_rek_no" => $trx_trp->kernet_rek_no,
  //     "ttl_pk"        => $ttl_pk,
  //     "ptg_pk_ids"    => $ptg_pk_ids,
  //     "tanggal"       => $trx_trp->tanggal,
  //     "created_at"    => $trx_trp->created_at,
  //     "asal"          => env("app_name"),
  //     "xto"           => $trx_trp->xto,
  //     "jenis"         => $trx_trp->jenis,
  //     "tipe"          => $trx_trp->tipe,
  //     "details"       => $details,
  //     "total"         => $ujalan->harga,
  //     "is_transition" => $trx_trp->transition_type=='From',
  //     "user_1"        => $this->admin->the_user->username,
  //   ];   
    
  //   // $date = new \DateTime();
  //   // $filename = $date->format("YmdHis");
  //   // Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
  //   // $pdf = PDF::loadView('pdf.trx_trp_ujalan', $sendData)->setPaper('a4', 'portrait');
    
  //   $html = view("html.trx_trp_ujalan",$sendData);
  
  //   // $mime = MyLib::mime("pdf");
  //   // $bs64 = base64_encode($pdf->download($filename . "." . $mime["ext"]));
  
  //   $result = [
  //     // "contentType" => $mime["contentType"],
  //     // "data" => $bs64,
  //     // "dataBase64" => $mime["dataBase64"] . $bs64,
  //     // "filename" => $filename . "." . $mime["ext"],
  //     "html"=>$html->render()
  //   ];
  //   return $result;
  // }

  // public function previewFileBT(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.preview_file');

  //   set_time_limit(0);

  //   $trx_trp = TrxTrp::find($request->id);

  //   if($trx_trp->val1==0)
  //   return response()->json([
  //     "message" => "Mandor harus Validasi Terlebih Dahulu",
  //   ], 400);


  //   $supir_id   = $trx_trp->supir_id;
  //   $kernet_id  = $trx_trp->kernet_id;
  //   $ttl_ps     = 0;
  //   $ttl_pk     = 0;

  //   $supir_money = 0;
  //   $kernet_money = 0;
  //   foreach ($trx_trp->uj_details2 as $key => $val) {
  //     if($val->xfor=='Kernet'){
  //       $kernet_money+=$val->amount*$val->qty;
  //     }else{
  //       $supir_money+=$val->amount*$val->qty;
  //     }
  //   }

  //   $ptg_ps_ids = "";
  //   $ptg_pk_ids = "";
  //   foreach ($trx_trp->potongan as $k => $v) {
  //     if($v->potongan_mst->employee_id == $supir_id){
  //       $ttl_ps+=$v->nominal_cut;
  //       $ptg_ps_ids.="#".$v->potongan_mst->id." ";
  //     }

  //     if($v->potongan_mst->employee_id == $kernet_id){
  //       $ttl_pk+=$v->nominal_cut;
  //       $ptg_pk_ids.="#".$v->potongan_mst->id." ";
  //     }
  //   }


  //   $supir_money -= $ttl_ps;
  //   $kernet_money -= $ttl_pk;
  
  //   $sendData = [
  //     "id"            => $trx_trp->id,
  //     "id_uj"         => $trx_trp->id_uj,
  //     "logo"          => File::exists(files_path("/duitku.png")) ? "data:image/png;base64,".base64_encode(File::get(files_path("/duitku.png"))) :"",
  //     "ref_no0"       => $trx_trp->duitku_supir_disburseId,
  //     "supir"         => $trx_trp->supir,
  //     "supir_rek_no"  => $trx_trp->supir_rek_no,
  //     "nominal0"      => $supir_money,

  //     "ref_no1"       => $trx_trp->duitku_kernet_disburseId,
  //     "kernet"        => $trx_trp->kernet,
  //     "kernet_rek_no" => $trx_trp->kernet_rek_no,
  //     "nominal1"      => $kernet_money,
      
  //     "tanggal"       => $trx_trp->val5_at,
  //   ];   
  //   $html = view("html.trx_trp_ujalan_bt",$sendData);  
  //   $result = [
  //     "html"=>$html->render()
  //   ];
  //   return $result;
  // }

  // public function previewFiles(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.download_file');

  //   // set_time_limit(0);

  //   // $rules = [
  //   //   'date_from' => "required|date_format:Y-m-d H:i:s",
  //   // ];

  //   // $messages = [
  //   //   'date_from.required' => 'Date From is required',
  //   //   'date_from.date_format' => 'Please Select Date From',
  //   // ];

  //   // $validator = Validator::make($request->all(), $rules, $messages);

  //   // if ($validator->fails()) {
  //   //   throw new ValidationException($validator);
  //   // }


  //   // // Change some request value
  //   // $request['period'] = "Daily";

  //   // $date_from = $request->date_from;
  //   // $d_from = date("Y-m", MyLib::manualMillis($date_from) / 1000) . "-01 00:00:00";
  //   // $date_f = new \DateTime($d_from);

  //   // $start = clone $date_f;
  //   // $start->add(new \DateInterval('P1M'));
  //   // $start->sub(new \DateInterval('P1D'));
  //   // $x = $start->format("Y-m-d H:i:s");

  //   // $request['date_from'] = $d_from;
  //   // $request['date_to'] = $x;
  //   // return response()->json(["data"=>[$d_from,$x]],200);

  //   set_time_limit(0);
  //   $callGet = $this->index($request, true);
  //   if ($callGet->getStatusCode() != 200) return $callGet;
  //   $ori = json_decode(json_encode($callGet), true)["original"];
  //   $data = $ori["data"];
    
  //   // $additional = $ori["additional"];


  //   // $date = new \DateTime();
  //   // $filename = $date->format("YmdHis") . "-" . $additional["company_name"] . "[" . $additional["date_from"] . "-" . $additional["date_to"] . "]";
  //   // // $filename=$date->format("YmdHis");

  //   // // return response()->json(["message"=>$filename],200);

  //   // $mime = MyLib::mime("csv");
  //   // $bs64 = base64_encode(Excel::raw(new MyReport($data, 'report.sensor_get_data_by_location'), $mime["exportType"]));
  //   // $mime = MyLib::mime("xlsx");
  //   // $bs64 = base64_encode(Excel::raw(new MyReport($data, 'report.tracking_info2'), $mime["exportType"]));

    

  //   // $sendData = [
  //   //   'pag_no'  => $pag->no,
  //   //   'created_at'    => $pag->created_at,
  //   //   'updated_at'    => $pag->updated_at,
  //   //   'proyek'  => $pag->project ?? "",
  //   //   'need'    => $pag->need,
  //   //   'part'    => $pag->part,
  //   //   'datas'   => $pag->pag_details,
  //   //   'title'   => "PENGAMBILAN BARANG GUDANG (PAG)"
  //   // ];
  //   // dd($sendData);

  //   $shows=["id","tanggal","no_pol","jenis","xto","amount"];
  //   if($this->role != "Finance"){
  //     $shows = array_merge($shows,[
  //       'ticket_a_out_at','ticket_b_in_at',
  //       'ticket_a_bruto','ticket_b_bruto','ticket_b_a_bruto','ticket_b_a_bruto_persen',
  //       'ticket_a_tara','ticket_b_tara','ticket_b_a_tara','ticket_b_a_tara_persen',
  //       'ticket_a_netto','ticket_b_netto','ticket_b_a_netto','ticket_b_a_netto_persen',
  //     ]);
  //   }

  //   if($this->role == "Finance"){
  //     $shows = array_merge($shows,[
  //       "pv_no","pvr_no","pv_total","pv_datetime"
  //     ]);
  //   }
  //   $newDetails = [];
  //   $total_a_bruto = 0;
  //   $total_a_tara = 0;
  //   $total_a_netto = 0;
  //   $total_b_bruto = 0;
  //   $total_b_tara = 0;
  //   $total_b_netto = 0;
  //   $total_b_a_bruto = 0;
  //   $total_b_a_tara = 0;
  //   $total_b_a_netto = 0;
  //   foreach ($ori["data"] as $key => $value) {
  //     $ticket_a_bruto = (float)$value["ticket_a_bruto"];
  //     $ticket_b_bruto = (float)$value["ticket_b_bruto"];
  //     list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($value["ticket_a_bruto"],$value["ticket_b_bruto"]);
  //     $ticket_a_tara = (float)$value["ticket_a_tara"];
  //     $ticket_b_tara = (float)$value["ticket_b_tara"];
  //     list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($value["ticket_a_tara"],$value["ticket_b_tara"]);
  //     $ticket_a_netto = (float)$value["ticket_a_netto"];
  //     $ticket_b_netto = (float)$value["ticket_b_netto"];
  //     list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($value["ticket_a_netto"],$value["ticket_b_netto"]);

  //     $total_a_bruto+=$ticket_a_bruto;
  //     $total_a_tara+=$ticket_a_tara;
  //     $total_a_netto+=$ticket_a_netto;

  //     $total_b_bruto+=$ticket_b_bruto;
  //     $total_b_tara+=$ticket_b_tara;
  //     $total_b_netto+=$ticket_b_netto;

  //     $limitSusut = 0.4;

  //     $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
  //     $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
  //     $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
  //     $value['ticket_a_bruto']=number_format($ticket_a_bruto, 0,',','.');
  //     $value['ticket_b_bruto']=number_format($ticket_b_bruto, 0,',','.');
  //     $value['ticket_b_a_bruto']=block_negative($ticket_b_a_bruto, 0);
  //     $value['ticket_b_a_bruto_persen_red']=abs($ticket_b_a_bruto_persen) >= $limitSusut ? 'color:red;' : '';
  //     $value['ticket_b_a_bruto_persen']=block_negative($ticket_b_a_bruto_persen, 2);
  //     $value['ticket_a_tara']=number_format($ticket_a_tara, 0,',','.');
  //     $value['ticket_b_tara']=number_format($ticket_b_tara, 0,',','.');
  //     $value['ticket_b_a_tara']=block_negative($ticket_b_a_tara, 0);
  //     $value['ticket_b_a_tara_persen_red']=abs($ticket_b_a_tara_persen) >= $limitSusut ? 'color:red;' : '';
  //     $value['ticket_b_a_tara_persen']=block_negative($ticket_b_a_tara_persen, 2);
  //     $value['ticket_a_netto']=number_format($ticket_a_netto, 0,',','.');
  //     $value['ticket_b_netto']=number_format($ticket_b_netto, 0,',','.');
  //     $value['ticket_b_a_netto']=block_negative($ticket_b_a_netto, 0);
  //     $value['ticket_b_a_netto_persen_red']=abs($ticket_b_a_netto_persen) >= $limitSusut ? 'color:red;' : '';
  //     $value['ticket_b_a_netto_persen']=block_negative($ticket_b_a_netto_persen, 2);
  //     $value['amount']=number_format((float)$value["amount"], 0,',','.');
  //     $value['pv_total']=number_format((float)$value["pv_total"], 0,',','.');
  //     $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
  //     array_push($newDetails,$value);
  //   }

  //   list($total_b_a_bruto, $total_b_a_bruto_persen) =  $this->genPersen($total_a_bruto,$total_b_bruto);
  //   list($total_b_a_tara, $total_b_a_tara_persen) =  $this->genPersen($total_a_tara,$total_b_tara);
  //   list($total_b_a_netto, $total_b_a_netto_persen) =  $this->genPersen($total_a_netto,$total_b_netto);
    

  //   $ttl_a_tara=number_format($total_a_tara, 0,',','.');
  //   $ttl_a_bruto=number_format($total_a_bruto, 0,',','.');
  //   $ttl_a_netto=number_format($total_a_netto, 0,',','.');

  //   $ttl_b_tara=number_format($total_b_tara, 0,',','.');
  //   $ttl_b_bruto=number_format($total_b_bruto, 0,',','.');
  //   $ttl_b_netto=number_format($total_b_netto, 0,',','.');


  //   $ttl_b_a_tara=block_negative($total_b_a_tara, 0);
  //   $ttl_b_a_bruto=block_negative($total_b_a_bruto, 0);
  //   $ttl_b_a_netto=block_negative($total_b_a_netto, 0);
    
  //   $ttl_b_a_bruto_persen=block_negative($total_b_a_bruto_persen, 2);
  //   $ttl_b_a_tara_persen=block_negative($total_b_a_tara_persen, 2);
  //   $ttl_b_a_netto_persen=block_negative($total_b_a_netto_persen, 2);

  //   // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

  //   $date = new \DateTime();
  //   $filename = $date->format("YmdHis");
  //   Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
  //   $pdf = PDF::loadView('pdf.trx_trp', ["data"=>$newDetails,"shows"=>$shows,"info"=>[
  //     "from"=>date("d-m-Y",strtotime($request->date_from)),
  //     "to"=>date("d-m-Y",strtotime($request->date_to)),
  //     "now"=>date("d-m-Y H:i:s"),
  //     "ttl_a_bruto"=>$ttl_a_bruto,
  //     "ttl_a_tara"=>$ttl_a_tara,
  //     "ttl_a_netto"=>$ttl_a_netto,
  //     "ttl_b_bruto"=>$ttl_b_bruto,
  //     "ttl_b_tara"=>$ttl_b_tara,
  //     "ttl_b_netto"=>$ttl_b_netto,
  //     "ttl_b_a_bruto"=>$ttl_b_a_bruto,
  //     "ttl_b_a_tara"=>$ttl_b_a_tara,
  //     "ttl_b_a_netto"=>$ttl_b_a_netto,
  //     // "ttl_b_a_bruto_persen"=>$ttl_b_a_bruto_persen,
  //     // "ttl_b_a_tara_persen"=>$ttl_b_a_tara_persen,
  //     // "ttl_b_a_netto_persen"=>$ttl_b_a_netto_persen,
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

  // public function downloadExcel(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.download_file');

  //   set_time_limit(0);
  //   $callGet = $this->index($request, true);
  //   if ($callGet->getStatusCode() != 200) return $callGet;
  //   $ori = json_decode(json_encode($callGet), true)["original"];
  //   $data = $ori["data"];
    
  //   $shows=["id","tanggal","no_pol","jenis","xto","amount"];
  //   if($this->role != "Finance"){
  //     $shows = array_merge($shows,[
  //       'ticket_a_out_at','ticket_b_in_at',
  //       'ticket_a_bruto','ticket_b_bruto','ticket_b_a_bruto','ticket_b_a_bruto_persen',
  //       'ticket_a_tara','ticket_b_tara','ticket_b_a_tara','ticket_b_a_tara_persen',
  //       'ticket_a_netto','ticket_b_netto','ticket_b_a_netto','ticket_b_a_netto_persen',
  //     ]);
  //   }
    
  //   if($this->role == "Finance"){
  //     $shows = array_merge($shows,[
  //       "pv_no","pvr_no","pv_total","pv_datetime"
  //     ]);
  //   }

  //   $newDetails = [];

  //   foreach ($ori["data"] as $key => $value) {
  //     $ticket_a_bruto = (float)$value["ticket_a_bruto"];
  //     $ticket_b_bruto = (float)$value["ticket_b_bruto"];
  //     list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($value["ticket_a_bruto"],$value["ticket_b_bruto"]);
  //     $ticket_a_tara = (float)$value["ticket_a_tara"];
  //     $ticket_b_tara = (float)$value["ticket_b_tara"];
  //     list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($value["ticket_a_tara"],$value["ticket_b_tara"]);
  //     $ticket_a_netto = (float)$value["ticket_a_netto"];
  //     $ticket_b_netto = (float)$value["ticket_b_netto"];
  //     list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($value["ticket_a_netto"],$value["ticket_b_netto"]);

  //     $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
  //     $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
  //     $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
  //     $value['ticket_a_bruto']=$ticket_a_bruto;
  //     $value['ticket_b_bruto']=$ticket_b_bruto;
  //     $value['ticket_b_a_bruto']=$ticket_b_a_bruto;
  //     $value['ticket_b_a_bruto_persen']=$ticket_b_a_bruto_persen;
  //     $value['ticket_a_tara']=$ticket_a_tara;
  //     $value['ticket_b_tara']=$ticket_b_tara;
  //     $value['ticket_b_a_tara']=$ticket_b_a_tara;
  //     $value['ticket_b_a_tara_persen']=$ticket_b_a_tara_persen;
  //     $value['ticket_a_netto']=$ticket_a_netto;
  //     $value['ticket_b_netto']=$ticket_b_netto;
  //     $value['ticket_b_a_netto']=$ticket_b_a_netto;
  //     $value['ticket_b_a_netto_persen']=$ticket_b_a_netto_persen;
  //     $value['amount']=$value["amount"];
  //     $value['pv_total']=$value["pv_total"];
  //     $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
  //     array_push($newDetails,$value);
  //   }

  //   // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

  //   $date = new \DateTime();
  //   $filename=$date->format("YmdHis").'-trx_trp'."[".$request["date_from"]."-".$request["date_to"]."]";

  //   $mime=MyLib::mime("xlsx");
  //   // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
  //   $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails,"shows"=>$shows],'excel.trx_trp'), $mime["exportType"]));


  //   $result = [
  //     "contentType" => $mime["contentType"],
  //     "data" => $bs64,
  //     "dataBase64" => $mime["dataBase64"] . $bs64,
  //     "filename" => $filename . "." . $mime["ext"],
  //   ];
  //   return $result;
  // }

  // public function validasi(Request $request){
  //   MyAdmin::checkMultiScope($this->permissions, ['trp_trx.val','trp_trx.val1','trp_trx.val2','trp_trx.val3','trp_trx.val4','trp_trx.val5','trp_trx.ticket.val_ticket']);

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
  //     $model_query = TrxTrp::find($request->id);
  //     if($model_query->val && $model_query->val1 && $model_query->val2 && $model_query->val3 && $model_query->val4 && $model_query->val5 && $model_query->val_ticket){
  //       throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
  //     }

  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.val',true) && !$model_query->val){
  //       $model_query->val = 1;
  //       $model_query->val_user = $this->admin_id;
  //       $model_query->val_at = $t_stamp;
  //     }
  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.val1',true) && !$model_query->val1){
  //       $model_query->val1 = 1;
  //       $model_query->val1_user = $this->admin_id;
  //       $model_query->val1_at = $t_stamp;
  //     }
  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.val2',true) && !$model_query->val2){
  //       $model_query->val2 = 1;
  //       $model_query->val2_user = $this->admin_id;
  //       $model_query->val2_at = $t_stamp;
  //     }
  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.val3',true) && !$model_query->val3){
  //       $model_query->val3 = 1;
  //       $model_query->val3_user = $this->admin_id;
  //       $model_query->val3_at = $t_stamp;
  //     }
  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.val4',true) && !$model_query->val4){
  //       $model_query->val4 = 1;
  //       $model_query->val4_user = $this->admin_id;
  //       $model_query->val4_at = $t_stamp;
  //     }
  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.val5',true) && !$model_query->val5){
  //       $model_query->val5 = 1;
  //       $model_query->val5_user = $this->admin_id;
  //       $model_query->val5_at = $t_stamp;
  //     }

  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.val_ticket',true) && !$model_query->val_ticket){
  //       $model_query->val_ticket = 1;
  //       $model_query->val_ticket_user = $this->admin_id;
  //       $model_query->val_ticket_at = $t_stamp;
  //     }

  //     $model_query->save();

  //     MyLog::sys("trx_trp",$request->id,"approve");

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses validasi data berhasil",
  //       "val"=>$model_query->val,
  //       "val_user"=>$model_query->val_user,
  //       "val_at"=>$model_query->val_at,
  //       "val_by"=>$model_query->val_user ? new IsUserResource(IsUser::find($model_query->val_user)) : null,
  //       "val1"=>$model_query->val1,
  //       "val1_user"=>$model_query->val1_user,
  //       "val1_at"=>$model_query->val1_at,
  //       "val1_by"=>$model_query->val1_user ? new IsUserResource(IsUser::find($model_query->val1_user)) : null,
  //       "val2"=>$model_query->val2,
  //       "val2_user"=>$model_query->val2_user,
  //       "val2_at"=>$model_query->val2_at,
  //       "val2_by"=>$model_query->val2_user ? new IsUserResource(IsUser::find($model_query->val2_user)) : null,
  //       "val3"=>$model_query->val3,
  //       "val3_user"=>$model_query->val3_user,
  //       "val3_at"=>$model_query->val3_at,
  //       "val3_by"=>$model_query->val3_user ? new IsUserResource(IsUser::find($model_query->val3_user)) : null,
  //       "val4"=>$model_query->val4,
  //       "val4_user"=>$model_query->val4_user,
  //       "val4_at"=>$model_query->val4_at,
  //       "val4_by"=>$model_query->val4_user ? new IsUserResource(IsUser::find($model_query->val4_user)) : null,
  //       "val5"=>$model_query->val5,
  //       "val5_user"=>$model_query->val5_user,
  //       "val5_at"=>$model_query->val5_at,
  //       "val5_by"=>$model_query->val5_user ? new IsUserResource(IsUser::find($model_query->val5_user)) : null,
  //       "val_ticket"=>$model_query->val_ticket,
  //       "val_ticket_user"=>$model_query->val_ticket_user,
  //       "val_ticket_at"=>$model_query->val_ticket_at,
  //       "val_ticket_by"=>$model_query->val_ticket_user ? new IsUserResource(IsUser::find($model_query->val_ticket_user)) : null,
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

  public function valTickets(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.ticket.val_ticket']);

    $ids = json_decode($request->ids, true);
    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_querys = TrxTrp::whereIn("id",$ids)->get();
      $valList = [];

      foreach ($model_querys as $key => $v) {
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.val_ticket',true) && !$v->val_ticket){
          $v->val_ticket = 1;
          $v->val_ticket_user = $this->admin_id;
          $v->val_ticket_at = $t_stamp;
          $v->save();
          array_push($valList,[
            "id"=>$v->id,
            "val_ticket"=>$v->val_ticket,
            "val_ticket_user"=>$v->val_ticket_user,
            "val_ticket_at"=>$v->val_ticket_at,
            "val_ticket_by"=>$v->val_ticket_user ? new IsUserResource(IsUser::find($v->val_ticket_user)) : null,
          ]);
        }
      }

      $nids = array_map(function($x) {
        return $x['id'];        
      },$valList);

      MyLog::sys("trx_trp",null,"val_tickets",implode(",",$nids));

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

  public function genPersen($a,$b){
    $a = (float)$a;
    $b = (float)$b;
    
    $diff=(float)($b-$a);
    $bigger = $diff > 0 ? $b  : $a;

    if($bigger==0) return [$diff,0];

    return [$diff , $diff / $bigger * 100];
  }

  // public function doGenPVR(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.generate_pvr');
  //   $rules = [
  //     // 'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
  //     'online_status' => "required",
  //   ];

  //   $messages = [
  //     // 'id.required' => 'ID tidak boleh kosong',
  //     // 'id.exists' => 'ID tidak terdaftar',
  //   ];

  //   $validator = Validator::make($request->all(), $rules, $messages);

  //   if ($validator->fails()) {
  //     throw new ValidationException($validator);
  //   }
  //   $online_status=$request->online_status;
  //   if($online_status!="true")
  //   return response()->json([
  //     "message" => "Mode Harus Online",
  //   ], 400);

  //   $miniError="";
  //   $id="";
  //   try {
  //     $trx_trps = TrxTrp::where(function($q1){$q1->where('pvr_had_detail',0)->orWhereNull("pvr_id");})->whereNull("pv_id")->where("req_deleted",0)->where("deleted",0)->where('val',1)->where('val1',1)->get();      
  //     if(count($trx_trps)==0){
  //       throw new \Exception("Semua PVR sudah terisi",1);
  //     }
  //     $changes=[];
  //     foreach ($trx_trps as $key => $tt) {
  //       $id=$tt->id;
  //       $callGet = $this->genPVR($id);
  //       array_push($changes,$callGet);
  //     }
  //     if(count($changes)>0){
  //       $ids = array_map(function ($x) {
  //         return $x["id"];
  //       },$changes);
  //       MyLog::sys("trx_trp",null,"generate_pvr",implode(",",$ids));
  //     }
  //     return response()->json($changes, 200);
  //   } catch (\Exception $e) {
  //     if(isset($changes) && count($changes)>0){
  //       $ids = array_map(function ($x) {
  //         return $x["id"];
  //       },$changes);
  //       MyLog::sys("trx_trp",null,"generate_pvr",implode(",",$ids));
  //     }

  //     if ($e->getCode() == 1) {
  //       if($id!=""){
  //         $miniError.="Trx-".$id.".";
  //       }
  //       $miniError.="PVR Batal Dibuat: ".$e->getMessage();
  //     }else{
  //       if($id!=""){
  //         $miniError.="Trx-".$id.".";
  //       }
  //       $miniError.="PVR Batal Dibuat. Akses Jaringan Gagal";
  //     }
  //     return response()->json([
  //       "message" => $miniError,
  //     ], 400);
  //   }
  // }

  // public function genPVR($trx_trp_id){

  //   $t_stamp = date("Y-m-d H:i:s");

  //   $time = microtime(true);
  //   $mSecs = sprintf('%03d', ($time - floor($time)) * 1000);
  //   $t_stamp_ms = date("Y-m-d H:i:s",strtotime($t_stamp)).".".$mSecs;

  //   $trx_trp = TrxTrp::where("id",$trx_trp_id)->first();
  //   if(!$trx_trp){
  //     throw new \Exception("Karna Transaksi tidak ditemukan",1);
  //   }

  //   if($trx_trp->pvr_had_detail==1) throw new \Exception("Karna PVR sudah selesai dibuat",1);
  //   // if($trx_trp->cost_center_code==null) throw new \Exception("Cost Center Code belum diisi",1);
  //   if($trx_trp->pv_id!=null) throw new \Exception("Karna PV sudah diisi",1);

  //   if($trx_trp->cost_center_code==null){
  //     $trx_trp->cost_center_code = '112';
  //     $trx_trp->cost_center_desc = 'Transport';      
  
  //     $get_cost_center = DB::connection('sqlsrv')->table("AC_CostCenterNames")
  //     ->select('CostCenter','Description')
  //     ->where('CostCenter','like', '112%')
  //     ->where('Description','like', '%'.trim($trx_trp->no_pol))
  //     ->first();
  
  //     if($get_cost_center){
  //       $trx_trp->cost_center_code = $get_cost_center->CostCenter;
  //       $trx_trp->cost_center_desc = $get_cost_center->Description;
  //     }
  //   }
      
  //   $supir = $trx_trp->supir;
  //   $no_pol = $trx_trp->no_pol;
  //   $kernet = $trx_trp->kernet;
  //   $associate_name="(S) ".$supir.($kernet?" (K) ".$kernet." ":" (Tanpa Kernet) ").$no_pol; // max 80char

  //   $ujalan=Ujalan::where("id",$trx_trp->id_uj)->first();

  //   $arrRemarks = [];
  //   array_push($arrRemarks,"#".$trx_trp->id.($trx_trp->transition_type=="From" ?" (P) " : " ").$associate_name.".");
  //   array_push($arrRemarks,"BIAYA UANG JALAN ".$trx_trp->jenis." ".env("app_name")."-".$trx_trp->xto." P/".date("d-m-y",strtotime($trx_trp->tanggal))).".";


  //   // $arr=[1];
    
  //   // if($trx_trp->jenis=="PK" && env("app_name")!="SMP")
  //   // $arr=[1,2];
    

  //   $ujalan_details = UjalanDetail::where("id_uj",$trx_trp->id_uj)->where("for_remarks",1)->orderBy("ordinal","asc")->get();
  //   if(count($ujalan_details)==0)
  //   throw new \Exception("Detail Ujalan Harus diisi terlebih dahulu",1);
    
  //   foreach ($ujalan_details as $key => $v) {
  //     array_push($arrRemarks,$v->xdesc." ".number_format($v->qty, 0,',','.')."x".number_format($v->harga, 0,',','.')."=".number_format($v->qty*$v->harga, 0,',','.').";");
  //   }

  //   if($ujalan->note_for_remarks!=null){
  //     $note_for_remarks_arr = preg_split('/\r\n|\r|\n/', $ujalan->note_for_remarks);
  //     $arrRemarks = array_merge($arrRemarks,$note_for_remarks_arr);
  //   }
    
  //   $remarks = implode(chr(10),$arrRemarks);

  //   $ujalan_details2 = \App\Models\MySql\UjalanDetail2::where("id_uj",$trx_trp->id_uj)->get();
  //   if(count($ujalan_details2)==0)
  //   throw new \Exception("Detail PVR Harus diisi terlebih dahulu",1);

  //   if(strlen($associate_name)>80){
  //     $associate_name = substr($associate_name,0,80);
  //   }

  //   // $bank_account_code=env("PVR_BANK_ACCOUNT_CODE");
  //   $bank_account_code=$trx_trp->payment_method->account_code;
    
  //   $bank_acccount_db = DB::connection('sqlsrv')->table('FI_BankAccounts')
  //   ->select('BankAccountID')
  //   ->where("bankaccountcode",$bank_account_code)
  //   ->first();
  //   if(!$bank_acccount_db) throw new \Exception("Bank account code tidak terdaftar ,segera infokan ke tim IT",1);

  //   $bank_account_id = $bank_acccount_db->BankAccountID;
    
  //   // @VoucherID INT = 0,
  //   $voucher_no = "(AUTO)";
  //   $voucher_type = "TRP";
  //   $voucher_date = date("Y-m-d");

  //   $income_or_expense = 1;
  //   $currency_id = 1;
  //   $payment_method="Cash";
  //   $check_no=$bank_name=$account_no= '';
  //   $check_due_date= null;

  //   $sql = \App\Models\MySql\UjalanDetail2::selectRaw('SUM(qty*amount) as total')->where("id_uj",$trx_trp->id_uj)->first();
  //   $amount_paid = $sql->total; // call from child
  //   $exclude_in_ARAP = 0;
  //   $login_name = $this->admin->the_user->username;
  //   $expense_or_revenue_type_id=0;
  //   $confidential=1;
  //   $PVR_source = 'gtsource'; // digenerate melalui program
  //   $PVR_source_id = $trx_trp_id; //ambil id trx
  //     // DB::select("exec USP_FI_APRequest_Update(0,'(AUTO)','TRP',1,1,1,0,)",array($ts,$param2));
  //   $VoucherID = -1;

  //   $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
  //   ->select('VoucherID','VoucherNo','AmountPaid')
  //   ->where("PVRSource",$PVR_source)
  //   ->where("PVRSourceID",$trx_trp->id)
  //   ->where("Void",0)
  //   ->first();

  //   if(!$pvr){
  //     // $myData = DB::connection('sqlsrv')->update("SET NOCOUNT ON;exec USP_FI_APRequest_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
  //     $myData = DB::connection('sqlsrv')->update("exec USP_FI_APRequest_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
  //     @VoucherDate=:voucher_date,@IncomeOrExpense=:income_or_expense,@CurrencyID=:currency_id,@AssociateName=:associate_name,
  //     @BankAccountID=:bank_account_id,@PaymentMethod=:payment_method,@CheckNo=:check_no,@CheckDueDate=:check_due_date,
  //     @BankName=:bank_name,@AmountPaid=:amount_paid,@AccountNo=:account_no,@Remarks=:remarks,@ExcludeInARAP=:exclude_in_ARAP,
  //     @LoginName=:login_name,@ExpenseOrRevenueTypeID=:expense_or_revenue_type_id,@Confidential=:confidential,
  //     @PVRSource=:PVR_source,@PVRSourceID=:PVR_source_id",[
  //       ":voucher_no"=>$voucher_no,
  //       ":voucher_type"=>$voucher_type,
  //       ":voucher_date"=>$voucher_date,
  //       ":income_or_expense"=>$income_or_expense,
  //       ":currency_id"=>$currency_id,
  //       ":associate_name"=>$associate_name,
  //       ":bank_account_id"=>$bank_account_id,
  //       ":payment_method"=>$payment_method,
  //       ":check_no"=>$check_no,
  //       ":check_due_date"=>$check_due_date,
  //       ":bank_name"=>$bank_name,
  //       ":amount_paid"=>$amount_paid,
  //       ":account_no"=>$account_no,
  //       ":remarks"=>$remarks,
  //       ":exclude_in_ARAP"=>$exclude_in_ARAP,
  //       ":login_name"=>$login_name,
  //       ":expense_or_revenue_type_id"=>$expense_or_revenue_type_id,
  //       ":confidential"=>$confidential,
  //       ":PVR_source"=>$PVR_source,
  //       ":PVR_source_id"=>$PVR_source_id,
  //     ]);
  //     if(!$myData)
  //     throw new \Exception("Data yang diperlukan tidak terpenuhi",1);
    
  //     $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
  //     ->select('VoucherID','VoucherNo','AmountPaid')
  //     ->where("PVRSource",$PVR_source)
  //     ->where("PVRSourceID",$trx_trp->id)
  //     ->where("Void",0)
  //     ->first();
  //     if(!$pvr)
  //     throw new \Exception("Akses Ke Jaringan Gagal",1);
  //   }

  //   $trx_trp->pvr_id = $pvr->VoucherID;
  //   $trx_trp->pvr_no = $pvr->VoucherNo;
  //   $trx_trp->pvr_total = $pvr->AmountPaid;
  //   $trx_trp->save();
    
  //   $d_voucher_id = $pvr->VoucherID;
  //   $d_voucher_extra_item_id = 0;
  //   $d_type = 0;

  //   $pvr_detail= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
  //   ->select('VoucherID')
  //   ->where("VoucherID",$d_voucher_id)
  //   ->get();

  //   if(count($pvr_detail)==0 || count($pvr_detail) < count($ujalan_details2)){
  //     $start = count($pvr_detail);
  //     foreach ($ujalan_details2 as $key => $v) {
  //       if($key < $start){ continue; }
  //       $d_description = $v->description;
  //       $d_amount = $v->qty * $v->amount;
  //       $d_account_id = $v->ac_account_id;
  //       $d_dept = $trx_trp->cost_center_code;
  //       $d_qty=$v->qty;
  //       $d_unit_price=$v->amount;
  //       $details = DB::connection('sqlsrv')->update("exec 
  //       USP_FI_APRequestExtraItems_Update @VoucherID=:d_voucher_id,
  //       @VoucherExtraItemID=:d_voucher_extra_item_id,
  //       @Description=:d_description,@Amount=:d_amount,
  //       @AccountID=:d_account_id,@TypeID=:d_type,
  //       @Department=:d_dept,@LoginName=:login_name,
  //       @Qty=:d_qty,@UnitPrice=:d_unit_price",[
  //         ":d_voucher_id"=>$d_voucher_id,
  //         ":d_voucher_extra_item_id"=>$d_voucher_extra_item_id,
  //         ":d_description"=>$d_description,
  //         ":d_amount"=>$d_amount,
  //         ":d_account_id"=>$d_account_id,
  //         ":d_type"=>$d_type,
  //         ":d_dept"=>$d_dept,
  //         ":login_name"=>$login_name,
  //         ":d_qty"=>$d_qty,
  //         ":d_unit_price"=>$d_unit_price
  //       ]);
  //     }
  //   }


  //   if($trx_trp->payment_method->id==2){
  //     $admin_cost_code=env("PVR_ADMIN_COST");
  
  //     $admin_cost_db = DB::connection('sqlsrv')->table('FI_BankAccounts')
  //     ->select('BankAccountID')
  //     ->where("bankaccountcode",$admin_cost_code)
  //     ->first();
  //     if(!$admin_cost_db) throw new \Exception("Bank account code tidak terdaftar ,segera infokan ke tim IT",1);

  //     $adm_cost_id = $admin_cost_db->BankAccountID;
  //     $adm_fee_exists= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
  //     ->select('VoucherID')
  //     ->where("VoucherID",$d_voucher_id)
  //     ->where("AccountID",$adm_cost_id)
  //     ->first();

  //     if(!$adm_fee_exists){
  //       $adm_cost = 2500;
  
  //       $d_description = "Biaya Admin";
  //       $d_amount = $adm_cost;
  //       $d_account_id = $adm_cost_id;
  //       $d_dept = $trx_trp->cost_center_code;
  //       $d_qty=1;
  //       $d_unit_price=$adm_cost;
  
  //       DB::connection('sqlsrv')->update("exec 
  //       USP_FI_APRequestExtraItems_Update @VoucherID=:d_voucher_id,
  //       @VoucherExtraItemID=:d_voucher_extra_item_id,
  //       @Description=:d_description,@Amount=:d_amount,
  //       @AccountID=:d_account_id,@TypeID=:d_type,
  //       @Department=:d_dept,@LoginName=:login_name,
  //       @Qty=:d_qty,@UnitPrice=:d_unit_price",[
  //         ":d_voucher_id"=>$d_voucher_id,
  //         ":d_voucher_extra_item_id"=>$d_voucher_extra_item_id,
  //         ":d_description"=>$d_description,
  //         ":d_amount"=>$d_amount,
  //         ":d_account_id"=>$d_account_id,
  //         ":d_type"=>$d_type,
  //         ":d_dept"=>$d_dept,
  //         ":login_name"=>$login_name,
  //         ":d_qty"=>$d_qty,
  //         ":d_unit_price"=>$d_unit_price
  //       ]);
  //     }
  //   }

  //   $tocheck = DB::connection('sqlsrv')->table('FI_APRequest')->where("VoucherID",$d_voucher_id)->first();

  //   if(!$tocheck)
  //   throw new \Exception("Voucher Tidak terdaftar",1);

  //   $checked2 = IsUser::where("id",$trx_trp->val1_user)->first();
  //   if(!$checked2)
  //   throw new \Exception("User Tidak terdaftar",1);

  //   if($tocheck->Checked==0){
  //     DB::connection('sqlsrv')->update("exec USP_FI_APRequest_DoCheck @VoucherID=:voucher_no,
  //     @CheckedBy=:login_name",[
  //       ":voucher_no"=>$d_voucher_id,
  //       ":login_name"=>$login_name,
  //     ]);
  //   }

  //   if($tocheck->Approved==0){
  //     DB::connection('sqlsrv')->update("exec USP_FI_APRequest_DoApprove @VoucherID=:voucher_no,
  //     @ApprovedBy=:login_name",[
  //       ":voucher_no"=>$d_voucher_id,
  //       ":login_name"=>$checked2->username,
  //     ]);
  //   }

  //   $trx_trp->pvr_had_detail = 1;
  //   $trx_trp->save();

  //   return [
  //     "message" => "PVR berhasil dibuat",
  //     "id"=>$trx_trp->id,
  //     "pvr_id" => $trx_trp->pvr_id,
  //     "pvr_no" => $trx_trp->pvr_no,
  //     "pvr_total" => $trx_trp->pvr_total,
  //     "pvr_had_detail" => $trx_trp->pvr_had_detail,
  //     "updated_at"=>$t_stamp
  //   ];
  // }

  // public function doUpdatePV(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.get_pv');
  //   $rules = [
  //     'online_status' => "required",
  //   ];

  //   $messages = [
  //     'id.exists' => 'ID tidak terdaftar',
  //   ];

  //   $validator = Validator::make($request->all(), $rules, $messages);

  //   if ($validator->fails()) {
  //     throw new ValidationException($validator);
  //   }
  //   $online_status=$request->online_status;
  //   if($online_status!="true")
  //   return response()->json([
  //     "message" => "Mode Harus Online",
  //   ], 400);

  //   $miniError="";
  //   try {
  //     $t_stamp = date("Y-m-d H:i:s");
  //     $trx_trps = TrxTrp::whereNotNull("pvr_id")->whereNull("pv_id")->where("deleted",0)->get();
  //     if(count($trx_trps)==0){
  //       throw new \Exception("Semua PVR yang ada ,PV ny sudah terisi",1);
  //     }

  //     $pvr_nos=$trx_trps->pluck('pvr_no');
  //     // $pvr_nos=['KPN/PV-R/2404/0951','KPN/PV-R/2404/1000'];
  //     $get_data_pvs = DB::connection('sqlsrv')->table('FI_ARAPINFO')
  //     ->selectRaw('fi_arap.VoucherID,fi_arap.VoucherDate,Sources,fi_arap.VoucherNo,FI_APRequest.PVRSourceID,fi_arap.AmountPaid')
  //     ->join('fi_arap',function ($join){
  //       $join->on("fi_arap.VoucherID","FI_ARAPINFO.VoucherID");        
  //     })
  //     ->join('FI_APRequest',function ($join){
  //       $join->on("FI_APRequest.VoucherNo","FI_ARAPINFO.Sources");        
  //     })
  //     ->whereIn("sources",$pvr_nos)
  //     ->get();

  //     $get_data_pvs=MyLib::objsToArray($get_data_pvs);
  //     $changes=[];
  //     foreach ($get_data_pvs as $key => $v) {
  //       $ud_trx_trp=TrxTrp::where("id", $v["PVRSourceID"])->where("pvr_no", $v["Sources"])->first();
  //       if(!$ud_trx_trp) continue;
  //       $ud_trx_trp->pv_id=$v["VoucherID"];
  //       $ud_trx_trp->pv_no=$v["VoucherNo"];
  //       $ud_trx_trp->pv_total=$v["AmountPaid"];
  //       $ud_trx_trp->pv_datetime=$v["VoucherDate"];
  //       $ud_trx_trp->updated_at=$t_stamp;
  //       $ud_trx_trp->save();
  //       array_push($changes,[
  //         "id"=>$ud_trx_trp->id,
  //         "pv_id"=>$ud_trx_trp->pv_id,
  //         "pv_no"=>$ud_trx_trp->pv_no,
  //         "pv_total"=>$ud_trx_trp->pv_total,
  //         "pv_datetime"=>$ud_trx_trp->pv_datetime,
  //         "updated_at"=>$t_stamp
  //       ]);
  //     }

  //     if(count($changes)==0)
  //     throw new \Exception("PV Tidak ada yang di Update",1);

  //     $ids = array_map(function ($x) {
  //       return $x["id"];
  //     }, $changes);
  //     MyLog::sys("trx_trp",null,"update_pv",implode(",",$ids));

  //     return response()->json([
  //       "message" => "PV Berhasil di Update",
  //       "data" => $changes,
  //     ], 200);
      
  //   } catch (\Exception $e) {
  //     if ($e->getCode() == 1) {
  //       $miniError="PV Batal Update: ".$e->getMessage();
  //     }else{
  //       $miniError="PV Batal Update. Akses Jaringan Gagal";
  //     }
  //     return response()->json([
  //       "message" => $miniError,
  //     ], 400);
  //   }
  // }

  // public function doUpdateTicket(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.get_ticket');

  //   $miniError="";
  //   try {
  //     $t_stamp = date("Y-m-d H:i:s");
  //     $trx_trps = TrxTrp::where(function ($q){
  //       $q->where(function($q1){
  //         $q1->where(function ($q2){
  //           $q2->whereNull("ticket_a_id")->orWhereNull("ticket_b_id");        
  //         })->whereIn('jenis',['TBS']);    
  //       });
  //       $q->orWhere(function($q1){
  //         $q1->whereNull("ticket_b_id")->whereIn('jenis',['TBSK']);
  //       });
  //       $q->orWhere(function($q1){
  //         $q1->whereNull("ticket_a_id")->whereIn('jenis',['CPO','PK']);        
  //       });
  //     })->where('val_ticket',0)->where("deleted",0)->get();
  //     if(count($trx_trps)==0){
  //       throw new \Exception("Semua transaksi uang jalan yang ada sudah terisi",1);
  //     }

  //     $ids=$trx_trps->pluck('id');
      
  //     $get_data_tickets = DB::connection('sqlsrv')->table('palm_tickets')->whereIn('tag1',$ids)->get();
      
  //     $get_data_tickets=MyLib::objsToArray($get_data_tickets);
  //     MyLog::logging($get_data_tickets);
  //     $changes=[];
  //     foreach ($get_data_tickets as $key => $v) {
  //       MyLog::logging($v);
  //       $ud_trx_trp=TrxTrp::where("id", $v["Tag1"])->first();
  //       if(!$ud_trx_trp) continue;

  //       $ticket_no = $v['TicketNo'];
  //       $ticket_path = explode("/",$ticket_no);

  //       if(in_array($ud_trx_trp->jenis,['TBS'])){
  //         if($ticket_path[0]==env("app_name")){
  //           if($ud_trx_trp->ticket_b_id==null){
  //             $ud_trx_trp->ticket_b_id=$v["TicketID"];
  //             $ud_trx_trp->ticket_b_no=$v["TicketNo"];
  //             $ud_trx_trp->ticket_b_bruto=(int)$v["Bruto"];
  //             $ud_trx_trp->ticket_b_tara=(int)$v["Tara"];
  //             $ud_trx_trp->ticket_b_netto=(int)$v["Bruto"]-(int)$v["Tara"];
  //             $ud_trx_trp->ticket_b_ori_bruto=(int)$v["OriginalBruto"];
  //             $ud_trx_trp->ticket_b_ori_tara=(int)$v["OriginalTara"];
  //             $ud_trx_trp->ticket_b_ori_netto=(int)$v["OriginalBruto"] - (int)$v["OriginalTara"];
  //             $ud_trx_trp->ticket_b_supir=$v["NamaSupir"];
  //             $ud_trx_trp->ticket_b_no_pol=$v["VehicleNo"];
  //             $ud_trx_trp->ticket_b_in_at=$v["DateTimeIn"];
  //             $ud_trx_trp->ticket_b_out_at=$v["DateTimeOut"];
  //           }
  //         }else{
  //           if($ud_trx_trp->ticket_a_id==null){
  //             $ud_trx_trp->ticket_a_id=$v["TicketID"];
  //             $ud_trx_trp->ticket_a_no=$v["TicketNo"];
  //             $ud_trx_trp->ticket_a_bruto=(int)$v["Bruto"];
  //             $ud_trx_trp->ticket_a_tara=(int)$v["Tara"];
  //             $ud_trx_trp->ticket_a_netto=(int)$v["Bruto"]-(int)$v["Tara"];
  //             $ud_trx_trp->ticket_a_ori_bruto=(int)$v["OriginalBruto"];
  //             $ud_trx_trp->ticket_a_ori_tara=(int)$v["OriginalTara"];
  //             $ud_trx_trp->ticket_a_ori_netto=(int)$v["OriginalBruto"] - (int)$v["OriginalTara"];
  //             $ud_trx_trp->ticket_a_supir=$v["NamaSupir"];
  //             $ud_trx_trp->ticket_a_no_pol=$v["VehicleNo"];
  //             $ud_trx_trp->ticket_a_in_at=$v["DateTimeIn"];
  //             $ud_trx_trp->ticket_a_out_at=$v["DateTimeOut"];
  //           }
  //         }
  //       }else if(in_array($ud_trx_trp->jenis,['TBSK'])){
  //         if($ticket_path[0]==env("app_name")){
  //           if($ud_trx_trp->ticket_b_id==null){
  //             $ud_trx_trp->ticket_b_id=$v["TicketID"];
  //             $ud_trx_trp->ticket_b_no=$v["TicketNo"];
  //             $ud_trx_trp->ticket_b_bruto=(int)$v["Bruto"];
  //             $ud_trx_trp->ticket_b_tara=(int)$v["Tara"];
  //             $ud_trx_trp->ticket_b_netto=(int)$v["Bruto"]-(int)$v["Tara"];
  //             $ud_trx_trp->ticket_b_ori_bruto=(int)$v["OriginalBruto"];
  //             $ud_trx_trp->ticket_b_ori_tara=(int)$v["OriginalTara"];
  //             $ud_trx_trp->ticket_b_ori_netto=(int)$v["OriginalBruto"] - (int)$v["OriginalTara"];
  //             $ud_trx_trp->ticket_b_supir=$v["NamaSupir"];
  //             $ud_trx_trp->ticket_b_no_pol=$v["VehicleNo"];
  //             $ud_trx_trp->ticket_b_in_at=$v["DateTimeIn"];
  //             $ud_trx_trp->ticket_b_out_at=$v["DateTimeOut"];
  //           }
  //         }
  //       }else{
  //         if($ticket_path[0]==env("app_name")){
  //           if($ud_trx_trp->ticket_a_id==null){
  //             $ud_trx_trp->ticket_a_id=$v["TicketID"];
  //             $ud_trx_trp->ticket_a_no=$v["TicketNo"];
  //             $ud_trx_trp->ticket_a_bruto=(int)$v["Bruto"];
  //             $ud_trx_trp->ticket_a_tara=(int)$v["Tara"];
  //             $ud_trx_trp->ticket_a_netto=(int)$v["Bruto"]-(int)$v["Tara"];
  //             $ud_trx_trp->ticket_a_ori_bruto=(int)$v["OriginalBruto"];
  //             $ud_trx_trp->ticket_a_ori_tara=(int)$v["OriginalTara"];
  //             $ud_trx_trp->ticket_a_ori_netto=(int)$v["OriginalBruto"] - (int)$v["OriginalTara"];
  //             $ud_trx_trp->ticket_a_supir=$v["NamaSupir"];
  //             $ud_trx_trp->ticket_a_no_pol=$v["VehicleNo"];
  //             $ud_trx_trp->ticket_a_in_at=$v["DateTimeIn"];
  //             $ud_trx_trp->ticket_a_out_at=$v["DateTimeOut"];
  //           }
  //         }
  //       }
  //       $ud_trx_trp->updated_at=$t_stamp;
  //       $ud_trx_trp->save();
  //       array_push($changes,[
  //         "id"                  => $ud_trx_trp->id,
  //         "ticket_a_id"         => $ud_trx_trp->ticket_a_id,
  //         "ticket_a_no"         => $ud_trx_trp->ticket_a_no,
  //         "ticket_a_bruto"      => $ud_trx_trp->ticket_a_bruto,
  //         "ticket_a_tara"       => $ud_trx_trp->ticket_a_tara,
  //         "ticket_a_netto"      => $ud_trx_trp->ticket_a_netto,
  //         // "ticket_a_ori_bruto"  => $ud_trx_trp->ticket_a_ori_bruto,
  //         // "ticket_a_ori_tara"   => $ud_trx_trp->ticket_a_ori_tara,
  //         // "ticket_a_ori_netto"  => $ud_trx_trp->ticket_a_ori_netto,
  //         "ticket_a_supir"      => $ud_trx_trp->ticket_a_supir,
  //         "ticket_a_no_pol"     => $ud_trx_trp->ticket_a_no_pol,
  //         "ticket_a_in_at"      => $ud_trx_trp->ticket_a_in_at,
  //         "ticket_a_out_at"     => $ud_trx_trp->ticket_a_out_at,

  //         "ticket_b_id"         => $ud_trx_trp->ticket_b_id,
  //         "ticket_b_no"         => $ud_trx_trp->ticket_b_no,
  //         "ticket_b_bruto"      => $ud_trx_trp->ticket_b_bruto,
  //         "ticket_b_tara"       => $ud_trx_trp->ticket_b_tara,
  //         "ticket_b_netto"      => $ud_trx_trp->ticket_b_netto,
  //         // "ticket_b_ori_bruto"  => $ud_trx_trp->ticket_b_ori_bruto,
  //         // "ticket_b_ori_tara"   => $ud_trx_trp->ticket_b_ori_tara,
  //         // "ticket_b_ori_netto"  => $ud_trx_trp->ticket_b_ori_netto,
  //         "ticket_b_supir"      => $ud_trx_trp->ticket_b_supir,
  //         "ticket_b_no_pol"     => $ud_trx_trp->ticket_b_no_pol,
  //         "ticket_b_in_at"      => $ud_trx_trp->ticket_b_in_at,
  //         "ticket_b_out_at"     => $ud_trx_trp->ticket_b_out_at,
  //         "updated_at"          => $t_stamp
  //       ]);
  //     }

  //     if(count($changes)==0)
  //     throw new \Exception("Ticket Tidak ada yang di Update",1);

  //     $ids = array_map(function ($x) {
  //       return $x["id"];
  //     }, $changes);
  //     MyLog::sys("trx_trp",null,"do_update_ticket",implode(",",$ids));

  //     return response()->json([
  //       "message" => "Ticket Berhasil di Update",
  //       "data" => $changes,
  //     ], 200);
      
  //   } catch (\Exception $e) {
  //     if ($e->getCode() == 1) {
  //       $miniError="Ticket Batal Update: ".$e->getMessage();
  //     }else{
  //       $miniError="Ticket Batal Update. Akses Jaringan Gagal";
  //     }
  //     return response()->json([
  //       "message" => $miniError,
  //       // "e"=>$e->getMessage(),
  //       // "line" => $e->getLine(),
  //     ], 400);
  //   }
  // }

  // public function doUpdateTicket(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.get_ticket');
  //   $t_stamp = date("Y-m-d H:i:s");
    
  //   $miniError="";
  //   $SYSNOTES=[];
  //   $changes=[];

  //   $dkey = "vehiclesAllowedUpdateTicket";
  //   $lists = json_decode($request->vehicles, true);
    
  //   $vehicles = Vehicle::whereIn("id",$lists)->where("deleted",0)->get();

  //   if(count($vehicles) != count($lists))
  //   throw new MyException(["message" => "No Pol Tidak Valid"], 400);

  //   $dval = MyLib::emptyStrToNull(json_encode($lists));
  //   $model_query = TempData::where('dkey',$dkey)->first();
  //   if(!$model_query){
  //     $model_query                  = new TempData();
  //     $model_query->dkey            = $dkey;
  //     $model_query->dval            = $dval;
  //     $model_query->updated_at      = $t_stamp;
  //     $model_query->updated_user    = $this->admin_id;
  //   }else{
  //     $SYSOLD                       = clone($model_query);
  //     if($model_query->dval         != $dval){
  //       $model_query->dval          = $dval;
  //       $model_query->updated_at    = $t_stamp;
  //       $model_query->updated_user  = $this->admin_id;
  //     }
  //   }
  //   $model_query->save();

  //   if(isset($SYSOLD)){
  //     $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
  //     if($SYSNOTE)
  //       MyLog::sys("temp_data_mst",$model_query->id,"update",$SYSNOTE);
  //   }else{
  //     MyLog::sys("temp_data_mst",$model_query->id,"insert");
  //   }

  //   try {
  //     $vehicles = Vehicle::whereIn("id",$lists)->where('deleted',0)->get();
  //     foreach ($vehicles as $k => $vehicle) {

  //       $empty_one = TrxTrp::where("no_pol",$vehicle->no_pol)->where("deleted",0)->where("req_deleted",0)
  //       ->where(function ($q){
  //         $q->orWhere(function ($q1){
  //           $q1->where("jenis","TBS")->where(function($q2){
  //             $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_no");
  //           });
  //         });
  //         $q->orWhere(function ($q1){
  //           $q1->where("jenis","TBSK")->whereNull("ticket_b_no");
  //         });
  //         $q->orWhere(function ($q1){
  //           $q1->whereIn("jenis",["CPO","PK"])->where(function($q2){
  //             $q2->whereNull("ticket_a_no");
  //           });
  //         });
  //       })->orderBy('tanggal','asc')->orderBy("created_at","asc")->first();
  
  //       if($empty_one){
  //         $empty_one = $empty_one->toArray();

  //         // $created_at = $empty_one['created_at'];
  //         // $created_at = date("Y-m-d H:i:s", strtotime($created_at));
  //         // $all_afters = TrxTrp::where("no_pol",$vehicle->no_pol)->where("created_at",">=",$created_at)->where('deleted',0)->where('req_deleted',0)->get();

  //         // if(in_array($empty_one['jenis'],['CPO','PK'])){
  //         //   $created_at = $empty_one['tanggal'];
  //         // }


  //         $created_at = $empty_one['tanggal'];
  //         $all_afters = TrxTrp::where("no_pol",$vehicle->no_pol)->where("tanggal",">=",$created_at)->where('deleted',0)->where('req_deleted',0)->orderBy('tanggal','asc')->orderBy('created_at','asc')->get();

  //         $get_data_tickets = DB::connection('sqlsrv')->table('palm_tickets')->where('VehicleNo',$vehicle->no_pol)->where('DateTimeIn',">",$created_at);
  //         if(in_array($empty_one['jenis'],['PK','CPO'])){
  //           $get_data_tickets = $get_data_tickets->where('TicketNo','not like','%-%');
  //         }else{
  //           $get_data_tickets = $get_data_tickets->where('Void',0);
  //         }
  //         $get_data_tickets = $get_data_tickets->orderBy("TicketID","asc")->get();

  //         $get_data_tickets = MyLib::objsToArray($get_data_tickets);

  //         foreach ($all_afters as $key => $af) {
  //           // MyLog::logging($get_data_tickets[0]);
  //           // MyLog::logging($af);
  //           if($key==0){
  //             if(count($get_data_tickets) == 0)
  //             break;

  //             $gdt = $get_data_tickets[0];
  //             if(TrxTrp::where('ticket_b_no',$gdt['TicketNo'])->orWhere('ticket_a_no',$gdt['TicketNo'])->first()){
  //               array_shift( $get_data_tickets );
  //             }
  //           }
  //           // MyLog::logging($get_data_tickets[0]);

  //           if(count($get_data_tickets) == 0)
  //           break;

  //           if($af['jenis']=="TBS"){
  //             $gdt = $get_data_tickets[0];
  //             $tn = explode("/",$gdt['TicketNo']);
  //             if($tn[0]==env("app_name")){

  //               // if(!$af['ticket_a_no']){
  //               //   break;
  //               // }

  //               if($af['transition_type']!='To'){
                  
  //                 if($af['ticket_b_no'] && $af['ticket_b_no'] !== $gdt['TicketNo']){
  //                   break;
  //                 }elseif (!$af['ticket_b_no']) {
  
  //                   $trx_trp = TrxTrp::where("id",$af->id)->first();
  //                   $SYSOLD  = clone($trx_trp);
  //                   try {
  //                     $trx_trp->ticket_b_id         = $gdt['TicketID'];
  //                     $trx_trp->ticket_b_id         = $gdt['TicketID'];
  //                     $trx_trp->ticket_b_no         = $gdt['TicketNo'];
  //                     $trx_trp->ticket_b_bruto      = (int)$gdt['Bruto'];
  //                     $trx_trp->ticket_b_tara       = (int)$gdt['Tara'];
  //                     $trx_trp->ticket_b_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
  //                     $trx_trp->ticket_b_ori_bruto  = (int)$gdt['OriginalBruto'];
  //                     $trx_trp->ticket_b_ori_tara   = (int)$gdt['OriginalTara'];
  //                     $trx_trp->ticket_b_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
  //                     $trx_trp->ticket_b_supir      = $gdt['NamaSupir'];
  //                     $trx_trp->ticket_b_no_pol     = $gdt['VehicleNo'];
  //                     $trx_trp->ticket_b_in_at      = $gdt['DateTimeIn'];
  //                     $trx_trp->ticket_b_out_at     = $gdt['DateTimeOut'];
  //                     $trx_trp->save();
  //                     array_shift( $get_data_tickets );
  //                     array_push( $SYSNOTES ,"Details:".$af->id );
  //                     array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));
  
  //                     array_push($changes,[
  //                       "id"                  => $trx_trp->id,
  //                       "ticket_a_id"         => $trx_trp->ticket_a_id,
  //                       "ticket_a_no"         => $trx_trp->ticket_a_no,
  //                       "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
  //                       "ticket_a_tara"       => $trx_trp->ticket_a_tara,
  //                       "ticket_a_netto"      => $trx_trp->ticket_a_netto,
  //                       "ticket_a_supir"      => $trx_trp->ticket_a_supir,
  //                       "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
  //                       "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
  //                       "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
  //                       "ticket_b_id"         => $trx_trp->ticket_b_id,
  //                       "ticket_b_no"         => $trx_trp->ticket_b_no,
  //                       "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
  //                       "ticket_b_tara"       => $trx_trp->ticket_b_tara,
  //                       "ticket_b_netto"      => $trx_trp->ticket_b_netto,
  //                       "ticket_b_supir"      => $trx_trp->ticket_b_supir,
  //                       "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
  //                       "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
  //                       "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
  //                       "updated_at"          => $t_stamp
  //                     ]);
  //                   } catch (\Throwable $th) {
  //                     break;
  //                   }
  
  //                 }

  //               }

  //               if(count($get_data_tickets) == 0)
  //               break;

  //               if($key==0){    
  //                 $gdt = $get_data_tickets[0];
  //                 if(TrxTrp::where('ticket_b_no',$gdt['TicketNo'])->orWhere('ticket_a_no',$gdt['TicketNo'])->first()){
  //                   array_shift( $get_data_tickets );
  //                 }
  //               }

                
  //               $gdt = $get_data_tickets[0];
  //               $tn = explode("/",$gdt['TicketNo']);

  //               if($tn[0]==env("app_name"))
  //               break;

  //               if($af['transition_type']!='To'){

  //                 if($af['ticket_a_no'] && $af['ticket_a_no'] !== $gdt['TicketNo']){
  //                   break;
  //                 }elseif($af['ticket_a_no'] && $af['ticket_a_no'] == $gdt['TicketNo']){
  //                   array_shift( $get_data_tickets );
  //                 }elseif (!$af['ticket_a_no']) {
  //                   $trx_trp = TrxTrp::where("id",$af->id)->first();
  //                   $SYSOLD  = clone($trx_trp);
  //                   try {
  //                     $trx_trp->ticket_a_id         = $gdt['TicketID'];
  //                     $trx_trp->ticket_a_id         = $gdt['TicketID'];
  //                     $trx_trp->ticket_a_no         = $gdt['TicketNo'];
  //                     $trx_trp->ticket_a_bruto      = (int)$gdt['Bruto'];
  //                     $trx_trp->ticket_a_tara       = (int)$gdt['Tara'];
  //                     $trx_trp->ticket_a_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
  //                     $trx_trp->ticket_a_ori_bruto  = (int)$gdt['OriginalBruto'];
  //                     $trx_trp->ticket_a_ori_tara   = (int)$gdt['OriginalTara'];
  //                     $trx_trp->ticket_a_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
  //                     $trx_trp->ticket_a_supir      = $gdt['NamaSupir'];
  //                     $trx_trp->ticket_a_no_pol     = $gdt['VehicleNo'];
  //                     $trx_trp->ticket_a_in_at      = $gdt['DateTimeIn'];
  //                     $trx_trp->ticket_a_out_at     = $gdt['DateTimeOut'];
  //                     $trx_trp->save();
  //                     array_shift( $get_data_tickets );
  //                     array_push( $SYSNOTES ,"Details:".$af->id );
  //                     array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));
  
  //                     array_push($changes,[
  //                       "id"                  => $trx_trp->id,
  //                       "ticket_a_id"         => $trx_trp->ticket_a_id,
  //                       "ticket_a_no"         => $trx_trp->ticket_a_no,
  //                       "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
  //                       "ticket_a_tara"       => $trx_trp->ticket_a_tara,
  //                       "ticket_a_netto"      => $trx_trp->ticket_a_netto,
  //                       "ticket_a_supir"      => $trx_trp->ticket_a_supir,
  //                       "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
  //                       "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
  //                       "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
  //                       "ticket_b_id"         => $trx_trp->ticket_b_id,
  //                       "ticket_b_no"         => $trx_trp->ticket_b_no,
  //                       "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
  //                       "ticket_b_tara"       => $trx_trp->ticket_b_tara,
  //                       "ticket_b_netto"      => $trx_trp->ticket_b_netto,
  //                       "ticket_b_supir"      => $trx_trp->ticket_b_supir,
  //                       "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
  //                       "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
  //                       "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
  //                       "updated_at"          => $t_stamp
  //                     ]);
  //                   } catch (\Throwable $th) {
  //                     break;
  //                   }
  //                 }
  //               }

  //             }else{

  //               if($af['transition_type']!='From'){
  //                 if($af['ticket_a_no'] && $af['ticket_a_no'] !== $gdt['TicketNo']){
  //                   break;
  //                 }elseif($af['ticket_a_no'] && $af['ticket_a_no'] == $gdt['TicketNo']){
  //                   array_shift( $get_data_tickets );
  //                 }elseif (!$af['ticket_a_no']) {
  //                   $trx_trp = TrxTrp::where("id",$af->id)->first();
  //                   $SYSOLD  = clone($trx_trp);
  //                   try {
  //                     $trx_trp->ticket_a_id         = $gdt['TicketID'];
  //                     $trx_trp->ticket_a_id         = $gdt['TicketID'];
  //                     $trx_trp->ticket_a_no         = $gdt['TicketNo'];
  //                     $trx_trp->ticket_a_bruto      = (int)$gdt['Bruto'];
  //                     $trx_trp->ticket_a_tara       = (int)$gdt['Tara'];
  //                     $trx_trp->ticket_a_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
  //                     $trx_trp->ticket_a_ori_bruto  = (int)$gdt['OriginalBruto'];
  //                     $trx_trp->ticket_a_ori_tara   = (int)$gdt['OriginalTara'];
  //                     $trx_trp->ticket_a_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
  //                     $trx_trp->ticket_a_supir      = $gdt['NamaSupir'];
  //                     $trx_trp->ticket_a_no_pol     = $gdt['VehicleNo'];
  //                     $trx_trp->ticket_a_in_at      = $gdt['DateTimeIn'];
  //                     $trx_trp->ticket_a_out_at     = $gdt['DateTimeOut'];
  //                     $trx_trp->save();
  //                     array_shift( $get_data_tickets );
  //                     array_push( $SYSNOTES ,"Details:".$af->id );
  //                     array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));
  
  //                     array_push($changes,[
  //                       "id"                  => $trx_trp->id,
  //                       "ticket_a_id"         => $trx_trp->ticket_a_id,
  //                       "ticket_a_no"         => $trx_trp->ticket_a_no,
  //                       "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
  //                       "ticket_a_tara"       => $trx_trp->ticket_a_tara,
  //                       "ticket_a_netto"      => $trx_trp->ticket_a_netto,
  //                       "ticket_a_supir"      => $trx_trp->ticket_a_supir,
  //                       "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
  //                       "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
  //                       "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
  //                       "ticket_b_id"         => $trx_trp->ticket_b_id,
  //                       "ticket_b_no"         => $trx_trp->ticket_b_no,
  //                       "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
  //                       "ticket_b_tara"       => $trx_trp->ticket_b_tara,
  //                       "ticket_b_netto"      => $trx_trp->ticket_b_netto,
  //                       "ticket_b_supir"      => $trx_trp->ticket_b_supir,
  //                       "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
  //                       "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
  //                       "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
  //                       "updated_at"          => $t_stamp
  //                     ]);
  //                   } catch (\Throwable $th) {
  //                     break;
  //                   }
  //                 }
  //               }

  //               if(count($get_data_tickets) == 0)
  //               break;

  //               if($key==0){    
  //                 $gdt = $get_data_tickets[0];
  //                 if(TrxTrp::where('ticket_b_no',$gdt['TicketNo'])->orWhere('ticket_a_no',$gdt['TicketNo'])->first()){
  //                   array_shift( $get_data_tickets );
  //                 }
  //               }
                
  //               $gdt = $get_data_tickets[0];
  //               $tn = explode("/",$gdt['TicketNo']);

  //               if($tn[0]!=env("app_name"))
  //               break;

  //               if($af['transition_type']!='To'){
  //                 if($af['ticket_b_no'] && $af['ticket_b_no'] !== $gdt['TicketNo']){
  //                   break;
  //                 }elseif($af['ticket_b_no'] && $af['ticket_b_no'] == $gdt['TicketNo']){
  //                   array_shift( $get_data_tickets );
  //                 }elseif (!$af['ticket_b_no']) {
  //                   $trx_trp = TrxTrp::where("id",$af->id)->first();
  //                   $SYSOLD  = clone($trx_trp);
  //                   try {
  //                     $trx_trp->ticket_b_id         = $gdt['TicketID'];
  //                     $trx_trp->ticket_b_id         = $gdt['TicketID'];
  //                     $trx_trp->ticket_b_no         = $gdt['TicketNo'];
  //                     $trx_trp->ticket_b_bruto      = (int)$gdt['Bruto'];
  //                     $trx_trp->ticket_b_tara       = (int)$gdt['Tara'];
  //                     $trx_trp->ticket_b_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
  //                     $trx_trp->ticket_b_ori_bruto  = (int)$gdt['OriginalBruto'];
  //                     $trx_trp->ticket_b_ori_tara   = (int)$gdt['OriginalTara'];
  //                     $trx_trp->ticket_b_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
  //                     $trx_trp->ticket_b_supir      = $gdt['NamaSupir'];
  //                     $trx_trp->ticket_b_no_pol     = $gdt['VehicleNo'];
  //                     $trx_trp->ticket_b_in_at      = $gdt['DateTimeIn'];
  //                     $trx_trp->ticket_b_out_at     = $gdt['DateTimeOut'];
  //                     $trx_trp->save();
  //                     array_shift( $get_data_tickets );
  //                     array_push( $SYSNOTES ,"Details:".$af->id );
  //                     array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));
  
  //                     array_push($changes,[
  //                       "id"                  => $trx_trp->id,
  //                       "ticket_a_id"         => $trx_trp->ticket_a_id,
  //                       "ticket_a_no"         => $trx_trp->ticket_a_no,
  //                       "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
  //                       "ticket_a_tara"       => $trx_trp->ticket_a_tara,
  //                       "ticket_a_netto"      => $trx_trp->ticket_a_netto,
  //                       "ticket_a_supir"      => $trx_trp->ticket_a_supir,
  //                       "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
  //                       "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
  //                       "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
  //                       "ticket_b_id"         => $trx_trp->ticket_b_id,
  //                       "ticket_b_no"         => $trx_trp->ticket_b_no,
  //                       "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
  //                       "ticket_b_tara"       => $trx_trp->ticket_b_tara,
  //                       "ticket_b_netto"      => $trx_trp->ticket_b_netto,
  //                       "ticket_b_supir"      => $trx_trp->ticket_b_supir,
  //                       "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
  //                       "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
  //                       "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
  //                       "updated_at"          => $t_stamp
  //                     ]);
  //                   } catch (\Throwable $th) {
  //                     break;
  //                   }
  //                 }
  //               }

  //             }

  //           }elseif($af['jenis']=="TBSK") {
  //             $gdt = $get_data_tickets[0];
  //             $tn = explode("/",$gdt['TicketNo']);
  //             if($tn[0]!==env("app_name")){
  //               break;
  //             }
  //             if($af['transition_type']!='To'){                
  //               if($af['ticket_b_no'] && $af['ticket_b_no'] !== $gdt['TicketNo']){
  //                 break;
  //               }elseif($af['ticket_b_no'] && $af['ticket_b_no'] == $gdt['TicketNo']){
  //                 array_shift( $get_data_tickets );
  //               }elseif (!$af['ticket_b_no']) {
  //                   $trx_trp = TrxTrp::where("id",$af->id)->first();
  //                   $SYSOLD  = clone($trx_trp);
  //                   try {
  //                     $trx_trp->ticket_b_id         = $gdt['TicketID'];
  //                     $trx_trp->ticket_b_id         = $gdt['TicketID'];
  //                     $trx_trp->ticket_b_no         = $gdt['TicketNo'];
  //                     $trx_trp->ticket_b_bruto      = (int)$gdt['Bruto'];
  //                     $trx_trp->ticket_b_tara       = (int)$gdt['Tara'];
  //                     $trx_trp->ticket_b_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
  //                     $trx_trp->ticket_b_ori_bruto  = (int)$gdt['OriginalBruto'];
  //                     $trx_trp->ticket_b_ori_tara   = (int)$gdt['OriginalTara'];
  //                     $trx_trp->ticket_b_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
  //                     $trx_trp->ticket_b_supir      = $gdt['NamaSupir'];
  //                     $trx_trp->ticket_b_no_pol     = $gdt['VehicleNo'];
  //                     $trx_trp->ticket_b_in_at      = $gdt['DateTimeIn'];
  //                     $trx_trp->ticket_b_out_at     = $gdt['DateTimeOut'];
  //                     $trx_trp->save();
  //                     array_shift( $get_data_tickets );
  //                     array_push( $SYSNOTES ,"Details:".$af->id );
  //                     array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));
  
  //                     array_push($changes,[
  //                       "id"                  => $trx_trp->id,
  //                       "ticket_a_id"         => $trx_trp->ticket_a_id,
  //                       "ticket_a_no"         => $trx_trp->ticket_a_no,
  //                       "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
  //                       "ticket_a_tara"       => $trx_trp->ticket_a_tara,
  //                       "ticket_a_netto"      => $trx_trp->ticket_a_netto,
  //                       "ticket_a_supir"      => $trx_trp->ticket_a_supir,
  //                       "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
  //                       "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
  //                       "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
  //                       "ticket_b_id"         => $trx_trp->ticket_b_id,
  //                       "ticket_b_no"         => $trx_trp->ticket_b_no,
  //                       "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
  //                       "ticket_b_tara"       => $trx_trp->ticket_b_tara,
  //                       "ticket_b_netto"      => $trx_trp->ticket_b_netto,
  //                       "ticket_b_supir"      => $trx_trp->ticket_b_supir,
  //                       "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
  //                       "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
  //                       "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
  //                       "updated_at"          => $t_stamp
  //                     ]);
  //                   } catch (\Throwable $th) {
  //                     break;
  //                   }
  //               }
  //             }
  //           }elseif($af['jenis']=="CPO" || $af['jenis']=="PK") {
  //             $gdt = $get_data_tickets[0];
  //             $tn = explode("/",$gdt['TicketNo']);
  //             if($tn[0]!==env("app_name")){
  //               break;
  //             }
  //             if($af['ticket_a_no'] && $af['ticket_a_no'] !== $gdt['TicketNo']){
  //               break;
  //             }elseif($af['ticket_a_no'] && $af['ticket_a_no'] == $gdt['TicketNo']){
  //               array_shift( $get_data_tickets );
  //             }elseif (!$af['ticket_a_no']) {
  //               $trx_trp = TrxTrp::where("id",$af->id)->first();
  //                 $SYSOLD  = clone($trx_trp);
  //                 try {
  //                   $trx_trp->ticket_a_id         = $gdt['TicketID'];
  //                   $trx_trp->ticket_a_id         = $gdt['TicketID'];
  //                   $trx_trp->ticket_a_no         = $gdt['TicketNo'];
  //                   $trx_trp->ticket_a_bruto      = (int)$gdt['Bruto'];
  //                   $trx_trp->ticket_a_tara       = (int)$gdt['Tara'];
  //                   $trx_trp->ticket_a_netto      = (int)$gdt['Bruto'] - (int)$gdt['Tara'];
  //                   $trx_trp->ticket_a_ori_bruto  = (int)$gdt['OriginalBruto'];
  //                   $trx_trp->ticket_a_ori_tara   = (int)$gdt['OriginalTara'];
  //                   $trx_trp->ticket_a_ori_netto  = (int)$gdt['OriginalBruto'] - (int)$gdt['OriginalTara'];
  //                   $trx_trp->ticket_a_supir      = $gdt['NamaSupir'];
  //                   $trx_trp->ticket_a_no_pol     = $gdt['VehicleNo'];
  //                   $trx_trp->ticket_a_in_at      = $gdt['DateTimeIn'];
  //                   $trx_trp->ticket_a_out_at     = $gdt['DateTimeOut'];
  //                   $trx_trp->save();
  //                   array_shift( $get_data_tickets );
  //                   array_push( $SYSNOTES ,"Details:".$af->id );
  //                   array_push( $SYSNOTES ,MyLib::compareChange($SYSOLD,$trx_trp));

  //                   array_push($changes,[
  //                     "id"                  => $trx_trp->id,
  //                     "ticket_a_id"         => $trx_trp->ticket_a_id,
  //                     "ticket_a_no"         => $trx_trp->ticket_a_no,
  //                     "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
  //                     "ticket_a_tara"       => $trx_trp->ticket_a_tara,
  //                     "ticket_a_netto"      => $trx_trp->ticket_a_netto,
  //                     "ticket_a_supir"      => $trx_trp->ticket_a_supir,
  //                     "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
  //                     "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
  //                     "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,

  //                     "ticket_b_id"         => $trx_trp->ticket_b_id,
  //                     "ticket_b_no"         => $trx_trp->ticket_b_no,
  //                     "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
  //                     "ticket_b_tara"       => $trx_trp->ticket_b_tara,
  //                     "ticket_b_netto"      => $trx_trp->ticket_b_netto,
  //                     "ticket_b_supir"      => $trx_trp->ticket_b_supir,
  //                     "ticket_b_no_pol"     => $trx_trp->ticket_b_no_pol,
  //                     "ticket_b_in_at"      => $trx_trp->ticket_b_in_at,
  //                     "ticket_b_out_at"     => $trx_trp->ticket_b_out_at,
  //                     "updated_at"          => $t_stamp
  //                   ]);
  //                 } catch (\Throwable $th) {
  //                   break;
  //                 }
  //             }
  //           }        
  //         }
  //       }
  //     }


  //     MyLog::sys("trx_trp",null,"update ticket",implode("\n",$SYSNOTES));      

  //     if(count($changes)==0)
  //     throw new \Exception("Ticket Tidak ada yang di Update",1);

  //     $ids = array_map(function ($x) {
  //       return $x["id"];
  //     }, $changes);
  //     MyLog::sys("trx_trp",null,"do_update_ticket",implode(",",$ids));

  //     return response()->json([
  //       "message" => "Ticket Berhasil di Update",
  //       "data" => $changes,
  //     ], 200);
      
  //   } catch (\Exception $e) {
  //     if ($e->getCode() == 1) {
  //       $miniError="Ticket Batal Update: ".$e->getMessage();
  //     }else{
  //       $miniError="Ticket Batal Update. Akses Jaringan Gagal";
  //     }
  //     return response()->json([
  //       "message" => $miniError,
  //       "e"=>$e->getMessage(),
  //       "line" => $e->getLine(),
  //     ], 400);
  //   }
  // }


  public function doUpdateTicket(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.get_ticket');
    $t_stamp = date("Y-m-d H:i:s");
    
    $miniError="";
    $SYSNOTES=[];
    $changes=[];

    $dkey = "vehiclesAllowedUpdateTicket";
    $lists = json_decode($request->vehicles, true);
    
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

          $get_data_tickets = DB::connection('sqlsrv')->table('palm_tickets')->where('VehicleNo',$vehicle->no_pol)->where('DateTimeIn',">",$created_at);
          if(in_array($empty_one['jenis'],['PK','CPO'])){
            $get_data_tickets = $get_data_tickets->where('TicketNo','not like','%-%');
          }else{
            $get_data_tickets = $get_data_tickets->where('Void',0);
          }
          $get_data_tickets = $get_data_tickets->orderBy("TicketID","asc")->get();

          $get_data_tickets = MyLib::objsToArray($get_data_tickets);

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
                        "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
                        "ticket_a_tara"       => $trx_trp->ticket_a_tara,
                        "ticket_a_netto"      => $trx_trp->ticket_a_netto,
                        "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                        "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                        "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                        "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
                        "ticket_b_id"         => $trx_trp->ticket_b_id,
                        "ticket_b_no"         => $trx_trp->ticket_b_no,
                        "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
                        "ticket_b_tara"       => $trx_trp->ticket_b_tara,
                        "ticket_b_netto"      => $trx_trp->ticket_b_netto,
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

                if($af['transition_type']!='To'){

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
                        "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
                        "ticket_a_tara"       => $trx_trp->ticket_a_tara,
                        "ticket_a_netto"      => $trx_trp->ticket_a_netto,
                        "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                        "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                        "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                        "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
                        "ticket_b_id"         => $trx_trp->ticket_b_id,
                        "ticket_b_no"         => $trx_trp->ticket_b_no,
                        "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
                        "ticket_b_tara"       => $trx_trp->ticket_b_tara,
                        "ticket_b_netto"      => $trx_trp->ticket_b_netto,
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

              }else{

                if($af['transition_type']!='From'){
                  if($af['ticket_a_no'] && $af['ticket_a_no'] !== $gdt['TicketNo']){
                    break;
                  }elseif($af['ticket_a_no'] && $af['ticket_a_no'] == $gdt['TicketNo']){
                    array_shift( $get_data_tickets );
                  }elseif (!$af['ticket_a_no']) {
                    $trx_trp = TrxTrp::where("id",$af->id)->first();
                    $SYSOLD  = clone($trx_trp);
                    try {
                      $trx_trp->ticket_a_id         = $gdt['TicketID'];
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
                        "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
                        "ticket_a_tara"       => $trx_trp->ticket_a_tara,
                        "ticket_a_netto"      => $trx_trp->ticket_a_netto,
                        "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                        "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                        "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                        "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
                        "ticket_b_id"         => $trx_trp->ticket_b_id,
                        "ticket_b_no"         => $trx_trp->ticket_b_no,
                        "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
                        "ticket_b_tara"       => $trx_trp->ticket_b_tara,
                        "ticket_b_netto"      => $trx_trp->ticket_b_netto,
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
                        "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
                        "ticket_a_tara"       => $trx_trp->ticket_a_tara,
                        "ticket_a_netto"      => $trx_trp->ticket_a_netto,
                        "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                        "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                        "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                        "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
                        "ticket_b_id"         => $trx_trp->ticket_b_id,
                        "ticket_b_no"         => $trx_trp->ticket_b_no,
                        "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
                        "ticket_b_tara"       => $trx_trp->ticket_b_tara,
                        "ticket_b_netto"      => $trx_trp->ticket_b_netto,
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
                        "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
                        "ticket_a_tara"       => $trx_trp->ticket_a_tara,
                        "ticket_a_netto"      => $trx_trp->ticket_a_netto,
                        "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                        "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                        "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                        "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,
  
                        "ticket_b_id"         => $trx_trp->ticket_b_id,
                        "ticket_b_no"         => $trx_trp->ticket_b_no,
                        "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
                        "ticket_b_tara"       => $trx_trp->ticket_b_tara,
                        "ticket_b_netto"      => $trx_trp->ticket_b_netto,
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
                      "ticket_a_bruto"      => $trx_trp->ticket_a_bruto,
                      "ticket_a_tara"       => $trx_trp->ticket_a_tara,
                      "ticket_a_netto"      => $trx_trp->ticket_a_netto,
                      "ticket_a_supir"      => $trx_trp->ticket_a_supir,
                      "ticket_a_no_pol"     => $trx_trp->ticket_a_no_pol,
                      "ticket_a_in_at"      => $trx_trp->ticket_a_in_at,
                      "ticket_a_out_at"     => $trx_trp->ticket_a_out_at,

                      "ticket_b_id"         => $trx_trp->ticket_b_id,
                      "ticket_b_no"         => $trx_trp->ticket_b_no,
                      "ticket_b_bruto"      => $trx_trp->ticket_b_bruto,
                      "ticket_b_tara"       => $trx_trp->ticket_b_tara,
                      "ticket_b_netto"      => $trx_trp->ticket_b_netto,
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
    $table1 = DB::table('trx_trp')->selectRaw("concat('A') as jenis, ticket_a_no as ticket_no")->whereNotNull("ticket_a_no");

    $table2 = DB::table('trx_trp')->selectRaw("concat('B') as jenis, ticket_b_no as ticket_no")->whereNotNull("ticket_b_no");

    $final = $table1->unionAll($table2);

    $querySql = $final->toSql();
     
    $model_query = DB::table(DB::raw("($querySql) as a"))->mergeBindings($final);
     
    //Now you can do anything u like:
     
    $model_query = $model_query->selectRaw("jenis, ticket_no,count(*) as lebih")->groupBy('ticket_no','jenis')->having('lebih',">",1)->offset($offset)->limit($limit)->get(); 
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

  // public function delete_absen(Request $request)
  // {
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.absen.remove');

  //   $ids = json_decode($request->ids, true);


  //   $rules = [
      
  //     // 'details'                          => 'required|array',
  //     'details.*.id'               => 'required|exists:\App\Models\MySql\TrxAbsen,id',
  //   ];

  //   $messages = [
  //     'details.required' => 'Item harus di isi',
  //     'details.array' => 'Format Pengambilan Barang Salah',
  //   ];

  //   // // Replace :index with the actual index value in the custom error messages
  //   foreach ($ids as $k => $v) {
  //     $messages["details.{$k}.id_uj.required"]          = "Baris #" . ($k + 1) . ". ID tidak boleh kosong.";
  //     $messages["details.{$k}.id_uj.exists"]            = "Baris #" . ($k + 1) . ". ID harus diisi";
  //   }

  //   $validator = Validator::make(['details' => $ids], $rules, $messages);

  //   // Check if validation fails
  //   if ($validator->fails()) {
  //     foreach ($validator->messages()->all() as $k => $v) {
  //       throw new MyException(["message" => $v], 400);
  //     }
  //   }


  //   DB::beginTransaction();

  //   try {
  //     $all_id = array_map(function ($x){
  //       return $x['id'];
  //     },$ids);

  //     $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
  //     // if($model_query->requested_by != $this->admin_id){
  //     //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
  //     // }
  //     if (!$model_query) {
  //       throw new \Exception("Data tidak terdaftar", 1);
  //     }
      
  //     if($model_query->val4==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
  //     throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

  //     $model_query = TrxAbsen::whereIn("id",$all_id)->where("status","B")->lockForUpdate()->delete();

  //     MyLog::sys("trx_absen",null,"delete",implode(",",$all_id));

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses Hapus data berhasil",
  //     ], 200);
  //   } catch (\Exception  $e) {
  //     DB::rollback();
  //     if ($e->getCode() == "23000")
  //       return response()->json([
  //         "message" => "Data tidak dapat dihapus, data terkait dengan data yang lain nya",
  //       ], 400);

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
  //       "message" => "Proses hapus data gagal",
  //     ], 400);
  //     //throw $th;
  //   }
  // }


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
