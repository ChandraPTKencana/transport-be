<?php

namespace App\Http\Controllers\ExtraMoney;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;

use App\Exceptions\MyException;
use Exception;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\ExtraMoneyTrx;
use App\Models\MySql\IsUser;
use App\Models\MySql\ExtraMoneyDtl;
use App\Models\MySql\ExtraMoneyMst;
use App\Models\MySql\ExtraMoneyTrxDtl;

use App\Http\Requests\MySql\ExtraMoneyTrxRequest;

use App\Http\Resources\MySql\ExtraMoneyTrxResource;
use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\ExtraMoney;

class ExtraMoneyTrxController extends Controller
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
    MyAdmin::checkMultiScope($this->permissions, ['extra_money_trx.create','extra_money_trx.modify']);

    $list_extra_money = \App\Models\MySql\ExtraMoney::selectRaw("*,qty * nominal as total")->where('val1',1)->where('val2',1)->where("deleted",0)->get();
    // $list_extra_money = \App\Models\MySql\ExtraMoney::where("deleted",0)->where('val',1)->where('val1',1)->get();
    $list_vehicle = \App\Models\MySql\Vehicle::where("deleted",0)->get();
    $list_employee = \App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->available()->verified()->whereIn("role",['Supir','Kernet','BLANK'])->get();
    $list_payment_methods = \App\Models\MySql\PaymentMethod::get();
    
    return response()->json([
      "list_vehicle" => $list_vehicle,
      "list_employee" => $list_employee,
      "list_extra_money" => $list_extra_money,
      "list_payment_methods"  => $list_payment_methods,
    ], 200);
  }

  public function loadSqlSrv(Request $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['extra_money_trx.create','extra_money_trx.modify']);

    $online_status = $request->online_status;

    $list_cost_center=[];
    $connectionDB = DB::connection('sqlsrv');
    try {
      if($online_status=="true"){
        
        $list_cost_center = $connectionDB->table("AC_CostCenterNames")
        ->select('CostCenter','Description')
        ->where('CostCenter','like', '112%')
        ->get();
  
        $list_cost_center= MyLib::objsToArray($list_cost_center); 
      }
    } catch (\Exception $e) {
      // return response()->json($e->getMessage(), 400);
    }
    
    return response()->json([
      "list_cost_center" => $list_cost_center,
    ], 200);
  }

  public function index(Request $request, $download = false)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.views');
 
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
    $model_query = new ExtraMoneyTrx();
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

      $list_to_like = ["id","employee_name","no_pol","pvr_id","pvr_no","pv_id","pv_no",
      "cost_center_code","cost_center_desc"];

      $list_to_like_user = [
        ["val1_name","val1_user"],
        ["val2_name","val2_user"],
        ["val3_name","val3_user"],
        ["val4_name","val4_user"],
        ["val5_name","val5_user"],
        ["val6_name","val6_user"],
        ["req_deleted_name","req_deleted_user"],
        ["deleted_name","deleted_user"],
      ];

      $list_to_like_employee = [
        ["employee_name","name"],
      ];

      $list_to_like_extra_money = [
        ["extra_money_xto","xto"],
        ["extra_money_jenis","jenis"]
      ];

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists,$list_to_like,$list_to_like_user,$list_to_like_extra_money,$list_to_like_employee){
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

          foreach ($list_to_like_extra_money as $key => $v) {
            if (isset($like_lists[$v[0]])) {
              $q->orWhereIn('extra_money_id', function($q2)use($like_lists,$v) {
                $q2->from('extra_money')
                ->select('id')->where($v[1],'like',$like_lists[$v[0]]);          
              });
            }
          }

          foreach ($list_to_like_employee as $key => $v) {
            if (isset($like_lists[$v[0]])) {
              $q->orWhereIn('extra_money_trx_id', function($q2)use($like_lists,$v) {
                $q2->from('extra_money_trx')
                ->select('id')->where($v[1],'like',$like_lists[$v[0]]);
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

    if($filter_status=="done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->whereNotNull("pv_no");
    }

    if($filter_status=="undone"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->whereNull("pv_no");
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    if($filter_status=="req_deleted"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",1);
    }

    $model_query = $model_query->with(['val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','deleted_by','req_deleted_by','extra_money','employee','payment_method'])->get();

    return response()->json([
      "data" => ExtraMoneyTrxResource::collection($model_query),
    ], 200);
  }

  public function show(ExtraMoneyTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.view');

    $model_query = ExtraMoneyTrx::with(['val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','deleted_by','req_deleted_by','extra_money','employee','payment_method'])->find($request->id);
    return response()->json([
      "data" => new ExtraMoneyTrxResource($model_query),
    ], 200);
  }

  private $height = 500;
  private $quality = 100;

  public function store(ExtraMoneyTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.create');

    $t_stamp = date("Y-m-d H:i:s");
    $location = null;
    $rollback_id = -1;
    DB::beginTransaction();
    try {

      $model_query                      = new ExtraMoneyTrx();
      $employee = \App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where("id",$request->employee_id)
      ->where("deleted",0)
      ->lockForUpdate()
      ->first();

      if(!$employee)
      throw new \Exception("Pekerja tidak terdaftar",1);

      if($request->payment_method_id == 2){
        if(!$employee->rek_no && $employee->id != 1)
        throw new \Exception("Tidak ada no rekening pekerja",1);
      }

      $extra_money = \App\Models\MySql\ExtraMoney::where("id",$request->extra_money_id)
      ->where("deleted",0)
      ->lockForUpdate()
      ->first();

      if(!$extra_money) 
      throw new \Exception("Silahkan Isi Data Uang Tambahan Dengan Benar",1);

      $model_query->extra_money_id      = $request->extra_money_id;
      $model_query->tanggal             = $request->tanggal;
      $model_query->employee_id         = $employee->id;
      $model_query->employee_rek_no     = $employee->rek_no;
      $model_query->employee_rek_name   = $employee->rek_name;
      $model_query->no_pol              = $request->no_pol;
      $model_query->note_for_remarks    = $request->note_for_remarks;
      
      $model_query->payment_method_id   = $request->payment_method_id;
      
      $model_query->created_at          = $t_stamp;
      $model_query->created_user        = $this->admin_id;

      $model_query->updated_at          = $t_stamp;
      $model_query->updated_user        = $this->admin_id;


      if($request->hasFile('attachment_1')){
        $file = $request->file('attachment_1');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $ext = $file->extension();
        if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
        throw new MyException([ "attachment_1" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);

        $image = Image::read($path)->scale(height: $this->height);
        $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));

        $date = new \DateTime();
        $timestamp = $date->format("Y-m-d H:i:s.v");
        $file_name = md5(preg_replace('/( |-|:)/', '', $timestamp)) . '.' . $ext;
        $location = "/files/extra_money_trx/{$file_name}";
        try {
          ini_set('memory_limit', '256M');
          Image::read($compressedImageBinary)->save(files_path($location));
        } catch (\Exception $e) {
          throw new \Exception("Simpan Foto Gagal");
        }

        // $blob_img_leave = base64_encode($compressedImageBinary); 
        // $blobFile = base64_encode(file_get_contents($path));
        $model_query->attachment_1_loc = $location;
        $model_query->attachment_1_type = $fileType;
      }

      $model_query->save();

      $rollback_id = $model_query->id - 1;

      MyLog::sys("extra_money_trx",$model_query->id,"insert");

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
      DB::statement("ALTER TABLE extra_money_trx AUTO_INCREMENT = $rollback_id");

      if ($location && File::exists(files_path($location)))
      unlink(files_path($location));

      // return response()->json([
      //   "message" => $e->getMessage(),
      //   "code" => $e->getCode(),
      //   "line" => $e->getLine(),
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
        "message" => "Proses tambah data gagal",
      ], 400);
    }
  }

  public function update(ExtraMoneyTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.modify');
    
    $t_stamp        = date("Y-m-d H:i:s");
    $location             = null;
    $attachment_1_preview = $request->attachment_1_preview;
    $fileType             = null;

    DB::beginTransaction();
    try {
      $SYSNOTES=[];
      $model_query = ExtraMoneyTrx::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);
      // array_push( $SYSNOTES ,"Details: \n");

      if($model_query->val1==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);
      
      $employee = \App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where("id",$request->employee_id)
      ->where("deleted",0)
      ->lockForUpdate()
      ->first();

      if(!$employee)
      throw new \Exception("Pekerja tidak terdaftar",1);

      if($request->payment_method_id == 2){
        if(!$employee->rek_no && $employee->id != 1)
        throw new \Exception("Tidak ada no rekening pekerja",1);
      }

      $extra_money = \App\Models\MySql\ExtraMoney::where("id",$request->extra_money_id)
      ->where("deleted",0)
      ->lockForUpdate()
      ->first();

      if(!$extra_money) 
      throw new \Exception("Silahkan Isi Data Uang Tambahan Dengan Benar",1);


      if($request->hasFile('attachment_1')){
        $file = $request->file('attachment_1');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        // $blobFile = base64_encode(file_get_contents($path));
        // $change++;


        $ext = $file->extension();
        if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
        throw new MyException([ "attachment_1" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);

        $image = Image::read($path)->scale(height: $this->height);
        $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));

        $date = new \DateTime();
        $timestamp = $date->format("Y-m-d H:i:s.v");
        $file_name = md5(preg_replace('/( |-|:)/', '', $timestamp)) . '.' . $ext;
        $location = "/files/extra_money_trx/{$file_name}";
        try {
          ini_set('memory_limit', '256M');
          Image::read($compressedImageBinary)->save(files_path($location));
        } catch (\Exception $e) {
          throw new \Exception("Simpan Foto Gagal");
        }

        if (File::exists(files_path($model_query->attachment_1_loc)) && $model_query->attachment_1_loc != null) {
          if (!unlink(files_path($model_query->attachment_1_loc)))
            throw new \Exception("Gagal Hapus Gambar", 1);
        }
        
        $model_query->attachment_1_type   = $fileType;
        $model_query->attachment_1_loc    = $location;

      }

      if (!$request->hasFile('attachment_1') && $attachment_1_preview == null) {
        if (File::exists(files_path($model_query->attachment_1_loc)) && $model_query->attachment_1_loc != null) {
          if (!unlink(files_path($model_query->attachment_1_loc)))
            throw new \Exception("Gagal Hapus Gambar", 1);
        }
        $location = null;
        $fileType = null;
        $model_query->attachment_1_type   = $fileType;
        $model_query->attachment_1_loc    = $location;
      }

      
      $model_query->extra_money_id      = $request->extra_money_id;
      $model_query->tanggal             = $request->tanggal;
      $model_query->employee_id         = $employee->id;
      $model_query->employee_rek_no     = $employee->rek_no;
      $model_query->employee_rek_name   = $employee->rek_name;
      $model_query->no_pol              = $request->no_pol;
      $model_query->note_for_remarks    = $request->note_for_remarks;

      $model_query->payment_method_id   = $request->payment_method_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      array_unshift( $SYSNOTES , $SYSNOTE);            
      MyLog::sys("extra_money_trx",$request->id,"update",implode("\n",$SYSNOTES));

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if ($location && File::exists(files_path($location)))
      unlink(files_path($location));
      
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
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.remove');

    DB::beginTransaction();

    try {
      $model_query = ExtraMoneyTrx::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      if($model_query->val2 || $model_query->req_deleted==1  || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      if($model_query->pvr_id!="" || $model_query->pvr_id!=null)
      throw new \Exception("Harap Lakukan Permintaan Penghapusan Terlebih Dahulu",1);

      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();

      MyLog::sys("extra_money_trx",$request->id,"delete");

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

  public function reqDelete(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.request_remove');

    DB::beginTransaction();

    try {
      $model_query = ExtraMoneyTrx::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      
      if($model_query->val2)
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      if($model_query->deleted==1 || $model_query->req_deleted==1 )
      throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

      if($model_query->pvr_id=="" || $model_query->pvr_id==null)
      throw new \Exception("Harap Lakukan Penghapusan",1);

      $req_deleted_reason = $request->req_deleted_reason;
      if(!$req_deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query->req_deleted = 1;
      $model_query->req_deleted_user = $this->admin_id;
      $model_query->req_deleted_at = date("Y-m-d H:i:s");
      $model_query->req_deleted_reason = $req_deleted_reason;
      $model_query->save();

      MyLog::sys("extra_money_trx",$request->id,"delete","Request Delete (Void)");


      DB::commit();
      return response()->json([
        "message" => "Proses Permintaan Hapus data berhasil",
        "req_deleted"=>$model_query->req_deleted,
        "req_deleted_user"=>$model_query->req_deleted_user,
        "req_deleted_by"=>$model_query->req_deleted_user ? new IsUserResource(IsUser::find($model_query->req_deleted_user)) : null,
        "req_deleted_at"=>$model_query->req_deleted_at,
        "req_deleted_reason"=>$model_query->req_deleted_reason,
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

  public function approveReqDelete(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.approve_request_remove');

    $time = microtime(true);
    $mSecs = sprintf('%03d', ($time - floor($time)) * 1000);
    $t_stamp_ms = date("Y-m-d H:i:s").".".$mSecs;

    DB::beginTransaction();

    try {
      $model_query = ExtraMoneyTrx::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      
      if($model_query->val2)
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      if($model_query->deleted==1 )
      throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

      if($model_query->pvr_id=="" || $model_query->pvr_id==null)
      throw new \Exception("Harap Lakukan Penghapusan",1);

      $deleted_reason = $model_query->req_deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;

      // if($model_query->pvr_no){
      //   DB::connection('sqlsrv')->table('FI_APRequest')
      //   ->where("VoucherNo",$model_query->pvr_no)->update([
      //     "Void" => 1,
      //     "VoidBy" => $this->admin->the_user->username,
      //     "VoidDateTime" => $t_stamp_ms,
      //     "VoidReason" => $deleted_reason
      //   ]);
      // }

      // if($model_query->pv_no){
      //   DB::connection('sqlsrv')->table('FI_Arap')
      //   ->where("VoucherNo",$model_query->pv_no)->update([
      //     "Void" => 1,
      //     "VoidBy" => $this->admin->the_user->username,
      //     "VoidDateTime" => $t_stamp_ms,
      //     "VoidReason" => $deleted_reason
      //   ]);
      // }
      
      $model_query->save();

      MyLog::sys("extra_money_trx",$request->id,"delete","Approve Request Delete (Void)");

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

  public function previewFile(Request $request){
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.preview_file');

    set_time_limit(0);

    $extra_money_trx = ExtraMoneyTrx::find($request->id);

    if($extra_money_trx->val1==0)
    return response()->json([
      "message" => "Harap Di Validasi Terlebih Dahulu",
    ], 400);
    $extra_money = \App\Models\MySql\ExtraMoney::where("id",$extra_money_trx->extra_money_id)->first();

    $sendData = [
      "created_at"=>$extra_money_trx->created_at,
      "id"=>$extra_money_trx->id,
      "tanggal"=>$extra_money_trx->tanggal,
      "extra_money"=>$extra_money,
      // "extra_money_id"=>$extra_money_trx->extra_money_id,
      // "extra_money_xto"=>$extra_money_trx->extra_money->xto,
      // "extra_money_jenis"=>$extra_money_trx->extra_money->jenis,
      // "extra_money_name"=>$extra_money_trx->extra_money_name,
      // "extra_money_type"=>$extra_money_trx->extra_money_type,
      // "extra_money_amount"=>$extra_money_trx->extra_money_amount,
      "no_pol"=>$extra_money_trx->no_pol,
      "employee_name"=>$extra_money_trx->employee->name,
      "asal"=>env("app_name"),
      "is_transition"=>$extra_money_trx->extra_money->transition_type,
      "user_1"=>$this->admin->the_user->username,
    ];   
    
    
    $html = view("html.extra_money_trx",$sendData);
    
    $result = [
      "html"=>$html->render()
    ];
    return $result;
  }

  // public function previewFiles(Request $request){

  //   // set_time_limit(0);

  //   // $rules = [
  //   //   'date_from' => "required|date_format:Y-m-d H:i:s",
  //   // ];

  //   // $messages = [
  //   //   'date_from.required' => 'Date From is required',
  //   //   'date_from.date_format' => 'Please Select Date From',
  //   // ];

  //   // $validator = Validator::make($request->all(), $rules, $messages);

  //   // if ($validator->fails()) {
  //   //   throw new ValidationException($validator);
  //   // }


  //   // // Change some request value
  //   // $request['period'] = "Daily";

  //   // $date_from = $request->date_from;
  //   // $d_from = date("Y-m", MyLib::manualMillis($date_from) / 1000) . "-01 00:00:00";
  //   // $date_f = new \DateTime($d_from);

  //   // $start = clone $date_f;
  //   // $start->add(new \DateInterval('P1M'));
  //   // $start->sub(new \DateInterval('P1D'));
  //   // $x = $start->format("Y-m-d H:i:s");

  //   // $request['date_from'] = $d_from;
  //   // $request['date_to'] = $x;
  //   // return response()->json(["data"=>[$d_from,$x]],200);

  //   set_time_limit(0);
  //   $callGet = $this->index($request, true);
  //   if ($callGet->getStatusCode() != 200) return $callGet;
  //   $ori = json_decode(json_encode($callGet), true)["original"];
  //   $data = $ori["data"];
    
  //   // $additional = $ori["additional"];


  //   // $date = new \DateTime();
  //   // $filename = $date->format("YmdHis") . "-" . $additional["company_name"] . "[" . $additional["date_from"] . "-" . $additional["date_to"] . "]";
  //   // // $filename=$date->format("YmdHis");

  //   // // return response()->json(["message"=>$filename],200);

  //   // $mime = MyLib::mime("csv");
  //   // $bs64 = base64_encode(Excel::raw(new MyReport($data, 'report.sensor_get_data_by_location'), $mime["exportType"]));
  //   // $mime = MyLib::mime("xlsx");
  //   // $bs64 = base64_encode(Excel::raw(new MyReport($data, 'report.tracking_info2'), $mime["exportType"]));

    

  //   // $sendData = [
  //   //   'pag_no'  => $pag->no,
  //   //   'created_at'    => $pag->created_at,
  //   //   'updated_at'    => $pag->updated_at,
  //   //   'proyek'  => $pag->project ?? "",
  //   //   'need'    => $pag->need,
  //   //   'part'    => $pag->part,
  //   //   'datas'   => $pag->pag_details,
  //   //   'title'   => "PENGAMBILAN BARANG GUDANG (PAG)"
  //   // ];
  //   // dd($sendData);

  //   $shows=["id","tanggal","no_pol","jenis","xto","amount"];
  //   $newDetails = [];
  //   $total_a_bruto = 0;
  //   $total_a_tara = 0;
  //   $total_a_netto = 0;
  //   $total_b_bruto = 0;
  //   $total_b_tara = 0;
  //   $total_b_netto = 0;
  //   $total_b_a_bruto = 0;
  //   $total_b_a_tara = 0;
  //   $total_b_a_netto = 0;
  //   foreach ($ori["data"] as $key => $value) {
  //     $ticket_a_bruto = (float)$value["ticket_a_bruto"];
  //     $ticket_b_bruto = (float)$value["ticket_b_bruto"];
  //     list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($value["ticket_a_bruto"],$value["ticket_b_bruto"]);
  //     $ticket_a_tara = (float)$value["ticket_a_tara"];
  //     $ticket_b_tara = (float)$value["ticket_b_tara"];
  //     list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($value["ticket_a_tara"],$value["ticket_b_tara"]);
  //     $ticket_a_netto = (float)$value["ticket_a_netto"];
  //     $ticket_b_netto = (float)$value["ticket_b_netto"];
  //     list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($value["ticket_a_netto"],$value["ticket_b_netto"]);

  //     $total_a_bruto+=$ticket_a_bruto;
  //     $total_a_tara+=$ticket_a_tara;
  //     $total_a_netto+=$ticket_a_netto;

  //     $total_b_bruto+=$ticket_b_bruto;
  //     $total_b_tara+=$ticket_b_tara;
  //     $total_b_netto+=$ticket_b_netto;

  //     $limitSusut = 0.4;

  //     $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
  //     $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
  //     $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
  //     $value['ticket_a_bruto']=number_format($ticket_a_bruto, 0,',','.');
  //     $value['ticket_b_bruto']=number_format($ticket_b_bruto, 0,',','.');
  //     $value['ticket_b_a_bruto']=block_negative($ticket_b_a_bruto, 0);
  //     $value['ticket_b_a_bruto_persen_red']=abs($ticket_b_a_bruto_persen) >= $limitSusut ? 'color:red;' : '';
  //     $value['ticket_b_a_bruto_persen']=block_negative($ticket_b_a_bruto_persen, 2);
  //     $value['ticket_a_tara']=number_format($ticket_a_tara, 0,',','.');
  //     $value['ticket_b_tara']=number_format($ticket_b_tara, 0,',','.');
  //     $value['ticket_b_a_tara']=block_negative($ticket_b_a_tara, 0);
  //     $value['ticket_b_a_tara_persen_red']=abs($ticket_b_a_tara_persen) >= $limitSusut ? 'color:red;' : '';
  //     $value['ticket_b_a_tara_persen']=block_negative($ticket_b_a_tara_persen, 2);
  //     $value['ticket_a_netto']=number_format($ticket_a_netto, 0,',','.');
  //     $value['ticket_b_netto']=number_format($ticket_b_netto, 0,',','.');
  //     $value['ticket_b_a_netto']=block_negative($ticket_b_a_netto, 0);
  //     $value['ticket_b_a_netto_persen_red']=abs($ticket_b_a_netto_persen) >= $limitSusut ? 'color:red;' : '';
  //     $value['ticket_b_a_netto_persen']=block_negative($ticket_b_a_netto_persen, 2);
  //     $value['amount']=number_format((float)$value["amount"], 0,',','.');
  //     $value['pv_total']=number_format((float)$value["pv_total"], 0,',','.');
  //     $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
  //     array_push($newDetails,$value);
  //   }

  //   list($total_b_a_bruto, $total_b_a_bruto_persen) =  $this->genPersen($total_a_bruto,$total_b_bruto);
  //   list($total_b_a_tara, $total_b_a_tara_persen) =  $this->genPersen($total_a_tara,$total_b_tara);
  //   list($total_b_a_netto, $total_b_a_netto_persen) =  $this->genPersen($total_a_netto,$total_b_netto);
    

  //   $ttl_a_tara=number_format($total_a_tara, 0,',','.');
  //   $ttl_a_bruto=number_format($total_a_bruto, 0,',','.');
  //   $ttl_a_netto=number_format($total_a_netto, 0,',','.');

  //   $ttl_b_tara=number_format($total_b_tara, 0,',','.');
  //   $ttl_b_bruto=number_format($total_b_bruto, 0,',','.');
  //   $ttl_b_netto=number_format($total_b_netto, 0,',','.');


  //   $ttl_b_a_tara=block_negative($total_b_a_tara, 0);
  //   $ttl_b_a_bruto=block_negative($total_b_a_bruto, 0);
  //   $ttl_b_a_netto=block_negative($total_b_a_netto, 0);
    
  //   $ttl_b_a_bruto_persen=block_negative($total_b_a_bruto_persen, 2);
  //   $ttl_b_a_tara_persen=block_negative($total_b_a_tara_persen, 2);
  //   $ttl_b_a_netto_persen=block_negative($total_b_a_netto_persen, 2);

  //   // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

  //   $date = new \DateTime();
  //   $filename = $date->format("YmdHis");
  //   Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
  //   $pdf = PDF::loadView('pdf.extra_money_trx', ["data"=>$newDetails,"shows"=>$shows,"info"=>[
  //     "from"=>date("d-m-Y",strtotime($request->date_from)),
  //     "to"=>date("d-m-Y",strtotime($request->date_to)),
  //     "now"=>date("d-m-Y H:i:s"),
  //     "ttl_a_bruto"=>$ttl_a_bruto,
  //     "ttl_a_tara"=>$ttl_a_tara,
  //     "ttl_a_netto"=>$ttl_a_netto,
  //     "ttl_b_bruto"=>$ttl_b_bruto,
  //     "ttl_b_tara"=>$ttl_b_tara,
  //     "ttl_b_netto"=>$ttl_b_netto,
  //     "ttl_b_a_bruto"=>$ttl_b_a_bruto,
  //     "ttl_b_a_tara"=>$ttl_b_a_tara,
  //     "ttl_b_a_netto"=>$ttl_b_a_netto,
  //     // "ttl_b_a_bruto_persen"=>$ttl_b_a_bruto_persen,
  //     // "ttl_b_a_tara_persen"=>$ttl_b_a_tara_persen,
  //     // "ttl_b_a_netto_persen"=>$ttl_b_a_netto_persen,
  //   ]])->setPaper('a4', 'landscape');


  //   $mime = MyLib::mime("pdf");
  //   $bs64 = base64_encode($pdf->download($filename . "." . $mime["ext"]));

  //   $result = [
  //     "contentType" => $mime["contentType"],
  //     "data" => $bs64,
  //     "dataBase64" => $mime["dataBase64"] . $bs64,
  //     "filename" => $filename . "." . $mime["ext"],
  //   ];
  //   return $result;
  // }

  // public function downloadExcel(Request $request){

  //   set_time_limit(0);
  //   $callGet = $this->index($request, true);
  //   if ($callGet->getStatusCode() != 200) return $callGet;
  //   $ori = json_decode(json_encode($callGet), true)["original"];
  //   $data = $ori["data"];
    
  //   $shows=["id","tanggal","no_pol","jenis","xto","amount"];

  //   $newDetails = [];

  //   foreach ($ori["data"] as $key => $value) {
  //     $ticket_a_bruto = (float)$value["ticket_a_bruto"];
  //     $ticket_b_bruto = (float)$value["ticket_b_bruto"];
  //     list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($value["ticket_a_bruto"],$value["ticket_b_bruto"]);
  //     $ticket_a_tara = (float)$value["ticket_a_tara"];
  //     $ticket_b_tara = (float)$value["ticket_b_tara"];
  //     list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($value["ticket_a_tara"],$value["ticket_b_tara"]);
  //     $ticket_a_netto = (float)$value["ticket_a_netto"];
  //     $ticket_b_netto = (float)$value["ticket_b_netto"];
  //     list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($value["ticket_a_netto"],$value["ticket_b_netto"]);

  //     $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
  //     $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
  //     $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
  //     $value['ticket_a_bruto']=$ticket_a_bruto;
  //     $value['ticket_b_bruto']=$ticket_b_bruto;
  //     $value['ticket_b_a_bruto']=$ticket_b_a_bruto;
  //     $value['ticket_b_a_bruto_persen']=$ticket_b_a_bruto_persen;
  //     $value['ticket_a_tara']=$ticket_a_tara;
  //     $value['ticket_b_tara']=$ticket_b_tara;
  //     $value['ticket_b_a_tara']=$ticket_b_a_tara;
  //     $value['ticket_b_a_tara_persen']=$ticket_b_a_tara_persen;
  //     $value['ticket_a_netto']=$ticket_a_netto;
  //     $value['ticket_b_netto']=$ticket_b_netto;
  //     $value['ticket_b_a_netto']=$ticket_b_a_netto;
  //     $value['ticket_b_a_netto_persen']=$ticket_b_a_netto_persen;
  //     $value['amount']=$value["amount"];
  //     $value['pv_total']=$value["pv_total"];
  //     $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
  //     array_push($newDetails,$value);
  //   }

  //   // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

  //   $date = new \DateTime();
  //   $filename=$date->format("YmdHis").'-extra_money_trx'."[".$request["date_from"]."-".$request["date_to"]."]";

  //   $mime=MyLib::mime("xlsx");
  //   // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
  //   $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails,"shows"=>$shows],'excel.extra_money_trx'), $mime["exportType"]));


  //   $result = [
  //     "contentType" => $mime["contentType"],
  //     "data" => $bs64,
  //     "dataBase64" => $mime["dataBase64"] . $bs64,
  //     "filename" => $filename . "." . $mime["ext"],
  //   ];
  //   return $result;
  // }

  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['extra_money_trx.val1','extra_money_trx.val2','extra_money_trx.val3','extra_money_trx.val4','extra_money_trx.val5','extra_money_trx.val6']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\ExtraMoneyTrx,id",
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
      $model_query = ExtraMoneyTrx::find($request->id);
      
      // if($model_query->cost_center_code=="")
      // throw new \Exception("Harap Mengisi Cost Center Code Sebelum validasi",1);

      $run_val = 0; 
  
      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.val1',true) && !$model_query->val1){
        $run_val++;
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.val2',true) && !$model_query->val2){
        $run_val++;
        $model_query->val2 = 1;
        $model_query->val2_user = $this->admin_id;
        $model_query->val2_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.val3',true) && !$model_query->val3){
        $run_val++;
        $model_query->val3 = 1;
        $model_query->val3_user = $this->admin_id;
        $model_query->val3_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.val4',true) && !$model_query->val4){
        $run_val++;
        $model_query->val4 = 1;
        $model_query->val4_user = $this->admin_id;
        $model_query->val4_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.val5',true) && !$model_query->val5){
        $run_val++;
        $model_query->val5 = 1;
        $model_query->val5_user = $this->admin_id;
        $model_query->val5_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.val6',true) && !$model_query->val6){
        $run_val++;
        $model_query->val6 = 1;
        $model_query->val6_user = $this->admin_id;
        $model_query->val6_at = $t_stamp;
      }


      $model_query->save();

      MyLog::sys("extra_money_trx",$request->id,"approve");

      DB::commit();
      return response()->json([
        "message" => "Proses validasi data berhasil",
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
        "val4"=>$model_query->val4,
        "val4_user"=>$model_query->val4_user,
        "val4_at"=>$model_query->val4_at,
        "val4_by"=>$model_query->val4_user ? new IsUserResource(IsUser::find($model_query->val4_user)) : null,
        "val5"=>$model_query->val5,
        "val5_user"=>$model_query->val5_user,
        "val5_at"=>$model_query->val5_at,
        "val5_by"=>$model_query->val5_user ? new IsUserResource(IsUser::find($model_query->val5_user)) : null,
        "val6"=>$model_query->val6,
        "val6_user"=>$model_query->val6_user,
        "val6_at"=>$model_query->val6_at,
        "val6_by"=>$model_query->val6_user ? new IsUserResource(IsUser::find($model_query->val6_user)) : null,
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

  public function doGenPVR(Request $request){
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.generate_pvr');
    $rules = [
      // 'id' => "required|exists:\App\Models\MySql\ExtraMoneyTrx,id",
      'online_status' => "required",
    ];

    $messages = [
      // 'id.required' => 'ID tidak boleh kosong',
      // 'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }
    $online_status=$request->online_status;
    if($online_status!="true")
    return response()->json([
      "message" => "Mode Harus Online",
    ], 400);

    $miniError="";
    $id="";
    try {
      $extra_money_trxs = ExtraMoneyTrx::where(function($q1){$q1->where('pvr_complete',0)->orWhereNull("pvr_id");})->whereNull("pv_id")->where("req_deleted",0)->where("deleted",0)->where('val1',1)->where('val2',1)
      ->where(function ($q) {
        $q->where(function ($q1){
          $q1->where("payment_method_id",1);       
          $q1->where("received_payment",0);                  
        });

        $q->orWhere(function ($q1){
          $q1->where("payment_method_id",2);
          $q1->where("received_payment",1);                 
        });
      })->get();
      if(count($extra_money_trxs)==0){
        throw new \Exception("Semua PVR sudah terisi",1);
      }
      $changes=[];
      foreach ($extra_money_trxs as $key => $tt) {
        $id=$tt->id;
        $callGet = $this->genPVR($id);
        array_push($changes,$callGet);
      }
      if(count($changes)>0){
        $ids = array_map(function ($x) {
          return $x["id"];
        },$changes);
        MyLog::sys("extra_money_trx",null,"generate_pvr",implode(",",$ids));
      }
      return response()->json($changes, 200);
    } catch (\Exception $e) {
      if(isset($changes) && count($changes)>0){
        $ids = array_map(function ($x) {
          return $x["id"];
        },$changes);
        MyLog::sys("extra_money_trx",null,"generate_pvr",implode(",",$ids));
      }

      if ($e->getCode() == 1) {
        if($id!=""){
          $miniError.="Trx-".$id.".";
        }
        $miniError.="PVR Batal Dibuat: ".$e->getMessage();
      }else{
        if($id!=""){
          $miniError.="Trx-".$id.".";
        }
        $miniError.="PVR Batal Dibuat. Akses Jaringan Gagal";
      }
      return response()->json([
        "message" => $miniError,
      ], 400);
    }
  }

  public function genPVR($extra_money_trx_id){

    $t_stamp = date("Y-m-d H:i:s");

    $time = microtime(true);
    $mSecs = sprintf('%03d', ($time - floor($time)) * 1000);
    $t_stamp_ms = date("Y-m-d H:i:s",strtotime($t_stamp)).".".$mSecs;

    $extra_money_trx = ExtraMoneyTrx::where("id",$extra_money_trx_id)->first();
    if(!$extra_money_trx){
      throw new \Exception("Karna Transaksi tidak ditemukan",1);
    }

    if($extra_money_trx->pvr_complete==1) throw new \Exception("Karna PVR sudah selesai dibuat",1);
    // if($extra_money_trx->cost_center_code==null) throw new \Exception("Cost Center Code belum diisi",1);
    if($extra_money_trx->pv_id!=null) throw new \Exception("Karna PV sudah diisi",1);
    
    if($extra_money_trx->cost_center_code==null){
      $extra_money_trx->cost_center_code = '112';
      $extra_money_trx->cost_center_desc = 'Transport';      
  
      $get_cost_center = DB::connection('sqlsrv')->table("AC_CostCenterNames")
      ->select('CostCenter','Description')
      ->where('CostCenter','like', '112%')
      ->where('Description','like', '%'.trim($extra_money_trx->no_pol))
      ->first();
  
      if($get_cost_center){
        $extra_money_trx->cost_center_code = $get_cost_center->CostCenter;
        $extra_money_trx->cost_center_desc = $get_cost_center->Description;
      }
    }

    $employee_name = $extra_money_trx->employee->name;
    $no_pol = $extra_money_trx->no_pol;
    $associate_name=($employee_name?"(S) ".$employee_name." ":"(Tanpa Supir) ").$no_pol; // max 80char

    $extra_money = $extra_money_trx->extra_money;
  
    $arrRemarks = [];
    array_push($arrRemarks,"#".$extra_money_trx->id.($extra_money->transition_type!=''?" (P) " : " ").$associate_name.".");
    array_push($arrRemarks,$extra_money->description." ".($extra_money->xto ? env("app_name")."-".$extra_money->xto : "")).".";
    array_push($arrRemarks," P/".date("d-m-y",strtotime($extra_money_trx->tanggal)));

    if($extra_money_trx->note_for_remarks!=null){
      $note_for_remarks_arr = preg_split('/\r\n|\r|\n/', $extra_money_trx->note_for_remarks);
      $arrRemarks = array_merge($arrRemarks,$note_for_remarks_arr);
    }
    
    $remarks = implode(chr(10),$arrRemarks);
    array_push($arrRemarks,";");

    if(strlen($associate_name)>80){
      $associate_name = substr($associate_name,0,80);
    }

    // $bank_account_code=env("PVR_BANK_ACCOUNT_CODE");
    $bank_account_code=$extra_money_trx->payment_method->account_code;
    
    $bank_acccount_db = DB::connection('sqlsrv')->table('FI_BankAccounts')
    ->select('BankAccountID')
    ->where("bankaccountcode",$bank_account_code)
    ->first();
    if(!$bank_acccount_db) throw new \Exception("Bank account code tidak terdaftar ,segera infokan ke tim IT",1);

    $bank_account_id = $bank_acccount_db->BankAccountID;
    
    // @VoucherID INT = 0,
    $voucher_no = "(AUTO)";
    $voucher_type = "TRP";
    $voucher_date = date("Y-m-d");

    $income_or_expense = 1;
    $currency_id = 1;
    $payment_method="Cash";
    $check_no=$bank_name=$account_no= '';
    $check_due_date= null;

    $amount_paid = $extra_money->nominal * $extra_money->qty; // call from child

    if($extra_money_trx->payment_method->id==2){
      $adm_cost = 2500;
      $adm_qty = 1;

      $amount_paid += ($adm_cost * $adm_qty);
    }

    $exclude_in_ARAP = 0;
    $login_name = $this->admin->the_user->username;
    $expense_or_revenue_type_id=0;
    $confidential=1;
    $PVR_source = 'gt_extra'; // digenerate melalui program
    $PVR_source_id = $extra_money_trx_id; //ambil id trx
      // DB::select("exec USP_FI_APRequest_Update(0,'(AUTO)','TRP',1,1,1,0,)",array($ts,$param2));
    $VoucherID = -1;

    $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
    ->select('VoucherID','VoucherNo','AmountPaid')
    ->where("PVRSource",$PVR_source)
    ->where("PVRSourceID",$extra_money_trx->id)
    ->where("Void",0)
    ->first();

    if(!$pvr){
      // $myData = DB::connection('sqlsrv')->update("SET NOCOUNT ON;exec USP_FI_APRequest_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
      $myData = DB::connection('sqlsrv')->update("exec USP_FI_APRequest_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
      @VoucherDate=:voucher_date,@IncomeOrExpense=:income_or_expense,@CurrencyID=:currency_id,@AssociateName=:associate_name,
      @BankAccountID=:bank_account_id,@PaymentMethod=:payment_method,@CheckNo=:check_no,@CheckDueDate=:check_due_date,
      @BankName=:bank_name,@AmountPaid=:amount_paid,@AccountNo=:account_no,@Remarks=:remarks,@ExcludeInARAP=:exclude_in_ARAP,
      @LoginName=:login_name,@ExpenseOrRevenueTypeID=:expense_or_revenue_type_id,@Confidential=:confidential,
      @PVRSource=:PVR_source,@PVRSourceID=:PVR_source_id",[
        ":voucher_no"=>$voucher_no,
        ":voucher_type"=>$voucher_type,
        ":voucher_date"=>$voucher_date,
        ":income_or_expense"=>$income_or_expense,
        ":currency_id"=>$currency_id,
        ":associate_name"=>$associate_name,
        ":bank_account_id"=>$bank_account_id,
        ":payment_method"=>$payment_method,
        ":check_no"=>$check_no,
        ":check_due_date"=>$check_due_date,
        ":bank_name"=>$bank_name,
        ":amount_paid"=>$amount_paid,
        ":account_no"=>$account_no,
        ":remarks"=>$remarks,
        ":exclude_in_ARAP"=>$exclude_in_ARAP,
        ":login_name"=>$login_name,
        ":expense_or_revenue_type_id"=>$expense_or_revenue_type_id,
        ":confidential"=>$confidential,
        ":PVR_source"=>$PVR_source,
        ":PVR_source_id"=>$PVR_source_id,
      ]);
      if(!$myData)
      throw new \Exception("Data yang diperlukan tidak terpenuhi",1);
    
      $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
      ->select('VoucherID','VoucherNo','AmountPaid')
      ->where("PVRSource",$PVR_source)
      ->where("PVRSourceID",$extra_money_trx->id)
      ->where("Void",0)
      ->first();
      if(!$pvr)
      throw new \Exception("Akses Ke Jaringan Gagal",1);
    }

    $extra_money_trx->pvr_id = $pvr->VoucherID;
    $extra_money_trx->pvr_no = $pvr->VoucherNo;
    $extra_money_trx->pvr_total = $pvr->AmountPaid;
    $extra_money_trx->save();
    
    $d_voucher_id = $pvr->VoucherID;
    $d_voucher_extra_item_id = 0;
    $d_type = 0;

    $pvr_detail= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
    ->select('VoucherID')
    ->where("VoucherID",$d_voucher_id)
    ->get();

    if(count($pvr_detail)==0 || count($pvr_detail) < 1){
      // $start = count($pvr_detail);
      // foreach ($extra_money_dtl as $key => $v) {
        // if($key < $start){ continue; }
        $d_description = $extra_money->description;
        $d_amount = $extra_money->nominal * $extra_money->qty;
        $d_account_id = $extra_money->ac_account_id;
        $d_dept = $extra_money_trx->cost_center_code;
        $d_qty=$extra_money->qty;
        $d_unit_price=$extra_money->nominal;
        $details = DB::connection('sqlsrv')->update("exec 
        USP_FI_APRequestExtraItems_Update @VoucherID=:d_voucher_id,
        @VoucherExtraItemID=:d_voucher_extra_item_id,
        @Description=:d_description,@Amount=:d_amount,
        @AccountID=:d_account_id,@TypeID=:d_type,
        @Department=:d_dept,@LoginName=:login_name,
        @Qty=:d_qty,@UnitPrice=:d_unit_price",[
          ":d_voucher_id"=>$d_voucher_id,
          ":d_voucher_extra_item_id"=>$d_voucher_extra_item_id,
          ":d_description"=>$d_description,
          ":d_amount"=>$d_amount,
          ":d_account_id"=>$d_account_id,
          ":d_type"=>$d_type,
          ":d_dept"=>$d_dept,
          ":login_name"=>$login_name,
          ":d_qty"=>$d_qty,
          ":d_unit_price"=>$d_unit_price
        ]);
      // }
    }

    if($extra_money_trx->payment_method->id==2){
      $admin_cost_code=env("PVR_ADMIN_COST");
  
      $admin_cost_db = DB::connection('sqlsrv')->table('ac_accounts')
      ->select('AccountID')
      ->where("AccountCode",$admin_cost_code)
      ->first();
      if(!$admin_cost_db) throw new \Exception("GL account code tidak terdaftar ,segera infokan ke tim IT",1);

      $adm_cost_id = $admin_cost_db->AccountID;
      $adm_fee_exists= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
      ->select('VoucherID')
      ->where("VoucherID",$d_voucher_id)
      ->where("AccountID",$adm_cost_id)
      ->first();

      if(!$adm_fee_exists){
        $d_description  = "Biaya Admin";
        $d_account_id   = $adm_cost_id;
        $d_dept         = '112';
        $d_qty          = $adm_qty;
        $d_unit_price   = $adm_cost;
        $d_amount       = $d_qty * $d_unit_price;
  
        DB::connection('sqlsrv')->update("exec 
        USP_FI_APRequestExtraItems_Update @VoucherID=:d_voucher_id,
        @VoucherExtraItemID=:d_voucher_extra_item_id,
        @Description=:d_description,@Amount=:d_amount,
        @AccountID=:d_account_id,@TypeID=:d_type,
        @Department=:d_dept,@LoginName=:login_name,
        @Qty=:d_qty,@UnitPrice=:d_unit_price",[
          ":d_voucher_id"=>$d_voucher_id,
          ":d_voucher_extra_item_id"=>$d_voucher_extra_item_id,
          ":d_description"=>$d_description,
          ":d_amount"=>$d_amount,
          ":d_account_id"=>$d_account_id,
          ":d_type"=>$d_type,
          ":d_dept"=>$d_dept,
          ":login_name"=>$login_name,
          ":d_qty"=>$d_qty,
          ":d_unit_price"=>$d_unit_price
        ]);
      }
    }

    $tocheck = DB::connection('sqlsrv')->table('FI_APRequest')->where("VoucherID",$d_voucher_id)->first();

    if(!$tocheck)
    throw new \Exception("Voucher Tidak terdaftar",1);

    $checked2 = IsUser::where("id",$extra_money_trx->val1_user)->first();
    if(!$checked2)
    throw new \Exception("User Tidak terdaftar",1);
    if($tocheck->Checked==0){
      DB::connection('sqlsrv')->update("exec USP_FI_APRequest_DoCheck @VoucherID=:voucher_no,
      @CheckedBy=:login_name",[
        ":voucher_no"=>$d_voucher_id,
        ":login_name"=>$login_name,
      ]);
    }

    if($tocheck->Approved==0){
      DB::connection('sqlsrv')->update("exec USP_FI_APRequest_DoApprove @VoucherID=:voucher_no,
      @ApprovedBy=:login_name",[
        ":voucher_no"=>$d_voucher_id,
        ":login_name"=>$checked2->username,
      ]);
    }

    $extra_money_trx->pvr_complete = 1;
    $extra_money_trx->save();

    return [
      "message" => "PVR berhasil dibuat",
      "id"=>$extra_money_trx->id,
      "pvr_id" => $extra_money_trx->pvr_id,
      "pvr_no" => $extra_money_trx->pvr_no,
      "pvr_total" => $extra_money_trx->pvr_total,
      "pvr_complete" => $extra_money_trx->pvr_complete,
      "updated_at"=>$t_stamp
    ];
  }

  public function doUpdatePV(Request $request){
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.get_pv');
    $rules = [
      'online_status' => "required",
    ];

    $messages = [
      'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }
    $online_status=$request->online_status;
    if($online_status!="true")
    return response()->json([
      "message" => "Mode Harus Online",
    ], 400);

    $miniError="";
    try {
      $t_stamp = date("Y-m-d H:i:s");
      $extra_money_trxs = ExtraMoneyTrx::whereNotNull("pvr_id")->whereNull("pv_id")->where("req_deleted",0)->where("deleted",0)->get();
      if(count($extra_money_trxs)==0){
        throw new \Exception("Semua PVR yang ada ,PV ny sudah terisi",1);
      }

      $pvr_nos=$extra_money_trxs->pluck('pvr_no');
      // $pvr_nos=['KPN/PV-R/2404/0951','KPN/PV-R/2404/1000'];
      $get_data_pvs = DB::connection('sqlsrv')->table('FI_ARAPINFO')
      ->selectRaw('fi_arap.VoucherID,fi_arap.VoucherDate,Sources,fi_arap.VoucherNo,FI_APRequest.PVRSourceID,fi_arap.AmountPaid')
      ->join('fi_arap',function ($join){
        $join->on("fi_arap.VoucherID","FI_ARAPINFO.VoucherID");        
      })
      ->join('FI_APRequest',function ($join){
        $join->on("FI_APRequest.VoucherNo","FI_ARAPINFO.Sources");        
      })
      ->whereIn("sources",$pvr_nos)
      ->get();

      $get_data_pvs=MyLib::objsToArray($get_data_pvs);
      $changes=[];
      foreach ($get_data_pvs as $key => $v) {
        $ud_extra_money_trx=ExtraMoneyTrx::where("id", $v["PVRSourceID"])->where("pvr_no", $v["Sources"])->first();
        if(!$ud_extra_money_trx) continue;
        $ud_extra_money_trx->pv_id=$v["VoucherID"];
        $ud_extra_money_trx->pv_no=$v["VoucherNo"];
        $ud_extra_money_trx->pv_total=$v["AmountPaid"];
        $ud_extra_money_trx->pv_datetime=$v["VoucherDate"];
        $ud_extra_money_trx->updated_at=$t_stamp;
        $ud_extra_money_trx->save();
        array_push($changes,[
          "id"=>$ud_extra_money_trx->id,
          "pv_id"=>$ud_extra_money_trx->pv_id,
          "pv_no"=>$ud_extra_money_trx->pv_no,
          "pv_total"=>$ud_extra_money_trx->pv_total,
          "pv_datetime"=>$ud_extra_money_trx->pv_datetime,
          "updated_at"=>$t_stamp
        ]);
      }

      if(count($changes)==0)
      throw new \Exception("PV Tidak ada yang di Update",1);


      $ids = array_map(function ($x) {
        return $x["id"];
      }, $changes);

      MyLog::sys("extra_money_trx",null,"update_pv",implode(",",$ids));

      return response()->json([
        "message" => "PV Berhasil di Update",
        "data" => $changes,
      ], 200);
      
    } catch (\Exception $e) {
      if ($e->getCode() == 1) {
        $miniError="PV Batal Update: ".$e->getMessage();
      }else{
        $miniError="PV Batal Update. Akses Jaringan Gagal";
      }
      return response()->json([
        "message" => $miniError,
      ], 400);
    }
  }

}
