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

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MyReport;

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
    
    // $model_query = $model_query->orderBy('id', 'DESC');

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

      $list_to_like = ["id"];

      $list_to_like_employee = [
        ["employee_name","employee_id"],
      ];

      

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists,$list_to_like,$list_to_like_employee){
          foreach ($list_to_like as $key => $v) {
            if (isset($like_lists[$v])) {
              $q->orWhere($v, "like", $like_lists[$v]);
            }
          }

          foreach ($list_to_like_employee as $key => $v) {
            if (isset($like_lists[$v[0]])) {
              $q->orWhereIn($v[1], function($q2)use($like_lists,$v) {
                $q2->from('employee_mst')
                ->select('id')->where("name",'like',$like_lists[$v[0]]);          
              });
            }
          }

        });        
      }     
    }

    // ==============
    // Model Filter
    // ==============

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
          if(array_search($value['key'],['employee_name','employee_ktp_no','employee_sim_no'])!==false){
            $model_query = MyLib::queryOrderP1($model_query,"employee","employee_id",$value['key'],$filter_model[$value['key']]["sort_type"],"employee_mst");
          }else{
            $model_query = $model_query->orderBy($value['key'], $filter_model[$value['key']]["sort_type"]);
            if (count($first_row) > 0) {
              $sort_symbol = $filter_model[$value['key']]["sort_type"] == "desc" ? "<=" : ">=";
              $model_query = $model_query->where($value['key'],$sort_symbol,$first_row[$value['key']]);
            }
          }
        }
      }

      $model_query = $model_query->where(function ($q)use($filter_model,$request){

        foreach ($filter_model as $key => $value) {
          if(!isset($value['type'])) continue;

          if(array_search($key,['status'])!==false){
          }else if(array_search($key,['employee_name','employee_ktp_no','employee_sim_no'])!==false){
            MyLib::queryCheckP1("employee",$value,$key,$q,'employee_mst');
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
      $model_query = $model_query->orderBy('id', 'desc');
    }
    
    $filter_status = $request->filter_status;
    
    if($filter_status=="done"){
      $model_query = $model_query->where("deleted",0)->where("val1",1)->where("val2",1)->where("val3",1)->whereNotNull("salary_paid_id");
    }

    if($filter_status=="undone"){
      $model_query = $model_query->where("deleted",0)->where(function($q){
       $q->where("val1",0)->orWhere("val2",0)->orWhere("val3",0)->orWhereNull("salary_paid_id"); 
      });
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    // ==============
    // Model Filter
    // ==============
    $model_query = $model_query->exclude(['attachment_1']);
    $model_query = $model_query->with(['employee','deleted_by'])->get();
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
    ])->with(['val1_by','val2_by','val3_by','deleted_by'])->find($request->id);

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
        "attachment_1_type"=>$model_query->attachment_1_type
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
      
      if( $model_query->val3==1 )
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      if( $model_query->trx_trp_id > 0 )
      throw new \Exception("Data Bersumber Dari Trx Trp Tiket Tidak Dapat Diubah",1);

      // if($model_query->salary_paid_id) 
      // throw new \Exception("Data Sudah Digunakan Dan Tidak Dapat Di Ubah",1);

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

      if($model_query->val2=="0"){
        $model_query->tanggal           = $request->tanggal;
        $model_query->type              = $request->type;
        $model_query->employee_id       = $request->employee_id;
        $model_query->nominal           = $request->nominal;
        $model_query->note              = $request->note;
      }

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
        "attachment_1_type"=>$model_query->attachment_1_type
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

      if($model_query->val3==1)
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      if($model_query->salary_paid_id) 
      throw new \Exception("Data Sudah Digunakan Dan Tidak Dapat Di Hapus",1);

      // if($model_query->ref_id != null){
      //   throw new \Exception("Hapus data ditolak. Data berasal dari transfer",1);
      // }

      // if($model_query->confirmed_by != null){
      //   throw new \Exception("Hapus data ditolak. Data sudah dikonfirmasi",1);
      // }
      
      $SYSOLD                     = clone($model_query);
  
      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys($this->syslog_db,$request->id,"delete",$SYSNOTE);

      // SalaryBonusDtl::where("id_uj",$model_query->id)->delete();
      // $model_query->delete();

      DB::commit();
      return response()->json([
        "message"       => "Proses Hapus data berhasil",
        "deleted"       => $model_query->deleted,
        "deleted_user"  => $model_query->deleted_user,
        "deleted_by"    => $model_query->deleted_user ? new IsUserResource(IsUser::find($model_query->deleted_user)) : null,
        "deleted_at"    => $model_query->deleted_at,
        "deleted_reason"=> $model_query->deleted_reason,

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
    MyAdmin::checkMultiScope($this->permissions, ['salary_bonus.val1','salary_bonus.val2','salary_bonus.val3']);

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
      $SYSOLD                     = clone($model_query);
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

      if(MyAdmin::checkScope($this->permissions, 'salary_bonus.val3',true) && !$model_query->val3){
        $run_val++;
        $model_query->val3 = 1;
        $model_query->val3_user = $this->admin_id;
        $model_query->val3_at = $t_stamp;
      }

      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys($this->syslog_db,$request->id,"approve",$SYSNOTE);

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
        "val3"=>$model_query->val3,
        "val3_user"=>$model_query->val3_user,
        "val3_at"=>$model_query->val3_at,
        "val3_by"=>$model_query->val3_user ? new IsUserResource(IsUser::find($model_query->val3_user)) : null,
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

  public function downloadExcel(Request $request){
    MyAdmin::checkScope($this->permissions, 'salary_bonus.download_file');

    set_time_limit(0);
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    
    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {
      $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      $value['created_at']=date("d-m-Y H:i:s",strtotime($value["created_at"]));
      $value['updated_at']=date("d-m-Y H:i:s",strtotime($value["updated_at"]));
      $value['deleted_at']=date("d-m-Y H:i:s",strtotime($value["deleted_at"]));
      array_push($newDetails,$value);
    }

    // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

    $filter_model = json_decode($request->filter_model,true);
    $tanggal = $filter_model['tanggal'];

    $date_from=date("d-m-Y",strtotime($tanggal['value_1']));
    $date_to=date("d-m-Y",strtotime($tanggal['value_2']));

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-trx_trp_ticket'."[".$date_from."*".$date_to."]";

    $mime=MyLib::mime("xlsx");
    // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.salary_bonus_raw'), $mime["exportType"]));


    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }

}
