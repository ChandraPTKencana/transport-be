<?php

namespace App\Http\Controllers\StandBy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Helpers\MyLib;
use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;
use App\Models\MySql\StandByMst;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Http\Requests\MySql\StandByMstRequest;
use App\Http\Resources\MySql\StandByMstResource;
use App\Models\MySql\StandbyDtl;
use App\Http\Resources\IsUserResource;
use App\Models\MySql\IsUser;

use Exception;
use Illuminate\Support\Facades\DB;

class StandByMstController extends Controller
{
  private $admin;
  private $role;
  private $admin_id;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->role = $this->admin->the_user->hak_akses;

  }

  public function index(Request $request)
  {
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic','PabrikTransport']);
 
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
    $model_query = StandByMst::offset($offset)->limit($limit);

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

      if (isset($sort_lists["id"])) {
        $model_query = $model_query->orderBy("id", $sort_lists["id"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("id",$sort_symbol,$first_row["id"]);
        }
      }

      if (isset($sort_lists["name"])) {
        $model_query = $model_query->orderBy("name", $sort_lists["name"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("name",$sort_symbol,$first_row["name"]);
        }
      }
      

    } else {
      $model_query = $model_query->orderBy('updated_at', 'DESC');
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

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists){
            
          if (isset($like_lists["id"])) {
            $q->orWhere("id", "like", $like_lists["id"]);
          }
    
          if (isset($like_lists["name"])) {
            $q->orWhere("name", "like", $like_lists["name"]);
          }
        });        
      }

      
    }

    // ==============
    // Model Filter
    // ==============

    $model_query = $model_query->where("deleted",0)->get();

    return response()->json([
      "data" => StandByMstResource::collection($model_query),
    ], 200);
  }

  public function show(StandByMstRequest $request)
  {
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic','PabrikTransport']);

    $model_query = StandByMst::with([
    'details'=>function($q){
      $q->orderBy("ordinal","asc");
    },
    ])->with(['val_by','val1_by'])->where("deleted",0)->find($request->id);

    return response()->json([
      "data" => new StandByMstResource($model_query),
    ], 200);
  }

  public function validateItems($details_in){
    $rules = [
      
      // 'details'                          => 'required|array',
      // 'details.*.id_uj'               => 'required|exists:\App\Models\MySql\StandByMst',
      'details.*.p_status'               => 'required|in:Remove,Add,Edit',
      'details.*.ac_account_id'          => 'required_if:details.*.p_status,Add,Edit',
      'details.*.ac_account_code'        => 'required_if:details.*.p_status,Add,Edit',
      'details.*.ac_account_name'        => 'required_if:details.*.p_status,Add,Edit',
      'details.*.amount'                 => 'required_if:details.*.p_status,Add,Edit|numeric',
      'details.*.description'            => 'required_if:details.*.p_status,Add,Edit',
      // 'details.*.status'              => 'required|in:Y,N',
    ];

    $messages = [
      'details.required' => 'Item harus di isi',
      'details.array' => 'Format Pengambilan Barang Salah',
    ];

    // // Replace :index with the actual index value in the custom error messages
    foreach ($details_in as $index => $msg) {
      // $messages["details.{$index}.id_uj.required"]          = "Baris #" . ($index + 1) . ". ID StandByMst yang diminta tidak boleh kosong.";
      // $messages["details.{$index}.id_uj.exists"]            = "Baris #" . ($index + 1) . ". ID StandByMst yang diminta harus dipilih";

      $messages["details.{$index}.ac_account_id.required_if"]          = "Baris #" . ($index + 1) . ". Acc ID yang diminta tidak boleh kosong.";
      $messages["details.{$index}.ac_account_code.required_if"]          = "Baris #" . ($index + 1) . ". Acc Code yang diminta tidak boleh kosong.";
      $messages["details.{$index}.ac_account_name.required_if"]          = "Baris #" . ($index + 1) . ". Acc Name yang diminta tidak boleh kosong.";
      $messages["details.{$index}.ac_account_code.max"]              = "Baris #" . ($index + 1) . ". Acc Code Maksimal 255 Karakter";

      // $messages["details.{$index}.qty.required_if"]            = "Baris #" . ($index + 1) . ". Qty harus di isi";
      // $messages["details.{$index}.qty.numeric"]              = "Baris #" . ($index + 1) . ". Qty harus berupa angka";

      $messages["details.{$index}.amount.required_if"]            = "Baris #" . ($index + 1) . ". Amount harus di isi";
      $messages["details.{$index}.amount.numeric"]              = "Baris #" . ($index + 1) . ". Amount harus berupa angka";

      $messages["details.{$index}.description.required_if"]            = "Baris #" . ($index + 1) . ". Description harus di isi";
    }

    $validator = \Validator::make(['details' => $details_in], $rules, $messages);

    // Check if validation fails
    if ($validator->fails()) {
      foreach ($validator->messages()->all() as $k => $v) {
        throw new MyException(["message" => $v], 400);
      }
    }
  }

  public function store(StandByMstRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport']);

    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);

    $rollback_id = -1;
    DB::beginTransaction();
    try {
      //start for details2
      $unique_items = [];
      $unique_acc_code=[];
      foreach ($details_in as $key => $value) {
        $unique_data = strtolower(trim($value['ac_account_code']).trim($value['description']));
        if (in_array($unique_data, $unique_items) == 1) {
          throw new \Exception("Maaf terdapat Item yang sama",1);
        }
        array_push($unique_items, $unique_data);
        if($value["p_status"]!="Remove")
        array_push($unique_acc_code,$value['ac_account_code']);
      }
      $unique_acc_code = array_unique($unique_acc_code);
  
      $temp_ac_accounts = [];
      $listToCheck = [];
      if(count($unique_acc_code)>0){
        $connectionDB = DB::connection('sqlsrv');
        $temp_ac_accounts = $connectionDB->table("AC_Accounts")
        ->select('AccountID','AccountCode','AccountName')
        ->whereIn('AccountCode',$unique_acc_code)
        ->get();
  
        $temp_ac_accounts= MyLib::objsToArray($temp_ac_accounts);
  
        $listToCheck = array_map(function($x){
          return $x["AccountCode"];
        },$temp_ac_accounts);

        if(count($temp_ac_accounts)!=count($unique_acc_code)){
  
          $diff = array_diff($unique_acc_code,$listToCheck);
          throw new \Exception(implode(",",$diff)."tidak terdaftar",1);
        }
      }

      $t_stamp = date("Y-m-d H:i:s");

      $model_query                  = new StandByMst();      
      $model_query->name            = $request->name;
      $model_query->amount          = 0;

      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

      $model_query->save();
      $rollback_id = $model_query->id - 1;

      $ordinal=0;
      foreach ($details_in as $key => $value) {
        $ordinal = $key + 1;
        $detail                     = new StandbyDtl();
        $detail->standby_mst_id     = $model_query->id;
        $detail->ordinal            = $ordinal;
        
        $index_item = array_search($value['ac_account_code'], $listToCheck);
        
        if ($index_item !== false){
          $detail->ac_account_id    = $temp_ac_accounts[$index_item]['AccountID'];
          $detail->ac_account_name  = $temp_ac_accounts[$index_item]['AccountName'];
          $detail->ac_account_code  = $temp_ac_accounts[$index_item]['AccountCode'];
        }

        $detail->amount             = $value['amount'];
        $detail->description        = $value['description'];
        $model_query->amount        +=  $value["amount"];
        $detail->created_at         = $t_stamp;
        $detail->created_user       = $this->admin_id;
  
        $detail->updated_at         = $t_stamp;
        $detail->updated_user       = $this->admin_id;  
        $detail->save();
      }
      //end for details2

      $model_query->save();

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
      DB::statement("ALTER TABLE standby_mst AUTO_INCREMENT = $rollback_id");
      
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

  public function update(StandByMstRequest $request)
  {
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport']);
    $t_stamp = date("Y-m-d H:i:s");

    DB::beginTransaction();
    try {

      $model_query = StandByMst::where("id",$request->id)->lockForUpdate()->first();
      if($model_query->deleted==1)
      throw new \Exception("Data Sudah Dihapus",1);
      
      if(
        ($model_query->val==1 && $model_query->val1==1) || 
        ($this->role=="PabrikTransport" && $model_query->val == 1) ||
        ($this->role=="Logistic" && $model_query->val1 == 1)
      ) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      //start for details2
      $details_in = json_decode($request->details, true);
      $this->validateItems($details_in);   
      //end for details2
      
      //start for details2
      $unique_items = [];
      $unique_acc_code=[];
      foreach ($details_in as $key => $value) {
        $unique_data = strtolower(trim($value['ac_account_code']).trim($value['description']));
        if (in_array($unique_data, $unique_items) == 1) {
          throw new \Exception("Maaf terdapat Item yang sama",1);
        }
        array_push($unique_items, $unique_data);
        if($value["p_status"]!="Remove")
        array_push($unique_acc_code,$value['ac_account_code']);
      }
      $unique_acc_code = array_unique($unique_acc_code);
  
      $temp_ac_accounts = [];
      $listToCheck = [];
      if(count($unique_acc_code)>0){
        $connectionDB = DB::connection('sqlsrv');
        $temp_ac_accounts = $connectionDB->table("AC_Accounts")
        ->select('AccountID','AccountCode','AccountName')
        ->whereIn('AccountCode',$unique_acc_code) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
        ->get();
  
        $temp_ac_accounts= MyLib::objsToArray($temp_ac_accounts);
  
        $listToCheck = array_map(function($x){
          return $x["AccountCode"];
        },$temp_ac_accounts);

        if(count($temp_ac_accounts)!=count($unique_acc_code)){  
          $diff = array_diff($unique_acc_code,$listToCheck);
          throw new \Exception(implode(",",$diff)."tidak terdaftar",1);
        }
      }
      //end for details2

      
      $model_query->name            = $request->name;
      $model_query->amount          = 0;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;  

      //start for details2
      $data_from_db = StandbyDtl::where('standby_mst_id', $model_query->id)
      ->orderBy("ordinal", "asc")->lockForUpdate()
      ->get()->toArray();
      

      $in_keys = array_filter($details_in, function ($x) {
          return isset($x["key"]);
      });

      $in_keys = array_map(function ($x) {
          return $x["key"];
      }, $in_keys);

      $am_ordinal_db = array_map(function ($x) {
          return $x["ordinal"];
      }, $data_from_db);

      if (count(array_diff($in_keys, $am_ordinal_db)) > 0 || count(array_diff($am_ordinal_db, $in_keys)) > 0) {
          throw new Exception('Ada ketidak sesuaian data, harap hubungi staff IT atau refresh browser anda',1);
      }

      $id_items           = [];
      $ordinal            = 0;
      $for_deletes        = [];
      $for_edits          = [];
      $for_adds           = [];
      $data_to_processes  = [];
      foreach ($details_in as $k => $v) {        
        if (in_array($v["p_status"], ["Add", "Edit"])) {
          $unique = strtolower(trim($v['ac_account_code']).trim($v['description']));
          if (in_array($unique, $id_items) == 1) {
              throw new \Exception("Maaf terdapat Nama Item yang sama",1);
          }
          array_push($id_items, $unique);
        }

        if ($v["p_status"] !== "Remove") {
          $ordinal++;
          $details_in[$k]["ordinal"] = $ordinal;
          if ($v["p_status"] == "Edit")
              array_unshift($for_edits, $details_in[$k]);
          elseif ($v["p_status"] == "Add")
              array_push($for_adds, $details_in[$k]);
        } else
            array_push($for_deletes, $details_in[$k]);
      }

      $data_to_processes = array_merge($for_deletes, $for_edits, $for_adds);
      
      foreach ($data_to_processes as $k => $v) {
        $index = false;

        if (isset($v["key"])) {
            $index = array_search($v["key"], $am_ordinal_db);
        }

        if ($v["p_status"] == "Remove") {

            if ($index === false) {
                throw new \Exception("Data yang ingin dihapus tidak ditemukan",1);
            } else {
                $dt = $data_from_db[$index];
                StandbyDtl::where("standby_mst_id",$model_query->id)->where("ordinal",$dt["ordinal"])->delete();
            }
        } else if ($v["p_status"] == "Edit") {

            if ($index === false) {
                throw new \Exception("Data yang ingin diubah tidak ditemukan" . $k,1);
            } else {
             
              $model_query->amount  += $v["amount"];

              $index_item = array_search($v['ac_account_code'], $listToCheck);
              $ac_account_id        = null;
              $ac_account_name      = null;
              $ac_account_code      = null;
              if ($index_item !== false){
                $ac_account_id      = $temp_ac_accounts[$index_item]['AccountID'];
                $ac_account_name    = $temp_ac_accounts[$index_item]['AccountName'];
                $ac_account_code    = $temp_ac_accounts[$index_item]['AccountCode'];
              }

              StandbyDtl::where("standby_mst_id", $model_query->id)
              ->where("ordinal", $v["key"])->where("p_change",false)->update([
                  "ordinal"         =>$v["ordinal"],
                  "amount"          => $v["amount"],
                  "ac_account_id"   => $ac_account_id,
                  "ac_account_name" => $ac_account_name,
                  "ac_account_code" => $ac_account_code,
                  "description"     => $v["description"],
                  // "status" => $v["status"],
                  "p_change"        => true,
                  "updated_at"      => $t_stamp,
                  "updated_user"    => $this->admin_id,
              ]);

            }

            // $ordinal++;
        } else if ($v["p_status"] == "Add") {
            $model_query->amount += $v["amount"];

            $index_item = array_search($v['ac_account_code'], $listToCheck);
            $ac_account_id   = null;
            $ac_account_name = null;
            $ac_account_code = null;
            if ($index_item !== false){
              $ac_account_id    = $temp_ac_accounts[$index_item]['AccountID'];
              $ac_account_name  = $temp_ac_accounts[$index_item]['AccountName'];
              $ac_account_code  = $temp_ac_accounts[$index_item]['AccountCode'];
            }
            
            StandbyDtl::insert([
                'standby_mst_id'  => $model_query->id,
                'ordinal'         => $v["ordinal"],
                "amount"          => $v["amount"],
                "ac_account_id"   => $ac_account_id,
                "ac_account_name" => $ac_account_name,
                "ac_account_code" => $ac_account_code,
                "description"     => $v["description"],
                // 'status'            => $v['status'],
                "p_change"        => true,
                'created_at'      => $t_stamp,
                'created_user'    => $this->admin_id,
                'updated_at'      => $t_stamp,
                'updated_user'    => $this->admin_id,
            ]);
            // $ordinal++;
        }
      }
    //end for details2
    if(MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic'],null,true)){
      $model_query->save();
      StandbyDtl::where('standby_mst_id',$model_query->id)->update(["p_change"=>false]);
    }
      //start for details2
    StandbyDtl::where('standby_mst_id',$model_query->id)->update(["p_change"=>false]);
    //end for details2
      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at"=>$t_stamp
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
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

    DB::beginTransaction();

    try {
      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);
    
      $model_query = StandByMst::where("id",$request->id)->lockForUpdate()->first();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
  
      $model_query->deleted         = 1;
      $model_query->deleted_user    = $this->admin_id;
      $model_query->deleted_at      = date("Y-m-d H:i:s");
      $model_query->deleted_reason  = $deleted_reason;
      $model_query->save();

      DB::commit();
      return response()->json([
        "message" => "Proses Hapus data berhasil",
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
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic','PabrikTransport']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\StandByMst,id",
    ];

    $messages = [
      'id.required' => 'ID tidak boleh kosong',
      'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = \Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query = StandByMst::find($request->id);
      if($this->role=='PabrikTransport' &&  $model_query->val){
        throw new \Exception("Data Sudah Tervalidasi",1);
      }

      if($this->role == "Logistic" && $model_query->val1){
        throw new \Exception("Data Sudah Tervalidasi",1);
      }

      
      if(in_array($this->role,["SuperAdmin","PabrikTransport"]) && !$model_query->val){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
      }

      if(in_array($this->role,["SuperAdmin","Logistic"]) && !$model_query->val1){
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }
      $model_query->save();
      DB::commit();
      return response()->json([
        "message" => "Proses validasi data berhasil",
        "val"=>$model_query->val,
        "val_user"=>$model_query->val_user,
        "val_at"=>$model_query->val_at,
        "val_by"=>$model_query->val_user ? new IsUserResource(IsUser::find($model_query->val_user)) : null,
        "val1"=>$model_query->val1,
        "val1_user"=>$model_query->val1_user,
        "val1_at"=>$model_query->val1_at,
        "val1_by"=>$model_query->val1_user ? new IsUserResource(IsUser::find($model_query->val1_user)) : null, 
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      return response()->json([
        "getCode" => $e->getCode(),
        "line" => $e->getLine(),
        "message" => $e->getMessage(),
      ], 400);
      return response()->json([
        "message" => "Proses ubah data gagal",
      ], 400);
    }

  }

  
}
