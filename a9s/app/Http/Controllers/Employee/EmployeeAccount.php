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

class EmployeeAccount extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'employee_mst';

  public function login(Request $request)
  {
    // sleep(11);
    // return response()->json([
    //     "1"=>"test1",
    //     "2"=>"data",
    //     "token"=>"token",
    // ],400);
    $request["username"] = strtolower($request->username);
    $rules = [
      'enKey' => 'required|exists:\App\Models\MySql\Employee,m_enkey',
      'username' => 'required|exists:\App\Models\MySql\Employee,username',
      'password' => "required|min:8",
      // 'password' => "required",
    ];

    $messages = [
      'enKey.required' => 'Kode Kunci tidak boleh kosong',
      'enKey.exists' => 'Kode Kunci tidak terdaftar',

      'username.required' => 'Nama Pengguna tidak boleh kosong',
      'username.exists' => 'Nama Pengguna tidak terdaftar',

      'password.required' => 'Kata Sandi tidak boleh kosong',
      'password.min' => 'Kata Sandi minimal 8 Karakter',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $enKey = $request->enKey;
    $username = $request->username;
    $password = $request->password;
    

    $employee = Employee::where("m_enkey", $enKey)->where("username", $username)->first();
    if (!$employee) {
      return response()->json([
        "message" => "Kode Kunci dan Nama pengguna tidak cocok"
      ], 400);

      // return response()->json([
      //   "username" => ["Nama pengguna dan Kode Kunci tidak cocok"],
      //   "enKey" => ["Kode Kunci dan Nama pengguna tidak cocok"]
      // ], 422);
    }

    if(!$employee->password){
      return response()->json([
        "message" => "Password belum di daftarkan"
      ], 400);
    }
    
    // if ($employee && $employee->is_active == 0) {
    //   return response()->json([
    //     "message" => "Izin Masuk Tidak Diberikan"
    //   ], 403);
    // }

    if (Hash::check($password,$employee->password)) {

      if($employee->m_face_login){
        if(!$employee->face_loc_target)
        throw new \Exception("Anda Masih Belum Memiliki Foto, Kabari Ke Pihak Logistik",1);

        if(!$request->hasFile('image'))
        return response()->json([
          // "message"=>"Berhasil login",
          "choice"=>"need_face",
        ],300);

        $employee_source_face = File::get(files_path($employee->face_loc_target));
        if($request->hasFile('image')){
            $file = $request->file('image');
            $path = $file->getRealPath();

          $data = MyLib::compare_face([
            [
              'name'     => 'images', // Match API field name, if it expects an array
              // 'contents' => fopen(storage_path('app/public/image1.jpg'), 'r'),
              'contents' => $employee_source_face,
              'filename' => 'source.jpg'
            ],
            [
                'name'     => 'images', // Use the same name to send as an array
                'contents' => file_get_contents($path),
                'filename' => 'check.jpg'
            ],
          ]);


          if($data->getStatusCode()!=200) return $data;
        }
      }
      $token = $employee->generateToken();
      MyLog::sys_emp("employee_activity",$employee->id,"login");

      return response()->json([
        // "message"=>"Berhasil login",
        "token"=>$token,
      ],200);
    }else {
      return response()->json([
        "message"=>"Kata Sandi tidak cocok"
      ],400);
    }

    // DB::beginTransaction();
    // try{

    //   $token = $employee->generateToken();
    //   DB::commit();

    //   return response()->json([
    //     "message" => "Berhasil login",
    //     "token" => $token,
    //   ], 200);

    // }catch(\Exception $e){
    //   DB::rollback();

    //   // return response()->json([
    //   //   "message" => $e->getMessage(),
    //   // ], 400);
      
    //   return response()->json([
    //     "message" => "Ada Kesalahan"
    //   ], 400);
    // }
  }

  // public function delete(EmployeeRequest $request)
  // {
  //   MyAdmin::checkScope($this->permissions, 'employee.remove');

  //   DB::beginTransaction();
  //   try {
  //     $deleted_reason = $request->deleted_reason;
  //     if(!$deleted_reason)
  //     throw new \Exception("Sertakan Alasan Penghapusan",1);

  //     $model_query = Employee::exclude(['attachment_1','attachment_2'])->where("id",$request->id)->lockForUpdate()->first();
  //     $SYSOLD                     = clone($model_query);
  //     if (!$model_query) {
  //       throw new \Exception("Data tidak terdaftar", 1);
  //     }

  //     if($model_query->id==1){
  //       throw new \Exception("Izin Hapus Ditolak",1);
  //     }
  
  //     $model_query->deleted = 1;
  //     $model_query->deleted_user = $this->admin_id;
  //     $model_query->deleted_at = date("Y-m-d H:i:s");
  //     $model_query->deleted_reason = $deleted_reason;
  //     $model_query->save();

  //     $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
  //     MyLog::sys($this->syslog_db,$request->id,"delete",$SYSNOTE);

  //     DB::commit();
  //     return response()->json([
  //       "message"       => "Proses hapus data berhasil",
  //       "deleted"       => $model_query->deleted,
  //       "deleted_user"  => $model_query->deleted_user,
  //       "deleted_by"    => $model_query->deleted_user ? new IsUserResource(IsUser::find($model_query->deleted_user)) : null,
  //       "deleted_at"    => $model_query->deleted_at,
  //       "deleted_reason"=> $model_query->deleted_reason,
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
  //   // if ($model_query->delete()) {
  //   //     return response()->json([
  //   //         "message"=>"Proses ubah data berhasil",
  //   //     ],200);
  //   // }

  //   // return response()->json([
  //   //     "message"=>"Proses ubah data gagal",
  //   // ],400);
  // }

  public function faceRec(Request $request){

    $admin = MyAdmin::employee();
    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {

      if(!$admin->the_employee->face_loc_target)
      throw new \Exception("Anda Masih Belum Memiliki Foto, Kabari Ke Pihak Logistik",1);
      // MyLog::logging("get source","img_error");

$employee_source_face = File::get(files_path($admin->the_employee->face_loc_target));
// MyLog::logging("res source","img_error");

if($request->hasFile('image')){
// MyLog::logging("get hasFile","img_error");

  $file = $request->file('image');
  $path = $file->getRealPath();
  // $fileType = $file->getClientMimeType();
  // $blobFile = base64_encode(file_get_contents($path));
    $client = new \GuzzleHttp\Client();

    $endpoint = "http://127.0.0.1:5000/compare_face";

    // MyLog::logging("call api","img_error");

      $response = $client->post($endpoint, [
        'multipart' => [
            [
                'name'     => 'images[]', // Match API field name, if it expects an array
                // 'contents' => fopen(storage_path('app/public/image1.jpg'), 'r'),
                'contents' => $employee_source_face,
                'filename' => 'source.jpg'
            ],
            [
                'name'     => 'images[]', // Use the same name to send as an array
                'contents' => file_get_contents($path),
                'filename' => 'check.jpg'
            ],
        ],
        'headers' => [
            'Authorization' => 'Bearer your_token_here', // If authentication is needed
            'Accept'        => 'application/json',
        ]
      ]);
      // MyLog::logging("get api","img_error");

      $data = json_decode($response->getBody(), true);
// MyLog::logging("data hasFile","img_error");

      return response()->json([
        "message"=>"test",
    ],200);
   
    // $data = json_decode($response->getBody(), true);
    // return response()->json([
    //     "message"=>$data,
    // ],200);
}
      DB::commit();
      return response()->json([
        "message"       => "Proses Generate Code berhasil",
      ], 200);
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
        "message" => "Proses Generate Code gagal",
      ], 400);
    }
    

    return response()->json([
      "message"=>"Failed GET",
    ],400);
  }

  public function change_password(Request $request)
  {
    $admin = MyAdmin::employee();

    $rules = [
      'password' => 'required|confirmed|min:8',
      'password_confirmation' => 'required|same:password|min:8',
    ];

    $rule = [
      "password.required" => "Kata Sandi harus diisi",
      "password.confirmed" => "Kata Sandi dan Ulang Kata Sandi tidak cocok",
      "password.min" => "Kata Sandi  minimal 8 karakter",
      "password.max" => "Kata Sandi maksimal 255 karakter",

      "password_confirmation.required" => "Ulangi Kata Sandi harus diisi",
      "password_confirmation.same" => "Ulangi Kata Sandi dan Kata Sandi tidak cocok",
      "password_confirmation.min" => "Ulangi Kata Sandi minimal 8 karakter",
      "password_confirmation.max" => "Ulangi Kata Sandi maksimal 255 karakter",
    ];

    $validator = Validator::make($request->all(), $rules, $rule);
    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $admin->the_employee->password = bcrypt($request->password);
    $admin->the_employee->updated_at = date("Y-m-d H:i:s");
    $admin->the_employee->save();
    MyLog::sys_emp("employee_activity",$admin->the_employee->id,"change_password");

    return response()->json([
      "message" => "Kata sandi berhasil diubah".$admin->the_employee->id,
    ], 200);
  }

}
