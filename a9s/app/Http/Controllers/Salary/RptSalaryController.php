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

use App\Models\MySql\RptSalary;
use App\Models\MySql\RptSalaryDtl;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\RptSalaryRequest;

use App\Http\Resources\IsUserResource;
use App\Http\Resources\MySql\RptSalaryDtlResource;
use App\Http\Resources\MySql\RptSalaryResource;
use App\Models\MySql\Employee;
use App\Models\MySql\PotonganTrx;
use App\Models\MySql\SalaryBonus;
use App\Models\MySql\SalaryPaid;
use App\Models\MySql\StandbyTrx;
use App\Models\MySql\TrxTrp;

class RptSalaryController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'rpt_salary';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'rpt_salary.views');
 
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
    $model_query = RptSalary::offset($offset)->limit($limit);

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
    
    $model_query = $model_query->orderBy('period_end','DESC');

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
      "data" => RptSalaryResource::collection($model_query),
    ], 200);
  }

  public function show(RptSalaryRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'rpt_salary.view');

    // return response()->json([
    //   "message" => "Hanya yang membuat transaksi yang boleh melakukan pergantian atau konfirmasi data",
    // ], 400);

    $model_query = RptSalary::with([
    'details'=>function ($q){
      $q->with('employee')->orderBy(function($q){
        $q->from("employee_mst")
        ->select("name")
        ->whereColumn("id","employee_id");
      },'asc');
    }
    //end for details2
    ])->with(['val1_by'])->find($request->id);

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
      "data" => new RptSalaryResource($model_query),
    ], 200);
  }

  public function store(RptSalaryRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'rpt_salary.create');

    $rollback_id = -1;
    $t_stamp = date("Y-m-d H:i:s");

    $date = new \DateTime($request->period_end."-01");
    $date->modify('last day of this month');
    $last_day_of_month=$date->format('Y-m-d');

    $sp_before=RptSalary::orderBy("id","desc")->first();
    if($sp_before && $sp_before->val1==0)
    throw new MyException([ "message" => "Harap Validasi Periode Sebelumnya" ], 400);
  
    if(RptSalary::where('period_end',$last_day_of_month)->first())
    throw new MyException([ "period_end" => ["Periode Sudah Terdaftar"] ], 422);

    if(count(SalaryPaid::where("period_end","like",$request->period_end.'%')->where('val1',1)->get())!=2)
    throw new MyException([ "message" => "Salary Paid Butuh 2 Periode Untuk Melanjutkan" ], 400);

    DB::beginTransaction();
    try {
      // if(RptSalary::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar",1);

      $model_query                  = new RptSalary();

      $model_query->period_end      = $last_day_of_month;

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
        "details"=>RptSalaryDtlResource::collection(
          RptSalaryDtl::where("rpt_salary_id",$model_query->id)->with('employee')->orderBy(function($q){
            $q->from("employee_mst")
            ->select("name")
            ->whereColumn("id","employee_id");
          },'asc')->get()
        )
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE rpt_salary AUTO_INCREMENT = $rollback_id");
      
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

  public function update(RptSalaryRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'rpt_salary.modify');

    $t_stamp = date("Y-m-d H:i:s");
    
    DB::beginTransaction();
    try {
      $SYSNOTES=[];
      $model_query = RptSalary::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);
      
      if( $model_query->val==1 )
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      array_push( $SYSNOTES ,"Remove All Details \n");
      RptSalaryDtl::where('rpt_salary_id',$model_query->id)->lockForUpdate()->delete();

      array_push( $SYSNOTES ,"Change rpt_salary_id field in standby trx and salary bonus to ".$model_query->id." and insert new Details \n");
      $this->reInsertDetails($model_query);

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      array_unshift( $SYSNOTES , $SYSNOTE );            
      MyLog::sys($this->syslog_db,$request->id,"update",implode("\n",$SYSNOTES));

      DB::commit();
      return response()->json([
        "message" => "Proses Generate data berhasil",
        "updated_at"=>$t_stamp,
        "details"=>RptSalaryDtlResource::collection(
          RptSalaryDtl::where("rpt_salary_id",$model_query->id)->with('employee')->orderBy(function($q){
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

  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['rpt_salary.val1','rpt_salary.val2','rpt_salary.val3']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\RptSalary,id",
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
      $model_query = RptSalary::find($request->id);
      $run_val = 0;
      if(MyAdmin::checkScope($this->permissions, 'rpt_salary.val1',true) && !$model_query->val1){
        $run_val++;
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }

      // if(MyAdmin::checkScope($this->permissions, 'rpt_salary.val2',true) && !$model_query->val2){
      //   $run_val++;
      //   $model_query->val2 = 1;
      //   $model_query->val2_user = $this->admin_id;
      //   $model_query->val2_at = $t_stamp;
      // }

      // if(MyAdmin::checkScope($this->permissions, 'rpt_salary.val3',true) && !$model_query->val3){
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

  function reInsertDetails($model_query){

    $data = [];
    $smp_bulan = substr($model_query->period_end,0,7);
    $salary_paid = SalaryPaid::where("period_end","like",$smp_bulan.'%')->where('val1',1)->orderBy("id","asc")->get();
    foreach ($salary_paid as $key => $sp) {
      foreach ($sp->details as $key => $spd) {
        $map_e = array_map(function($x){
          return $x['employee_id'];
        },$data);

        $search = array_search($spd->employee_id,$map_e);

        if(count($data)==0 || $search===false){
          $emp = $spd->employee;
          array_push($data,[
            "rpt_salary_id"         => $model_query->id,
            "employee_id"           => $emp->id,
            "employee_name"         => $emp->name,
            "employee_role"         => $emp->role,
            "employee_birth_place"  => $emp->birth_place,
            "employee_birth_date"   => $emp->birth_date,
            "employee_tmk"          => $emp->tmk,
            "employee_ktp_no"       => $emp->ktp_no,
            "employee_address"      => $emp->address,
            "employee_status"       => $emp->status,
            "employee_rek_no"       => $emp->rek_no,
            "employee_bank_name"    => $emp->bank ? $emp->bank->code : "",
            
            "sb_gaji"               => $sp->period_part==1 ? $spd->sb_gaji : 0,
            "sb_makan"              => $sp->period_part==1 ? $spd->sb_makan : 0,
            "sb_dinas"              => $sp->period_part==1 ? $spd->sb_dinas : 0,
            "sb_gaji_2"             => $sp->period_part==2 ? $spd->sb_gaji : 0,
            "sb_makan_2"            => $sp->period_part==2 ? $spd->sb_makan : 0,
            "sb_dinas_2"            => $sp->period_part==2 ? $spd->sb_dinas : 0,
            "uj_gaji"               => 0,
            "uj_makan"              => 0,
            "uj_dinas"              => 0,
            "nominal_cut"           => 0,
            "salary_bonus_nominal"  => $spd->salary_bonus_nominal
          ]);
        }else{
          $data[$search]["sb_gaji"] += $sp->period_part==1 ? $spd->sb_gaji:0;
          $data[$search]["sb_makan"] += $sp->period_part==1 ? $spd->sb_makan:0;
          $data[$search]["sb_dinas"] += $sp->period_part==1 ?$spd->sb_dinas:0;

          $data[$search]["sb_gaji_2"] += $sp->period_part==2 ? $spd->sb_gaji :0;
          $data[$search]["sb_makan_2"] += $sp->period_part==2 ? $spd->sb_makan :0;
          $data[$search]["sb_dinas_2"] += $sp->period_part==2 ? $spd->sb_dinas :0;

          $data[$search]["salary_bonus_nominal"] += $spd->salary_bonus_nominal;
        }

      }
    }

    $tt = TrxTrp::whereNotNull("pv_id")
    ->where("req_deleted",0)
    ->where("deleted",0)
    ->where('val',1)
    ->where('val1',1)
    ->where('val2',1)
    ->where(function ($q) use($model_query,$smp_bulan) {
      $q->where(function ($q1)use($model_query,$smp_bulan){
        $q1->where("payment_method_id",1);       
        $q1->where("received_payment",0);                  
        $q1->where("tanggal",">=",$smp_bulan."-01");                  
        $q1->where("tanggal","<=",$model_query->period_end);                  
      });

      $q->orWhere(function ($q1)use($model_query,$smp_bulan){
        $q1->where("payment_method_id",2);
        $q1->where(function ($q2)use($model_query,$smp_bulan){
          // supir dan kernet dipisah krn asumsi di tf di waktu atau bahkan hari yang berbeda
          $q2->where(function ($q3) use($model_query,$smp_bulan) {            
            $q3->where("rp_supir_at",">=",$smp_bulan."-01 00:00:00");                  
            $q3->where("rp_supir_at","<=",$model_query->period_end." 23:59:59");                  
          });
          $q2->orWhere(function ($q3) use($model_query,$smp_bulan) {
            $q3->where("rp_kernet_at",">=",$smp_bulan."-01 00:00:00");                  
            $q3->where("rp_kernet_at","<=",$model_query->period_end." 23:59:59");                  
          });
        });                         
      });
    })->get();


    foreach ($tt as $k => $v) {
      $smd = $v->uj_details2;

      $nominal_s = 0;
      $uj_gaji_s = 0;
      $uj_makan_s = 0;
      $uj_dinas_s = 0;

      $nominal_k = 0;
      $uj_gaji_k = 0;
      $uj_makan_k = 0;
      $uj_dinas_k = 0;

      foreach ($smd as $k1 => $v1) {
        $amount = $v1->amount * $v1->qty;
        if($v1->xfor == 'Supir'){
          $nominal_s += $amount;
          if($v1->ac_account_code=='01.510.001') $uj_gaji_s += $amount;
          if($v1->ac_account_code=='01.510.005') $uj_makan_s += $amount;
          if($v1->ac_account_code=='01.575.002') $uj_dinas_s += $amount;
        }

        if($v1->xfor == 'Kernet'){
          $nominal_k += $amount;
          if($v1->ac_account_code=='01.510.001') $uj_gaji_k += $amount;
          if($v1->ac_account_code=='01.510.005') $uj_makan_k += $amount;          
          if($v1->ac_account_code=='01.575.002') $uj_dinas_k += $amount;          
        }
      }


      if($v->supir_id){

        $map_s = array_map(function($x){
          return $x['employee_id'];
        },$data);

        $search = array_search($v->supir_id,$map_s);

        if(count($data)==0 || $search===false){

          $emp = $v->employee_s;
          array_push($data,[
            "rpt_salary_id"         => $model_query->id,
            "employee_id"           => $emp->id,
            "employee_name"         => $emp->name,
            "employee_role"         => $emp->role,
            "employee_birth_place"  => $emp->birth_place,
            "employee_birth_date"   => $emp->birth_date,
            "employee_tmk"          => $emp->tmk,
            "employee_ktp_no"       => $emp->ktp_no,
            "employee_address"      => $emp->address,
            "employee_status"       => $emp->status,
            "employee_rek_no"       => $emp->rek_no,
            "employee_bank_name"    => $emp->bank ? $emp->bank->code : "",
            
            "sb_gaji"               => 0,
            "sb_makan"              => 0,
            "sb_dinas"              => 0,
            "sb_gaji_2"             => 0,
            "sb_makan_2"            => 0,
            "sb_dinas_2"            => 0,
            "uj_gaji"               => $uj_gaji_s,
            "uj_makan"              => $uj_makan_s,
            "uj_dinas"              => $uj_dinas_s,
            "nominal_cut"           => 0,
            "salary_bonus_nominal"  => 0
          ]);
        }else{
          // $dt_dtl[$search]['standby_nominal']+=$nominal_s;
          $data[$search]['uj_gaji']+=$uj_gaji_s;
          $data[$search]['uj_makan']+=$uj_makan_s;
          $data[$search]['uj_dinas']+=$uj_dinas_s;
        }
      }

      if($v->kernet_id){

        $map_k = array_map(function($x){
          return $x['employee_id'];
        },$data);

        $search = array_search($v->kernet_id,$map_k);

        if(count($data)==0 || $search===false){
          $emp = $v->employee_k;
          array_push($data,[
            "rpt_salary_id"         => $model_query->id,
            "employee_id"           => $emp->id,
            "employee_name"         => $emp->name,
            "employee_role"         => $emp->role,
            "employee_birth_place"  => $emp->birth_place,
            "employee_birth_date"   => $emp->birth_date,
            "employee_tmk"          => $emp->tmk,
            "employee_ktp_no"       => $emp->ktp_no,
            "employee_address"      => $emp->address,
            "employee_status"       => $emp->status,
            "employee_rek_no"       => $emp->rek_no,
            "employee_bank_name"    => $emp->bank ? $emp->bank->code : "",
            
            "sb_gaji"               => 0,
            "sb_makan"              => 0,
            "sb_dinas"              => 0,
            "sb_gaji_2"             => 0,
            "sb_makan_2"            => 0,
            "sb_dinas_2"            => 0,
            "uj_gaji"               => $uj_gaji_k,
            "uj_makan"              => $uj_makan_k,
            "uj_dinas"              => $uj_dinas_k,
            "nominal_cut"           => 0,
            "salary_bonus_nominal"  => 0
          ]);
        }else{
          // $dt_dtl[$search]['standby_nominal']+=$nominal_k;
          $data[$search]['uj_gaji']+=$uj_gaji_k;
          $data[$search]['uj_makan']+=$uj_makan_k;
          $data[$search]['uj_dinas']+=$uj_dinas_k;
        }
      }
    }


    $pt = PotonganTrx::where('created_at',"<=",$model_query->period_end." 23:59:59")
    ->where('created_at',">=",$smp_bulan."-01 00:00:00")
    ->where('val',1)
    ->where('deleted',0)
    ->get();
    
    foreach($pt as $v){

      $map_e = array_map(function($x){
        return $x['employee_id'];
      },$data);

      $search = array_search($v->potongan_mst->employee_id,$map_e);

      if(count($data)==0 || $search===false){

        $emp = $v->potongan_mst->employee;

        array_push($data,[
          "rpt_salary_id"         => $model_query->id,
          "employee_id"           => $emp->id,
          "employee_name"         => $emp->name,
          "employee_role"         => $emp->role,
          "employee_birth_place"  => $emp->birth_place,
          "employee_birth_date"   => $emp->birth_date,
          "employee_tmk"          => $emp->tmk,
          "employee_ktp_no"       => $emp->ktp_no,
          "employee_address"      => $emp->address,
          "employee_status"       => $emp->status,
          "employee_rek_no"       => $emp->rek_no,
          "employee_bank_name"    => $emp->bank ? $emp->bank->code : "",
          
          "sb_gaji"               => 0,
          "sb_makan"              => 0,
          "sb_dinas"              => 0,
          "sb_gaji_2"             => 0,
          "sb_makan_2"            => 0,
          "sb_dinas_2"            => 0,
          "uj_gaji"               => 0,
          "uj_makan"              => 0,
          "uj_dinas"              => 0,
          "nominal_cut"           => $v->nominal_cut,
          "salary_bonus_nominal"  => 0
        ]);
      }else{
        $data[$search]['nominal_cut']+=$v->nominal_cut;
      }
    }

    foreach ($data as $k => $v) {
      $kerajinan_s=400000;
      $kerajinan_k=200000;

      if($v["sb_gaji_2"]!=0 || $v["sb_makan_2"]!=0  || $v["sb_gaji"]!=0 || $v["sb_makan"]!=0 || $v["uj_gaji"]!=0 || $v["uj_makan"]!=0){
        $empx = Employee::where("id",$v['employee_id'])->exclude(['attachement_1','attachement_2'])->first();
        if($empx->deleted==0)
        $v['salary_bonus_nominal'] += $empx->role=='Supir' ? $kerajinan_s : $kerajinan_k;
      }

      RptSalaryDtl::insert($v);
    }
  }

  public function excelDownload(Request $request){
    MyAdmin::checkScope($this->permissions, 'rpt_salary.preview_file');

    set_time_limit(0);
    $sp = RptSalary::where("id",$request->id)->first();

    // $data = RptSalaryDtl::where('rpt_salary_id',$request->id)->with(["employee"=>function($q1){
    //   $q1->with('bank');
    // }])->orderBy(function($q){
    //   $q->from("employee_mst")
    //   ->select("name")
    //   ->whereColumn("id","employee_id");
    // },'asc')->get()->toArray();

    $data = RptSalaryDtl::where('rpt_salary_id',$request->id)->orderBy("employee_name",'asc')->get()->toArray();

    $info = [
      "ttl_sb_gaji"     => 0,
      "ttl_sb_makan"    => 0,
      "ttl_sb_dinas"    => 0,
      "ttl_sb_gaji_2"   => 0,
      "ttl_sb_makan_2"  => 0,
      "ttl_sb_dinas_2"  => 0,
      "ttl_uj_gaji"     => 0,
      "ttl_uj_makan"    => 0,
      "ttl_uj_dinas"    => 0,
      "ttl_nominal_cut" => 0,
      "ttl_bonus"       => 0,
      "ttl_all"         => 0,
      "now"             => date("d-m-Y H:i:s"),
      "periode"         => date("m-Y",strtotime($sp->period_end))
    ];

    foreach ($data as $k => $v) {
      $sg = $data[$k]["sb_gaji"];
      $sm = $data[$k]["sb_makan"];
      $sd = $data[$k]["sb_dinas"];
      $sg2 = $data[$k]["sb_gaji_2"];
      $sm2 = $data[$k]["sb_makan_2"];
      $sd2 = $data[$k]["sb_dinas_2"];
      $ug = $data[$k]["uj_gaji"];
      $um = $data[$k]["uj_makan"];
      $ud = $data[$k]["uj_dinas"];
      $nc = $data[$k]["nominal_cut"];
      $sbn = $data[$k]["salary_bonus_nominal"];
      $ttl = $sg + $sm + $sd +$sg2 + $sm2 + $sd2 + $ug + $um + $ud - $nc + $sbn;

      $info["ttl_sb_gaji"] += $sg;
      $info["ttl_sb_makan"] += $sm;
      $info["ttl_sb_dinas"] += $sd;
      $info["ttl_sb_gaji_2"] += $sg2;
      $info["ttl_sb_makan_2"] += $sm2;
      $info["ttl_sb_dinas_2"] += $sd2;
      $info["ttl_uj_gaji"] += $ug;
      $info["ttl_uj_makan"] += $um;
      $info["ttl_uj_dinas"] += $ud;
      $info["ttl_nominal_cut"] += $nc;
      $info["ttl_bonus"] += $sbn;
      $info["ttl_all"] += $ttl;

      $data[$k]["total"] = $ttl;
    }
    

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-rpt_salary-'.$info["periode"];

    $mime=MyLib::mime("xlsx");

    $blade= 'excel.rpt_salary';

    $columnFormats = [
      'G' => '0',
      'J' => '0',
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
