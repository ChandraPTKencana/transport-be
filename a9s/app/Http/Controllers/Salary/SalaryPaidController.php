<?php

namespace App\Http\Controllers\Salary;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Exceptions\MyException;
use Exception;

use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;

use App\Exports\MyReport;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\SalaryPaid;
use App\Models\MySql\SalaryPaidDtl;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\SalaryPaidRequest;

use App\Http\Resources\IsUserResource;
use App\Http\Resources\MySql\SalaryPaidDtlResource;
use App\Http\Resources\MySql\SalaryPaidResource;
use App\Models\MySql\Employee;
use App\Models\MySql\SalaryBonus;
use App\Models\MySql\StandbyTrx;

class SalaryPaidController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'salary_paid';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'salary_paid.views');
 
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
    $model_query = SalaryPaid::offset($offset)->limit($limit);

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
    
    $model_query = $model_query->orderBy('period_end','DESC')->orderBy('period_part', 'DESC');

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

    $model_query = $model_query->get();

    return response()->json([
      "data" => SalaryPaidResource::collection($model_query),
    ], 200);
  }

  public function show(SalaryPaidRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'salary_paid.view');

    // return response()->json([
    //   "message" => "Hanya yang membuat transaksi yang boleh melakukan pergantian atau konfirmasi data",
    // ], 400);

    $model_query = SalaryPaid::with([
    'details'=>function ($q){
      $q->with('employee')->orderBy(function($q){
        $q->from("employee_mst")
        ->select("name")
        ->whereColumn("id","employee_id");
      },'asc');
    }
    //end for details2
    ])->with(['val1_by','val2_by','val3_by'])->find($request->id);

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
      "data" => new SalaryPaidResource($model_query),
    ], 200);
  }

  public function store(SalaryPaidRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'salary_paid.create');

    $rollback_id = -1;
    $t_stamp = date("Y-m-d H:i:s");

    $period_part = $request->period_part;
    if($period_part==1){
      $date = new \DateTime($request->period_end."-13");
      // $date->modify('last day of this month');
      $last_day_of_month=$date->format('Y-m-d');        
    }else{
      $date = new \DateTime($request->period_end."-01");
      $date->modify('last day of this month');
      $last_day_of_month=$date->format('Y-m-d');
    }

    $sp_before=SalaryPaid::orderBy("id","desc")->first();
    if($sp_before && $sp_before->val1==0)
    throw new MyException([ "message" => "Harap Validasi Periode Sebelumnya" ], 400);

    if(SalaryPaid::where('period_end',$last_day_of_month)->where("period_part",$period_part)->first())
    throw new MyException([ "period_end" => ["Periode Sudah Terdaftar"] ], 422);

    DB::beginTransaction();
    try {
      // if(SalaryPaid::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar",1);

      $model_query                  = new SalaryPaid();

      $model_query->period_end      = $last_day_of_month;
      $model_query->period_part     = $period_part;

      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

      $model_query->save();
      $rollback_id = $model_query->id - 1;
      $this->reInsertDetails($model_query);
      
      MyLog::sys($this->syslog_db,$model_query->id,"insert");

      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
        "details"=>SalaryPaidDtlResource::collection(
          SalaryPaidDtl::where("salary_paid_id",$model_query->id)->with('employee')->orderBy(function($q){
            $q->from("employee_mst")
            ->select("name")
            ->whereColumn("id","employee_id");
          },'asc')->get()
        )
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE salary_paid AUTO_INCREMENT = $rollback_id");
      
      return response()->json([
        "message" => $e->getMessage(),
      ], 400);
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

  public function update(SalaryPaidRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'salary_paid.modify');

    $t_stamp = date("Y-m-d H:i:s");
    
    DB::beginTransaction();
    try {
      $SYSNOTES=[];
      $model_query = SalaryPaid::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);
      
      if( $model_query->val==1 )
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);


      array_push( $SYSNOTES ,"Change salary_paid_id ".$model_query->id." field in standby trx and salary bonus to null \n");
      StandbyTrx::where('salary_paid_id',$model_query->id)->lockForUpdate()->update(['salary_paid_id'=>null]);
      SalaryBonus::exclude(['attachment_1'])->where('salary_paid_id',$model_query->id)->where("type","!=","Kerajinan")->lockForUpdate()->update(['salary_paid_id'=>null]);

      array_push( $SYSNOTES ,"Remove All Details \n");
      SalaryPaidDtl::where('salary_paid_id',$model_query->id)->lockForUpdate()->delete();

      array_push( $SYSNOTES ,"Change salary_paid_id field in standby trx and salary bonus to ".$model_query->id." and insert new Details \n");
      $this->reInsertDetails($model_query);

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      array_unshift( $SYSNOTES , $SYSNOTE );            
      MyLog::sys($this->syslog_db,$request->id,"update",implode("\n",$SYSNOTES));

      DB::commit();
      return response()->json([
        "message" => "Proses Generate data berhasil",
        "updated_at"=>$t_stamp,
        "details"=>SalaryPaidDtlResource::collection(
          SalaryPaidDtl::where("salary_paid_id",$model_query->id)->with('employee')->orderBy(function($q){
            $q->from("employee_mst")
            ->select("name")
            ->whereColumn("id","employee_id");
          },'asc')->get()
        )
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

  // public function delete(Request $request)
  // {
  //   MyAdmin::checkScope($this->permissions, 'salary_paid.remove');

  //   DB::beginTransaction();

  //   try {
  //     $deleted_reason = $request->deleted_reason;
  //     if(!$deleted_reason)
  //     throw new \Exception("Sertakan Alasan Penghapusan",1);
    
  //     $model_query = SalaryPaid::where("id",$request->id)->lockForUpdate()->first();
  //     // if($model_query->requested_by != $this->admin_id){
  //     //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
  //     // }
      
  //     // $model_querys = SalaryPaidDtl::where("id_uj",$model_query->id)->lockForUpdate()->get();

  //     if (!$model_query) {
  //       throw new \Exception("Data tidak terdaftar", 1);
  //     }

  //     // if($model_query->val==1 || $model_query->deleted==1) 
  //     // throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);


  //     // if($model_query->ref_id != null){
  //     //   throw new \Exception("Hapus data ditolak. Data berasal dari transfer",1);
  //     // }

  //     // if($model_query->confirmed_by != null){
  //     //   throw new \Exception("Hapus data ditolak. Data sudah dikonfirmasi",1);
  //     // }
      
  
  //     $model_query->deleted = 1;
  //     $model_query->deleted_user = $this->admin_id;
  //     $model_query->deleted_at = date("Y-m-d H:i:s");
  //     $model_query->deleted_reason = $deleted_reason;
  //     $model_query->save();
  //     MyLog::sys($this->syslog_db,$request->id,"delete");

  //     // SalaryPaidDtl::where("id_uj",$model_query->id)->delete();
  //     // $model_query->delete();

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

  //     return response()->json([
  //       "message" => "Proses hapus data gagal",
  //     ], 400);
  //     //throw $th;
  //   }
  // }

  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['salary_paid.val1','salary_paid.val2','salary_paid.val3']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\SalaryPaid,id",
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
      $model_query = SalaryPaid::find($request->id);
      $run_val = 0;
      if(MyAdmin::checkScope($this->permissions, 'salary_paid.val1',true) && !$model_query->val1){
        $run_val++;
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }

      // if(MyAdmin::checkScope($this->permissions, 'salary_paid.val2',true) && !$model_query->val2){
      //   $run_val++;
      //   $model_query->val2 = 1;
      //   $model_query->val2_user = $this->admin_id;
      //   $model_query->val2_at = $t_stamp;
      // }

      // if(MyAdmin::checkScope($this->permissions, 'salary_paid.val3',true) && !$model_query->val3){
      //   $run_val++;
      //   $model_query->val3 = 1;
      //   $model_query->val3_user = $this->admin_id;
      //   $model_query->val3_at = $t_stamp;
      // }

      
      $model_query->save();

      MyLog::sys($this->syslog_db,$request->id,"approve");

      DB::commit();
      return response()->json([
        "message" => $run_val ? "Proses validasi data berhasil" : "Tidak Ada Data Yang Tervalidasi",
        "val1"=>$model_query->val1,
        "val1_user"=>$model_query->val1_user,
        "val1_at"=>$model_query->val1_at,
        "val1_by"=>$model_query->val1_user ? new IsUserResource(IsUser::find($model_query->val1_user)) : null, 
        // "val2"=>$model_query->val2,
        // "val2_user"=>$model_query->val2_user,
        // "val2_at"=>$model_query->val2_at,
        // "val2_by"=>$model_query->val2_user ? new IsUserResource(IsUser::find($model_query->val2_user)) : null, 
        // "val3"=>$model_query->val3,
        // "val3_user"=>$model_query->val3_user,
        // "val3_at"=>$model_query->val3_at,
        // "val3_by"=>$model_query->val3_user ? new IsUserResource(IsUser::find($model_query->val3_user)) : null, 
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

  // function reInsertDetails($model_query){
  //   $kerajinan_s=($model_query->period_part==2) ? 400000 : 0;
  //   $kerajinan_k=($model_query->period_part==2) ? 200000 : 0;

  //   $employees = Employee::exclude(['attachment_1','attachment_2'])->verified()->available()->where('name',"!=","BLANK")->get();
  //   $dt_dtl = [];

  //   foreach ($employees as $v) {
  //     array_push($dt_dtl,[
  //       "salary_paid_id" => $model_query->id,
  //       "employee_id" => $v->id,
  //       "standby_nominal" => 0,
  //       "salary_bonus_nominal" => $v->role == 'Supir' ? $kerajinan_s : $kerajinan_k,
  //     ]);
  //   }

  //   $sts = StandbyTrx::where('created_at',"<=",$model_query->period_end." 23:59:59")
  //   ->where('val2',1)->whereNull('salary_paid_id')->with('details')->lockForUpdate()->get();

  //   foreach ($sts as $k => $v) {
  //     $smd = $v->standby_mst->details;

  //     $nominal_s = 0;
  //     $nominal_k = 0;

  //     foreach ($smd as $k1 => $v1) {
  //       if($v1->xfor == 'Supir'){
  //         $nominal_s += $v1->amount * count($v->details);
  //       }

  //       if($v1->xfor == 'Kernet'){
  //         $nominal_k += $v1->amount * count($v->details);
  //       }
  //     }


  //     if($v->supir_id){

  //       $map_s = array_map(function($x){
  //         return $x['employee_id'];
  //       },$dt_dtl);

  //       $search = array_search($v->supir_id,$map_s);

  //       if(count($dt_dtl)==0 || $search===false){
  //         array_push($dt_dtl,[
  //           "salary_paid_id" => $model_query->id,
  //           "employee_id" => $v->supir_id,
  //           "standby_nominal" => $nominal_s,
  //           "salary_bonus_nominal" => $kerajinan_s,
  //         ]);
  //       }else{
  //         $dt_dtl[$search]['standby_nominal']+=$nominal_s;
  //       }
  //     }

  //     if($v->kernet_id){

  //       $map_k = array_map(function($x){
  //         return $x['employee_id'];
  //       },$dt_dtl);

  //       $search = array_search($v->kernet_id,$map_k);

  //       if(count($dt_dtl)==0 || $search===false){
  //         array_push($dt_dtl,[
  //           "salary_paid_id" => $model_query->id,
  //           "employee_id" => $v->kernet_id,
  //           "standby_nominal" => $nominal_k,
  //           "salary_bonus_nominal" => $kerajinan_k,
  //         ]);
  //       }else{
  //         $dt_dtl[$search]['standby_nominal']+=$nominal_k;
  //       }
  //     }

  //     $v->salary_paid_id = $model_query->id;
  //     $v->save();
  //   }
  //   if($model_query->period_part==2){
  //     $sbs = SalaryBonus::exclude(['attachment_1'])->where('tanggal',"<=",$model_query->period_end)
  //     ->where('val2',1)->whereNull('salary_paid_id')->lockForUpdate()->get();
      
  //     foreach($sbs as $v){
  
  //       $map_e = array_map(function($x){
  //         return $x['employee_id'];
  //       },$dt_dtl);
  
  //       $search = array_search($v->employee_id,$map_e);
  
  //       if(count($dt_dtl)==0 || $search===false){
  
  //         $emp = Employee::exclude(['attachment_1','attachment_2'])->where("id",$v->employee_id)->first();
  
  //         array_push($dt_dtl,[
  //           "salary_paid_id" => $model_query->id,
  //           "employee_id" => $v->employee_id,
  //           "standby_nominal" => 0,
  //           "salary_bonus_nominal" => ($emp->role == 'Supir' ? $kerajinan_s : $kerajinan_k) + $v->nominal,
  //         ]);
  //       }else{
  //         $dt_dtl[$search]['salary_bonus_nominal']+=$v->nominal;
  //       }
  //       $v->salary_paid_id = $model_query->id;
  //       $v->save();
  //     }
  //   }

  //   foreach ($dt_dtl as $k => $v) {
  //     SalaryPaidDtl::insert($v);
  //   }

  // }

  function reInsertDetails($model_query){
    // $kerajinan_s=($model_query->period_part==2) ? 400000 : 0;
    // $kerajinan_k=($model_query->period_part==2) ? 200000 : 0;

    // $employees = Employee::exclude(['attachment_1','attachment_2'])->verified()->available()->where('name',"!=","BLANK")->get();
    $dt_dtl = [];

    // foreach ($employees as $v) {
    //   array_push($dt_dtl,[
    //     "salary_paid_id" => $model_query->id,
    //     "employee_id" => $v->id,
    //     // "standby_nominal" => 0,
    //     "sb_gaji"=>0,
    //     "sb_makan"=>0,
    //     "sb_dinas"=>0,
    //     "salary_bonus_nominal" => $v->role == 'Supir' ? $kerajinan_s : $kerajinan_k,
    //   ]);
    // }

    $sts = StandbyTrx::where('created_at',"<=",$model_query->period_end." 23:59:59")
    ->where('val2',1)->whereNull('salary_paid_id')->where('req_deleted',0)->where('deleted',0)->with(['details'=>function ($q){
      $q->where("be_paid",1);      
    }])->lockForUpdate()->get();

    foreach ($sts as $k => $v) {
      $smd = $v->standby_mst->details;

      $nominal_s = 0;
      $sb_gaji_s = 0;
      $sb_makan_s = 0;
      $sb_dinas_s = 0;

      $nominal_k = 0;
      $sb_gaji_k = 0;
      $sb_makan_k = 0;
      $sb_dinas_k = 0;

      foreach ($smd as $k1 => $v1) {
        $amount = $v1->amount * count($v->details);
        if($v1->xfor == 'Supir'){
          $nominal_s += $amount;
          if($v1->ac_account_code=='01.510.001') $sb_gaji_s += $amount;
          if($v1->ac_account_code=='01.510.005') $sb_makan_s += $amount;
          if($v1->ac_account_code=='01.575.002') $sb_dinas_s += $amount;
        }

        if($v1->xfor == 'Kernet'){
          $nominal_k += $amount;
          if($v1->ac_account_code=='01.510.001') $sb_gaji_k += $amount;
          if($v1->ac_account_code=='01.510.005') $sb_makan_k += $amount;
          if($v1->ac_account_code=='01.575.002') $sb_dinas_k += $amount;
        }
      }


      if($v->supir_id){

        $map_s = array_map(function($x){
          return $x['employee_id'];
        },$dt_dtl);

        $search = array_search($v->supir_id,$map_s);

        if(count($dt_dtl)==0 || $search===false){
          array_push($dt_dtl,[
            "salary_paid_id"        => $model_query->id,
            "employee_id"           => $v->supir_id,
            // "standby_nominal"       => $nominal_s,
            "sb_gaji"               => $sb_gaji_s,
            "sb_makan"              => $sb_makan_s,
            "sb_dinas"              => $sb_dinas_s,
            // "salary_bonus_nominal"  => ($sb_dinas_s) || ($sb_gaji_s==0 && $sb_makan_s==0) ? 0 : $kerajinan_s,
            "salary_bonus_nominal"  => 0,
          ]);
        }else{
          // $dt_dtl[$search]['standby_nominal']+=$nominal_s;
          $dt_dtl[$search]['sb_gaji']+=$sb_gaji_s;
          $dt_dtl[$search]['sb_makan']+=$sb_makan_s;
          $dt_dtl[$search]['sb_dinas']+=$sb_dinas_s;
        }
      }

      if($v->kernet_id){

        $map_k = array_map(function($x){
          return $x['employee_id'];
        },$dt_dtl);

        $search = array_search($v->kernet_id,$map_k);

        if(count($dt_dtl)==0 || $search===false){
          array_push($dt_dtl,[
            "salary_paid_id"        => $model_query->id,
            "employee_id"           => $v->kernet_id,
            // "standby_nominal"       => $nominal_k,
            "sb_gaji"               => $sb_gaji_k,
            "sb_makan"              => $sb_makan_k,
            "sb_dinas"              => $sb_dinas_k,
            // "salary_bonus_nominal"  => ($sb_dinas_k) || ($sb_gaji_k==0 && $sb_makan_k==0) ? 0 : $kerajinan_k,
            "salary_bonus_nominal"  => 0,
          ]);
        }else{
          // $dt_dtl[$search]['standby_nominal']+=$nominal_k;
          $dt_dtl[$search]['sb_gaji']+=$sb_gaji_k;
          $dt_dtl[$search]['sb_makan']+=$sb_makan_k;
          $dt_dtl[$search]['sb_dinas']+=$sb_dinas_k;
        }
      }

      $v->salary_paid_id = $model_query->id;
      $v->save();
    }
    $sbs = SalaryBonus::exclude(['attachment_1'])->where('tanggal',"<=",$model_query->period_end)
    ->where('val2',1)->whereNull('salary_paid_id')->where('deleted',0)
    ->where("type","!=","Kerajinan")
    ->lockForUpdate()->get();
      
    foreach($sbs as $v){

      $map_e = array_map(function($x){
        return $x['employee_id'];
      },$dt_dtl);

      $search = array_search($v->employee_id,$map_e);

      if(count($dt_dtl)==0 || $search===false){

        $emp = Employee::exclude(['attachment_1','attachment_2'])->where("id",$v->employee_id)->first();

        array_push($dt_dtl,[
          "salary_paid_id"        => $model_query->id,
          "employee_id"           => $v->employee_id,
          // "standby_nominal"       => 0,
          "sb_gaji"               => 0,
          "sb_makan"              => 0,
          "sb_dinas"              => 0,
          "salary_bonus_nominal"  => 0 + $v->nominal,
        ]);
      }else{
        $dt_dtl[$search]['salary_bonus_nominal']+=$v->nominal;
      }
      $v->salary_paid_id = $model_query->id;
      $v->save();
    }

    foreach ($dt_dtl as $k => $v) {
      SalaryPaidDtl::insert($v);
    }

  }

  public function pdfPreview(Request $request){
    MyAdmin::checkScope($this->permissions, 'salary_paid.preview_file');

    set_time_limit(0);
    $sp = SalaryPaid::where("id",$request->id)->first();

    $data = SalaryPaidDtl::where('salary_paid_id',$request->id)->with(["employee"=>function($q1){
      $q1->with('bank');
    }])->orderBy(function($q){
      $q->from("employee_mst")
      ->select("name")
      ->whereColumn("id","employee_id");
    },'asc')->get()->toArray();

    $info = [
      "ttl_sb_gaji"=>0,
      "ttl_sb_makan"=>0,
      "ttl_sb_dinas"=>0,
      "ttl_bonus"=>0,
      "ttl_all"=>0,
      "now"=>date("d-m-Y H:i:s"),
      "periode"=>"[".$sp->period_part."]".date("m-Y",strtotime($sp->period_end))
    ];

    foreach ($data as $k => $v) {
      $sg = $data[$k]["sb_gaji"];
      $sm = $data[$k]["sb_makan"];
      $sd = $data[$k]["sb_dinas"];
      $sbn = $data[$k]["salary_bonus_nominal"];
      $ttl = $sg + $sm + $sd + $sbn;

      $info["ttl_sb_gaji"] += $sg;
      $info["ttl_sb_makan"] += $sm;
      $info["ttl_sb_dinas"] += $sd;
      $info["ttl_bonus"] += $sbn;
      $info["ttl_all"] += $ttl;
      
      $data[$k]["sb_gaji"] = number_format($sg,0,",",".");
      $data[$k]["sb_makan"] = number_format($sm,0,",",".");
      $data[$k]["sb_dinas"] = number_format($sm,0,",",".");
      $data[$k]["salary_bonus_nominal"] = number_format($sbn,0,",",".");
      $data[$k]["total"] = number_format($ttl,0,",",".");
    }
    
    $info["ttl_sb_gaji"]=number_format($info["ttl_sb_gaji"],0,",",".");
    $info["ttl_sb_makan"]=number_format($info["ttl_sb_makan"],0,",",".");
    $info["ttl_sb_dinas"]=number_format($info["ttl_sb_dinas"],0,",",".");
    $info["ttl_bonus"]=number_format($info["ttl_bonus"],0,",",".");
    $info["ttl_all"]=number_format($info["ttl_all"],0,",",".");

    $blade= 'pdf.salary_paid';

    $date = new \DateTime();
    $filename = $date->format("YmdHis");
    PDF::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
    $pdf = PDF::loadView($blade, ["data"=>$data,"info"=>$info])->setPaper('a4', 'portrait');


    $mime = MyLib::mime("pdf");
    $bs64 = base64_encode($pdf->download($filename . "." . $mime["ext"]));

    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }

  public function excelDownload(Request $request){
    MyAdmin::checkScope($this->permissions, 'salary_paid.preview_file');

    set_time_limit(0);
    $sp = SalaryPaid::where("id",$request->id)->first();

    $data = SalaryPaidDtl::where('salary_paid_id',$request->id)->with(["employee"=>function($q1){
      $q1->with('bank');
    }])->orderBy(function($q){
      $q->from("employee_mst")
      ->select("name")
      ->whereColumn("id","employee_id");
    },'asc')->get()->toArray();

    $info = [
      "ttl_sb_gaji"=>0,
      "ttl_sb_makan"=>0,
      "ttl_sb_dinas"=>0,
      "ttl_bonus"=>0,
      "ttl_all"=>0,
      "now"=>date("d-m-Y H:i:s"),
      "periode"=>date("m-Y",strtotime($sp->period_end))."[".$sp->period_part."]"
    ];

    foreach ($data as $k => $v) {
      $sg = $data[$k]["sb_gaji"];
      $sm = $data[$k]["sb_makan"];
      $sd = $data[$k]["sb_dinas"];
      $sbn = $data[$k]["salary_bonus_nominal"];
      $ttl = $sg + $sm + $sd + $sbn;

      $info["ttl_sb_gaji"] += $sg;
      $info["ttl_sb_makan"] += $sm;
      $info["ttl_sb_dinas"] += $sd;
      $info["ttl_bonus"] += $sbn;
      $info["ttl_all"] += $ttl;

      $data[$k]["total"] =$ttl;
    }
    

    $date = new \DateTime();
    $filename=env("app_name").'-salary_paid-'.$info["periode"]."-".$date->format("YmdHis");

    $mime=MyLib::mime("xlsx");

    $blade= 'excel.salary_paid';

    $columnFormats = [
        // 'D' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT,
        // 'E' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT,
        // 'D' => '@',
        // 'E' => '@',
        'D' => '0',
        'E' => '0',
        // Add more columns as needed
    ];

    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$data,"info"=>$info],$blade, $columnFormats), $mime["exportType"]));

    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }

  public function excelDownload2(Request $request){
    MyAdmin::checkScope($this->permissions, 'salary_paid.preview_file');

    set_time_limit(0);
    $sp = SalaryPaid::where("id",$request->id)->first();

    $data = SalaryPaidDtl::where('salary_paid_id',$request->id)->with(["employee"=>function($q1){
      $q1->with('bank');
    }])->orderBy(function($q){
      $q->from("employee_mst")
      ->select("name")
      ->whereColumn("id","employee_id");
    },'asc')->get()->toArray();

    $info = [
      "ttl_sb_gaji"=>0,
      "ttl_sb_makan"=>0,
      "ttl_sb_dinas"=>0,
      "ttl_bonus"=>0,
      "ttl_all"=>0,
      "now"=>date("d-m-Y H:i:s"),
      "periode"=>date("m-Y",strtotime($sp->period_end))."[".$sp->period_part."]"
    ];

    foreach ($data as $k => $v) {
      $sg = $data[$k]["sb_gaji"];
      $sm = $data[$k]["sb_makan"];
      $sd = $data[$k]["sb_dinas"];
      $sbn = $data[$k]["salary_bonus_nominal"];
      if($sbn<0){
        $diff = $sg+$sm+$sd+$sbn;
        if( $diff == 0){
          $sg = $sm = $sd=0;
        }else{
          $sg = $diff;
          $sm = $sd = 0;
        }
      }

      $data[$k]["sb_gaji"]  = $sg;
      $data[$k]["sb_makan"] = $sm;
      $data[$k]["sb_dinas"] = $sd;

      $ttl = $sg + $sm + $sd;

      $info["ttl_sb_gaji"] += $sg;
      $info["ttl_sb_makan"] += $sm;
      $info["ttl_sb_dinas"] += $sd;
      $info["ttl_all"] += $ttl;

      $data[$k]["total"] =$ttl;
    }
    

    $date = new \DateTime();
    $filename=env("app_name").'-salary_paid-'.$info["periode"]."-".$date->format("YmdHis");

    $mime=MyLib::mime("xlsx");

    $blade= 'excel.salary_paid2';

    $columnFormats = [
        // 'D' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT,
        // 'E' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT,
        // 'D' => '@',
        // 'E' => '@',
        'D' => '0',
        'E' => '0',
        // Add more columns as needed
    ];

    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$data,"info"=>$info],$blade, $columnFormats), $mime["exportType"]));

    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }
}
