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
