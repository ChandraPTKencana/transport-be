<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Helpers\MyLib;
use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;
use App\Models\MySql\IsUser;
use App\Http\Resources\MySql\IsUserResource;
use App\Http\Requests\MySql\IsUserRequest;

use Illuminate\Support\Facades\DB;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Models\MySql\PermissionUserDetail;

class UserController extends Controller
{
  private $admin;
  private $admin_id;
  private $role;
  private $permissions;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->role = $this->admin->the_user->hak_akses;
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function index(Request $request)
  {

    MyAdmin::checkScope($this->permissions, 'user.views');

    // \App\Helpers\MyAdmin::checkScope($this->auth, ['ap-user-view']);

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
    $model_query = IsUser::offset($offset)->limit($limit);

    $first_row=[];
    if($request->first_row){
      $first_row 	= json_decode($request->first_row, true);
    }
    //======================================================================================================
    // Model Sorting | Example $request->sort = "username:desc,role:desc";
    //======================================================================================================

    if ($request->sort) {
      $sort_lists = [];

      $sorts = explode(",", $request->sort);
      foreach ($sorts as $key => $sort) {
        $side = explode(":", $sort);
        $side[1] = isset($side[1]) ? $side[1] : 'ASC';
        $sort_symbol = $side[1] == "desc" ? "<=" : ">=";
        $sort_lists[$side[0]] = $side[1];
      }

      if (isset($sort_lists["username"])) {
        $model_query = $model_query->orderBy("username", $sort_lists["username"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("username",$sort_symbol,$first_row["username"]);
        }
      }

      if (isset($sort_lists["hak_akses"])) {
        $model_query = $model_query->orderBy("hak_akses", $sort_lists["hak_akses"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("hak_akses",$sort_symbol,$first_row["hak_akses"]);
        }
      }

      if (isset($sort_lists["is_active"])) {
        $model_query = $model_query->orderBy("is_active", $sort_lists["is_active"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("is_active",$sort_symbol,$first_row["is_active"]);
        }
      }

      // if (isset($sort_lists["role"])) {
      //   $model_query = $model_query->orderBy(function($q){
      //     $q->from("internal.roles")
      //     ->select("name")
      //     ->whereColumn("id","auths.role_id");
      //   },$sort_lists["role"]);
      // }

      // if (isset($sort_lists["auth"])) {
      //   $model_query = $model_query->orderBy(function($q){
      //     $q->from("users as u")
      //     ->select("u.username")
      //     ->whereColumn("u.id","users.id");
      //   },$sort_lists["auth"]);
      // }
    } else {
      $model_query = $model_query->orderBy('id', 'ASC');
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

      if (isset($like_lists["username"])) {
        $model_query = $model_query->orWhere("username", "like", $like_lists["username"]);
      }

      if (isset($like_lists["hak_akses"])) {
        $model_query = $model_query->orWhere("hak_akses", "like", $like_lists["hak_akses"]);
      }

      if (isset($like_lists["is_active"])) {
        $model_query = $model_query->orWhere("is_active", "like", $like_lists["is_active"]);
      }

      // if (isset($like_lists["role"])) {
      //   $model_query = $model_query->orWhere("role","like",$like_lists["role"]);
      // }
    }

    // ==============
    // Model Filter
    // ==============

    if (isset($request->username)) {
      $model_query = $model_query->where("username", 'like', '%' . $request->username . '%');
    }

    if (isset($request->hak_akses)) {
      $model_query = $model_query->where("hak_akses", 'like', '%' . $request->hak_akses . '%');
    }

    $model_query = $model_query->get();

    return response()->json([
      // "data"=>EmployeeResource::collection($employees->keyBy->id),
      "data" => IsUserResource::collection($model_query),
    ], 200);
  }

  // public function getProduct($store_domain,$product_domain)
  // {
  //   $data=[];

  //   if ($store_domain=="") {
  //     throw new MyException("Maaf nama toko tidak boleh kosong");
  //   }

  //   $store=SellerStore::where("domain",$store_domain)->first();
  //   if (!$store) {
  //     throw new MyException("Maaf toko tidak ditemukan");
  //   }

  //   if ($product_domain=="") {
  //     throw new MyException("Maaf nama produk tidak boleh kosong");
  //   }

  //   $product=Product::where("domain",$product_domain)->first();
  //   if (!$product) {
  //     throw new MyException("Maaf produk tidak ditemukan");
  //   }

  //   // $purchaseOrder=PurchaseOrder::sellerAvailable($store->seller->id)->first();
  //   $avaliable=PurchaseOrder::produkAvailable($store->seller->id,$product->id)->first() ? true : false;

  //   $data=[
  //     "domain"=>$product->domain,
  //     "name"=>$product->name,
  //     "price"=>$product->price,
  //     "image"=>$product->image,
  //     "available"=>$avaliable
  //   ];

  //   return response()->json([
  //     "data"=>$data
  //   ],200);

  // }
  public function show(IsUserRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'user.view');

    $model_query = IsUser::with([
      'details'=>function($q){
        $q->orderBy("ordinal","asc");
      },
    ])->find($request->id);
    return response()->json([
      "data" => new IsUserResource($model_query),
    ], 200);
  }

  public function validateItems($permission_list_in){
    $rules = [      
      // 'permission_list'                      => 'required|array',
      'permission_list.*.name'               => 'required|exists:App\Models\MySql\PermissionList,name',
    ];

    $messages = [
      // 'permission_list.required' => 'List Item harus di isi',
      // 'permission_list.array' => 'Format Pengambilan Barang Salah',
    ];

    foreach ($permission_list_in as $index => $msg) {
      $messages["permission_list.{$index}.name.required"]  = "Baris #" . ($index + 1) . ". Nama tidak boleh kosong.";
      $messages["permission_list.{$index}.name.exists"]    = "Baris #" . ($index + 1) . ". Nama tidak terdaftar.";
    }

    $validator = \Validator::make(['permission_list' => $permission_list_in], $rules, $messages);

    // Check if validation fails
    if ($validator->fails()) {
      foreach ($validator->messages()->all() as $k => $v) {
        throw new MyException(["message" => $v], 400);
      }
    }
  }

  public function store(IsUserRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'user.create');

    $permission_list_in = json_decode($request->permission_list, true);
    $this->validateItems($permission_list_in);

    if(count($permission_list_in)>0){
      MyAdmin::checkScope($this->permissions, 'permission_user.insert');
    }

    $rollback_id = -1;
    DB::beginTransaction();
    $t_stamp = date("Y-m-d H:i:s");
    try {
      $model_query             = new IsUser();
      $model_query->username      = trim($request->username);
      if ($request->password) {
        $model_query->password = bcrypt($request->password);
      }
      // $model_query->employee_no = $request->employee_no;
      $model_query->hak_akses  = $request->hak_akses;
      $model_query->is_active  = $request->is_active;
      $model_query->created_at = $t_stamp;
      $model_query->created_user = $this->admin_id;
      $model_query->updated_at = $t_stamp;
      $model_query->updated_user = $this->admin_id;
      $model_query->save();

      $rollback_id = $model_query->id - 1;

      // if($request->employee_no){
      //   $employee = Employee::where("no",$request->employee_no)->first();
      //   if(!$employee){
      //     throw new \Exception("Pegawai tidak terdaftar",1);
      //   }

      //   if($employee->which_user_id !== null){
      //     throw new \Exception("Pegawai tidak tersedia",1);
      //   }
      //   Employee::where("no",$request->employee_no)->update(["which_user_id"=>$model_query->id]);
      // }

      $ordinal=0;
      foreach ($permission_list_in as $key => $value) {
        $ordinal = $key + 1;
        PermissionUserDetail::insert([
          'ordinal' => $ordinal,
          'user_id' => $model_query->id,
          'permission_list_name' => $value['name'],
          'created_at' => $t_stamp,
          'created_user' => $this->admin_id,
          'updated_at' => $t_stamp,
          'updated_user' => $this->admin_id,
        ]);
      }
      

      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE is_users AUTO_INCREMENT = $rollback_id");

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

  public function update(IsUserRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'user.modify');

    $permission_list_in = json_decode($request->permission_list, true);
    $this->validateItems($permission_list_in);

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query              = IsUser::find($request->id);
      $model_query->username       = trim($request->username);
      if ($request->password) {
        $model_query->password  = bcrypt($request->password);
      }
      $model_query->hak_akses    = $request->hak_akses;
      $model_query->is_active   = $request->is_active;
      $model_query->updated_at  = $t_stamp;
      $model_query->updated_user  = $this->admin_id;

      // if($request->employee_no){
      //   //Check apakah di update dengan data yang sama
      //   $employee = Employee::where("no",$request->employee_no)->where("which_user_id",$request->id)->first();
      //   // jika berbeda
      //   if(!$employee){
      //     //check used employee and set null
      //     $employee = Employee::where("which_user_id",$request->id)->first();
      //     if($employee)
      //     Employee::where("which_user_id",$request->id)->update(["which_user_id"=>null]);

      //     //add used employee
      //     $employee = Employee::where("no",$request->employee_no)->first();
      //     if(!$employee){
      //       throw new \Exception("Karyawan tidak terdaftar",1);
      //     }
      //     if($employee->which_user_id !== null){
      //       throw new \Exception("Karyawan tidak tersedia",1);
      //     }
      //     Employee::where("no",$request->employee_no)->update(["which_user_id"=>$request->id]);
      //   }
      // }else{
      //   $employee = Employee::where("which_user_id",$request->id)->first();
      //   if($employee){
      //     Employee::where("which_user_id",$request->id)->update(["which_user_id"=>null]);
      //   }
      // }
      //start for permission_list
      $data_from_db = PermissionUserDetail::where('user_id', $model_query->id)
      ->orderBy("ordinal", "asc")
      ->lockForUpdate()
      ->get();

      $in_dt = array_map(function ($x) {
        return $x["name"];
      }, $permission_list_in);

      $from_dt = array_map(function ($x) {
        return $x['permission_list_name'];
      }, $data_from_db->toArray());

      
      if(count(array_diff($in_dt, $from_dt))>0) {
        MyAdmin::checkScope($this->permissions, 'permission_user.insert');
      }

      if(count(array_diff($from_dt, $in_dt))>0) {
        MyAdmin::checkScope($this->permissions, 'permission_user.remove');
      }

      $ordinal=0;
      foreach ($data_from_db as $k => $v) {
        $search = array_search($v->permission_list_name,$in_dt);
        if($search===false){
          PermissionUserDetail::where('user_id',$v->user_id)->where('permission_list_name',$v->permission_list_name)
          ->delete();
        }else{
          $ordinal++;
          
          $updateV = [
            'ordinal' => $ordinal,
            'p_change' => false,
            'updated_at' => $t_stamp,
            'updated_user' => $this->admin_id
          ];

          PermissionUserDetail::where('user_id',$v->user_id)->where('permission_list_name',$v->permission_list_name)
          ->update($updateV);
        }
        
        $in_dt = array_filter($in_dt,function($q)use($v){
          return $q != $v->permission_list_name;
        });
      }

      foreach ($in_dt as $k => $v) {
        $ordinal++;
        PermissionUserDetail::insert([
            'user_id'               => $model_query->id,
            'ordinal'               => $ordinal,
            "permission_list_name"  => $v,
            'created_at'            => $t_stamp,
            'created_user'          => $this->admin_id,
            'updated_at'            => $t_stamp,
            'updated_user'          => $this->admin_id,
        ]);
      }

      $model_query->save();
      //end for permission_list
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


  public function delete(IsUserRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'user.remove');

    DB::beginTransaction();

    try {

      $model_query = IsUser::find($request->id);
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      $model_query->delete();

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
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
}
