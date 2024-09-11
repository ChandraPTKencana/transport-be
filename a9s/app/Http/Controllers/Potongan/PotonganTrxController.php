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

use App\Models\MySql\PotonganTrx;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\PotonganTrxRequest;

use App\Http\Resources\MySql\PotonganTrxResource;
use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\PotonganMst;

class PotonganTrxController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'potongan_trx';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();
  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_trx.views');

    $potongan_mst_id = $request->potongan_mst_id;

    if(!$potongan_mst_id) 
    throw new MyException(["message" => "Silahkan refresh halaman terlebih dahulu"], 400);

    if(!PotonganMst::where('id',$potongan_mst_id)->first())
    throw new MyException(["message" => "Ada data yang tidak sesuai"], 400);


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
    // $model_query = PotonganTrx::offset($offset)->limit($limit);
    $model_query = new PotonganTrx();

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

      if (isset($sort_lists["id"])) {
        $model_query = $model_query->orderBy("id", $sort_lists["id"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("id",$sort_symbol,$first_row["id"]);
        }
      }

      if (isset($sort_lists["id_uj"])) {
        $model_query = $model_query->orderBy("id_uj", $sort_lists["id_uj"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("id_uj",$sort_symbol,$first_row["id_uj"]);
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
      $model_query = $model_query->orderBy('id', 'desc');
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
          if (isset($like_lists["id"])) {
            $q->orWhere("id", "like", $like_lists["id"]);
          }
    
          if (isset($like_lists["id_uj"])) {
            $q->orWhere("id_uj", "like", $like_lists["id_uj"]);
          }
        });        
      }

    }

    // ==============
    // Model Filter
    // ==============

    if (isset($request->id)) {
      $model_query = $model_query->where("id", 'like', '%' . $request->id . '%');
    }

    if (isset($request->id_uj)) {
      $model_query = $model_query->where("id_uj", 'like', '%' . $request->id_uj . '%');
    }


    $model_query = $model_query->where("potongan_mst_id",$potongan_mst_id)->with('deleted_by')->get();

    return response()->json([
      // "data"=>PotonganTrxResource::collection($potongan_trxs->keyBy->id),
      "data" => PotonganTrxResource::collection($model_query),
    ], 200);
  }

  public function show(PotonganTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_trx.view');
    
    $model_query = PotonganTrx::with(['val_by','val1_by'])->find($request->id);
    return response()->json([
      "data" => new PotonganTrxResource($model_query),
    ], 200);
  }

  public function store(PotonganTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_trx.create');

    DB::beginTransaction();
    $t_stamp = date("Y-m-d H:i:s");
    try {
      $model_query1                = PotonganMst::where("id",$request->potongan_mst_id)->lockForUpdate()->first();
      $SYSOLD                      = clone($model_query1);
      if($model_query1->remaining_cut < $request->nominal_cut)
      {
        throw new \Exception("Potongan Melebihi Sisa Potongan",1);
      }
      $model_query                  = new PotonganTrx();
      $model_query->potongan_mst_id = $request->potongan_mst_id;
      $model_query->note            = $request->note;
      $model_query->nominal_cut     = $request->nominal_cut;
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;
      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->save();

      $model_query1->remaining_cut  = $model_query1->remaining_cut - $request->nominal_cut;
      $model_query1->updated_at     = $t_stamp;
      $model_query1->save();

      MyLog::sys($this->syslog_db,$model_query->id,"insert");
      
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query1); 
      MyLog::sys("potongan_mst",$model_query1->id,"update",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
        "remaining_cut" =>$model_query1->remaining_cut
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

  public function update(PotonganTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_trx.modify');

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query1               = PotonganMst::where("id",$request->potongan_mst_id)->lockForUpdate()->first();
      $SYSOLD1                    = clone($model_query1);

      $model_query                = PotonganTrx::where("id",$request->id)->lockForUpdate()->first();
      if($model_query1->remaining_cut + $model_query->nominal_cut < $request->nominal_cut)
      {
        throw new \Exception("Potongan Melebihi Sisa Potongan",1);
      }
      
      $model_query1->remaining_cut = $model_query1->remaining_cut + $model_query->nominal_cut - $request->nominal_cut;
      
      if($model_query->id_uj){
        throw new \Exception("Izin Ubah Ditolak",1);
      }

      if($model_query->val==1)
      throw new \Exception("Data sudah tervalidasi",1);

      $SYSOLD                     = clone($model_query);

      $model_query->note          = $request->note;
      $model_query->nominal_cut   = $request->nominal_cut;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;
      $model_query->save();

      $model_query1->updated_at     = $t_stamp;
      $model_query1->save();

      
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys($this->syslog_db,$request->id,"update",$SYSNOTE);

      $SYSNOTE1 = MyLib::compareChange($SYSOLD1,$model_query1); 
      MyLog::sys("potongan_mst",$model_query1->id,"update",$SYSNOTE1);

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at" => $t_stamp,
        "remaining_cut" =>$model_query1->remaining_cut
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


  public function delete(PotonganTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_trx.remove');

    DB::beginTransaction();
    try {
      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query = PotonganTrx::where("id",$request->id)->lockForUpdate()->first();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      if ($model_query->id_uj) {
        throw new \Exception("Izin Hapus Ditolak", 1);
      }
  
      $model_query->deleted         = 1;
      $model_query->deleted_user    = $this->admin_id;
      $model_query->deleted_at      = date("Y-m-d H:i:s");
      $model_query->deleted_reason  = $deleted_reason;
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


  public function recalculate(Request $request)
  {
    $id = $request->potongan_mst_id;
    if(!$id) 
    throw new MyException(["message" => "Silahkan refresh halaman terlebih dahulu"], 400);
    
    if(!PotonganMst::where('id',$id)->first())
    throw new MyException(["message" => "Ada data yang tidak sesuai"], 400);
    $t_stamp=date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      

      $model_query = PotonganMst::where("id",$id)->lockForUpdate()->first();
      $model_query1 = PotonganTrx::selectRaw('sum(nominal_cut) as paid')->where("potongan_mst_id",$id)->where("deleted",0)->lockForUpdate()->first();
      $paid = 0; 
      if($model_query1){
        $paid = $model_query1->paid;
      }

      $model_query->remaining_cut = $model_query->nominal - $paid;

      $model_query->updated_user = $this->admin_id;
      $model_query->updated_at = $t_stamp;
      $model_query->save();

      // MyLog::sys($this->syslog_db,$request->id,"recalculate");
      MyLog::sys("potongan_mst",$request->id,"recalculate");

      DB::commit();
      return response()->json([
        "message"       => "Proses ubah data berhasil",
        "updated_at"    => $t_stamp,
        "remaining_cut" => $model_query->remaining_cut

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
    MyAdmin::checkMultiScope($this->permissions, ['potongan_trx.val','potongan_trx.val1']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\PotonganTrx,id",
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
      $model_query = PotonganTrx::lockForUpdate()->find($request->id);
      if($model_query->val && $model_query->val1){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }
      
      if(MyAdmin::checkScope($this->permissions, 'potongan_trx.val',true) && !$model_query->val){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'potongan_trx.val1',true) && !$model_query->val1){
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
