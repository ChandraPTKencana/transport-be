<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Helpers\MyLib;
use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;
use App\Models\MySql\Employee;
use App\Http\Resources\MySql\EmployeeResource;
use App\Http\Requests\MySql\EmployeeRequest;

use Illuminate\Support\Facades\DB;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

class EmployeeController extends Controller
{
  private $admin;
  private $admin_id;
  private $role;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->role = $this->admin->the_user->hak_akses;
    $this->admin_id = $this->admin->the_user->id;

  }

  public function index(Request $request)
  {
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport']);

    // \App\Helpers\MyAdmin::checkScope($this->auth, ['ap-user-view']);

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
    $model_query = Employee::offset($offset)->limit($limit);

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


    $model_query = $model_query->get();

    return response()->json([
      // "data"=>EmployeeResource::collection($employees->keyBy->id),
      "data" => EmployeeResource::collection($model_query),
    ], 200);
  }

  public function show(EmployeeRequest $request)
  {
    // MyLib::checkScope($this->auth, ['ap-user-view']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport']);
    $model_query = Employee::find($request->id);
    return response()->json([
      "data" => new EmployeeResource($model_query),
    ], 200);
  }

  public function store(EmployeeRequest $request)
  {
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport']);
    // MyLib::checkScope($this->auth, ['ap-user-add']);

    DB::beginTransaction();
    $t_stamp = date("Y-m-d H:i:s");
    try {
      $model_query                = new Employee();
      $model_query->name          = $request->name;
      $model_query->role          = $request->role;
      $model_query->created_at    = $t_stamp;
      $model_query->created_user  = $this->admin_id;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;
      $model_query->save();

      MyLog::sys("employee_mst",$model_query->id,"insert");
      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }

      // return response()->json([
      //   "message"=>$e->getMessage(),
      // ],400);

      return response()->json([
        "message" => "Proses tambah data gagal"
      ], 400);
    }
  }

  public function update(EmployeeRequest $request)
  {
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport']);
    // MyLib::checkScope($this->auth, ['ap-user-edit']);
    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query                = Employee::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD                     = clone($model_query);

      $model_query->name          = $request->name;
      $model_query->role          = $request->role;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;
      $model_query->save();
      
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("employee_mst",$request->id,"update",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at" => $t_stamp,
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


  public function delete(EmployeeRequest $request)
  {
    // MyLib::checkScope($this->auth, ['ap-user-remove']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport']);
    DB::beginTransaction();

    try {
      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query = Employee::where("id",$request->id)->lockForUpdate()->first();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
  
      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();

      MyLog::sys("employee_mst",$request->id,"delete");

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
}
