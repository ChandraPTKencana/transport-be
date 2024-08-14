<?php

namespace App\Http\Controllers\Potongan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Helpers\MyLib;

use App\Models\MySql\PotonganMst;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\PotonganMstRequest;

use App\Http\Resources\MySql\PotonganMstResource;
use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\Employee;
use App\Models\MySql\PotonganTrx;

class PotonganMstController extends Controller
{
  private $admin;
  private $admin_id;
  private $role;
  private $permissions;
  private $syslog_db = 'potongan_mst';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->role = $this->admin->the_user->hak_akses;
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();
  }

  public function loadLocal()
  {
    MyAdmin::checkMultiScope($this->permissions, ['potongan_mst.create','potongan_mst.modify']);

    $list_vehicle = \App\Models\MySql\Vehicle::where("deleted",0)->get();
    $list_employee = \App\Models\MySql\Employee::available()->verified()->whereIn("role",['Supir','Kernet'])->get();
    
    return response()->json([
      "list_vehicle" => $list_vehicle,
      "list_employee" => $list_employee,
    ], 200);
  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_mst.views');

    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 250; // Limit +> Much Data
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
    $model_query = PotonganMst::offset($offset)->limit($limit);

    $first_row=[];
    if($request->first_row){
      $first_row 	= json_decode($request->first_row, true);
    }
    //======================================================================================================
    // Model Sorting | Example $request->sort = "username:desc,role:desc";
    //======================================================================================================

    if ($request->sort) {
      $sort_lists = [];

      $sorts = explode(",", $request->sort);
      foreach ($sorts as $key => $sort) {
        $side = explode(":", $sort);
        $side[1] = isset($side[1]) ? $side[1] : 'ASC';
        $sort_symbol = $side[1] == "desc" ? "<=" : ">=";
        $sort_lists[$side[0]] = $side[1];
      }

      if (isset($sort_lists["name"])) {
        $model_query = $model_query->orderBy("name", $sort_lists["name"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("name",$sort_symbol,$first_row["name"]);
        }
      }

      if (isset($sort_lists["role"])) {
        $model_query = $model_query->orderBy("role", $sort_lists["role"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("role",$sort_symbol,$first_row["role"]);
        }
      }

      

      // if (isset($sort_lists["role"])) {
      //   $model_query = $model_query->orderBy(function($q){
      //     $q->from("internal.roles")
      //     ->select("name")
      //     ->whereColumn("id","auths.role_id");
      //   },$sort_lists["role"]);
      // }

      // if (isset($sort_lists["auth"])) {
      //   $model_query = $model_query->orderBy(function($q){
      //     $q->from("users as u")
      //     ->select("u.username")
      //     ->whereColumn("u.id","users.id");
      //   },$sort_lists["auth"]);
      // }
    } else {
      $model_query = $model_query->orderBy('id', 'ASC');
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

      if (isset($like_lists["name"])) {
        $model_query = $model_query->orWhere("name", "like", $like_lists["name"]);
      }

      if (isset($like_lists["role"])) {
        $model_query = $model_query->orWhere("role", "like", $like_lists["role"]);
      }


      if(isset($like_lists['employee_name'])){
        $model_query = $model_query->whereIn("employee_id", function($q)use($like_lists){
          $q->select("id")->from('employee_mst')->where('name','like',$like_lists["employee_name"]);          
        });

      }

      // if (isset($like_lists["role"])) {
      //   $model_query = $model_query->orWhere("role","like",$like_lists["role"]);
      // }
    }

    // ==============
    // Model Filter
    // ==============

    if (isset($request->name)) {
      $model_query = $model_query->where("name", 'like', '%' . $request->name . '%');
    }

    if (isset($request->role)) {
      $model_query = $model_query->where("role", 'like', '%' . $request->role . '%');
    }


    $model_query = $model_query->with('employee')->where("deleted",0)->get();

    return response()->json([
      // "data"=>PotonganMstResource::collection($potongan_msts->keyBy->id),
      "data" => PotonganMstResource::collection($model_query),
    ], 200);
  }

  public function show(PotonganMstRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_mst.view');
    
    $model_query = PotonganMst::with(['val_by','val1_by','employee'])
    ->find($request->id);
    return response()->json([
      "data" => new PotonganMstResource($model_query),
    ], 200);
  }

  public function store(PotonganMstRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_mst.create');

    DB::beginTransaction();
    $t_stamp = date("Y-m-d H:i:s");
    try {
      $model_query                = new PotonganMst();

      if($request->hasFile('attachment_1')){
        $file = $request->file('attachment_1');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $blobFile = base64_encode(file_get_contents($path));
        $model_query->attachment_1 = $blobFile;
        $model_query->attachment_1_type = $fileType;
      }

      if($request->hasFile('attachment_2')){
        $file = $request->file('attachment_2');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $blobFile = base64_encode(file_get_contents($path));
        $model_query->attachment_2 = $blobFile;
        $model_query->attachment_2_type = $fileType;
      }

      $model_query->kejadian      = $request->kejadian;
      $model_query->employee_id   = $request->employee_id;
      $model_query->no_pol        = $request->no_pol;
      $model_query->nominal       = $request->nominal;
      $model_query->nominal_cut   = $request->nominal_cut;
      $model_query->remaining_cut = $request->nominal;

      $model_query->status        = $request->status;

      $model_query->created_at    = $t_stamp;
      $model_query->created_user  = $this->admin_id;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;
      $model_query->save();

      MyLog::sys($this->syslog_db,$model_query->id,"insert");
      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
        "remaining_cut"=>$model_query->remaining_cut
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }

      return response()->json([
        "message"=>$e->getMessage(),
      ],400);

      return response()->json([
        "message" => "Proses tambah data gagal"
      ], 400);
    }
  }

  public function update(PotonganMstRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_mst.modify');

    $t_stamp = date("Y-m-d H:i:s");
    $attachment_1_preview = $request->attachment_1_preview;
    $attachment_2_preview = $request->attachment_2_preview;
    $fileType_1 = null;
    $fileType_2 = null;
    $blobFile_1 = null;
    $blobFile_2 = null;
    $change_1 = 0;
    $change_2 = 0;
    DB::beginTransaction();
    try {
      
      $model_query                = PotonganMst::where("id",$request->id)->lockForUpdate()->first();
      
      // if($model_query->id==1){
      //   throw new \Exception("Izin Ubah Ditolak",1);
      // }

      if($model_query->val==1)
      throw new \Exception("Data sudah tervalidasi",1);

      $SYSOLD                     = clone($model_query);

      $fileType_1     = $model_query->attachment_1_type;
      $fileType_2     = $model_query->attachment_2_type;
      
      $model_query->kejadian      = $request->kejadian;
      $model_query->employee_id   = $request->employee_id;
      $model_query->no_pol        = $request->no_pol;
      $model_query->nominal       = $request->nominal;
      $model_query->nominal_cut   = $request->nominal_cut;
      $model_query->status        = $request->status;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;

      $model_query1 = PotonganTrx::selectRaw('sum(nominal_cut) as paid')->where("potongan_mst_id",$request->id)->where("deleted",0)->lockForUpdate()->first();
      $paid = 0; 
      if($model_query1){
        $paid = $model_query1->paid;
      }
      $model_query->remaining_cut = $model_query->nominal - $paid;
      
      if($model_query->remaining_cut < 0)
      throw new \Exception("Nominal yang sudah terpotong Melebihi Nominal yang ada",1);
      
      if($request->hasFile('attachment_1')){
        $file = $request->file('attachment_1');
        $path = $file->getRealPath();
        $fileType_1 = $file->getClientMimeType();
        $blobFile_1 = base64_encode(file_get_contents($path));
        $change_1++;
      }

      if (!$request->hasFile('attachment_1') && $attachment_1_preview == null) {
        $fileType_1 = null;
        $blobFile_1 = null;
        $change_1++;
      }

      if($request->hasFile('attachment_2')){
        $file = $request->file('attachment_2');
        $path = $file->getRealPath();
        $fileType_2 = $file->getClientMimeType();
        $blobFile_2 = base64_encode(file_get_contents($path));
        $change_2++;
      }

      if (!$request->hasFile('attachment_2') && $attachment_2_preview == null) {
        $fileType_2 = null;
        $blobFile_2 = null;
        $change_2++;
      }

      $model_query->attachment_1_type = $fileType_1;
      $model_query->attachment_2_type = $fileType_2;

      $model_query->save();

      $update=[];

      if($change_1){
        $update["attachment_1"] = $blobFile_1;
      }

      if($change_2){
        $update["attachment_2"] = $blobFile_2;
      }

      if($change_1 || $change_2){
        PotonganMst::where("id",$request->id)->update($update);
      }
      
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys($this->syslog_db,$request->id,"update",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at" => $t_stamp,
        "remaining_cut"=>$model_query->remaining_cut
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      return response()->json([
        "message" => $e->getMessage(),
      ], 400);
      return response()->json([
        "message" => "Proses ubah data gagal"
      ], 400);
    }
  }


  public function delete(PotonganMstRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_mst.remove');

    DB::beginTransaction();
    try {
      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query = PotonganMst::where("id",$request->id)->lockForUpdate()->first();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      // if($model_query->id==1){
      //   throw new \Exception("Izin Hapus Ditolak",1);
      // }
  
      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();

      MyLog::sys($this->syslog_db,$request->id,"delete");

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
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

      return response()->json([
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
    // if ($model_query->delete()) {
    //     return response()->json([
    //         "message"=>"Proses ubah data berhasil",
    //     ],200);
    // }

    // return response()->json([
    //     "message"=>"Proses ubah data gagal",
    // ],400);
  }


  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['potongan_mst.val','potongan_mst.val1']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\PotonganMst,id",
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
      $model_query = PotonganMst::lockForUpdate()->find($request->id);
      if($model_query->val && $model_query->val1){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }
      
      if(MyAdmin::checkScope($this->permissions, 'potongan_mst.val',true) && !$model_query->val){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'potongan_mst.val1',true) && !$model_query->val1){
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }

      $model_query->save();

      MyLog::sys($this->syslog_db,$request->id,"approve");

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
