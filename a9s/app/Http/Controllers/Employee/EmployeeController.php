<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Helpers\MyLib;

use App\Models\MySql\Employee;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\EmployeeRequest;

use App\Http\Resources\MySql\EmployeeResource;
use App\Http\Resources\MySql\IsUserResource;

class EmployeeController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'employee_mst';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'employee.views');

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

      $list_to_like = ["id","name","role","ktp_no","sim_no","phone_number","rek_no"];

      // $list_to_like_user = [
      //   ["val_name","val_user"],
      //   ["val1_name","val1_user"],
      //   ["val2_name","val2_user"],
      //   ["req_deleted_name","req_deleted_user"],
      //   ["deleted_name","deleted_user"],
      // ];

      

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists,$list_to_like){
          foreach ($list_to_like as $key => $v) {
            if (isset($like_lists[$v])) {
              $q->orWhere($v, "like", $like_lists[$v]);
            }
          }

          // foreach ($list_to_like_user as $key => $v) {
          //   if (isset($like_lists[$v[0]])) {
          //     $q->orWhereIn($v[1], function($q2)use($like_lists,$v) {
          //       $q2->from('is_users')
          //       ->select('id')->where("username",'like',$like_lists[$v[0]]);          
          //     });
          //   }
          // }

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
      $model_query = $model_query->orderBy('name', 'asc')->orderBy('id','DESC');
    }
    
    $filter_status = $request->filter_status;
    
    if($filter_status=="available"){
      $model_query = $model_query->where("deleted",0)->where("val",1);
    }

    if($filter_status=="unapprove"){
      $model_query = $model_query->where("deleted",0)->where(function($q){
       $q->where("val",0); 
      });
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    // if($filter_status=="req_deleted"){
    //   $model_query = $model_query->where("deleted",0);
    // }

    $model_query = $model_query->exclude(["attachment_1"]);

    $model_query = $model_query->with(['val_by','bank','deleted_by'])->get();

    return response()->json([
      // "data"=>EmployeeResource::collection($employees->keyBy->id),
      "data" => EmployeeResource::collection($model_query),
    ], 200);
  }

  public function show(EmployeeRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'employee.view');
    
    $model_query = Employee::with(['val_by','bank','deleted_by'])->find($request->id);
    return response()->json([
      "data" => new EmployeeResource($model_query),
    ], 200);
  }

  public function store(EmployeeRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'employee.create');
    // set_time_limit(0);

    DB::beginTransaction();
    $t_stamp = date("Y-m-d H:i:s");
    try {
      $ktp_no = MyLib::emptyStrToNull($request->ktp_no);
      if($ktp_no!=null){
        $emp = Employee::exclude(['attachment_1','attachment_2'])->whereNotNull("ktp_no")->where('ktp_no',$ktp_no)->first();
        if($emp)
        throw new \Exception("No KTP Telah Terdaftar",1);
      }

      $sim_no = MyLib::emptyStrToNull($request->sim_no);
      if($sim_no!=null){
        $emp = Employee::exclude(['attachment_1','attachment_2'])->whereNotNull("sim_no")->where('sim_no',$sim_no)->first();
        if($emp)
        throw new \Exception("No SIM Telah Terdaftar",1);
      }

      $model_query                = new Employee();

      if($request->hasFile('attachment_1')){
        $file = $request->file('attachment_1');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $blobFile = base64_encode(file_get_contents($path));
        $model_query->attachment_1 = $blobFile;
        $model_query->attachment_1_type = $fileType;
      }

      $model_query->name          = $request->name;
      $model_query->role          = $request->role;
      $model_query->ktp_no        = $ktp_no;
      $model_query->sim_no        = $sim_no;
      $model_query->bank_id       = $request->bank_id;
      $model_query->rek_no        = MyLib::emptyStrToNull($request->rek_no);
      $model_query->rek_name      = MyLib::emptyStrToNull($request->rek_name);
      $model_query->phone_number  = MyLib::emptyStrToNull($request->phone_number);
      $model_query->created_at    = $t_stamp;
      $model_query->created_user  = $this->admin_id;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;

      $model_query->birth_date    = MyLib::emptyStrToNull($request->birth_date);
      $model_query->birth_place   = MyLib::emptyStrToNull($request->birth_place);
      $model_query->tmk           = MyLib::emptyStrToNull($request->tmk);
      $model_query->address       = MyLib::emptyStrToNull($request->address);
      $model_query->status        = MyLib::emptyStrToNull($request->status);

      $model_query->bpjs_kesehatan   = $request->bpjs_kesehatan;
      $model_query->bpjs_jamsos      = $request->bpjs_jamsos;

      $model_query->save();

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
    MyAdmin::checkScope($this->permissions, 'employee.modify');
    // set_time_limit(0);
    $t_stamp = date("Y-m-d H:i:s");
    $attachment_1_preview = $request->attachment_1_preview;
    $fileType = null;
    $blobFile = null;
    $change = 0;
    DB::beginTransaction();
    try {
      $ktp_no = MyLib::emptyStrToNull($request->ktp_no);
      if($ktp_no!=null){
        $emp = Employee::exclude(['attachment_1','attachment_2'])->where("id","!=",$request->id)->whereNotNull("ktp_no")->where('ktp_no',$ktp_no)->first();
        if($emp)
        throw new \Exception("No KTP Telah Terdaftar",1);
      }

      $sim_no = MyLib::emptyStrToNull($request->sim_no);
      if($sim_no!=null){
        $emp = Employee::exclude(['attachment_1','attachment_2'])->where("id","!=",$request->id)->whereNotNull("sim_no")->where('sim_no',$sim_no)->first();
        if($emp)
        throw new \Exception("No SIM Telah Terdaftar",1);
      }

      $model_query                = Employee::where("id",$request->id)->lockForUpdate()->first();
      
      if($model_query->id==1){
        throw new \Exception("Izin Ubah Ditolak",1);
      }

      if($model_query->val==1)
      throw new \Exception("Data sudah tervalidasi",1);

      $SYSOLD                     = clone($model_query);

      $fileType = $model_query->attachment_1_type;

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

      if(!$model_query->val_at){
        $model_query->name          = $request->name;
      }
      $model_query->role          = $request->role;
      $model_query->ktp_no        = $ktp_no;
      $model_query->sim_no        = $sim_no;
      $model_query->bank_id       = $request->bank_id;
      $model_query->rek_no        = MyLib::emptyStrToNull($request->rek_no);
      $model_query->rek_name      = MyLib::emptyStrToNull($request->rek_name);
      $model_query->phone_number  = MyLib::emptyStrToNull($request->phone_number);
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;

      $model_query->birth_date    = MyLib::emptyStrToNull($request->birth_date);
      $model_query->birth_place   = MyLib::emptyStrToNull($request->birth_place);
      $model_query->tmk           = MyLib::emptyStrToNull($request->tmk);
      $model_query->address       = MyLib::emptyStrToNull($request->address);
      $model_query->status        = MyLib::emptyStrToNull($request->status);

      $model_query->bpjs_kesehatan   = $request->bpjs_kesehatan;
      $model_query->bpjs_jamsos      = $request->bpjs_jamsos;

      $model_query->save();
      
      if($change){
        Employee::where("id",$request->id)->update([
          "attachment_1"      => $blobFile
        ]);
      }

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys($this->syslog_db,$request->id,"update",$SYSNOTE);

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
    MyAdmin::checkScope($this->permissions, 'employee.remove');

    DB::beginTransaction();
    try {
      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query = Employee::exclude(['attachment_1','attachment_2'])->where("id",$request->id)->lockForUpdate()->first();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      if($model_query->id==1){
        throw new \Exception("Izin Hapus Ditolak",1);
      }
  
      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();

      MyLog::sys($this->syslog_db,$request->id,"delete");

      DB::commit();
      return response()->json([
        "message"       => "Proses hapus data berhasil",
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
    MyAdmin::checkScope($this->permissions, 'employee.val');

    $rules = [
      'id' => "required|exists:\App\Models\MySql\Employee,id",
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
      $model_query = Employee::exclude(['attachment_1','attachment_2'])->lockForUpdate()->find($request->id);
      if($model_query->val){
        throw new \Exception("Data Sudah Tervalidasi",1);
      }
      
      if(!$model_query->val){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
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
