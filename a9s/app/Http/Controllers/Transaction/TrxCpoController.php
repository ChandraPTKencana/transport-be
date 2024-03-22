<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Helpers\MyLib;
use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;
use App\Models\MySql\TrxCpo;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Http\Requests\MySql\TrxCpoRequest;
use App\Http\Resources\MySql\TrxCpoResource;
use App\Models\HrmRevisiLokasi;
use App\Models\Stok\Item;
use App\Models\MySql\TrxCpoDetail;
use Exception;
use Illuminate\Support\Facades\DB;
use Image;
use File;
use App\Http\Resources\IsUserResource;

class TrxCpoController extends Controller
{
  private $admin;
  private $role;
  private $admin_id;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
  }

  public function index(Request $request)
  {
 
    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 10; // Limit +> Much Data
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
    $model_query = TrxCpo::offset($offset)->limit($limit);

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

      // if (isset($sort_lists["id"])) {
      //   $model_query = $model_query->orderBy("id", $sort_lists["id"]);
      //   if (count($first_row) > 0) {
      //     $model_query = $model_query->where("id",$sort_symbol,$first_row["id"]);
      //   }
      // }

      // if (isset($sort_lists["xto"])) {
      //   $model_query = $model_query->orderBy("xto", $sort_lists["xto"]);
      //   if (count($first_row) > 0) {
      //     $model_query = $model_query->where("xto",$sort_symbol,$first_row["xto"]);
      //   }
      // }

      // if (isset($sort_lists["tipe"])) {
      //   $model_query = $model_query->orderBy("tipe", $sort_lists["tipe"]);
      //   if (count($first_row) > 0) {
      //     $model_query = $model_query->where("tipe",$sort_symbol,$first_row["tipe"]);
      //   }
      // }

      // if (isset($sort_lists["jenis"])) {
      //   $model_query = $model_query->orderBy("jenis", $sort_lists["jenis"]);
      //   if (count($first_row) > 0) {
      //     $model_query = $model_query->where("jenis",$sort_symbol,$first_row["jenis"]);
      //   }
      // }
      

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

      // if(count($like_lists) > 0){
      //   $model_query = $model_query->where(function ($q)use($like_lists){
            
      //     if (isset($like_lists["id"])) {
      //       $q->orWhere("id", "like", $like_lists["id"]);
      //     }
    
      //     if (isset($like_lists["xto"])) {
      //       $q->orWhere("xto", "like", $like_lists["xto"]);
      //     }
    
      //     if (isset($like_lists["tipe"])) {
      //       $q->orWhere("tipe", "like", $like_lists["tipe"]);
      //     }

      //     if (isset($like_lists["jenis"])) {
      //       $q->orWhere("jenis", "like", $like_lists["jenis"]);
      //     }
    
      //     // if (isset($like_lists["requested_name"])) {
      //     //   $q->orWhereIn("requested_by", function($q2)use($like_lists) {
      //     //     $q2->from('is_users')
      //     //     ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
      //     //   });
      //     // }
    
      //     // if (isset($like_lists["confirmed_name"])) {
      //     //   $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
      //     //     $q2->from('is_users')
      //     //     ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
      //     //   });
      //     // }
      //   });        
      // }

      
    }

    // ==============
    // Model Filter
    // ==============

    $model_query = $model_query->get();

    return response()->json([
      "data" => TrxCpoResource::collection($model_query),
    ], 200);
  }

  public function show(TrxCpoRequest $request)
  {

    // return response()->json([
    //   "message" => "Hanya yang membuat transaksi yang boleh melakukan pergantian atau konfirmasi data",
    // ], 400);
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);

    $model_query = TrxCpo::with([
    'details'=>function($q){
      $q->orderBy("ordinal","asc");
    }])->find($request->id);

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
      "data" => new TrxCpoResource($model_query),
    ], 200);
  }

  public function validateItems($details_in){
    $rules = [
      'details'                          => 'required|array',
      // 'details.*.id_uj'                  => 'required|exists:\App\Models\MySql\TrxCpo',
      'details.*.xdesc'                  => 'required|max:50',
      'details.*.qty'                    => 'required|numeric',
      'details.*.harga'                 => 'required|numeric',
      'details.*.status'                => 'required|in:Y,N',
    ];

    $messages = [
      'details.required' => 'Item harus di isi',
      'details.array' => 'Format Pengambilan Barang Salah',
    ];

    // // Replace :index with the actual index value in the custom error messages
    foreach ($details_in as $index => $msg) {
      // $messages["details.{$index}.id_uj.required"]          = "Baris #" . ($index + 1) . ". ID TrxCpo yang diminta tidak boleh kosong.";
      // $messages["details.{$index}.id_uj.exists"]            = "Baris #" . ($index + 1) . ". ID TrxCpo yang diminta harus dipilih";

      $messages["details.{$index}.xdesc.required"]          = "Baris #" . ($index + 1) . ". Desc yang diminta tidak boleh kosong.";
      $messages["details.{$index}.xdesc.max"]              = "Baris #" . ($index + 1) . ". Desc Maksimal 50 Karakter";

      $messages["details.{$index}.qty.required"]            = "Baris #" . ($index + 1) . ". Qty harus di isi";
      $messages["details.{$index}.qty.numeric"]              = "Baris #" . ($index + 1) . ". Qty harus berupa angka";

      $messages["details.{$index}.harga.required"]            = "Baris #" . ($index + 1) . ". Harga harus di isi";
      $messages["details.{$index}.harga.numeric"]              = "Baris #" . ($index + 1) . ". Harga harus berupa angka";

      $messages["details.{$index}.status.required"]            = "Baris #" . ($index + 1) . ". Status harus di isi";
      $messages["details.{$index}.status.in"]                   = "Baris #" . ($index + 1) . ". Status tidak sesuai format";
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


  public function store(TrxCpoRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);

    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);
    
    DB::beginTransaction();
    try {
      $t_stamp = date("Y-m-d H:i:s");
      if(TrxCpo::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      throw new \Exception("List sudah terdaftar");

      $model_query                  = new TrxCpo();      
      $model_query->xto             = $request->xto;
      $model_query->tipe            = $request->tipe;
      $model_query->jenis           = $request->jenis;
      $model_query->status          = $request->status;
      $model_query->harga          = $request->harga;
      
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

      $model_query->save();

      $ordinal=0;
      $unique_items = [];
      foreach ($details_in as $key => $value) {
        $unique_data = $value['xdesc'];
        if (in_array(strtolower($unique_data), $unique_items) == 1) {
          throw new \Exception("Maaf terdapat Item yang sama");
        }
        array_push($unique_items, strtolower($unique_data));
      }
     
      foreach ($details_in as $key => $value) {
        $ordinal = $key + 1;
        $detail                     = new TrxCpoDetail();
        $detail->id_uj              = $model_query->id;
        $detail->ordinal            = $ordinal;
        $detail->xdesc              = $value['xdesc'];
        $detail->qty                = $value['qty'];
        $detail->harga              = $value['harga'];

        $detail->created_at      = $t_stamp;
        $detail->created_user    = $this->admin_id;
  
        $detail->updated_at      = $t_stamp;
        $detail->updated_user    = $this->admin_id;  
        $detail->save();
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

  public function update(TrxCpoRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    
    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);

    $t_stamp = date("Y-m-d H:i:s");

    DB::beginTransaction();
    try {
      $model_query             = TrxCpo::where("id",$request->id)->lockForUpdate()->first();

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
      $model_query->status          = $request->status;
      $model_query->harga          = $request->harga;
  
      // $model_query->created_at      = $t_stamp;
      // $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;  


      $data_from_db = TrxCpoDetail::where('id_uj', $model_query->id)
      ->orderBy("ordinal", "asc")
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
          throw new Exception('Ada ketidak sesuaian data, harap hubungi staff IT atau refresh browser anda');
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
              throw new \Exception("Maaf terdapat Nama Item yang sama");
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
                throw new \Exception("Data yang ingin dihapus tidak ditemukan");
            } else {
                $dt = $data_from_db[$index];
                // $has_permit = count(array_intersect(['ap-project_material_item-remove'], $scopes));
                // if (!$dt["is_locked"] && $dt["created_by"] == $auth_id && $has_permit) {
                //     ProjectMaterial::where("project_no", $model_query->no)->where("ordinal", $dt["ordinal"])->delete();
                // }
                TrxCpoDetail::where("id_uj",$model_query->id)->where("ordinal",$dt["ordinal"])->delete();
            }
        } else if ($v["p_status"] == "Edit") {

            if ($index === false) {
                throw new \Exception("Data yang ingin diubah tidak ditemukan" . $k);
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
             


              TrxCpoDetail::where("id_uj", $model_query->id)
              ->where("ordinal", $v["key"])->where("p_change",false)->update([
                  "ordinal"=>$v["ordinal"],
                  "xdesc" => $v["xdesc"],
                  "qty" => $v["qty"],
                  "harga" => $v["harga"],
                  "status" => $v["status"],
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

            TrxCpoDetail::insert([
                'id_uj'             => $model_query->id,
                'ordinal'           => $v["ordinal"],
                'xdesc'             => $v['xdesc'],
                'qty'               => $v["qty"],
                'harga'             => $v['harga'],
                'status'            => $v['status'],
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
    TrxCpoDetail::where('id_uj',$model_query->id)->update(["p_change"=>false]);
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

  public function delete(TrxCpoRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);

    DB::beginTransaction();

    try {
      $model_query = TrxCpo::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      
      $model_querys = TrxCpoDetail::where("id_uj",$model_query->id)->lockForUpdate()->get();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      // if($model_query->ref_id != null){
      //   throw new \Exception("Hapus data ditolak. Data berasal dari transfer",1);
      // }

      // if($model_query->confirmed_by != null){
      //   throw new \Exception("Hapus data ditolak. Data sudah dikonfirmasi",1);
      // }
      
      // if($this->role=='ClientPabrik' || $this->role=='KTU')
      // MyAdmin::checkReturnOrFailLocation($this->admin->the_user,$model_query->hrm_revisi_lokasi_id);
  

      TrxCpoDetail::where("id_uj",$model_query->id)->delete();
      $model_query->delete();

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

}
