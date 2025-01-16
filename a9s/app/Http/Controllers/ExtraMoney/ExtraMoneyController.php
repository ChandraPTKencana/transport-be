<?php

namespace App\Http\Controllers\ExtraMoney;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Exceptions\MyException;
use Exception;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\ExtraMoney;
use App\Models\MySql\IsUser;
use App\Models\MySql\ExtraMoneyTrx;
use App\Models\MySql\ExtraMoneyDtl;

use App\Http\Requests\MySql\ExtraMoneyRequest;

use App\Http\Resources\MySql\ExtraMoneyResource;
use App\Http\Resources\MySql\IsUserResource;

class ExtraMoneyController extends Controller
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

  public function loadLocal()
  {
    MyAdmin::checkMultiScope($this->permissions, ['extra_money.create','extra_money.modify']);

    $list_xto = \App\Models\MySql\Ujalan::select('xto')->where("deleted",0)->where('val',1)->where('val1',1)->groupBy('xto')->get()->pluck('xto');    
    return response()->json([
      "list_xto" => $list_xto,
    ], 200);
  }

  public function loadSqlSrv(Request $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['extra_money.create','extra_money.modify']);

    $list_accs=[];
    $connectionDB = DB::connection('sqlsrv');
    try {
      $list_accs = DB::connection('sqlsrv')->table("AC_Accounts")
      ->select('AccountID','AccountCode','AccountName')
      ->get();
      $list_accs= MyLib::objsToArray($list_accs); 
    } catch (\Exception $e) {
      throw new MyException(['message'=>"Pastikan Internet Aktif untuk melanjutkan"]);
    }
    
    return response()->json([
      "list_accs" => $list_accs,
    ], 200);
  }

  public function index(Request $request, $download = false)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money.views');
 
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
    $model_query = new ExtraMoney();
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

      $list_to_like = ["id","transition_target","transition_type",
      "xto","jenis","ac_account_id","ac_account_name","ac_account_code","description"];

      $list_to_like_user = [
        ["val_name","val_user"],
        ["val1_name","val1_user"],
        ["val2_name","val2_user"],
        ["req_deleted_name","req_deleted_user"],
        ["deleted_name","deleted_user"],
      ];

      

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists,$list_to_like,$list_to_like_user){
          foreach ($list_to_like as $key => $v) {
            if (isset($like_lists[$v])) {
              $q->orWhere($v, "like", $like_lists[$v]);
            }
          }

          foreach ($list_to_like_user as $key => $v) {
            if (isset($like_lists[$v[0]])) {
              $q->orWhereIn($v[1], function($q2)use($like_lists,$v) {
                $q2->from('is_users')
                ->select('id')->where("username",'like',$like_lists[$v[0]]);          
              });
            }
          }

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
      $model_query = $model_query->orderBy('updated_at', 'DESC')->orderBy('id','DESC');
    }
    
    $filter_status = $request->filter_status;
    
    if($filter_status=="available"){
      $model_query = $model_query->where("deleted",0)->where("val1",1)->where("val2",1);
    }

    if($filter_status=="unapprove"){
      $model_query = $model_query->where("deleted",0)->where(function($q){
       $q->where("val1",0)->orwhere("val2",0); 
      });
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    if($filter_status=="req_deleted"){
      $model_query = $model_query->where("deleted",0);
    }

    $model_query = $model_query->with(['val1_by','val2_by','deleted_by','req_deleted_by'])
    ->get();

    return response()->json([
      "data" => ExtraMoneyResource::collection($model_query),
    ], 200);
  }

  public function show(ExtraMoneyRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money.view');

    $model_query = ExtraMoney::with(['val1_by','val2_by','deleted_by','req_deleted_by'])->find($request->id);
    return response()->json([
      "data" => new ExtraMoneyResource($model_query),
    ], 200);
  }

  public function store(ExtraMoneyRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money.create');

    $t_stamp = date("Y-m-d H:i:s");

    $rollback_id = -1;
    DB::beginTransaction();
    try {

      $acc = DB::connection('sqlsrv')->table("AC_Accounts")
      ->select('AccountID','AccountCode','AccountName')
      ->where('AccountID',$request->ac_account_id)
      ->first();
      if(!$acc)
      throw new \Exception(json_encode(["ac_account_id"=>["Cost Center ID Tidak Ditemukan"]]), 422);
 
      $model_query                      = new ExtraMoney();      
      $model_query->xto                 = $request->xto;
      $model_query->jenis               = $request->jenis;
      $model_query->transition_target   = $request->transition_target;
      $model_query->transition_type     = $request->transition_type;
         
      $model_query->ac_account_id       = $acc->AccountID;
      $model_query->ac_account_code     = $acc->AccountCode;
      $model_query->ac_account_name     = $acc->AccountName;
      $model_query->nominal              = $request->nominal;
      $model_query->qty                 = $request->qty;
      $model_query->description         = $request->description;
  
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

      $model_query->save();

      $rollback_id = $model_query->id - 1;

      MyLog::sys("extra_money",$model_query->id,"insert");

      DB::commit();
      return response()->json([
        "message"     => "Proses tambah data berhasil",
        "id"          => $model_query->id,
        "created_at"  => $t_stamp,
        "updated_at"  => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE extra_money AUTO_INCREMENT = $rollback_id");

      return response()->json([
        "message" => $e->getMessage(),
        "code" => $e->getCode(),
        "line" => $e->getLine(),
      ], 400);
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

  public function update(ExtraMoneyRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money.modify');
    
    $t_stamp        = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query = ExtraMoney::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);

      if($model_query->val1==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);
      
      $acc = DB::connection('sqlsrv')->table("AC_Accounts")
      ->select('AccountID','AccountCode','AccountName')
      ->where('AccountID',$request->ac_account_id)
      ->first();
      if(!$acc)
      throw new \Exception(json_encode(["ac_account_id"=>["Cost Center ID Tidak Ditemukan"]]), 422);

      $model_query->xto                 = $request->xto;
      $model_query->jenis               = $request->jenis;
      $model_query->transition_target   = $request->transition_target;
      $model_query->transition_type     = $request->transition_type;
         
      $model_query->ac_account_id       = $acc->AccountID;
      $model_query->ac_account_code     = $acc->AccountCode;
      $model_query->ac_account_name     = $acc->AccountName;
      $model_query->nominal              = $request->nominal;
      $model_query->qty                 = $request->qty;
      $model_query->description         = $request->description;

      $model_query->updated_at          = $t_stamp;
      $model_query->updated_user        = $this->admin_id;
      
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("extra_money",$request->id,"update",$SYSNOTE);

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
    MyAdmin::checkScope($this->permissions, 'extra_money.remove');

    DB::beginTransaction();

    try {
      $model_query = ExtraMoney::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD                     = clone($model_query);

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }


      if($model_query->req_deleted==1  || $model_query->deleted==1) 
      throw new \Exception("Data sudah tidak dapat di Hapus",1);
      
      // if($model_query->pvr_id!="" || $model_query->pvr_id!=null)
      // throw new \Exception("Harap Lakukan Permintaan Penghapusan Terlebih Dahulu",1);

      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("extra_money",$request->id,"delete",$SYSNOTE);

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

  // public function reqDelete(Request $request)
  // {
  //   MyAdmin::checkScope($this->permissions, 'extra_money.request_remove');

  //   DB::beginTransaction();

  //   try {
  //     $model_query = ExtraMoney::where("id",$request->id)->lockForUpdate()->first();
  //     // if($model_query->requested_by != $this->admin_id){
  //     //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
  //     // }
  //     if (!$model_query) {
  //       throw new \Exception("Data tidak terdaftar", 1);
  //     }
      
  //     if($model_query->val2)
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

  //     MyLog::sys("extra_money",$request->id,"delete","Request Delete (Void)");


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

  // public function approveReqDelete(Request $request)
  // {
  //   MyAdmin::checkScope($this->permissions, 'extra_money.approve_request_remove');

  //   $time = microtime(true);
  //   $mSecs = sprintf('%03d', ($time - floor($time)) * 1000);
  //   $t_stamp_ms = date("Y-m-d H:i:s").".".$mSecs;

  //   DB::beginTransaction();

  //   try {
  //     $model_query = ExtraMoney::where("id",$request->id)->lockForUpdate()->first();
  //     // if($model_query->requested_by != $this->admin_id){
  //     //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
  //     // }
  //     if (!$model_query) {
  //       throw new \Exception("Data tidak terdaftar", 1);
  //     }
      
  //     if($model_query->val2)
  //     throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

  //     if($model_query->deleted==1 )
  //     throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

  //     if($model_query->pvr_id=="" || $model_query->pvr_id==null)
  //     throw new \Exception("Harap Lakukan Penghapusan",1);

  //     $deleted_reason = $model_query->req_deleted_reason;
  //     if(!$deleted_reason)
  //     throw new \Exception("Sertakan Alasan Penghapusan",1);

  //     $model_query->deleted = 1;
  //     $model_query->deleted_user = $this->admin_id;
  //     $model_query->deleted_at = date("Y-m-d H:i:s");
  //     $model_query->deleted_reason = $deleted_reason;
      
  //     $model_query->save();

  //     MyLog::sys("extra_money",$request->id,"delete","Approve Request Delete (Void)");

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses Hapus data berhasil",
  //       "deleted"=>$model_query->deleted,
  //       "deleted_user"=>$model_query->deleted_user,
  //       "deleted_by"=>$model_query->deleted_user ? new IsUserResource(IsUser::find($model_query->deleted_user)) : null,
  //       "deleted_at"=>$model_query->deleted_at,
  //       "deleted_reason"=>$model_query->deleted_reason,
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

  // public function previewFile(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'extra_money.preview_file');

  //   set_time_limit(0);

  //   $extra_money = ExtraMoney::find($request->id);

  //   if($extra_money->val==0)
  //   return response()->json([
  //     "message" => "Harap Di Validasi Terlebih Dahulu",
  //   ], 400);
  //   $extra_money_details = \App\Models\MySql\ExtraMoneyDtl::where("extra_money_id",$extra_money->id)->orderBy("ordinal","asc")->get();

  //   $standby_mst = \App\Models\MySql\ExtraMoneyMst::where("id",$extra_money->standby_mst_id)->first();
  //   $standby_details = \App\Models\MySql\ExtraMoneyDtl::where("standby_mst_id",$extra_money->standby_mst_id)->orderBy("ordinal","asc")->get();

  //   $sendData = [
  //     "created_at"=>$extra_money->created_at,
  //     "id"=>$extra_money->id,
  //     "standby_mst_id"=>$extra_money->standby_mst_id,
  //     // "standby_mst_name"=>$extra_money->standby_mst_name,
  //     // "standby_mst_type"=>$extra_money->standby_mst_type,
  //     // "standby_mst_amount"=>$extra_money->standby_mst_amount,
  //     "no_pol"=>$extra_money->no_pol,
  //     "supir"=>$extra_money->supir,
  //     "kernet"=>$extra_money->kernet,
  //     "xto"=>$extra_money->xto,
  //     "asal"=>env("app_name"),
  //     "standby_details"=>$standby_details,
  //     "standby_mst"=>$standby_mst,
  //     "extra_money_details"=>$extra_money_details,
  //     "is_transition"=>$extra_money->transition_type,
  //     "user_1"=>$this->admin->the_user->username,
  //   ];   
    
    
  //   $html = view("html.extra_money",$sendData);
    
  //   $result = [
  //     "html"=>$html->render()
  //   ];
  //   return $result;
  // }

  // public function previewFiles(Request $request){

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
  //   $pdf = PDF::loadView('pdf.extra_money', ["data"=>$newDetails,"shows"=>$shows,"info"=>[
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

  //   set_time_limit(0);
  //   $callGet = $this->index($request, true);
  //   if ($callGet->getStatusCode() != 200) return $callGet;
  //   $ori = json_decode(json_encode($callGet), true)["original"];
  //   $data = $ori["data"];
    
  //   $shows=["id","tanggal","no_pol","jenis","xto","amount"];

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
  //   $filename=$date->format("YmdHis").'-extra_money'."[".$request["date_from"]."-".$request["date_to"]."]";

  //   $mime=MyLib::mime("xlsx");
  //   // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
  //   $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails,"shows"=>$shows],'excel.extra_money'), $mime["exportType"]));


  //   $result = [
  //     "contentType" => $mime["contentType"],
  //     "data" => $bs64,
  //     "dataBase64" => $mime["dataBase64"] . $bs64,
  //     "filename" => $filename . "." . $mime["ext"],
  //   ];
  //   return $result;
  // }

  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['extra_money.val','extra_money.val1','extra_money.val2']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\ExtraMoney,id",
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
      $model_query = ExtraMoney::find($request->id);
      $SYSOLD                     = clone($model_query);

      if($model_query->val && $model_query->val1 && $model_query->val2){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money.val',true) && !$model_query->val){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
      }
  
      if(MyAdmin::checkScope($this->permissions, 'extra_money.val1',true) && !$model_query->val1){
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money.val2',true) && !$model_query->val2){
        $model_query->val2 = 1;
        $model_query->val2_user = $this->admin_id;
        $model_query->val2_at = $t_stamp;
      }

      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("extra_money",$request->id,"approve",$SYSNOTE);

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
