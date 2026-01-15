<?php

namespace App\Http\Controllers\ExtraMoney;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;

use Maatwebsite\Excel\Facades\Excel;

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
use App\Models\MySql\TrxTrp;

use App\Exports\MyReport;


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
    $list_payment_methods = \App\Models\MySql\PaymentMethod::where('hidden',0)->get();
    
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

      $list_to_like = ["id","employee_rek_no","no_pol","pvr_id","pvr_no","pv_id","pv_no",
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
        ["extra_money_jenis","jenis"],
        ["extra_money_desc","description"]
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
              $q->orWhereIn('employee_id', function($q2)use($like_lists,$v) {
                $q2->from('employee_mst')
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

          if(array_search($value['key'],['employee_name'])!==false){
            $model_query = MyLib::queryOrderP1($model_query,"employee","employee_id",$value['key'],$filter_model[$value['key']]["sort_type"],"employee_mst");
          } else if(array_search($value['key'],['extra_money_xto','extra_money_jenis','extra_money_desc','extra_money_transition_target','extra_money_transition_type'])!==false){
            $model_query = MyLib::queryOrderP1($model_query,"extra_money","extra_money_id",$value['key'],$filter_model[$value['key']]["sort_type"]);
          } else{
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
          }else if(array_search($key,['extra_money_xto','extra_money_jenis','extra_money_desc','extra_money_transition_target','extra_money_transition_type'])!==false){
            MyLib::queryCheckP1("extra_money",$value,$key,$q);
          }else if(array_search($key,['employee_name'])!==false){
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

    $model_query = $model_query->with(['val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','deleted_by','req_deleted_by','extra_money','employee','payment_method'])->exclude(['attachment_1_loc','attachment_2_loc'])->get();

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

      if(in_array($request->payment_method_id,[2,3,4,5])){
        if(!$employee->rek_no && $employee->id != 1)
        throw new \Exception("Tidak ada no rekening pekerja",1);
      }

      $extra_money = \App\Models\MySql\ExtraMoney::where("id",$request->extra_money_id)
      ->where("deleted",0)
      ->lockForUpdate()
      ->first();

      if(!$extra_money) 
      throw new \Exception("Silahkan Isi Data Uang Tambahan Dengan Benar",1);

      $trx_trp = TrxTrp::where("id",$request->prev_trx_trp_id)->where("supir_id",$employee->id)->first();
      if(!$trx_trp) 
      throw new \Exception("Trx Trp Pekerja Tidak Sesuai",1);

      $model_query->extra_money_id      = $request->extra_money_id;
      $model_query->tanggal             = $request->tanggal;
      $model_query->employee_id         = $employee->id;
      $model_query->employee_rek_no     = $employee->rek_no;
      $model_query->employee_rek_name   = $employee->rek_name;
      $model_query->no_pol              = $request->no_pol;
      $model_query->note_for_remarks    = $request->note_for_remarks;
      
      $model_query->prev_trx_trp_id     = $request->prev_trx_trp_id;
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

        if(!preg_match("/^image\/(jpeg|jpg|png)$/",$fileType))
        throw new \Exception("File Attachment 1 : Tipe Data Harus berupa jpg,jpeg, atau png", 1);
        // throw new MyException([ "attachment_1" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);

        $image = Image::read($path)->scale(height: $this->height);
        $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));

        $file_name = "att1_".Str::uuid() . '.' . $ext;
        $location = "extra_money_trx/$file_name";


        // $date = new \DateTime();
        // $timestamp = $date->format("Y-m-d H:i:s.v");
        // $file_name = md5(preg_replace('/( |-|:)/', '', $timestamp)) . '.' . $ext;
        // $location = "/files/extra_money_trx/{$file_name}";
        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put(
            $location,
            $compressedImageBinary
          );
          // Image::read($compressedImageBinary)->save(files_path($location));
        } catch (\Exception $e) {
          throw new \Exception("Simpan Foto 1 Gagal");
        }

        // $blob_img_leave = base64_encode($compressedImageBinary); 
        // $blobFile = base64_encode(file_get_contents($path));
        $model_query->attachment_1_loc = $location;
        $model_query->attachment_1_type = $fileType;
      }

      // if($request->hasFile('attachment_2')){
      //   $file = $request->file('attachment_2');
      //   $path = $file->getRealPath();
      //   $fileType = $file->getClientMimeType();
      //   $ext = $file->extension();
      //   if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
      //   throw new MyException([ "attachment_2" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);

      //   $image = Image::read($path)->scale(height: $this->height);
      //   $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));

      //   $date = new \DateTime();
      //   $timestamp = $date->format("Y-m-d H:i:s.v");
      //   $file_name = md5(preg_replace('/( |-|:)/', '', $timestamp)) . '-2.' . $ext;
      //   $location = "/files/extra_money_trx/{$file_name}";
      //   try {
      //     ini_set('memory_limit', '256M');
      //     Image::read($compressedImageBinary)->save(files_path($location));
      //   } catch (\Exception $e) {
      //     throw new \Exception("Simpan Foto Gagal");
      //   }

      //   // $blob_img_leave = base64_encode($compressedImageBinary); 
      //   // $blobFile = base64_encode(file_get_contents($path));
      //   $model_query->attachment_2_loc = $location;
      //   $model_query->attachment_2_type = $fileType;
      // }

      if($request->hasFile('attachment_2')){
        $file = $request->file('attachment_2');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $ext = $file->extension();

        $file_name = "att2_".Str::uuid() . '.' . $ext;
        $location2 = "extra_money_trx/$file_name";

        // $date = new \DateTime();
        // $timestamp = $date->format("Y-m-d H:i:s.v");
        // $file_name = md5(preg_replace('/( |-|:)/', '', $timestamp)) . '-2.' . $ext;
        // $location2 = "/files/extra_money_trx/{$file_name}";

        if(preg_match("/^image\/(jpeg|jpg|png)$/",$fileType)){
          $image = Image::read($path)->scale(height: $this->height);
          $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
          try {
            ini_set('memory_limit', '256M');
            // Image::read($compressedImageBinary)->save(files_path($location2));
            Storage::disk('public')->put(
              $location2,
              $compressedImageBinary
            );
          } catch (\Exception $e) {
            throw new \Exception("Simpan Gambar 2 Gagal");
          }  
        }else{
          try {
              // Set memory limit (optional)
              ini_set('memory_limit', '256M');      
              // Save the PDF file
              // file_put_contents(files_path($location2), file_get_contents($path));
              Storage::disk('public')->put($location2, file_get_contents($path));
          } catch (\Exception $e) {
              throw new \Exception("Simpan PDF 2 Gagal");
          }
        }

        // $blob_img_leave = base64_encode($compressedImageBinary); 
        // $blobFile = base64_encode(file_get_contents($path));
        $model_query->attachment_2_loc = $location2;
        $model_query->attachment_2_type = $fileType;
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

      // if ($location && File::exists(files_path($location)))
      // unlink(files_path($location));

      if ($location && Storage::disk('public')->exists($location)) {
        Storage::disk('public')->delete($location);
      }

      if ($location2 && Storage::disk('public')->exists($location2)) {
        Storage::disk('public')->delete($location2);
      }

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
    $attachment_2_preview = $request->attachment_2_preview;
    $fileType             = null;


    $att_temp = [
      "att_1"=>[
        "newLoc"=>null,
        "oldLoc"=>null,
        "newType"=>null,
        "oldType"=>null,
        "useNew"=>false
      ],
      "att_2"=>[
        "newLoc"=>null,
        "oldLoc"=>null,
        "newType"=>null,
        "oldType"=>null,
        "useNew"=>false
      ],
    ];

    DB::beginTransaction();
    try {
      $SYSNOTES=[];
      $model_query = ExtraMoneyTrx::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);
      // array_push( $SYSNOTES ,"Details: \n");

      if($model_query->val6==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      if($model_query->val1==0){

        if($model_query->trx_trp_id) 
        throw new \Exception("Data Sudah Terlink Dengan Trx Trp Dan Tidak Dapat Di Ubah",1);
  
        $employee = \App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where("id",$request->employee_id)
        ->where("deleted",0)
        ->lockForUpdate()
        ->first();
  
        if(!$employee)
        throw new \Exception("Pekerja tidak terdaftar",1);
  
        if(in_array($request->payment_method_id,[2,3,4,5])){
          if(!$employee->rek_no && $employee->id != 1)
          throw new \Exception("Tidak ada no rekening pekerja",1);
        }
  
        $extra_money = \App\Models\MySql\ExtraMoney::where("id",$request->extra_money_id)
        ->where("deleted",0)
        ->lockForUpdate()
        ->first();
  
        if(!$extra_money) 
        throw new \Exception("Silahkan Isi Data Uang Tambahan Dengan Benar",1);
  
        $trx_trp = TrxTrp::where("id",$request->prev_trx_trp_id)->where("supir_id",$employee->id)->first();
        if(!$trx_trp) 
        throw new \Exception("Trx Trp Pekerja Tidak Sesuai",1);
  
        $att_temp['att_1']['oldLoc']=$model_query->attachment_1_loc;
        $att_temp['att_1']['oldType']=$model_query->attachment_1_type;
  

        if($request->hasFile('attachment_1')){
          $file = $request->file('attachment_1');
          $path = $file->getRealPath();
          $fileType = $file->getClientMimeType();
  
          $ext = $file->extension();
          if(!preg_match("/^image\/(jpeg|jpg|png)$/",$fileType))
          throw new \Exception("File Attachment 1 : Tipe Data Harus berupa jpg,jpeg, atau png", 1);
          // throw new MyException([ "attachment_1" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);
  
          $image = Image::read($path)->scale(height: $this->height);
          $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
  

          $file_name = $model_query->id."_att1_".Str::uuid() . '.' . $ext;
          $location = "extra_money_trx/$file_name";

          // $date = new \DateTime();
          // $timestamp = $date->format("Y-m-d H:i:s.v");
          // $file_name = md5(preg_replace('/( |-|:)/', '', $timestamp)) . '.' . $ext;
          // $location = "/files/extra_money_trx/{$file_name}";
          try {
            ini_set('memory_limit', '256M');
            Storage::disk('public')->put(
              $location,
              $compressedImageBinary
            );
            // Image::read($compressedImageBinary)->save(files_path($location));
          } catch (\Exception $e) {
            throw new \Exception("Simpan Foto Gagal");
          }
  
          // if ($model_query->attachment_1_loc != null && Storage::disk('public')->exists($model_query->attachment_1_loc)) {
          //     Storage::disk('public')->delete($model_query->attachment_1_loc);
          // }
          
          $att_temp['att_1']['newLoc']=$location;
          $att_temp['att_1']['newType']=$fileType;
          $att_temp['att_1']['useNew']=true;
        }
  
        if (!$request->hasFile('attachment_1') && in_array($attachment_1_preview,[null,'null'])) {
          // if ($model_query->attachment_1_loc != null && Storage::disk('public')->exists(files_path($model_query->attachment_1_loc))) {
          //   Storage::disk('public')->delete($model_query->attachment_1_loc);
          // }
          // $location = null;
          // $fileType = null;

          $att_temp['att_1']['newLoc']=null;
          $att_temp['att_1']['newType']=null;
          $att_temp['att_1']['useNew']=true;

          // $model_query->attachment_1_type   = $fileType;
          // $model_query->attachment_1_loc    = $location;
        }
  
        $model_query->attachment_1_type = $att_temp['att_1']['useNew']?$att_temp['att_1']['newType']:$att_temp['att_1']['oldType'];
        $model_query->attachment_1_loc = $att_temp['att_1']['useNew']?$att_temp['att_1']['newLoc']:$att_temp['att_1']['oldLoc'];

        $model_query->extra_money_id      = $request->extra_money_id;
        $model_query->tanggal             = $request->tanggal;
        $model_query->employee_id         = $employee->id;
        $model_query->employee_rek_no     = $employee->rek_no;
        $model_query->employee_rek_name   = $employee->rek_name;
        $model_query->no_pol              = $request->no_pol;
        $model_query->note_for_remarks    = $request->note_for_remarks;
  
        $model_query->prev_trx_trp_id     = $request->prev_trx_trp_id;
        $model_query->payment_method_id   = $request->payment_method_id;
      }

      // if($request->hasFile('attachment_2')){
      //   $file = $request->file('attachment_2');
      //   $path = $file->getRealPath();
      //   $fileType = $file->getClientMimeType();
      //   // $blobFile = base64_encode(file_get_contents($path));
      //   // $change++;


      //   $ext = $file->extension();
      //   if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
      //   throw new MyException([ "attachment_2" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);

      //   $image = Image::read($path)->scale(height: $this->height);
      //   $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));

      //   $date = new \DateTime();
      //   $timestamp = $date->format("Y-m-d H:i:s.v");
      //   $file_name = md5(preg_replace('/( |-|:)/', '', $timestamp)) . '.' . $ext;
      //   $location = "/files/extra_money_trx/{$file_name}";
      //   try {
      //     ini_set('memory_limit', '256M');
      //     Image::read($compressedImageBinary)->save(files_path($location));
      //   } catch (\Exception $e) {
      //     throw new \Exception("Simpan Foto Gagal");
      //   }

      //   if (File::exists(files_path($model_query->attachment_2_loc)) && $model_query->attachment_2_loc != null) {
      //     if (!unlink(files_path($model_query->attachment_2_loc)))
      //       throw new \Exception("Gagal Hapus Gambar", 1);
      //   }
        
      //   $model_query->attachment_2_type   = $fileType;
      //   $model_query->attachment_2_loc    = $location;

      // }
      $att_temp['att_2']['oldLoc']=$model_query->attachment_2_loc;
      $att_temp['att_2']['oldType']=$model_query->attachment_2_type;

      if($request->hasFile('attachment_2')){
        $file = $request->file('attachment_2');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        // $blobFile = base64_encode(file_get_contents($path));
        // $change++;

        $ext = $file->extension();


        $file_name = $model_query->id."_att2_".Str::uuid() . '.' . $ext;
        $location2 = "extra_money_trx/$file_name";

        // $date = new \DateTime();
        // $timestamp = $date->format("Y-m-d H:i:s.v");
        // $file_name = md5(preg_replace('/( |-|:)/', '', $timestamp)) . '.' . $ext;
        // $location2 = "/files/extra_money_trx/{$file_name}";

        if(preg_match("/^image\/(jpeg|jpg|png)$/",$fileType)){
          $image = Image::read($path)->scale(height: $this->height);
          $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
          try {
            ini_set('memory_limit', '256M');
            Storage::disk('public')->put(
              $location2,
              $compressedImageBinary
            );
            // Image::read($compressedImageBinary)->save(files_path($location2));
          } catch (\Exception $e) {
            throw new \Exception("Simpan Foto Gagal");
          }
        }else{
          try {
            // Set memory limit (optional)
            ini_set('memory_limit', '256M');      
            // Save the PDF file
            Storage::disk('public')->put($location2, file_get_contents($path));
            // file_put_contents(files_path($location2), file_get_contents($path));
          } catch (\Exception $e) {
            throw new \Exception("Simpan PDF Gagal");
          }
        }

        
        $att_temp['att_2']['newLoc']=$location2;
        $att_temp['att_2']['newType']=$fileType;
        $att_temp['att_2']['useNew']=true;

        // if ($model_query->attachment_2_loc != null && Storage::disk('public')->exists($model_query->attachment_2_loc)) {
        //   Storage::disk('public')->delete($model_query->attachment_2_loc);
        // }
        
        // $model_query->attachment_2_type   = $fileType;
        // $model_query->attachment_2_loc    = $location2;

      }

      if (!$request->hasFile('attachment_2') && in_array($attachment_2_preview,[null,'null'])) {
        $att_temp['att_2']['newLoc']=null;
        $att_temp['att_2']['newType']=null;
        $att_temp['att_2']['useNew']=true;

        // if ($model_query->attachment_2_loc != null && Storage::disk('public')->exists($model_query->attachment_2_loc)) {
        //   Storage::disk('public')->delete($model_query->attachment_2_loc);
        // }
        // $location2 = null;
        // $fileType = null;
        // $model_query->attachment_2_type   = $fileType;
        // $model_query->attachment_2_loc    = $location2;
      }
      $model_query->attachment_2_type = $att_temp['att_2']['useNew']?$att_temp['att_2']['newType']:$att_temp['att_2']['oldType'];
      $model_query->attachment_2_loc = $att_temp['att_2']['useNew']?$att_temp['att_2']['newLoc']:$att_temp['att_2']['oldLoc'];

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      array_unshift( $SYSNOTES , $SYSNOTE);            
      MyLog::sys("extra_money_trx",$request->id,"update",implode("\n",$SYSNOTES));

      DB::commit();

      try {
        ini_set('memory_limit', '256M');
        if ($att_temp['att_1']['useNew'] &&  $att_temp['att_1']['oldLoc']!= null && Storage::disk('public')->exists($att_temp['att_1']['oldLoc'])) {
          Storage::disk('public')->delete($att_temp['att_1']['oldLoc']);
        }

        if ($att_temp['att_2']['useNew'] &&  $att_temp['att_2']['oldLoc']!= null && Storage::disk('public')->exists($att_temp['att_2']['oldLoc'])) {
          Storage::disk('public')->delete($att_temp['att_2']['oldLoc']);
        }
      } catch (\Exception $e) {
        
      }

      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      try {
        ini_set('memory_limit', '256M');
        if ($att_temp['att_1']['useNew'] &&  $att_temp['att_1']['newLoc']!= null && Storage::disk('public')->exists($att_temp['att_1']['newLoc'])) {
          Storage::disk('public')->delete($att_temp['att_1']['newLoc']);
        }

        if ($att_temp['att_2']['useNew'] &&  $att_temp['att_2']['newLoc']!= null && Storage::disk('public')->exists($att_temp['att_2']['newLoc'])) {
          Storage::disk('public')->delete($att_temp['att_2']['newLoc']);
        }
      } catch (\Exception $e) {
        
      }

      // if ($location && File::exists(files_path($location)))
      // unlink(files_path($location));
      
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
      $SYSOLD                     = clone($model_query);
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      if($model_query->req_deleted==1  || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Di (Permintaan) Hapus",1);

      if($model_query->pvr_id)
      throw new \Exception("Harap Lakukan Permintaan Penghapusan Terlebih Dahulu",1);

      if($model_query->received_payment==1) 
      throw new \Exception("Pembayaran Sudah Dilakukan. Harap Lakukan Permintaan Penghapusan Terlebih Dahulu",1);

      if($model_query->trx_trp_id){
        if(TrxTrp::where('id',$model_query->trx_trp_id)->lockForUpdate()->first()->pvr_no){
          throw new \Exception("Harap Lakukan Permintaan Penghapusan Terlebih Dahulu",1);
        }
      }

      if($model_query->val1 || $model_query->val2 || $model_query->val3 || $model_query->val4 || $model_query->val5 || $model_query->val6)
      throw new \Exception("Unvalidasi terlebih dahulu untuk menghapus",1);

      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      

      // if($model_query->trx_trp_id) 
      // throw new \Exception("Data Sudah Terlink Dengan Trx Trp Dan Tidak Dapat Di hapus",1);
      $model_query->trx_trp_id = null;
      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("extra_money_trx",$request->id,"delete",$SYSNOTE);

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
      
      if($model_query->deleted==1 || $model_query->req_deleted==1 )
      throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

      if($model_query->pvr_id==null)
      throw new \Exception("Harap Lakukan Penghapusan",1);

      // if($model_query->trx_trp_id) 
      // throw new \Exception("Data Sudah Terlink Dengan Trx Trp Dan Tidak Dapat Di Ubah",1);

      $req_deleted_reason = $request->req_deleted_reason;
      if(!$req_deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $SYSOLD                     = clone($model_query);
      $model_query->req_deleted = 1;
      $model_query->req_deleted_user = $this->admin_id;
      $model_query->req_deleted_at = date("Y-m-d H:i:s");
      $model_query->req_deleted_reason = $req_deleted_reason;
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("extra_money_trx",$request->id,"req_delete",$SYSNOTE);


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

      if($model_query->deleted==1 )
      throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

      if($model_query->pvr_id==null)
      throw new \Exception("Harap Lakukan Penghapusan",1);

      $reason_adder = $request->reason_adder;
      $deleted_reason = $model_query->req_deleted_reason.($reason_adder?" | ".$reason_adder:"");
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);
      
      $SYSOLD                     = clone($model_query);
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

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("extra_money_trx",$request->id,"req_app_delete",$SYSNOTE);

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
      "note_for_remarks"=>$extra_money_trx->note_for_remarks,
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

  public function previewFileBT(Request $request){
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.preview_file');

    set_time_limit(0);

    $extra_money_trx = ExtraMoneyTrx::find($request->id);

    if($extra_money_trx->val1==0)
    return response()->json([
      "message" => "Harap Di Validasi Terlebih Dahulu",
    ], 400);
    $extra_money = \App\Models\MySql\ExtraMoney::where("id",$extra_money_trx->extra_money_id)->first();

    $employee_remarks  = "EM#".$extra_money_trx->id;

    $employee_money = $extra_money->nominal * $extra_money->qty;
  
    $sendData = [
      "id"              => $extra_money_trx->id,
      "ext"             => $extra_money->id,
      "logo"            => File::exists(files_path("/duitku.png")) ? "data:image/png;base64,".base64_encode(File::get(files_path("/duitku.png"))) :"",

      "ref_no"          => $extra_money_trx->duitku_employee_disburseId,
      "employee_name"   => $extra_money_trx->employee->name,
      "employee_rek_no" => $extra_money_trx->employee_rek_no,
      "nominal"         => $employee_money,
      "tanggal"         => $extra_money_trx->rp_employee_at,
      "remarks"         => $employee_remarks,
    ];   
    $html = view("html.extra_money_trx_bt",$sendData);  
    $result = [
      "html"=>$html->render()
    ];
    return $result;
  }

  public function reportExcel(Request $request){
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.views');

    set_time_limit(0);
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];

    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {

      $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      // $value['amount']=$value["amount"];
      // $value['pv_total']=$value["pv_total"];
      $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
      $value['created_at']=$value["created_at"] ? date("d-m-Y H:i:s",strtotime($value["created_at"])) : "";
      $value['updated_at']=$value["updated_at"] ? date("d-m-Y H:i:s",strtotime($value["updated_at"])) : "";
      
      $value["xto"] = $value["extra_money"]["xto"];
      $value["jenis"] = $value["extra_money"]["jenis"];
      $value["transition_target"] = $value["extra_money"]["transition_target"];
      $value["transition_type"] = $value["extra_money"]["transition_type"];
      $value["nominal"] = $value["extra_money"]["nominal"];
      $value["qty"] = $value["extra_money"]["qty"];
      $value["description"] = $value["extra_money"]["description"];
      $value["amount"] = $value["nominal"] * $value["qty"];
      array_push($newDetails,$value);
    }

    $filter_model = json_decode($request->filter_model,true);
    $tanggal = $filter_model['tanggal'];    

    $date_from=date("d-m-Y",strtotime($tanggal['value_1']));
    $date_to=date("d-m-Y",strtotime($tanggal['value_2']));

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-extra_money_trx'."[".$date_from."_".$date_to."]";

    $mime=MyLib::mime("xlsx");
    // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.extra_money_trxs'), $mime["exportType"]));

    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }

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
      $model_query = ExtraMoneyTrx::where("id",$request->id)->lockForUpdate()->first();
      
      if(!$model_query->attachment_1_loc)
      throw new \Exception("Harap Mengisi Attachment Terlebih dahulu",1);
      $SYSOLD                     = clone($model_query);

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
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 

      MyLog::sys("extra_money_trx",$request->id,"approve",$SYSNOTE);

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

  public function unvalidasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['extra_money_trx.unval1','extra_money_trx.unval2','extra_money_trx.unval3','extra_money_trx.unval4','extra_money_trx.unval5','extra_money_trx.unval6']);

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
      $model_query = ExtraMoneyTrx::where("id",$request->id)->lockForUpdate()->first();
      
      $SYSOLD                     = clone($model_query);

      if($model_query->pvr_no || $model_query->received_payment)
      throw new \Exception("Extra money ini sudah tidak bisa di unvalidasi",1);
    
      $run_val = 0; 
  
      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.unval1',true) && $model_query->val1){
        $run_val++;
        $model_query->val1 = 0;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.unval2',true) && $model_query->val2){
        $run_val++;
        $model_query->val2 = 0;
        $model_query->val2_user = $this->admin_id;
        $model_query->val2_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.unval3',true) && $model_query->val3){
        $run_val++;
        $model_query->val3 = 0;
        $model_query->val3_user = $this->admin_id;
        $model_query->val3_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.unval4',true) && $model_query->val4){
        $run_val++;
        $model_query->val4 = 0;
        $model_query->val4_user = $this->admin_id;
        $model_query->val4_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.unval5',true) && $model_query->val5){
        $run_val++;
        $model_query->val5 = 0;
        $model_query->val5_user = $this->admin_id;
        $model_query->val5_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'extra_money_trx.unval6',true) && $model_query->val6){
        $run_val++;
        $model_query->val6 = 0;
        $model_query->val6_user = $this->admin_id;
        $model_query->val6_at = $t_stamp;
      }


      $model_query->save();
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 

      MyLog::sys("extra_money_trx",$request->id,"unvalidate",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses unvalidasi data berhasil",
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
          $q1->whereIn("payment_method_id",[2,3,4,5]);
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
    array_push($arrRemarks,"EM#".$extra_money_trx->id.($extra_money_trx->prev_trx_trp_id?" UJ#".$extra_money_trx->prev_trx_trp_id:"").($extra_money->transition_type!=''?" (P) " : " ").$associate_name.".");
    array_push($arrRemarks,$extra_money->description." ".($extra_money->xto ? env("app_name")."-".$extra_money->xto : "")).".";
    // array_push($arrRemarks," P/".date("d-m-y",strtotime($extra_money_trx->tanggal)));

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

    //A=== karena tidak bayar terpisah maka ini tidak perlu di aktifkan
    // if($extra_money_trx->duitku_employee_disburseId && $extra_money_trx->payment_method->id==2){
    //   $adm_cost = 2500;
    //   // $adm_cost = 5000;
    //   $adm_qty = 1;

    //   $amount_paid += ($adm_cost * $adm_qty);
    // }

    // if($extra_money_trx->duitku_employee_disburseId && $extra_money_trx->payment_method->id==3){
    //   // $adm_cost = 2500;
    //   $adm_cost = 5000;
    //   $adm_qty = 1;

    //   $amount_paid += ($adm_cost * $adm_qty);
    // }
    //A=== sehingga untuk payment_method 4 tidak di pertimbangkan.


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

    //A=== karena tidak bayar terpisah maka ini tidak perlu di aktifkan
    
    // if($extra_money_trx->duitku_employee_disburseId && in_array($extra_money_trx->payment_method->id,[2,3])){
    //   $admin_cost_code=env("PVR_ADMIN_COST");
  
    //   $admin_cost_db = DB::connection('sqlsrv')->table('ac_accounts')
    //   ->select('AccountID')
    //   ->where('isdisabled',0)
    //   ->where("AccountCode",$admin_cost_code)
    //   ->first();
    //   if(!$admin_cost_db) throw new \Exception("GL account code tidak terdaftar ,segera infokan ke tim IT",1);

    //   $adm_cost_id = $admin_cost_db->AccountID;
    //   $adm_fee_exists= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
    //   ->select('VoucherID')
    //   ->where("VoucherID",$d_voucher_id)
    //   ->where("AccountID",$adm_cost_id)
    //   ->first();

    //   if(!$adm_fee_exists){
    //     $d_description  = "Biaya Admin";
    //     $d_account_id   = $adm_cost_id;
    //     $d_dept         = '112';
    //     $d_qty          = $adm_qty;
    //     $d_unit_price   = $adm_cost;
    //     $d_amount       = $d_qty * $d_unit_price;
  
    //     DB::connection('sqlsrv')->update("exec 
    //     USP_FI_APRequestExtraItems_Update @VoucherID=:d_voucher_id,
    //     @VoucherExtraItemID=:d_voucher_extra_item_id,
    //     @Description=:d_description,@Amount=:d_amount,
    //     @AccountID=:d_account_id,@TypeID=:d_type,
    //     @Department=:d_dept,@LoginName=:login_name,
    //     @Qty=:d_qty,@UnitPrice=:d_unit_price",[
    //       ":d_voucher_id"=>$d_voucher_id,
    //       ":d_voucher_extra_item_id"=>$d_voucher_extra_item_id,
    //       ":d_description"=>$d_description,
    //       ":d_amount"=>$d_amount,
    //       ":d_account_id"=>$d_account_id,
    //       ":d_type"=>$d_type,
    //       ":d_dept"=>$d_dept,
    //       ":login_name"=>$login_name,
    //       ":d_qty"=>$d_qty,
    //       ":d_unit_price"=>$d_unit_price
    //     ]);
    //   }
    // }
    //A=== sehingga untuk payment_method 4 tidak di pertimbangkan.

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

  public function doGenPV(Request $request){
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.generate_pv');
    $rules = [
      // 'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
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
      $extra_money_trxs = ExtraMoneyTrx::where(function($q1){$q1->where('pv_complete',0)->orWhereNull("pv_id");})->whereNotNull("pvr_id")->where("req_deleted",0)->where("deleted",0)->where('val1',1)->where('val2',1)
      ->where(function ($q) {
        $q->where(function ($q1){
          $q1->where("payment_method_id",1);       
          $q1->where("received_payment",0);                  
        });

        $q->orWhere(function ($q1){
          $q1->whereIn("payment_method_id",[2,3,4,5]);
          $q1->where("received_payment",1);                 
        });
      })->get();      
      if(count($extra_money_trxs)==0){
        throw new \Exception("Semua PV sudah terisi",1);
      }
      $changes=[];
      foreach ($extra_money_trxs as $key => $tt) {
        $id=$tt->id;
        $callGet = $this->genPV($id);
        array_push($changes,$callGet);
      }
      if(count($changes)>0){
        $ids = array_map(function ($x) {
          return $x["id"];
        },$changes);
        MyLog::sys("extra_money_trx",null,"generate_pv",implode(",",$ids));
      }
      return response()->json($changes, 200);
    } catch (\Exception $e) {
      if(isset($changes) && count($changes)>0){
        $ids = array_map(function ($x) {
          return $x["id"];
        },$changes);
        MyLog::sys("extra_money_trx",null,"generate_pv",implode(",",$ids));
      }

      if ($e->getCode() == 1) {
        if($id!=""){
          $miniError.="Trx-".$id.".";
        }
        $miniError.="PV Batal Dibuat: ".$e->getMessage();
      }else{
        if($id!=""){
          $miniError.="Trx-".$id.".";
        }
        $miniError.="PV Batal Dibuat. Akses Jaringan Gagal";
      }
      return response()->json([
        "message" => $miniError,
      ], 400);
    }
  }

  public function genPV($extra_money_trx_id){

    $t_stamp = date("Y-m-d H:i:s");

    $extra_money_trx = ExtraMoneyTrx::where("id",$extra_money_trx_id)->first();
    if(!$extra_money_trx){
      throw new \Exception("Karna Transaksi tidak ditemukan",1);
    }

    if($extra_money_trx->pv_complete==1) throw new \Exception("Karna PV sudah selesai dibuat",1);
    if($extra_money_trx->pvr_id==null) throw new \Exception("Karna PVR Masih Kosong",1);
    if($extra_money_trx->pvr_complete==0) throw new \Exception("Karna PVR Masih Belum Selesai",1);

    $pvr_dt = DB::connection('sqlsrv')->table('FI_APRequest')
    ->select('BankAccountID','AmountPaid','VoucherID','AssociateName','Remarks','VoucherType','IncomeOrExpense','CurrencyID','PaymentMethod','CheckNo','CheckDueDate','BankName','AccountNo','ExcludeInARAP','ExpenseOrRevenueTypeID','Confidential','AssociateName')
    ->where("VoucherID",$extra_money_trx->pvr_id)
    ->first();

    if(!$pvr_dt) throw new \Exception("PVR tidak terdaftar ,segera infokan ke tim IT",1);
    
    $voucher_no = "(AUTO)";
    $voucher_date = date("Y-m-d");

    $login_name = $this->admin->the_user->username;
    $sourceVoucherId = $extra_money_trx->pvr_id; //ambil pvr id trx

    $pv= DB::connection('sqlsrv')->table('FI_ARAP')
    ->select('VoucherID','VoucherDate','VoucherNo','AmountPaid','SourceVoucherId')
    ->where("SourceVoucherId",$sourceVoucherId)
    ->where("Void",0)
    ->first();

    if(!$pv){
      $myData = DB::connection('sqlsrv')->update("exec USP_FI_ARAP_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
      @VoucherDate=:voucher_date,@IncomeOrExpense=:income_or_expense,@CurrencyID=:currency_id,@AssociateName=:associate_name,
      @BankAccountID=:bank_account_id,@PaymentMethod=:payment_method,@CheckNo=:check_no,@CheckDueDate=:check_due_date,
      @BankName=:bank_name,@AmountPaid=:amount_paid,@AccountNo=:account_no,@Remarks=:remarks,@ExcludeInARAP=:exclude_in_ARAP,
      @LoginName=:login_name,@ExpenseOrRevenueTypeID=:expense_or_revenue_type_id,@Confidential=:confidential,
      @SourceVoucherId=:sourceVoucherId",[
        ":voucher_no"                 => $voucher_no,
        ":voucher_type"               => $pvr_dt->VoucherType,
        ":voucher_date"               => $voucher_date,
        ":income_or_expense"          => $pvr_dt->IncomeOrExpense,
        ":currency_id"                => $pvr_dt->CurrencyID,
        ":associate_name"             => $pvr_dt->AssociateName,
        ":bank_account_id"            => $pvr_dt->BankAccountID,
        ":payment_method"             => $pvr_dt->PaymentMethod,
        ":check_no"                   => $pvr_dt->CheckNo,
        ":check_due_date"             => $pvr_dt->CheckDueDate,
        ":bank_name"                  => $pvr_dt->BankName,
        ":amount_paid"                => $pvr_dt->AmountPaid,
        ":account_no"                 => $pvr_dt->AccountNo,
        ":remarks"                    => $pvr_dt->Remarks,
        ":exclude_in_ARAP"            => $pvr_dt->ExcludeInARAP,
        ":login_name"                 => $login_name,
        ":expense_or_revenue_type_id" => $pvr_dt->ExpenseOrRevenueTypeID,
        ":confidential"               => $pvr_dt->Confidential,
        ":sourceVoucherId"            => $sourceVoucherId,
      ]);
      if(!$myData)
      throw new \Exception("Data yang diperlukan tidak terpenuhi",1);
    
      $pv= DB::connection('sqlsrv')->table('FI_ARAP')
      ->select('VoucherID','VoucherDate','VoucherNo','AmountPaid','SourceVoucherId')
      ->where("SourceVoucherId",$sourceVoucherId)
      ->where("Void",0)
      ->first();
      if(!$pv)
      throw new \Exception("Akses Ke Jaringan Gagal",1);
    }

    $extra_money_trx->pv_id       = $pv->VoucherID;
    $extra_money_trx->pv_datetime = $pv->VoucherDate;
    $extra_money_trx->pv_no       = $pv->VoucherNo;
    $extra_money_trx->pv_total    = $pv->AmountPaid;
    $extra_money_trx->save();
    
    $d_voucher_id                 = $pv->VoucherID;
    $d_voucher_extra_item_id      = 0;

    $pvr_detail= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
    ->select('VoucherID','VoucherExtraItemID','Description','Amount','AccountID','TypeID','Department','Qty','UnitPrice')
    ->where("VoucherID",$pvr_dt->VoucherID)
    ->get();


    $pv_detail= DB::connection('sqlsrv')->table('FI_ARAPExtraItems')
    ->select('VoucherID')
    ->where("VoucherID",$d_voucher_id)
    ->get();

    if(count($pv_detail)==0 || count($pv_detail) < count($pvr_detail)){
      $start = count($pv_detail);
      foreach ($pvr_detail as $key => $v) {
        if($key < $start){ continue; }
        $details = DB::connection('sqlsrv')->update("exec 
        USP_FI_ARAPExtraItems_Update @VoucherID=:d_voucher_id,
        @VoucherExtraItemID=:d_voucher_extra_item_id,
        @Description=:d_description,@Amount=:d_amount,
        @AccountID=:d_account_id,@TypeID=:d_type,
        @Department=:d_dept,@LoginName=:login_name,
        @Qty=:d_qty,@UnitPrice=:d_unit_price,@PVRVoucherExtraItemID=:pvr_voucher_extra_item_id",[
          ":d_voucher_id"               => $d_voucher_id,
          ":d_voucher_extra_item_id"    => $d_voucher_extra_item_id,
          ":d_description"              => $v->Description,
          ":d_amount"                   => $v->Amount,
          ":d_account_id"               => $v->AccountID,
          ":d_type"                     => $v->TypeID,
          ":d_dept"                     => $v->Department,
          ":login_name"                 => $login_name,
          ":d_qty"                      => $v->Qty,
          ":d_unit_price"               => $v->UnitPrice,
          ":pvr_voucher_extra_item_id"  => $v->VoucherExtraItemID
        ]);
      }
    }

    if($pv){
      DB::connection('sqlsrv')->update("exec 
      USP_FI_ARAP_UpdateAmountAllocated @VoucherID=:d_voucher_id",[
        ":d_voucher_id"               => $d_voucher_id,
      ]);

      DB::connection('sqlsrv')->update("exec 
      USP_FI_ARAPSources_Update @VoucherID=:d_voucher_id, @PVRVoucherID=:d_pvr_voucher_id",[
        ":d_voucher_id"               => $d_voucher_id,
        ":d_pvr_voucher_id"           => $pvr_dt->VoucherID,
      ]);

      DB::connection('sqlsrv')->update("exec 
      USP_FI_ARAP_BatchPostingToGL @VoucherID=:d_voucher_id, @LoginName=:login_name",[
        ":d_voucher_id"               => $d_voucher_id,
        ":login_name"                 => $login_name,
      ]);
    }

    $extra_money_trx->pv_complete = 1;
    $extra_money_trx->save();

    return [
      "message"     => "PVR berhasil dibuat",
      "id"          => $extra_money_trx->id,
      "pv_id"       => $extra_money_trx->pv_id,
      "pv_no"       => $extra_money_trx->pv_no,
      "pv_datetime" => $extra_money_trx->pv_datetime,
      "pv_total"    => $extra_money_trx->pv_total,
      "pv_complete" => $extra_money_trx->pv_complete,
      "updated_at"  => $t_stamp
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
      $extra_money_trxs = ExtraMoneyTrx::whereNotNull("pvr_id")->where("pvr_complete",1)->whereNull("pv_id")->where("req_deleted",0)->where("deleted",0)->get();
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
        $ud_extra_money_trx->pv_complete=1;
        $ud_extra_money_trx->updated_at=$t_stamp;
        $ud_extra_money_trx->save();
        array_push($changes,[
          "id"=>$ud_extra_money_trx->id,
          "pv_id"=>$ud_extra_money_trx->pv_id,
          "pv_no"=>$ud_extra_money_trx->pv_no,
          "pv_total"=>$ud_extra_money_trx->pv_total,
          "pv_datetime"=>$ud_extra_money_trx->pv_datetime,
          "pv_complete"=>$ud_extra_money_trx->pv_complete,
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


  public function getAttachment($id,$n)
  {
    MyAdmin::checkScope($this->permissions, 'extra_money_trx.view');



    $trx = ExtraMoneyTrx::findOrFail($id);

    $locField  = "attachment_{$n}_loc";
    $typeField = "attachment_{$n}_type";

    abort_unless($trx->$locField, 404);

    abort_unless(Storage::disk('public')->exists($trx->$locField), 404);

    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
    $disk = Storage::disk('public');  
    return $disk->response(
        $trx->$locField,
        null,
        [
            'Cache-Control' => 'no-store, private',
            'Content-Type'  => $trx->$typeField,
            'X-Attachment'  => $n,
        ]
    );

    // $path = files_path($trx->$locField);
    // abort_unless(File::exists($path), 404);

    // return response()->file($path, [
    //   'Cache-Control'=> 'no-store, private',
    //   'Content-Type'  => $trx->$typeField,
    //   'X-Attachment' => $n,
    // ]);

      // MyAdmin::checkScope(['extra_money_trx.view']);

      // $trx = ExtraMoneyTrx::findOrFail($id);

      // if (!$trx->attachment_1_loc) {
      //     abort(404);
      // }

      // $path = files_path($trx->attachment_1_loc);

      // if (!File::exists($path)) {
      //     abort(404);
      // }

      // return response()->file($path, [
      //     'Content-Type' => $trx->attachment_1_type,
      //     'Cache-Control' => 'no-store, private',
      //     'X-Attachment-Id' => $trx->id,
      //     'X-Attachment-Type' => 'attachment_1',
      // ]);
  }

  
}
