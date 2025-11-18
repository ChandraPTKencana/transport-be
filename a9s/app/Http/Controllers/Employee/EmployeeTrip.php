<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

use App\Models\MySql\TrxTrp;

use App\Http\Resources\MySql\TrxTrpAbsenResource;
use App\Models\MySql\TrxAbsen;
use Spatie\Browsershot\Browsershot;

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
      // ->where("ritase_val2",0)
      ->where("deleted",0)
      ->where("req_deleted",0)
      ->where("tanggal",">=","2025-09-17")
      ->orderBy("id","asc")
      ->first();

      if(!$model_query)
        return response()->json([
          "data"=>["id"=>-1],
        ],200);


      $data = new TrxTrpAbsenResource($model_query);
      $data = collect($data);

      $data['tanggal'] = date("d-m-Y",strtotime($data['tanggal']));
      $data['ritase_leave_at'] = date("d-m-Y H:i:s",strtotime($data['ritase_leave_at']));
      $data['ritase_arrive_at'] = date("d-m-Y H:i:s",strtotime($data['ritase_arrive_at']));
      $data['ritase_return_at'] = date("d-m-Y H:i:s",strtotime($data['ritase_return_at']));
      $data['ritase_till_at'] = date("d-m-Y H:i:s",strtotime($data['ritase_till_at']));

      $data['supir_name'] = $model_query->employee_s->name;
      $data['kernet_name'] = $model_query->employee_k ? $model_query->employee_k->name : "";

      // $img_leaves = [];
      $data['img_leave']="";
      foreach ($model_query->trx_absens as $k => $v) {
        // mb_convert_encoding($img, 'UTF-8', 'UTF-8')
        $img = "data:image/png;base64,";
        if(mb_detect_encoding($v->gambar)===false){
          // $img.=base64_encode($v->gambar);
          $image = Image::read($v->gambar);

            // Mengubah ukuran gambar (misalnya, menjadi lebar 600px).
            // Gunakan scale() untuk menjaga rasio aspek secara otomatis.
            $image->scale(width: 300);

            // Mengubah ke format JPEG dengan kualitas 75 (dapat disesuaikan).
            // toJpeg() mengembalikan objek EncodedImage.
            $compressedImage = $image->toJpeg(quality: 50);

            // Mengonversi data biner dari objek EncodedImage ke Base64
            $img .= base64_encode($compressedImage);
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
      // MyLog::logging($e->getMessage(),"img_error");

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

    if(!isset($request->lat) || !isset($request->lng)){
      return response()->json([
        "message" => "Koordinat harus disertakan",
      ], 400);
    }
    
    $chromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe';

    $t_stamp = date("Y-m-d H:i:s");
    $data=[];
    DB::beginTransaction();
    try {
      $model_query = TrxTrp::where("id",$request->id)
      ->first();

      $status = substr($request->status,0,1);
      if($request->hasFile('image')){

        // $url = "https://www.google.com/maps/@{$request->lat},{$request->lng},17z";
        // // $url = "https://www.google.com/maps/@3.704385,98.660519,17z";
        
        // $svg_file=File::get(files_path("/location_on.png"));

        // $position = Image::read(Browsershot::url($url)
        // ->setChromePath($chromePath) // Set Chrome path manually for Windows
        // ->windowSize(800, 800) // Set viewport size
        // ->waitUntilNetworkIdle() // Wait for page to fully load
        // // ->save(storage_path('app/public/map_screenshot.png'));
        // // ->fullPage()
        // ->screenshot())->crop(320 ,320 ,240,240)
        // ->place(Image::read($svg_file)->scale(40,40),'center',0,-10);

        // $file = $request->file('image');
        // $path = $file->getRealPath();
        // $fileType = $file->getClientMimeType();
        // $image = Image::read($path)->scale(height: $this->height)->place(Image::read($position)->scale(150),'top-left',10,10);
        // $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
        // $blob_img = base64_encode($compressedImageBinary); 


        // $imgtext = Image::text('Hello World!', 50, 50, function($font) {
        //   // $font->file(public_path('fonts/arial.ttf')); // Font custom
        //   $font->size(50); // Ukuran font
        //   $font->color('#ffffff'); // Warna font (putih)
        //   $font->align('center');
        //   $font->valign('top');
        //   $font->angle(0); // Sudut teks
        // });
        $data['tanggalwaktu'] = date("d-m-Y H:i:s",strtotime($t_stamp));

        $file = $request->file('image');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $image = ImageManager::gd()->read($path)->scale(height: $this->height)
        ->text("LAT:{$request->lat},LONG:{$request->lng}", 5, 5, function($font) {
          // $font->file(public_path('fonts/arial.ttf')); // Font custom
          $font->size(200);
          $font->color('#ffffff'); // Warna font (putih)
          $font->stroke('ff5500', 1); // Ukuran font
          $font->align('left');
          $font->valign('top');
          $font->angle(0); // Sudut teks
        })
        ->text($data['tanggalwaktu'], 5, 15, function($font) {
          // $font->file(public_path('fonts/arial.ttf')); // Font custom
          $font->size(200);
          $font->color('#ffffff'); // Warna font (putih)
          $font->stroke('ff5500', 1); // Ukuran font
          $font->align('left');
          $font->valign('top');
          $font->angle(0); // Sudut teks
        });
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
      // MyLog::logging(response()->json([
      //     "getCode" => $e->getCode(),
      //     "line" => $e->getLine(),
      //     "message" => $e->getMessage(),
      //   ], 400));
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
