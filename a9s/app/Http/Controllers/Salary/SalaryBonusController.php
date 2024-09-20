<?php

namespace App\Http\Controllers\Salary;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\File;

use App\Exceptions\MyException;
use Exception;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\SalaryBonus;
use App\Models\MySql\SalaryBonusDtl;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\SalaryBonusRequest;

use App\Http\Resources\IsUserResource;
use App\Http\Resources\MySql\SalaryBonusDtlResource;
use App\Http\Resources\MySql\SalaryBonusResource;
use App\Models\MySql\StandbyTrx;

class SalaryBonusController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'salary_bonus';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();

  }
  public function loadLocal()
  {
    MyAdmin::checkMultiScope($this->permissions, ['salary_bonus.create','salary_bonus.modify']);

    $list_employee = \App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->available()->verified()->whereIn("role",['Supir','Kernet','BLANK'])->get();
    
    return response()->json([
      "list_employee" => $list_employee,
    ], 200);
  }
  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'salary_bonus.views');
 
    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 50; // Limit +> Much Data
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
    $model_query = SalaryBonus::offset($offset)->limit($limit);

    $first_row=[];
    if($request->first_row){
      $first_row 	= json_decode($request->first_row, true);
    }

    //======================================================================================================
    // Model Sorting | Example $request->sort = "username:desc,role:desc";
    //======================================================================================================
    

    // if ($request->sort) {
    //   $sort_lists = [];

    //   $sorts = explode(",", $request->sort);
    //   foreach ($sorts as $key => $sort) {
    //     $side = explode(":", $sort);
    //     $side[1] = isset($side[1]) ? $side[1] : 'ASC';
    //     $sort_symbol = $side[1] == "desc" ? "<=" : ">=";
    //     $sort_lists[$side[0]] = $side[1];
    //   }

    //   if (isset($sort_lists["id"])) {
    //     $model_query = $model_query->orderBy("id", $sort_lists["id"]);
    //     if (count($first_row) > 0) {
    //       $model_query = $model_query->where("id",$sort_symbol,$first_row["id"]);
    //     }
    //   }

    //   if (isset($sort_lists["xto"])) {
    //     $model_query = $model_query->orderBy("xto", $sort_lists["xto"]);
    //     if (count($first_row) > 0) {
    //       $model_query = $model_query->where("xto",$sort_symbol,$first_row["xto"]);
    //     }
    //   }

    //   if (isset($sort_lists["tipe"])) {
    //     $model_query = $model_query->orderBy("tipe", $sort_lists["tipe"]);
    //     if (count($first_row) > 0) {
    //       $model_query = $model_query->where("tipe",$sort_symbol,$first_row["tipe"]);
    //     }
    //   }

    //   if (isset($sort_lists["jenis"])) {
    //     $model_query = $model_query->orderBy("jenis", $sort_lists["jenis"]);
    //     if (count($first_row) > 0) {
    //       $model_query = $model_query->where("jenis",$sort_symbol,$first_row["jenis"]);
    //     }
    //   }

    //   if (isset($sort_lists["harga"])) {
    //     $model_query = $model_query->orderBy("harga", $sort_lists["harga"]);
    //     if (count($first_row) > 0) {
    //       $model_query = $model_query->where("harga",$sort_symbol,$first_row["harga"]);
    //     }
    //   }
      

    // } else {
    //   $model_query = $model_query->orderBy('period_start', 'DESC');
    // }
    
    $model_query = $model_query->orderBy('id', 'DESC');

    //======================================================================================================
    // Model Filter | Example $request->like = "username:%username,role:%role%,name:role%,";
    //======================================================================================================

    // if ($request->like) {
    //   $like_lists = [];

    //   $likes = explode(",", $request->like);
    //   foreach ($likes as $key => $like) {
    //     $side = explode(":", $like);
    //     $side[1] = isset($side[1]) ? $side[1] : '';
    //     $like_lists[$side[0]] = $side[1];
    //   }

    //   if(count($like_lists) > 0){
    //     $model_query = $model_query->where(function ($q)use($like_lists){
            
    //       if (isset($like_lists["id"])) {
    //         $q->orWhere("id", "like", $like_lists["id"]);
    //       }
    
    //       if (isset($like_lists["xto"])) {
    //         $q->orWhere("xto", "like", $like_lists["xto"]);
    //       }
    
    //       if (isset($like_lists["tipe"])) {
    //         $q->orWhere("tipe", "like", $like_lists["tipe"]);
    //       }

    //       if (isset($like_lists["jenis"])) {
    //         $q->orWhere("jenis", "like", $like_lists["jenis"]);
    //       }
    //       if (isset($like_lists["harga"])) {
    //         $q->orWhere("harga", "like", $like_lists["harga"]);
    //       }
    
    //       // if (isset($like_lists["requested_name"])) {
    //       //   $q->orWhereIn("requested_by", function($q2)use($like_lists) {
    //       //     $q2->from('is_users')
    //       //     ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
    //       //   });
    //       // }
    
    //       // if (isset($like_lists["confirmed_name"])) {
    //       //   $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
    //       //     $q2->from('is_users')
    //       //     ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
    //       //   });
    //       // }
    //     });        
    //   }

      
    // }

    // ==============
    // Model Filter
    // ==============
    $model_query = $model_query->exclude(['attachment_1']);
    $model_query = $model_query->where('deleted',0)->with('employee')->get();
    return response()->json([
      "data" => SalaryBonusResource::collection($model_query),
    ], 200);
  }

  public function show(SalaryBonusRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'salary_bonus.view');

    // return response()->json([
    //   "message" => "Hanya yang membuat transaksi yang boleh melakukan pergantian atau konfirmasi data",
    // ], 400);

    $model_query = SalaryBonus::with([
    'employee'
    //end for details2
    ])->with(['val1_by','val2_by'])->find($request->id);

    // if($model_query->requested_by != $this->admin_id){
    //   return response()->json([
    //     "message" => "Hanya yang membuat transaksi yang boleh melakukan pergantian atau konfirmasi data",
    //   ], 400);
    // }
    

    // if($model_query->ref_id!=null){
    //   return response()->json([
    //     "message" => "Ubah data ditolak",
    //   ], 400);
    // }

    return response()->json([
      "data" => new SalaryBonusResource($model_query),
    ], 200);
  }

  public function store(SalaryBonusRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'salary_bonus.create');

    $rollback_id = -1;
    $t_stamp = date("Y-m-d H:i:s");

    DB::beginTransaction();
    try {
      $model_query                  = new SalaryBonus();

      if($request->hasFile('attachment_1')){
        $file = $request->file('attachment_1');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $blobFile = base64_encode(file_get_contents($path));
        $model_query->attachment_1 = $blobFile;
        $model_query->attachment_1_type = $fileType;
      }

      // if(SalaryBonus::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar",1);

      $model_query->tanggal         = $request->tanggal;
      $model_query->type            = $request->type;
      $model_query->employee_id     = $request->employee_id;
      $model_query->nominal         = $request->nominal;
      $model_query->note            = $request->note;

      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

      $model_query->save();
      $rollback_id = $model_query->id - 1;
      
      MyLog::sys($this->syslog_db,$model_query->id,"insert");

      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE salary_bonus AUTO_INCREMENT = $rollback_id");

      // return response()->json([
      //   "message" => $e->getMessage(),
      // ], 400);
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }

      return response()->json([
        "message" => "Proses tambah data gagal",
      ], 400);
    }
  }

  public function update(SalaryBonusRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'salary_bonus.modify');

    $t_stamp = date("Y-m-d H:i:s");
    $attachment_1_preview = $request->attachment_1_preview;
    $fileType = null;
    $blobFile = null;
    $change = 0;
    DB::beginTransaction();
    try {
      $SYSNOTES=[];
      $model_query = SalaryBonus::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);
        // $model_query->attachment_1_type = $fileType;
      $fileType     = $model_query->attachment_1_type;
      
      if( $model_query->val==1 )
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      if($model_query->salary_paid_id) 
      throw new \Exception("Data Sudah Digunakan Dan Tidak Dapat Di Ubah",1);

      if($request->hasFile('attachment_1')){
        $file = $request->file('attachment_1');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $blobFile = base64_encode(file_get_contents($path));
        $change++;
      }

      if (!$request->hasFile('attachment_1') && $attachment_1_preview == null) {
        $blobFile = null;
        $fileType = null;
        $change++;
      }

      $model_query->attachment_1_type = $fileType;

      $model_query->tanggal           = $request->tanggal;
      $model_query->type              = $request->type;
      $model_query->employee_id       = $request->employee_id;
      $model_query->nominal           = $request->nominal;
      $model_query->note              = $request->note;

      $model_query->updated_at        = $t_stamp;
      $model_query->updated_user      = $this->admin_id;

      $model_query->save();

      if($change){
        SalaryBonus::where("id",$request->id)->update([
          "attachment_1"      => $blobFile
        ]);
      }

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      array_unshift( $SYSNOTES , $SYSNOTE );            
      MyLog::sys($this->syslog_db,$request->id,"update",implode("\n",$SYSNOTES));

      DB::commit();
      return response()->json([
        "message" => "Proses Generate data berhasil",
        "updated_at"=>$t_stamp,
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
      
      return response()->json([
        "message" => "Proses ubah data gagal",
      ], 400);
    }
  }

  public function delete(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'salary_bonus.remove');

    DB::beginTransaction();

    try {
      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);
    
      $model_query = SalaryBonus::exclude(['attachment_1'])->where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      
      // $model_querys = SalaryBonusDtl::where("id_uj",$model_query->id)->lockForUpdate()->get();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      if($model_query->salary_paid_id) 
      throw new \Exception("Data Sudah Digunakan Dan Tidak Dapat Di Hapus",1);

      // if($model_query->ref_id != null){
      //   throw new \Exception("Hapus data ditolak. Data berasal dari transfer",1);
      // }

      // if($model_query->confirmed_by != null){
      //   throw new \Exception("Hapus data ditolak. Data sudah dikonfirmasi",1);
      // }
      
  
      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();
      MyLog::sys($this->syslog_db,$request->id,"delete");

      // SalaryBonusDtl::where("id_uj",$model_query->id)->delete();
      // $model_query->delete();

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

      return response()->json([
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  }

  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['salary_bonus.val1','salary_bonus.val2']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\SalaryBonus,id",
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
      $model_query = SalaryBonus::exclude(['attachment_1'])->lockForUpdate()->find($request->id);
      $run_val = 0;
      if(MyAdmin::checkScope($this->permissions, 'salary_bonus.val1',true) && !$model_query->val1){
        $run_val++;
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'salary_bonus.val2',true) && !$model_query->val2){
        $run_val++;
        $model_query->val2 = 1;
        $model_query->val2_user = $this->admin_id;
        $model_query->val2_at = $t_stamp;
      }

      $model_query->save();

      MyLog::sys($this->syslog_db,$request->id,"approve");

      DB::commit();
      return response()->json([
        "message" => $run_val ? "Proses validasi data berhasil" : "Tidak Ada Data Yang Tervalidasi",
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
