<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Exceptions\MyException;
use App\Helpers\GAuthor;
use App\Helpers\MyAdmin;

use App\Http\Resources\MySql\BankResource;
use App\Models\MySql\Bank;
use App\Models\MySql\IsUser;

class GAController extends Controller
{
  private $admin;
  private $role;
  private $admin_id;
  private $permissions;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->role = $this->admin->the_user->hak_akses;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function qr(Request $request)
  {
    try {
      $model_query = IsUser::where("id",$request->id)->lockForUpdate()->first();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      $qr=GAuthor::return_qr($model_query->username);
      return response()->json($qr, 200);
    } catch (\Exception $e) {
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
        "message" => "QR Error",
      ], 400);
    }    
  }

  public function pin(Request $request)
  {
    try {
      $pin = (string)(int)$request->pin;
      $model_query = IsUser::where("id",$this->admin->the_user->id)->lockForUpdate()->first();
      
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      if($model_query->ga_secret_key=="")
      throw new \Exception("Belum ada 2FA Auth", 1);

      $valid=GAuthor::validate_pin($model_query->ga_secret_key,$pin);

      if(!$valid){
        throw new \Exception("PIN Tidak Cocok", 1);
      }else{
        $menit = 15;
        $date = new \DateTime();
        $date->add(new \DateInterval('PT'.$menit.'M'));
        $model_query->ga_timeout = $date->format('Y-m-d H:i:s.v');
        $model_query->save();
        return response()->json( $menit * 60, 200);
        // return response()->json( 1000, 200);
      }
    } catch (\Exception $e) {

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
        "message" => "PIN Error",
      ], 400);

    }    
  }
}
