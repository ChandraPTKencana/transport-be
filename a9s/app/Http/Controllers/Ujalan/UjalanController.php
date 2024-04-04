<?php

namespace App\Http\Controllers\Ujalan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Helpers\MyLib;
use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;
use App\Models\MySql\Ujalan;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Http\Requests\MySql\UjalanRequest;
use App\Http\Resources\MySql\UjalanResource;
use App\Models\HrmRevisiLokasi;
use App\Models\Stok\Item;
use App\Models\MySql\UjalanDetail;
use Exception;
use Illuminate\Support\Facades\DB;
use Image;
use File;
use App\Http\Resources\IsUserResource;
use App\Models\MySql\IsUser;

class UjalanController extends Controller
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
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);
 
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
    $model_query = Ujalan::offset($offset)->limit($limit);

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

      if (isset($sort_lists["xto"])) {
        $model_query = $model_query->orderBy("xto", $sort_lists["xto"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("xto",$sort_symbol,$first_row["xto"]);
        }
      }

      if (isset($sort_lists["tipe"])) {
        $model_query = $model_query->orderBy("tipe", $sort_lists["tipe"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("tipe",$sort_symbol,$first_row["tipe"]);
        }
      }

      if (isset($sort_lists["jenis"])) {
        $model_query = $model_query->orderBy("jenis", $sort_lists["jenis"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("jenis",$sort_symbol,$first_row["jenis"]);
        }
      }

      if (isset($sort_lists["harga"])) {
        $model_query = $model_query->orderBy("harga", $sort_lists["harga"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("harga",$sort_symbol,$first_row["harga"]);
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
    
          if (isset($like_lists["xto"])) {
            $q->orWhere("xto", "like", $like_lists["xto"]);
          }
    
          if (isset($like_lists["tipe"])) {
            $q->orWhere("tipe", "like", $like_lists["tipe"]);
          }

          if (isset($like_lists["jenis"])) {
            $q->orWhere("jenis", "like", $like_lists["jenis"]);
          }
          if (isset($like_lists["harga"])) {
            $q->orWhere("harga", "like", $like_lists["harga"]);
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

      
    }

    // ==============
    // Model Filter
    // ==============

    $model_query = $model_query->where("deleted",0)->get();

    return response()->json([
      "data" => UjalanResource::collection($model_query),
    ], 200);
  }

  public function show(UjalanRequest $request)
  {
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

    // return response()->json([
    //   "message" => "Hanya yang membuat transaksi yang boleh melakukan pergantian atau konfirmasi data",
    // ], 400);
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);

    $model_query = Ujalan::with([
    'details'=>function($q){
      $q->orderBy("ordinal","asc");
    }])->where("deleted",0)->find($request->id);

    // if($model_query->requested_by != $this->admin_id){
    //   return response()->json([
    //     "message" => "Hanya yang membuat transaksi yang boleh melakukan pergantian atau konfirmasi data",
    //   ], 400);
    // }
    
    // if($this->role=='ClientPabrik' || $this->role=='KTU')
    // MyAdmin::checkReturnOrFailLocation($this->admin->the_user,$model_query->hrm_revisi_lokasi_id);

    // if($model_query->ref_id!=null){
    //   return response()->json([
    //     "message" => "Ubah data ditolak",
    //   ], 400);
    // }

    return response()->json([
      "data" => new UjalanResource($model_query),
    ], 200);
  }

  public function validateItems($details_in){
    $rules = [
      'details'                          => 'required|array',
      // 'details.*.id_uj'                  => 'required|exists:\App\Models\MySql\Ujalan',
      'details.*.xdesc'                  => 'required|max:50',
      'details.*.qty'                    => 'required|numeric',
      'details.*.harga'                 => 'required|numeric',
      // 'details.*.status'                => 'required|in:Y,N',
    ];

    $messages = [
      'details.required' => 'Item harus di isi',
      'details.array' => 'Format Pengambilan Barang Salah',
    ];

    // // Replace :index with the actual index value in the custom error messages
    foreach ($details_in as $index => $msg) {
      // $messages["details.{$index}.id_uj.required"]          = "Baris #" . ($index + 1) . ". ID Ujalan yang diminta tidak boleh kosong.";
      // $messages["details.{$index}.id_uj.exists"]            = "Baris #" . ($index + 1) . ". ID Ujalan yang diminta harus dipilih";

      $messages["details.{$index}.xdesc.required"]          = "Baris #" . ($index + 1) . ". Desc yang diminta tidak boleh kosong.";
      $messages["details.{$index}.xdesc.max"]              = "Baris #" . ($index + 1) . ". Desc Maksimal 50 Karakter";

      $messages["details.{$index}.qty.required"]            = "Baris #" . ($index + 1) . ". Qty harus di isi";
      $messages["details.{$index}.qty.numeric"]              = "Baris #" . ($index + 1) . ". Qty harus berupa angka";

      $messages["details.{$index}.harga.required"]            = "Baris #" . ($index + 1) . ". Harga harus di isi";
      $messages["details.{$index}.harga.numeric"]              = "Baris #" . ($index + 1) . ". Harga harus berupa angka";

      // $messages["details.{$index}.status.required"]            = "Baris #" . ($index + 1) . ". Status harus di isi";
      // $messages["details.{$index}.status.in"]                   = "Baris #" . ($index + 1) . ". Status tidak sesuai format";
      // $messages["details.{$index}.item.required"]                 = "Baris #" . ($index + 1) . ". Item di Form Pengambilan Barang Gudang harus di isi";
      // $messages["details.{$index}.item.array"]                    = "Baris #" . ($index + 1) . ". Format Item di Pengambilan Barang Gudang Salah";
      // $messages["details.{$index}.item.code.required"]            = "Baris #" . ($index + 1) . ". Item harus di isi";
      // $messages["details.{$index}.item.code.exists"]              = "Baris #" . ($index + 1) . ". Item tidak terdaftar";

      // $messages["details.{$index}.unit.required"]                 = 'Baris #' . ($index + 1) . '. Satuan di Pengambilan Barang Gudang harus di isi';
      // $messages["details.{$index}.unit.array"]                    = 'Baris #' . ($index + 1) . '. Format Satuan di Pengambilan Barang Gudang Salah';
      // $messages["details.{$index}.unit.code.required"]            = 'Baris #' . ($index + 1) . '. Satuan harus di isi';
      // $messages["details.{$index}.unit.code.exists"]              = 'Baris #' . ($index + 1) . '. Satuan tidak terdaftar';

    }

    $validator = \Validator::make(['details' => $details_in], $rules, $messages);

    // Check if validation fails
    if ($validator->fails()) {
      foreach ($validator->messages()->all() as $k => $v) {
        throw new MyException(["message" => $v], 400);
      }
    }
  }


  public function store(UjalanRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);
    $rollback_id = -1;
    DB::beginTransaction();
    try {
      $t_stamp = date("Y-m-d H:i:s");
      // if(Ujalan::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar",1);

      $model_query                  = new Ujalan();      
      $model_query->xto             = $request->xto;
      $model_query->tipe            = $request->tipe;
      $model_query->jenis           = $request->jenis;
      // $model_query->status          = $request->status;
      $model_query->harga          = 0;
      
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

      $model_query->save();
      $rollback_id = $model_query->id - 1;

      $ordinal=0;
      $unique_items = [];
      foreach ($details_in as $key => $value) {
        $unique_data = $value['xdesc'];
        if (in_array(strtolower($unique_data), $unique_items) == 1) {
          throw new \Exception("Maaf terdapat Item yang sama",1);
        }
        array_push($unique_items, strtolower($unique_data));
      }
      
      foreach ($details_in as $key => $value) {
        $ordinal = $key + 1;
        $detail                     = new UjalanDetail();
        $detail->id_uj              = $model_query->id;
        $detail->ordinal            = $ordinal;
        $detail->xdesc              = $value['xdesc'];
        $detail->qty                = $value['qty'];
        $detail->harga              = $value['harga'];

        $model_query->harga +=  ($value["qty"] * $value["harga"]);
        $detail->created_at      = $t_stamp;
        $detail->created_user    = $this->admin_id;
  
        $detail->updated_at      = $t_stamp;
        $detail->updated_user    = $this->admin_id;  
        $detail->save();
      }
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
      DB::statement("ALTER TABLE is_uj AUTO_INCREMENT = $rollback_id");
      
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

  public function update(UjalanRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);
    
    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);

    $t_stamp = date("Y-m-d H:i:s");

    DB::beginTransaction();
    try {
      // if(Ujalan::where("id","!=",$request->id)->where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar",1);

      $model_query             = Ujalan::where("id",$request->id)->lockForUpdate()->first();
      if($model_query->val==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan pergantian data",1);
      // }

      // if($model_query->ref_id!=null){
      //   throw new \Exception("Ubah data ditolak. Data berasal dari transfer",1);
      // }

      // if($model_query->val != null){
      //   throw new \Exception("Ubah ditolak. Data sudah di validasi.",1);
      // }

      // $warehouse_id = $request->warehouse_id;
      // if($this->role=='ClientPabrik' || $this->role=='KTU')
      // $warehouse_id = MyAdmin::checkReturnOrFailLocation($this->admin->the_user,$model_query->hrm_revisi_lokasi_id);
  
      // $dt_before = $this->getLastDataConfirmed($warehouse_id,$request->item_id);
      // if($dt_before && $dt_before->id != $model_query->id){
      //   throw new \Exception("Ubah ditolak. Hanya data terbaru yang bisa diubah.",1);
      // }
      
      $model_query->xto             = $request->xto;
      $model_query->tipe            = $request->tipe;
      $model_query->jenis           = $request->jenis;
      $model_query->harga           = 0;
      // $model_query->status          = $request->status;
  
      // $model_query->created_at      = $t_stamp;
      // $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;  


      $data_from_db = UjalanDetail::where('id_uj', $model_query->id)
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

      $id_items = [];
      $ordinal = 0;
      $for_deletes = [];
      $for_edits = [];
      $for_adds = [];
      $data_to_processes = [];
      foreach ($details_in as $k => $v) {
        // $xdesc = $v['xdesc'] ? $v['xdesc'] : "";
        
        if (in_array($v["p_status"], ["Add", "Edit"])) {
          if (in_array(strtolower($v['xdesc']), $id_items) == 1) {
              throw new \Exception("Maaf terdapat Nama Item yang sama",1);
          }
          array_push($id_items, strtolower($v['xdesc']));
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

      if(count($for_adds)==0 && count($for_edits)==0){
        throw new \Exception("Item harus Diisi",1);
      }

      $data_to_processes = array_merge($for_deletes, $for_edits, $for_adds);
      // $ordinal = 0;
      // MyLog::logging([
      //   "data_to_processes"=>$data_to_processes,
      //   "data_from_db"=>$data_from_db,
      // ]);

      // return response()->json([
      //   "message" => "test",
      // ], 400);

      foreach ($data_to_processes as $k => $v) {
        $index = false;

        if (isset($v["key"])) {
            $index = array_search($v["key"], $am_ordinal_db);
        }
        
        //         if($k==2)
        // {        MyLog::logging([
        //           "item_name"=>$v["item"]["name"],
        //           "key"=>$v["key"],
        //           "index"=>$index,
        //           "ordinal_arr"=>$am_ordinal_db,
        //           "v"=>$v,
        //           "w"=>$data_from_db,
        //         ]);

        //         return response()->json([
        //           "message" => "test",
        //         ], 400);
        // }


        if(in_array($v["p_status"],["Add","Edit"])){
          // $ordinal++;

          // if(($type=="transfer" || $type=="used")){
          //   $v['qty_in']=null;
          //   if($v['qty_out']==0) 
          //     throw new \Exception("Baris #" .$ordinal." Qty Out Tidak Boleh 0",1);
          // }

          // if($type=="in"){
          //   $v['qty_out']=null;
          //   if($v['qty_in']==0)
          //   throw new \Exception("Baris #" .$ordinal.".Qty In Tidak Boleh 0",1);
          // }


          // $indexItem = array_search($v['xdesc'], $items_id);
          // $qty_reminder = 0;

          // if ($indexItem !== false){
          //   $qty_reminder = $prev_checks[$indexItem]["qty_reminder"];
          // }
  
          // if(($type=="used" || $type=="transfer") && $qty_reminder - $v['qty_out'] < 0){
          //   // MyLog::logging($prev_checks);

          //   // throw new \Exception("Baris #" .$ordinal.".Qty melebihi stok : ".$qty_reminder, 1);
          // }
        }


        // $v["item_code"] = MyLib::emptyStrToNull($v["item_code"]);
        // $v["note"] = MyLib::emptyStrToNull($v["note"]);
        // $v["qty_assumption"] = MyLib::emptyStrToNull($v["qty_assumption"]);
        // $v["qty_realization"] = MyLib::emptyStrToNull($v["qty_realization"]);
        // $v["stock"] = MyLib::emptyStrToNull($v["stock"]);
        // $v["price_assumption"] = MyLib::emptyStrToNull($v["price_assumption"]);
        // $v["price_realization"] = MyLib::emptyStrToNull($v["price_realization"]);

        if ($v["p_status"] == "Remove") {

            if ($index === false) {
                throw new \Exception("Data yang ingin dihapus tidak ditemukan",1);
            } else {
                $dt = $data_from_db[$index];
                // $has_permit = count(array_intersect(['ap-project_material_item-remove'], $scopes));
                // if (!$dt["is_locked"] && $dt["created_by"] == $auth_id && $has_permit) {
                //     ProjectMaterial::where("project_no", $model_query->no)->where("ordinal", $dt["ordinal"])->delete();
                // }
                UjalanDetail::where("id_uj",$model_query->id)->where("ordinal",$dt["ordinal"])->delete();
            }
        } else if ($v["p_status"] == "Edit") {

            if ($index === false) {
                throw new \Exception("Data yang ingin diubah tidak ditemukan" . $k,1);
            } else {
                // $dt = $data_from_db[$index];
                // $has_permit = count(array_intersect(['ap-project_material_item-edit'], $scopes));
                // if (!$has_permit) {
                //     throw new Exception('Ubah Project Material Item Tidak diizinkan');
                // }

                // if ($v["qty_assumption"] != $dt['qty_assumption']) {
                //     $has_value = count(array_intersect(['dp-project_material-manage-qty_assumption'], $scopes));

                //     if ($dt["is_locked"] || !$has_value || $dt["created_by"] != $auth_id)
                //         throw new Exception('Ubah Jumlah Asumsi Tidak diizinkan');
                // }
             

              $model_query->harga          += ($v["qty"] * $v["harga"]);

              UjalanDetail::where("id_uj", $model_query->id)
              ->where("ordinal", $v["key"])->where("p_change",false)->update([
                  "ordinal"=>$v["ordinal"],
                  "xdesc" => $v["xdesc"],
                  "qty" => $v["qty"],
                  "harga" => $v["harga"],
                  // "status" => $v["status"],
                  "p_change"=> true,
                  "updated_at"=> $t_stamp,
                  "updated_user"=> $this->admin_id,
              ]);

            }

            // $ordinal++;
        } else if ($v["p_status"] == "Add") {

            // if (!count(array_intersect(['ap-project_material_item-add'], $scopes)))
            //     throw new Exception('Tambah Project Material Item Tidak diizinkan');

            // if (!count(array_intersect(['dp-project_material-manage-item_code'], $scopes))  && $v["item_code"] != "")
            //     throw new Exception('Tidak ada izin mengelola Kode item');
            $model_query->harga          += ($v["qty"] * $v["harga"]);
            UjalanDetail::insert([
                'id_uj'             => $model_query->id,
                'ordinal'           => $v["ordinal"],
                'xdesc'             => $v['xdesc'],
                'qty'               => $v["qty"],
                'harga'             => $v['harga'],
                // 'status'            => $v['status'],
                "p_change"          => true,
                'created_at'        => $t_stamp,
                'created_user'      => $this->admin_id,
                'updated_at'        => $t_stamp,
                'updated_user'      => $this->admin_id,
            ]);
            // $ordinal++;
        }
    }

    $model_query->save();
    UjalanDetail::where('id_uj',$model_query->id)->update(["p_change"=>false]);
      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at"=>$t_stamp
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

  public function delete(UjalanRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

    DB::beginTransaction();

    try {
      $model_query = Ujalan::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      
      // $model_querys = UjalanDetail::where("id_uj",$model_query->id)->lockForUpdate()->get();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      // if($model_query->val==1 || $model_query->deleted==1) 
      // throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);


      // if($model_query->ref_id != null){
      //   throw new \Exception("Hapus data ditolak. Data berasal dari transfer",1);
      // }

      // if($model_query->confirmed_by != null){
      //   throw new \Exception("Hapus data ditolak. Data sudah dikonfirmasi",1);
      // }
      
      // if($this->role=='ClientPabrik' || $this->role=='KTU')
      // MyAdmin::checkReturnOrFailLocation($this->admin->the_user,$model_query->hrm_revisi_lokasi_id);
  
      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->save();

      // UjalanDetail::where("id_uj",$model_query->id)->delete();
      // $model_query->delete();

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

  function validasi(Request $request){
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\Ujalan,id",
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
      $model_query = Ujalan::find($request->id);
      if($model_query->val){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }
    
      if(!$model_query->val && $model_query->created_user == $this->admin_id){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
      }
      $model_query->save();
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

  // public function request_ujalans(Request $request)
  // {
  //   MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);

  //   //======================================================================================================
  //   // Pembatasan Data hanya memerlukan limit dan offset
  //   //======================================================================================================

  //   $limit = 30; // Limit +> Much Data
  //   if (isset($request->limit)) {
  //     if ($request->limit <= 250) {
  //       $limit = $request->limit;
  //     } else {
  //       throw new MyException(["message" => "Max Limit 250"]);
  //     }
  //   }

  //   $offset = isset($request->offset) ? (int) $request->offset : 0; // example offset 400 start from 401

  //   //======================================================================================================
  //   // Jika Halaman Ditentutkan maka $offset akan disesuaikan
  //   //======================================================================================================
  //   if (isset($request->page)) {
  //     $page =  (int) $request->page;
  //     $offset = ($page * $limit) - $limit;
  //   }


  //   //======================================================================================================
  //   // Init Model
  //   //======================================================================================================
  //   $model_query = Ujalan::offset($offset)->limit($limit);

  //   $first_row=[];
  //   if($request->first_row){
  //     $first_row 	= json_decode($request->first_row, true);
  //   }

  //   //======================================================================================================
  //   // Model Sorting | Example $request->sort = "username:desc,role:desc";
  //   //======================================================================================================

  //   if ($request->sort) {
  //     $sort_lists = [];

  //     $sorts = explode(",", $request->sort);
  //     foreach ($sorts as $key => $sort) {
  //       $side = explode(":", $sort);
  //       $side[1] = isset($side[1]) ? $side[1] : 'ASC';
  //       $sort_symbol = $side[1] == "desc" ? "<=" : ">=";
  //       $sort_lists[$side[0]] = $side[1];
  //     }

  //     if (isset($sort_lists["name"])) {
  //       $model_query = $model_query->orderBy("name", $sort_lists["name"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("name",$sort_symbol,$first_row["name"]);
  //       }
  //     }

  //     if (isset($sort_lists["id"])) {
  //       $model_query = $model_query->orderBy("id", $sort_lists["id"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("id",$sort_symbol,$first_row["id"]);
  //       }
  //     }

  //     if (isset($sort_lists["input_at"])) {
  //       $model_query = $model_query->orderBy("input_at", $sort_lists["input_at"]);
  //       if (count($first_row) > 0) {
  //         $model_query = $model_query->where("input_at",$sort_symbol,MyLib::utcDateToIdnDate($first_row["input_at"]));
  //       }
  //     }

  //     // if (isset($sort_lists["fullname"])) {
  //     //   $model_query = $model_query->orderBy("fullname", $sort_lists["fullname"]);
  //     // }

  //     // if (isset($sort_lists["role"])) {
  //     //   $model_query = $model_query->orderBy("role", $sort_lists["role"]);
  //     // }
  //   } else {
  //     $model_query = $model_query->orderBy('input_at', 'DESC');
  //   }
  //   //======================================================================================================
  //   // Model Filter | Example $request->like = "username:%username,role:%role%,name:role%,";
  //   //======================================================================================================

  //   if ($request->like) {
  //     $like_lists = [];

  //     $likes = explode(",", $request->like);
  //     foreach ($likes as $key => $like) {
  //       $side = explode(":", $like);
  //       $side[1] = isset($side[1]) ? $side[1] : '';
  //       $like_lists[$side[0]] = $side[1];
  //     }

  //     if(count($like_lists) > 0){
  //       $model_query = $model_query->where(function ($q)use($like_lists){
          
  //         if (isset($like_lists["warehouse_name"])) {
  //           $q->orWhereIn("hrm_revisi_lokasi_id", function($q2)use($like_lists) {
  //             $q2->from('hrm_revisi_lokasi')
  //             ->select('id')->where("lokasi",'like',$like_lists['warehouse_name']);          
  //           });
  //         }
    
  //         if (isset($like_lists["warehouse_source_name"])) {
  //           $q->orWhereIn("hrm_revisi_lokasi_source_id", function($q2)use($like_lists) {
  //             $q2->from('hrm_revisi_lokasi')
  //             ->select('id')->where("lokasi",'like',$like_lists['warehouse_source_name']);          
  //           });
  //         }
    
  //         if (isset($like_lists["warehouse_target_name"])) {
  //           $q->orWhereIn("hrm_revisi_lokasi_target_id", function($q2)use($like_lists) {
  //             $q2->from('hrm_revisi_lokasi')
  //             ->select('id')->where("lokasi",'like',$like_lists['warehouse_target_name']);          
  //           });
  //         }
    
    
  //         if (isset($like_lists["id"])) {
  //           $q->orWhere("id", "like", $like_lists["id"]);
  //         }
    
    
  //         // if (isset($like_lists["item_name"])) {
  //         //   $q->orWhereIn("st_item_id", function($q2)use($like_lists) {
  //         //     $q2->from('st_items')
  //         //     ->select('id')->where("name",'like',$like_lists['item_name']);          
  //         //   });
  //         // }
    
  //         if (isset($like_lists["status"])) {
  //           $q->orWhere("status", "like", $like_lists["status"]);
  //         }
    
  //         if (isset($like_lists["type"])) {
  //           $q->orWhere("type", "like", $like_lists["type"]);
  //         }
    
  //         if (isset($like_lists["requested_name"])) {
  //           $q->orWhereIn("requested_by", function($q2)use($like_lists) {
  //             $q2->from('is_users')
  //             ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
  //           });
  //         }
    
  //         if (isset($like_lists["confirmed_name"])) {
  //           $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
  //             $q2->from('is_users')
  //             ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
  //           });
  //         }
  //       });        
  //     }

      

  //     // if (isset($like_lists["fullname"])) {
  //     //   $model_query = $model_query->orWhere("fullname", "like", $like_lists["fullname"]);
  //     // }

  //     // if (isset($like_lists["role"])) {
  //     //   $model_query = $model_query->orWhere("role", "like", $like_lists["role"]);
  //     // }
  //   }

  //   // ==============
  //   // Model Filter
  //   // ==============


  //   // if (isset($request->id)) {
  //   //   $model_query = $model_query->where("id", 'like', '%' . $request->id . '%');
  //   // }
  //   // if (isset($request->name)) {
  //   //   $model_query = $model_query->where("name", 'like', '%' . $request->name . '%');
  //   // }
  //   // if (isset($request->fullname)) {
  //   //   $model_query = $model_query->where("fullname", 'like', '%' . $request->fullname . '%');
  //   // }
  //   // if (isset($request->role)) {
  //   //   $model_query = $model_query->where("role", 'like', '%' . $request->role . '%');
  //   // }

  //   if($this->role=='ClientPabrik' || $this->role=='KTU')
  //   $warehouse_ids = $this->admin->the_user->hrm_revisi_lokasis();
  //   else
  //   $warehouse_ids = \App\Models\HrmRevisiLokasi::get()->pluck("id")->toArray();


  //   $model_query = $model_query->whereIn("hrm_revisi_lokasi_target_id",$warehouse_ids)->whereNull('confirmed_by')->whereNotNull("ref_id");
  //   $model_query = $model_query->orderBy("ref_id","desc");
  //   $model_query = $model_query->with(['warehouse','warehouse_source','warehouse_target','requester', 'confirmer',
  //   'details'=>function($q){
  //     $q->with([
  //       'item'=>function($q){
  //         $q->with('unit');      
  //       }
  //     ]);      
  //   }])->get();


  //   $model_query_notify = Ujalan::whereNull("confirmed_by")->get();

  //   return response()->json([
  //     // "data"=>EmployeeResource::collection($employees->keyBy->id),
  //     "data" => UjalanResource::collection($model_query),
  //     "request_notif"=> count($model_query_notify)
  //   ], 200);
  // }

  // public function summary_ujalans(Request $request)
  // {
  //   MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);

  //   $warehouses = new HrmRevisiLokasi();

  //   if($this->role=='ClientPabrik' || $this->role=='KTU'){
  //     $warehouse_ids = $this->admin->the_user->hrm_revisi_lokasis();
  //     $warehouses = $warehouses->whereIn("id",$warehouse_ids);
  //   }
  //   $warehouses = $warehouses->where("lokasi","not like","Ramp%");


  //   $date_to = $request->to;
  //   if(!$date_to){
  //     return response()->json([
  //       "message" => "Tanggal tidak boleh kosong",
  //     ], 400);
  //   }
  //   // $date_to = str_replace('"', '', $date_to);
  //   $date_to = MyLib::utcDateToIdnDate(trim($date_to, '"'));
  //   $dates = explode("T",$date_to);
  //   // $date_to = new \DateTime($date_to);
  //   // $date_to->add(new \DateInterval('P1D'));
  //   // // $date = $date->format('Y-m-d H:i:s');
  //   // $date_to = $date_to->format('Y-m-d')."T00:00:00.000Z";

  //   $warehouses = $warehouses->get(); 
  //   $items = Item::get();
  //   $items_id = $items->pluck("id");
  //   // $subquery = UjalanDetail::selectRaw("distinct st_item_id,st_ujalans.hrm_revisi_lokasi_id , max(st_ujalans.input_at) as max_input_at")
  //   // ->whereIn("st_item_id",$items_id)
  //   // ->join("st_ujalans",function ($q){
  //   //   $q->on('st_ujalans.id',"=","st_ujalan_details.id_uj");
  //   // })
  //   // ->whereNotNull("confirmed_by")
  //   // ->where("st_ujalans.input_at","<=",$dates[0])
  //   // // ->orderBy("st_ujalans.input_at","desc")
  //   // // ->orderBy("ref_id","desc")
  //   // ->groupBy(["st_item_id","hrm_revisi_lokasi_id"]);

  //   // $model_query =UjalanDetail::selectRaw("id_uj,st_ujalan_details.st_item_id, qty_reminder, st_ujalans.hrm_revisi_lokasi_id,st_ujalans.updated_at,st_ujalans.input_at")
  //   // ->joinSub($subquery,'dtfb',function ($join){
  //   //   $join->on('st_ujalan_details.st_item_id', '=', 'dtfb.st_item_id');
  //   // })
  //   // ->join("st_ujalans",function ($join) {
  //   //   $join->on("st_ujalans.input_at","dtfb.max_input_at");
  //   //   $join->on("st_ujalans.id","st_ujalan_details.id_uj");
  //   //   $join->orderBy("st_ujalans.input_ordinal","desc");
  //   // })
  //   // ->whereNotNull("st_ujalan_details.qty_reminder")
  //   // ->lockForUpdate()
  //   // ->get();

  //   $model_query =UjalanDetail::selectRaw("st_ujalan_details.st_item_id, sum( coalesce(qty_in, 0) - coalesce(qty_out, 0)) as qty_reminder, st_ujalans.hrm_revisi_lokasi_id")
  //   ->from('st_ujalans')
  //   ->join('st_ujalan_details',function ($join)use($items_id){
  //     $join->on('st_ujalans.id', '=', 'st_ujalan_details.id_uj');
  //     $join->whereIn("st_ujalan_details.st_item_id",$items_id);
  //   })
  //   ->whereNotNull("st_ujalan_details.qty_reminder")
  //   ->whereNotNull("confirmed_by")
  //   ->where("st_ujalans.input_at","<=",$dates[0])
  //   ->groupBy(["hrm_revisi_lokasi_id","st_item_id"])
  //   ->lockForUpdate()
  //   ->get();

  //   $temp_items =[];
  //   foreach ($items as $k => $v) {
  //     array_push($temp_items,[
  //       "id"=>$v->id,
  //       "name"=>$v->name,
  //       "qty_reminder"=>0,
  //       "unit_name"=>$v->unit->name
  //     ]);      
  //   }

  //   $temp_data =[];
  //   foreach ($warehouses as $k => $v) {
  //     array_push($temp_data,[
  //       "id"=>$v->id,
  //       "lokasi"=>$v->lokasi,
  //       "items"=>$temp_items
  //     ]);
  //   }

  //   $temp_data_hrm_revisi_lokasi_ids = array_map(function ($x){
  //     return $x["id"];
  //   },$temp_data);

  //   foreach ($model_query as $k => $v) {
  //     $index = array_search($v->hrm_revisi_lokasi_id, $temp_data_hrm_revisi_lokasi_ids);
  //     if($index===false) continue;

  //     $temp_items_id = array_map(function ($x) {
  //       return $x["id"];
  //     },$temp_data[$index]["items"]);
  //     $index2 = array_search($v->st_item_id, $temp_items_id);
  //     if($index2===false) continue;
  //     $temp_data[$index]["items"][$index2]["qty_reminder"] = $v->qty_reminder;
  //   }

  //   return response()->json([
  //     "all" => $temp_data,
  //     "column_header"=>$warehouses,
  //     "row_header"=>$items,
  //   ], 200);

  // }

  // public function summary_detail_ujalans(Request $request) {
  //   MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    
  //   $rules = [
  //     'item_id' => 'required|exists:\App\Models\Stok\Item,id',
  //     'warehouse_id' => 'required|exists:\App\Models\HrmRevisiLokasi,id',
  //   ];

  //   $messages = [
  //     'item_id.required' => 'Item ID tidak boleh kosong',
  //     'item_id.exists' => 'Item ID tidak terdaftar',

  //     'warehouse_id.required' => 'Warehouse ID tidak boleh kosong',
  //     'warehouse_id.exists' => 'Warehouse ID tidak terdaftar',
  //   ];

  //   $validator = \Validator::make($request->all(), $rules, $messages);

  //   if ($validator->fails()) {
  //     throw new ValidationException($validator);
  //   }

  //   $item_id = $request->item_id;
  //   $warehouse_id = $request->warehouse_id;

  //   if($this->role=='ClientPabrik' || $this->role=='KTU')
  //   MyAdmin::checkReturnOrFailLocation($this->admin->the_user,$warehouse_id);

  //   $model_query = UjalanDetail::selectRaw("st_ujalan_details.*,st_ujalans.type,st_ujalans.input_at,st_ujalans.updated_at,st_ujalans.confirmed_at")->where('st_item_id',$item_id)
  //   ->join("st_ujalans",function ($join)use($warehouse_id) {
  //     $join->on("st_ujalans.id","=","st_ujalan_details.id_uj");
  //     $join->where("hrm_revisi_lokasi_id",$warehouse_id);
  //     $join->whereNotNull("confirmed_by");
  //   })
  //   ->leftjoin("hrm_revisi_lokasi",function ($join)use($warehouse_id) {
  //     $join->on("hrm_revisi_lokasi.id","=","st_ujalans.hrm_revisi_lokasi_target_id");
  //   })
  //   ->orderBy("st_ujalans.input_at","desc")
  //   ->orderBy("st_ujalans.input_ordinal","desc")
  //   ->orderBy("ref_id","desc")
  //   ->get();

  //   // return response()->json([
  //   //   "data" => $model_query,
  //   // ], 400);

  //   // foreach ($model_query as $k => $v) {
  //   // }

  //   return response()->json([
  //     "data" => $model_query,
  //   ], 200);
  // }
    

  // public function getLastDataConfirmed($items_id,$hrm_revisi_lokasi_id,$date_limit=""){
    
  //   $subquery = UjalanDetail::selectRaw("distinct st_item_id,max(st_ujalans.input_at) as max_input_at")
  //   ->whereIn("st_item_id",$items_id)
  //   ->join("st_ujalans",function ($q)use($hrm_revisi_lokasi_id,$date_limit){
  //     $q->on('st_ujalans.id',"=","st_ujalan_details.id_uj");
  //     $q->where("hrm_revisi_lokasi_id",$hrm_revisi_lokasi_id);

  //     if($date_limit!="")
  //     $q->where("input_at","<=",$date_limit);
  //   })
  //   ->whereNotNull("confirmed_by")
  //   ->groupBy("st_item_id");

  //   return UjalanDetail::select("*")
  //     ->joinSub($subquery,'dtfb',function ($join){
  //       $join->on('st_ujalan_details.st_item_id', '=', 'dtfb.st_item_id');
  //     })
  //     ->join("st_ujalans",function ($join) use ($hrm_revisi_lokasi_id) {
  //       $join->on("st_ujalans.input_at","dtfb.max_input_at");
  //       $join->on("st_ujalans.id","st_ujalan_details.id_uj");
  //       $join->orderBy("st_ujalans.input_ordinal","desc");
  //       $join->where("hrm_revisi_lokasi_id",$hrm_revisi_lokasi_id);
  //     })
  //     ->whereNotNull("st_ujalan_details.qty_reminder")
  //     ->lockForUpdate()
  //     ->get();
  //   // return Ujalan::where('hrm_revisi_lokasi_id',$warehouse_id)
  //   // ->where('st_item_id',$item_id)
  //   // ->whereNotNull('confirmed_by')
  //   // ->orderBy("input_at","desc")
  //   // ->orderBy("ref_id","desc")
  //   // ->lockForUpdate()->first(); 
    
    
  // }

  // public function confirm_ujalan(Request $request) {
  //   MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    
  //   $rules = [
  //     'id' => 'required|exists:\App\Models\Stok\Ujalan,id',
  //   ];

  //   $messages = [
  //     'id.required' => 'ID tidak boleh kosong',
  //     'id.exists' => 'ID tidak terdaftar',
  //   ];

  //   $validator = \Validator::make($request->all(), $rules, $messages);

  //   if ($validator->fails()) {
  //     throw new ValidationException($validator);
  //   }

  //   $date_to = $request->to;
  //   if(!$date_to){
  //     return response()->json([
  //       "message" => "Tanggal tidak boleh kosong",
  //     ], 400);
  //   }
  //   // $date_to = str_replace('"', '', $date_to);
  //   $date_to = MyLib::utcDateToIdnDate(trim($date_to, '"'));
  //   $dates = explode("T",$date_to);
    
  //   // $date_to = new \DateTime($date_to);
  //   // $date_to->add(new \DateInterval('P1D'));
  //   // $date = $date->format('Y-m-d H:i:s');
  //   // $date_to = $date_to->format('Y-m-d')."T00:00:00.000Z";
  //   // $nowTime = date("H:i:s");
  //   // $nDateTime = $dates[0]." ".$nowTime;

  //   DB::beginTransaction();
  //   try {
  //     $model_query             = Ujalan::where("id",$request->id)->lockForUpdate()->first();

  //     $super = in_array($this->role,["Super Admin","User"]);
  //     if($model_query->ref_id == null &&  $model_query->requested_by != $this->admin_id && !$super){
  //       throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan konfirmasi data",1);
  //     }

  //     if($model_query->confirmed_by != null){
  //       throw new \Exception("Konfirmasi ditolak. Data sudah di konfirmasi.",1);
  //     }

  //     if($this->role=='ClientPabrik' || $this->role=='KTU')
  //     MyAdmin::checkReturnOrFailLocation($this->admin->the_user,$model_query->hrm_revisi_lokasi_id);
  
  //     $type = $model_query->type;

  //     if($type=="transfer" && $model_query->ref_id==null){
  //       $model_query2                               = new Ujalan();
  //       $model_query2->ref_id                       = $model_query->id;
  //       $model_query2->hrm_revisi_lokasi_id         = $model_query->hrm_revisi_lokasi_target_id;
  //       $model_query2->type                         = $type;
  //       $model_query2->status                       = "pending";
  //       $model_query2->hrm_revisi_lokasi_source_id  = $model_query->hrm_revisi_lokasi_source_id;
  //       $model_query2->hrm_revisi_lokasi_target_id  = $model_query->hrm_revisi_lokasi_target_id;
  //       $model_query2->requested_at                 = date("Y-m-d H:i:s");
  //       $model_query2->requested_by                 = $this->admin_id;
  //       $model_query2->confirmed_at                 = null;
  //       $model_query2->save();
  //     }

  //     $data_from_db = UjalanDetail::where('id_uj', $model_query->id)
  //     ->orderBy("ordinal", "asc")->lockForUpdate()
  //     ->get();

  //     $prev_checks = $this->getLastDataConfirmed($data_from_db->pluck("st_item_id"),$model_query->hrm_revisi_lokasi_id,$dates[0])->toArray();
  //     $items_id = array_map(function ($x) {
  //       return $x["st_item_id"];
  //     },$prev_checks);
     
  //     foreach ($data_from_db as $k => $v) {

  //       $index = array_search($v->st_item_id, $items_id);
  //       $qty_reminder = 0;
        
  //       if ($index !== false){
  //         $qty_reminder = $prev_checks[$index]["qty_reminder"];
  //       }

  //       if($model_query->ref_id == null){
  //         if($type=="in"){
  //           $qty_reminder += $v->qty_in;
  //         }elseif($type=="used" || $type=="transfer"){
  //           // if($qty_reminder - $v->qty_out < 0)
  //           //   throw new \Exception("Qty melebihi stok : ".$qty_reminder, 1);
  //           $qty_reminder-=$v->qty_out;
  //         }
  //       }else{
  //         $qty_reminder += $v->qty_in;
  //       }


  //       UjalanDetail::where("id_uj",$v->id_uj)
  //       ->where("st_item_id",$v->st_item_id)->update([
  //         "qty_reminder"=>$qty_reminder
  //       ]);
        
  //       if($type=="transfer" && $model_query->ref_id==null){
  //         $model_query3 = new UjalanDetail();
  //         $model_query3->id_uj = $model_query2->id;
  //         $model_query3->ordinal = $v->ordinal;
  //         $model_query3->st_item_id = $v->st_item_id;
  //         $model_query3->qty_in = $v->qty_out;
  //         $model_query3->note = $v->note;
  //         $model_query3->save();
  //       }

  //       //recalculate qty reminder
  //       $this->recalculateQtyReminder($v->st_item_id,$model_query->hrm_revisi_lokasi_id,$dates[0],$v->qty_in - $v->qty_out);
  //     }

    
  //     $toGetMaxOrdinal = Ujalan::where("input_at",$dates[0])->where("hrm_revisi_lokasi_id",$model_query->hrm_revisi_lokasi_id)->orderBy("input_ordinal","desc")->first();
  //     // return response()->json([
  //     //   "xmessage" => $toGetMaxOrdinal,
  //     // ], 400);
  //     $model_query->confirmed_at               = date("Y-m-d H:i:s");
  //     $model_query->confirmed_by               = $this->admin_id;
  //     $model_query->input_at                   = $dates[0];
  //     if($toGetMaxOrdinal)
  //     $model_query->input_ordinal              = $toGetMaxOrdinal->input_ordinal + 1;
  //     $model_query->status                     = "done";

  //     $model_query->save();

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses ubah data berhasil",
  //       "data"=>[
  //         "input_at"=>$dates[0],
  //         "confirmed_by"=>$this->admin_id,
  //         "confirmer"=>new IsUserResource($this->admin->the_user)
  //       ]
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     // return response()->json([
  //     //   "getCode" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     //   "message" => $e->getMessage(),
  //     // ], 400);
  //     return response()->json([
  //       "message" => "Proses ubah data gagal"
  //     ], 400);
  //   }
  // }

  // public function recalculateQtyReminder($st_items_id,$hrm_revisi_lokasi_id,$date_limit,$qty){
    
  //   UjalanDetail::select("st_ujalan_details.*")
  //   ->where("st_item_id",$st_items_id)
  //   ->whereIn("id_uj",function ($q)use($hrm_revisi_lokasi_id,$date_limit){
  //     $q->select("id")->from("st_ujalans")->where("input_at",">",$date_limit);
  //     $q->where("hrm_revisi_lokasi_id",$hrm_revisi_lokasi_id);
  //   })->update([
  //     "qty_reminder"=>DB::raw("`qty_reminder`+".($qty))
  //   ]);
    
  // }
}
