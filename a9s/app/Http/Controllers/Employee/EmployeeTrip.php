<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Helpers\MyLib;

use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;

use App\Models\MySql\Employee;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\EmployeeRequest;

use App\Http\Resources\MySql\EmployeeResource;
use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\TrxTrp;

use App\Http\Resources\MySql\TrxTrpAbsenResource;
use App\Models\MySql\TrxAbsen;

class EmployeeTrip extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'employee_mst';

 
  public function get_trip(Request $request){

    $admin = MyAdmin::employee();
    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query = TrxTrp::where(function ($q)use($admin){
        $q->where("supir_id",$admin->the_employee->id);
        $q->orWhere("kernet_id",$admin->the_employee->id);
      })->where(function ($q)use($admin){
        $q->whereNull("ritase_leave_at");
        $q->orwhereNull("ritase_arrive_at");
        $q->orwhereNull("ritase_return_at");
        $q->orwhereNull("ritase_till_at");
      })
      ->orderBy("id","asc")
      ->first();

      if(!$model_query)
        return response()->json([
          "data"=>["id"=>-1],
        ],200);


      $data = new TrxTrpAbsenResource($model_query);
      $data = collect($data);

      $data['supir_name'] = $model_query->employee_s->name;
      $data['kernet_name'] = $model_query->employee_k ? $model_query->employee_k->name : "";

      // $img_leaves = [];
      $data['img_leave']="";
      foreach ($model_query->trx_absens as $k => $v) {
        // mb_convert_encoding($img, 'UTF-8', 'UTF-8')
        $img = "data:image/png;base64,";
        if(mb_detect_encoding($v->gambar)===false){
          $img.=base64_encode($v->gambar);
        }else{
          $img.=$v->gambar;        
        }
        
        if($v['status']=="B") {
          $data["img_leave"]   = $img;
          // array_push($img_leaves,[
          //   "id"=>$v["id"],
          //   "gambar"=>$img,
          // ]);
        }
  
        if($v['status']=="T") 
        $data["img_arrive"]   = $img;
  
        if($v['status']=="K") 
        $data["img_return"]   = $img;
  
        if($v['status']=="S") 
        $data["img_till"]   = $img;
      }
      // $data['img_leaves']=$img_leaves;



      DB::commit();

      return response()->json([
        "data"=>$data,
      ],200);
   
      // return response()->json([
      //   "message"       => "Proses Generate Code berhasil",
      // ], 200);
    } catch (\Exception $e) {
      MyLog::logging($e->getMessage(),"img_error");

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
        "message" => "Ambil Data Gagal",
      ], 400);
    }
  }

  private $height = 500;
  private $quality = 100;

  public function send_absen(Request $request){

    $admin = MyAdmin::employee();

    $rules = [
      'id' => 'required|exists:App\Models\MySql\TrxTrp,id',
      'status' => 'required|in:Berangkat,Tiba,Kembali,Sampai',
      'image' => 'required|image',
    ];

    $rule = [
      "id.required" => "ID diperlukan",
      "id.exists" => "ID tidak ditemukan",

      "status.required" => "Status diperlukan",
      "status.in" => "Status tidak ditemukan",

      "image.required" => "Gambar diperlukan",
      "image.image" => "Tipe gambar tidak cocok",
    ];

    $validator = Validator::make($request->all(), $rules, $rule);
    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $t_stamp = date("Y-m-d H:i:s");
    $data=[];
    DB::beginTransaction();
    try {
      $model_query = TrxTrp::where("id",$request->id)
      ->first();

      $status = substr($request->status,0,1);
      if($request->hasFile('image')){

        $file = $request->file('image');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $image = Image::read($path)->place(Image::read($path),'top-left',50,50)->scale(height: $this->height);
        $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
        $blob_img = base64_encode($compressedImageBinary); 


        TrxAbsen::insert([
          "status" => $status,
          "trx_trp_id" => $model_query->id,
          "gambar"=>$blob_img,
          "created_at"=>$t_stamp,
          "updated_at"=>$t_stamp,
          "created_user"=>$this->admin_id,
          "is_manual"=>0,
        ]);

        if($status=="B"){
          $model_query->ritase_leave_at = $t_stamp;
        }else if($status=="T"){
          $model_query->ritase_arrive_at = $t_stamp;
        }else if($status=="K"){
          $model_query->ritase_return_at = $t_stamp;
        }else if($status=="S"){          
          $model_query->ritase_till_at = $t_stamp;
        }

        $data['tanggalwaktu'] = date("d-m-Y H:i:s",strtotime($t_stamp));
        $data['gambar'] = "data:image/png;base64,".$blob_img;
        $model_query->save();
      }

      DB::commit();

      return response()->json([
        "data"=>$data,
      ],200);
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
        "message" => "Ambil Data Gagal",
      ], 400);
    }
  }

  // public function change_password(Request $request)
  // {
  //   $admin = MyAdmin::employee();

  //   $rules = [
  //     'password' => 'required|confirmed|min:8',
  //     'password_confirmation' => 'required|same:password|min:8',
  //   ];

  //   $rule = [
  //     "password.required" => "Kata Sandi harus diisi",
  //     "password.confirmed" => "Kata Sandi dan Ulang Kata Sandi tidak cocok",
  //     "password.min" => "Kata Sandi  minimal 8 karakter",
  //     "password.max" => "Kata Sandi maksimal 255 karakter",

  //     "password_confirmation.required" => "Ulangi Kata Sandi harus diisi",
  //     "password_confirmation.same" => "Ulangi Kata Sandi dan Kata Sandi tidak cocok",
  //     "password_confirmation.min" => "Ulangi Kata Sandi minimal 8 karakter",
  //     "password_confirmation.max" => "Ulangi Kata Sandi maksimal 255 karakter",
  //   ];

  //   $validator = Validator::make($request->all(), $rules, $rule);
  //   if ($validator->fails()) {
  //     throw new ValidationException($validator);
  //   }

  //   $admin->the_employee->password = bcrypt($request->password);
  //   $admin->the_employee->updated_at = date("Y-m-d H:i:s");
  //   $admin->the_employee->save();
  //   MyLog::sys_emp("employee_activity",$admin->the_employee->id,"change_password");

  //   return response()->json([
  //     "message" => "Kata sandi berhasil diubah".$admin->the_employee->id,
  //   ], 200);
  // }

}
