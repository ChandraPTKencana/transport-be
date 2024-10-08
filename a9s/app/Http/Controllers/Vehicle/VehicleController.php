<?php

namespace App\Http\Controllers\Vehicle;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Exceptions\MyException;

use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Helpers\MyLib;

use App\Models\MySql\Vehicle;

use App\Http\Requests\MySql\VehicleRequest;

use App\Http\Resources\MySql\VehicleResource;

class VehicleController extends Controller
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

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'vehicle.views');

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
    $model_query = Vehicle::offset($offset)->limit($limit);

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

      if (isset($sort_lists["no_pol"])) {
        $model_query = $model_query->orderBy("no_pol", $sort_lists["no_pol"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("no_pol",$sort_symbol,$first_row["no_pol"]);
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

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists){            
          if (isset($like_lists["no_pol"])) {
            $q->orWhere("no_pol", "like", $like_lists["no_pol"]);
          }         
        });        
      }



      // if (isset($like_lists["role"])) {
      //   $model_query = $model_query->orWhere("role","like",$like_lists["role"]);
      // }
    }

    // ==============
    // Model Filter
    // ==============

    if (isset($request->no_pol)) {
      $model_query = $model_query->where("no_pol", 'like', '%' . $request->no_pol . '%');
    }

    $model_query = $model_query->where("deleted",0)->get();

    return response()->json([
      // "data"=>EmployeeResource::collection($employees->keyBy->id),
      "data" => VehicleResource::collection($model_query),
    ], 200);
  }

  
  public function show(VehicleRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'vehicle.view');
    $model_query = Vehicle::find($request->id);
    return response()->json([
      "data" => new VehicleResource($model_query),
    ], 200);
  }

  public function store(VehicleRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'vehicle.create');

    DB::beginTransaction();
    $t_stamp = date("Y-m-d H:i:s");
    try {
      $model_query                = new Vehicle();
      $model_query->no_pol        = $request->no_pol;
      $model_query->created_at    = $t_stamp;
      $model_query->created_user  = $this->admin_id;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;
      $model_query->save();
      MyLog::sys("vechicle_mst",$model_query->id,"insert");

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

  public function update(VehicleRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'vehicle.modify');
    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query                = Vehicle::find($request->id);
      $SYSOLD                     = clone($model_query);

      $model_query->no_pol        = $request->no_pol;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("vehicle_mst",$request->id,"update",$SYSNOTE);

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


  public function delete(VehicleRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'vehicle.remove');
    DB::beginTransaction();

    try {

      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query = Vehicle::where("id",$request->id)->lockForUpdate()->first();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
  
      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();
      MyLog::sys("vehicle_mst",$request->id,"delete");

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



  public function available(Request $request)
  {
    $model_query = Vehicle::where("deleted",0)->get();
    return response()->json([
      "data" => VehicleResource::collection($model_query),
    ], 200);
  }
}
