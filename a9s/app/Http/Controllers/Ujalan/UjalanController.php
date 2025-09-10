<?php

namespace App\Http\Controllers\Ujalan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Exceptions\MyException;
use Exception;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\Ujalan;
use App\Models\MySql\UjalanDetail;
use App\Models\MySql\UjalanDetail2;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\UjalanRequest;

use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;

use App\Http\Resources\IsUserResource;
use App\Http\Resources\MySql\UjalanResource;
use App\Models\MySql\TrxTrp;

use App\Exports\MyReport;

class UjalanController extends Controller
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

  public function index(Request $request,$download = false)
  {
    MyAdmin::checkScope($this->permissions, 'ujalan.views');
 
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
    $model_query = new Ujalan();
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

      $list_to_like = ["id","xto","tipe","jenis","harga","km_range","bonus_trip_supir","bonus_trip_kernet"];

      // $list_to_like_user = [
      //   ["val_name","val_user"],
      //   ["val1_name","val1_user"],
      //   ["val2_name","val2_user"],
      //   ["req_deleted_name","req_deleted_user"],
      //   ["deleted_name","deleted_user"],
      // ];

      

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists,$list_to_like){
          foreach ($list_to_like as $key => $v) {
            if (isset($like_lists[$v])) {
              $q->orWhere($v, "like", $like_lists[$v]);
            }
          }

          // foreach ($list_to_like_user as $key => $v) {
          //   if (isset($like_lists[$v[0]])) {
          //     $q->orWhereIn($v[1], function($q2)use($like_lists,$v) {
          //       $q2->from('is_users')
          //       ->select('id')->where("username",'like',$like_lists[$v[0]]);          
          //     });
          //   }
          // }

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
    
    if($filter_status=="available"){
      $model_query = $model_query->where("deleted",0)->where("val",1)->where("val1",1);
    }

    if($filter_status=="unapprove"){
      $model_query = $model_query->where("deleted",0)->where(function($q){
       $q->where("val",0)->orwhere("val1",0); 
      });
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    if($filter_status=="req_deleted"){
      $model_query = $model_query->where("deleted",0);
    }

    if($download){
      $model_query = $model_query->with(['details','details2']);
    }
    
    $model_query = $model_query->with(['val_by','val1_by','deleted_by'])->get();

    return response()->json([
      "data" => UjalanResource::collection($model_query),
    ], 200);
  }

  public function show(UjalanRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'ujalan.view');

    // return response()->json([
    //   "message" => "Hanya yang membuat transaksi yang boleh melakukan pergantian atau konfirmasi data",
    // ], 400);

    $model_query = Ujalan::with([
    'details'=>function($q){
      $q->orderBy("ordinal","asc");
    },
    //start for details2
    'details2'=>function($q){
      $q->orderBy("ordinal","asc");
    }
    //end for details2
    ])->with(['val_by','val1_by','deleted_by'])->find($request->id);

    // if($model_query->requested_by != $this->admin_id){
    //   return response()->json([
    //     "message" => "Hanya yang membuat transaksi yang boleh melakukan pergantian atau konfirmasi data",
    //   ], 400);
    // }
    

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
      'details.*.for_remarks'           => 'required|in:0,1',
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

      $messages["details.{$index}.for_remarks.required"]            = "Baris #" . ($index + 1) . ". Untuk Remarks harus di isi";
      $messages["details.{$index}.for_remarks.in"]              = "Baris #" . ($index + 1) . ". Untuk Remarks harus dipilih";

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

    $validator = Validator::make(['details' => $details_in], $rules, $messages);

    // Check if validation fails
    if ($validator->fails()) {
      foreach ($validator->messages()->all() as $k => $v) {
        throw new MyException(["message" => $v], 400);
      }
    }
  }

  //start for details2
  public function validateItems2($details_in2){
    $rules = [
      
      // 'details'                          => 'required|array',
      // 'details.*.id_uj'               => 'required|exists:\App\Models\MySql\Ujalan',
      'details.*.p_status'               => 'required|in:Remove,Add,Edit',
      'details.*.ac_account_id'          => 'required_if:details.*.p_status,Add,Edit',
      'details.*.ac_account_code'        => 'required_if:details.*.p_status,Add,Edit',
      'details.*.ac_account_name'        => 'required_if:details.*.p_status,Add,Edit',
      'details.*.qty'                    => 'required_if:details.*.p_status,Add,Edit|numeric',
      'details.*.amount'                 => 'required_if:details.*.p_status,Add,Edit|numeric',
      'details.*.description'            => 'required_if:details.*.p_status,Add,Edit',
      'details.*.xfor'                   => 'nullable|in:Supir,Kernet',
      // 'details.*.status'              => 'required|in:Y,N',
    ];

    $messages = [
      'details.required' => 'Item harus di isi',
      'details.array' => 'Format Pengambilan Barang Salah',
    ];

    // // Replace :index with the actual index value in the custom error messages
    foreach ($details_in2 as $index => $msg) {
      // $messages["details.{$index}.id_uj.required"]          = "Baris #" . ($index + 1) . ". ID Ujalan yang diminta tidak boleh kosong.";
      // $messages["details.{$index}.id_uj.exists"]            = "Baris #" . ($index + 1) . ". ID Ujalan yang diminta harus dipilih";

      $messages["details.{$index}.ac_account_id.required_if"]   = "Baris #" . ($index + 1) . ". Acc ID yang diminta tidak boleh kosong.";
      $messages["details.{$index}.ac_account_code.required_if"] = "Baris #" . ($index + 1) . ". Acc Code yang diminta tidak boleh kosong.";
      $messages["details.{$index}.ac_account_name.required_if"] = "Baris #" . ($index + 1) . ". Acc Name yang diminta tidak boleh kosong.";
      $messages["details.{$index}.ac_account_code.max"]         = "Baris #" . ($index + 1) . ". Acc Code Maksimal 255 Karakter";

      $messages["details.{$index}.qty.required_if"]             = "Baris #" . ($index + 1) . ". Qty harus di isi";
      $messages["details.{$index}.qty.numeric"]                 = "Baris #" . ($index + 1) . ". Qty harus berupa angka";

      $messages["details.{$index}.amount.required_if"]          = "Baris #" . ($index + 1) . ". Amount harus di isi";
      $messages["details.{$index}.amount.numeric"]              = "Baris #" . ($index + 1) . ". Amount harus berupa angka";

      $messages["details.{$index}.description.required_if"]     = "Baris #" . ($index + 1) . ". Description harus di isi";
      $messages["details.{$index}.xfor.in"]                     = "Baris #" . ($index + 1) . ". Xfor harus di pilih";
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

    $validator = Validator::make(['details' => $details_in2], $rules, $messages);

    // Check if validation fails
    if ($validator->fails()) {
      foreach ($validator->messages()->all() as $k => $v) {
        throw new MyException(["message" => $v], 400);
      }
    }
  }
  //end for details2


  public function store(UjalanRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'ujalan.create');

    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);
    //start for details2
    $details_in2 = json_decode($request->details2, true);
    $this->validateItems2($details_in2);
    //end for details2

    if(count($details_in)>0){
      MyAdmin::checkScope($this->permissions, 'ujalan.detail.insert');
    }

    if(count($details_in2)>0){
      MyAdmin::checkScope($this->permissions, 'ujalan.detail2.insert');
    }

    $transition_from = $request->transition_from;
    if($transition_from==env("app_name") || !in_array($transition_from,MyLib::$list_pabrik)){
      $transition_from="";
    }

    $rollback_id = -1;
    DB::beginTransaction();
    try {
      //start for details2
      $unique_items2 = [];
      $unique_acc_code=[];
      foreach ($details_in2 as $key => $value) {
        $unique_data2 = strtolower(trim($value['ac_account_code']).trim($value['description']));
        if (in_array($unique_data2, $unique_items2) == 1) {
          throw new \Exception("Maaf terdapat Item yang sama",1);
        }
        array_push($unique_items2, $unique_data2);
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

      $t_stamp = date("Y-m-d H:i:s");
      // if(Ujalan::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar",1);

      $model_query                    = new Ujalan();      
      $model_query->xto               = $request->xto;
      $model_query->km_range          = $request->km_range ?? 0;
      $model_query->bonus_trip_supir  = $request->bonus_trip_supir ?? 0;
      $model_query->bonus_trip_kernet = $request->bonus_trip_kernet ?? 0;
      // $model_query->batas_persen_susut = MyLib::emptyStrToNull($request->batas_persen_susut);
      $model_query->tipe              = $request->tipe;
      $model_query->jenis             = $request->jenis;
      // $model_query->status          = $request->status;
      $model_query->harga             = 0;
      $model_query->note_for_remarks  = MyLib::emptyStrToNull($request->note_for_remarks);
      $model_query->transition_from   = $transition_from;
      
      $model_query->created_at        = $t_stamp;
      $model_query->created_user      = $this->admin_id;

      $model_query->updated_at        = $t_stamp;
      $model_query->updated_user      = $this->admin_id;

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
      
      $remarksign=0;
      foreach ($details_in as $key => $value) {
        $ordinal = $key + 1;
        $detail                     = new UjalanDetail();
        $detail->id_uj              = $model_query->id;
        $detail->ordinal            = $ordinal;
        $detail->xdesc              = $value['xdesc'];
        $detail->qty                = $value['qty'];
        $detail->harga              = $value['harga'];
        $detail->for_remarks        = $value['for_remarks'];
        if($value['for_remarks']){
          $remarksign++;
        }
        $model_query->harga +=  ($value["qty"] * $value["harga"]);
        $detail->created_at      = $t_stamp;
        $detail->created_user    = $this->admin_id;
  
        $detail->updated_at      = $t_stamp;
        $detail->updated_user    = $this->admin_id;  
        $detail->save();
      }
      if($remarksign == 0)
      throw new \Exception("Minimal Harus Memiliki 1 For Remarks Di Detail",1);

      
      //start for details2      
      $temp_amount_details2=0;
      $ordinal2=0;
      $asst_opt="TANPA KERNET";
      foreach ($details_in2 as $key => $value) {
        $ordinal2 = $key + 1;
        $detail2                     = new UjalanDetail2();
        $detail2->id_uj              = $model_query->id;
        $detail2->ordinal            = $ordinal2;
        
        $index_item = array_search($value['ac_account_code'], $listToCheck);
        
        if ($index_item !== false){
          $detail2->ac_account_id    = $temp_ac_accounts[$index_item]['AccountID'];
          $detail2->ac_account_name    = $temp_ac_accounts[$index_item]['AccountName'];
          $detail2->ac_account_code    = $temp_ac_accounts[$index_item]['AccountCode'];
        }

        $detail2->qty                = $value['qty'];
        $detail2->amount             = $value['amount'];
        $detail2->description        = $value['description'];
        $detail2->xfor               = $value['xfor'];

        $temp_amount_details2 +=  ($value["qty"] * $value["amount"]);
        $detail2->created_at      = $t_stamp;
        $detail2->created_user    = $this->admin_id;
  
        $detail2->updated_at      = $t_stamp;
        $detail2->updated_user    = $this->admin_id;  
        $detail2->save();

        if($value["xfor"]=="Kernet") $asst_opt = "DENGAN KERNET";
      }
      //end for details2
      if($ordinal2 > 0 && $model_query->harga!=$temp_amount_details2)
      throw new \Exception("Total Tidak Cocok harap Periksa Kembali",1);

      $model_query->asst_opt = $asst_opt;
      $model_query->save();
      MyLog::sys("ujalan_mst",$model_query->id,"insert");

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
    MyAdmin::checkMultiScope($this->permissions, ['ujalan.modify','ujalan.detail.insert','ujalan.detail.modify','ujalan.detail.remove','ujalan.detail2.insert','ujalan.detail2.modify','ujalan.detail2.remove']);

    $t_stamp = date("Y-m-d H:i:s");
    $transition_from = $request->transition_from;
    if($transition_from==env("app_name") || !in_array($transition_from,MyLib::$list_pabrik)){
      $transition_from="";
    }
    DB::beginTransaction();
    try {
      $SYSNOTES=[];
      $model_query = Ujalan::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);

      if($model_query->deleted==1)
      throw new \Exception("Data Sudah Dihapus",1);
      
      if(
        ($model_query->val==1 && $model_query->val1==1) || 
        (MyAdmin::checkScope($this->permissions, 'ujalan.val',true) && $model_query->val == 1) ||
        (MyAdmin::checkScope($this->permissions, 'ujalan.val1',true) && $model_query->val1 == 1)
      ) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      if(MyAdmin::checkMultiScope($this->permissions, ['ujalan.detail.insert','ujalan.detail.modify','ujalan.detail.remove'],true)){
        $details_in = json_decode($request->details, true);
        $this->validateItems($details_in);
      }
      //start for details2
      $details_in2 = json_decode($request->details2, true);
      $this->validateItems2($details_in2);   
      //end for details2
      
      //start for details2
      $unique_items2 = [];
      $unique_acc_code=[];
      foreach ($details_in2 as $key => $value) {
        $unique_data2 = strtolower(trim($value['ac_account_code']).trim($value['description']));
        if (in_array($unique_data2, $unique_items2) == 1) {
          throw new \Exception("Maaf terdapat Item yang sama",1);
        }
        array_push($unique_items2, $unique_data2);
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

      // if(Ujalan::where("id","!=",$request->id)->where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar",1);

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
  
      // $dt_before = $this->getLastDataConfirmed($warehouse_id,$request->item_id);
      // if($dt_before && $dt_before->id != $model_query->id){
      //   throw new \Exception("Ubah ditolak. Hanya data terbaru yang bisa diubah.",1);
      // }

      if(MyAdmin::checkScope($this->permissions,'ujalan.modify',true)){
        $model_query->xto               = $request->xto;
        $model_query->km_range          = $request->km_range ?? 0;
        $model_query->bonus_trip_supir  = $request->bonus_trip_supir ?? 0;
        $model_query->bonus_trip_kernet = $request->bonus_trip_kernet ?? 0;
        // $model_query->batas_persen_susut = MyLib::emptyStrToNull($request->batas_persen_susut);
        $model_query->tipe              = $request->tipe;
        $model_query->jenis             = $request->jenis;
        $model_query->harga             = 0;
        $model_query->note_for_remarks  = MyLib::emptyStrToNull($request->note_for_remarks);
        $model_query->transition_from   = $transition_from;

        // $model_query->status          = $request->status;
    
        // $model_query->created_at      = $t_stamp;
        // $model_query->created_user    = $this->admin_id;

        $model_query->updated_at      = $t_stamp;
        $model_query->updated_user    = $this->admin_id;  

      }

      if(MyAdmin::checkMultiScope($this->permissions, ['ujalan.detail.insert','ujalan.detail.modify','ujalan.detail.remove'],true)){
        array_push( $SYSNOTES ,"Details: \n");

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


        if(count($for_adds) > 0){
          MyAdmin::checkScope($this->permissions, 'ujalan.detail.insert');
        }
  
        if(count($for_edits) > 0){
          MyAdmin::checkScope($this->permissions, 'ujalan.detail.modify');
        }
  
        if(count($for_deletes) > 0){
          MyAdmin::checkScope($this->permissions, 'ujalan.detail.remove');
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
        $remarksign=0;

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
                  array_push( $SYSNOTES ,"Ordinal ".$dt["ordinal"]." [Deleted]");
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

                $mq=UjalanDetail::where("id_uj", $model_query->id)
                ->where("ordinal", $v["key"])->where("p_change",false)->lockForUpdate()->first();
                
                $mqToCom = clone($mq);

                $mq->ordinal      = $v["ordinal"];
                $mq->xdesc        = $v["xdesc"];
                $mq->qty          = $v["qty"];
                $mq->harga        = $v["harga"];
                $mq->for_remarks  = $v["for_remarks"];
                $mq->p_change     = true;
                $mq->updated_at   = $t_stamp;
                $mq->updated_user = $this->admin_id;
                $mq->save();

                $SYSNOTE = MyLib::compareChange($mqToCom,$mq); 
                array_push( $SYSNOTES ,"Ordinal ".$v["key"]."\n".$SYSNOTE);

                if($v['for_remarks']){
                  $remarksign++;
                }
                // UjalanDetail::where("id_uj", $model_query->id)
                // ->where("ordinal", $v["key"])->where("p_change",false)->update([
                //     "ordinal"=>$v["ordinal"],
                //     "xdesc" => $v["xdesc"],
                //     "qty" => $v["qty"],
                //     "harga" => $v["harga"],
                //     "for_remarks" => $v["for_remarks"],
                //     // "status" => $v["status"],
                //     "p_change"=> true,
                //     "updated_at"=> $t_stamp,
                //     "updated_user"=> $this->admin_id,
                // ]);

              }

              // $ordinal++;
          } else if ($v["p_status"] == "Add") {

              // if (!count(array_intersect(['ap-project_material_item-add'], $scopes)))
              //     throw new Exception('Tambah Project Material Item Tidak diizinkan');

              // if (!count(array_intersect(['dp-project_material-manage-item_code'], $scopes))  && $v["item_code"] != "")
              //     throw new Exception('Tidak ada izin mengelola Kode item');
              array_push( $SYSNOTES ,"Ordinal ".$v["ordinal"]." [Insert]");
              
              $model_query->harga          += ($v["qty"] * $v["harga"]);
              UjalanDetail::insert([
                  'id_uj'             => $model_query->id,
                  'ordinal'           => $v["ordinal"],
                  'xdesc'             => $v['xdesc'],
                  'qty'               => $v["qty"],
                  'harga'             => $v['harga'],
                  "for_remarks"       => $v["for_remarks"],
                  // 'status'            => $v['status'],
                  "p_change"          => true,
                  'created_at'        => $t_stamp,
                  'created_user'      => $this->admin_id,
                  'updated_at'        => $t_stamp,
                  'updated_user'      => $this->admin_id,
              ]);

              if($v['for_remarks']){
                $remarksign++;
              }
              // $ordinal++;
          }
        }

        if($remarksign == 0)
        throw new \Exception("Minimal Harus Memiliki 1 For Remarks Di Detail",1);
      }

      if(MyAdmin::checkMultiScope($this->permissions, ['ujalan.detail2.insert','ujalan.detail2.modify','ujalan.detail2.remove'],true)){
        //start for details2
        array_push( $SYSNOTES ,"Details PVR: \n");
  
        $data_from_db2 = UjalanDetail2::where('id_uj', $model_query->id)
        ->orderBy("ordinal", "asc")->lockForUpdate()
        ->get()->toArray();
        
  
        $in_keys2 = array_filter($details_in2, function ($x) {
            return isset($x["key"]);
        });
  
        $in_keys2 = array_map(function ($x) {
            return $x["key"];
        }, $in_keys2);
  
        $am_ordinal_db2 = array_map(function ($x) {
            return $x["ordinal"];
        }, $data_from_db2);
  
        if (count(array_diff($in_keys2, $am_ordinal_db2)) > 0 || count(array_diff($am_ordinal_db2, $in_keys2)) > 0) {
            throw new Exception('Ada ketidak sesuaian data, harap hubungi staff IT atau refresh browser anda',1);
        }
  
        $id_items2 = [];
        $ordinal2 = 0;
        $for_deletes2 = [];
        $for_edits2 = [];
        $for_adds2 = [];
        $data_to_processes2 = [];
        foreach ($details_in2 as $k => $v) {
          // $xdesc = $v['xdesc'] ? $v['xdesc'] : "";
          
          if (in_array($v["p_status"], ["Add", "Edit"])) {
            $unique2 = strtolower(trim($v['ac_account_code']).trim($v['description']));
            if (in_array($unique2, $id_items2) == 1) {
                throw new \Exception("Maaf terdapat Nama Item yang sama",1);
            }
            array_push($id_items2, $unique2);
          }
  
          if ($v["p_status"] !== "Remove") {
            $ordinal2++;
            $details_in2[$k]["ordinal"] = $ordinal2;
            if ($v["p_status"] == "Edit")
                array_unshift($for_edits2, $details_in2[$k]);
            elseif ($v["p_status"] == "Add")
                array_push($for_adds2, $details_in2[$k]);
          } else
              array_push($for_deletes2, $details_in2[$k]);
        }
  
        // if(count($details_in2) > 0 && count($for_adds2)==0 && count($for_edits2)==0){
        //   throw new \Exception("Item harus Diisi",1);
        // }
        if(count($for_adds2) > 0){
          MyAdmin::checkScope($this->permissions, 'ujalan.detail2.insert',false,true);
        }
  
        if(count($for_edits2) > 0){
          MyAdmin::checkScope($this->permissions, 'ujalan.detail2.modify',false,true);
        }
  
        if(count($for_deletes2) > 0){
          MyAdmin::checkScope($this->permissions, 'ujalan.detail2.remove',false,true);
        }
  
        $data_to_processes2 = array_merge($for_deletes2, $for_edits2, $for_adds2);
        
        $temp_amount_details2=0;
        $asst_opt="TANPA KERNET";
  
        foreach ($data_to_processes2 as $k => $v) {
          $index2 = false;
  
          if (isset($v["key"])) {
              $index2 = array_search($v["key"], $am_ordinal_db2);
          }
          
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
  
              if ($index2 === false) {
                  throw new \Exception("Data yang ingin dihapus tidak ditemukan",1);
              } else {
                  $dt2 = $data_from_db2[$index2];
                  // $has_permit = count(array_intersect(['ap-project_material_item-remove'], $scopes));
                  // if (!$dt["is_locked"] && $dt["created_by"] == $auth_id && $has_permit) {
                  //     ProjectMaterial::where("project_no", $model_query->no)->where("ordinal", $dt["ordinal"])->delete();
                  // }
                  array_push( $SYSNOTES ,"Ordinal ".$dt2["ordinal"]." [Deleted]");
                  UjalanDetail2::where("id_uj",$model_query->id)->where("ordinal",$dt2["ordinal"])->delete();
              }
          } else if ($v["p_status"] == "Edit") {
  
              if ($index2 === false) {
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
               
                $temp_amount_details2         += ($v["qty"] * $v["amount"]);
  
                $index_item = array_search($v['ac_account_code'], $listToCheck);
                $ac_account_id   = null;
                $ac_account_name = null;
                $ac_account_code = null;
                if ($index_item !== false){
                  $ac_account_id    = $temp_ac_accounts[$index_item]['AccountID'];
                  $ac_account_name    = $temp_ac_accounts[$index_item]['AccountName'];
                  $ac_account_code    = $temp_ac_accounts[$index_item]['AccountCode'];
                }
  
  
                $mq=UjalanDetail2::where("id_uj", $model_query->id)
                ->where("ordinal", $v["key"])->where("p_change",false)->lockForUpdate()->first();
                
                $mqToCom = clone($mq);
  
                $mq->ordinal            = $v["ordinal"];
                $mq->qty                = $v["qty"];
                $mq->amount             = $v["amount"];
                $mq->ac_account_id      = $ac_account_id;
                $mq->ac_account_name    = $ac_account_name;
                $mq->ac_account_code    = $ac_account_code;
                $mq->description        = $v["description"];
                $mq->xfor               = $v["xfor"];
                
                if($v["xfor"]=="Kernet") $asst_opt = "DENGAN KERNET";

                $mq->p_change           = true;
                $mq->updated_at         = $t_stamp;
                $mq->updated_user       = $this->admin_id;
                $mq->save();
  
                $SYSNOTE = MyLib::compareChange($mqToCom,$mq); 
                array_push( $SYSNOTES ,"Ordinal ".$v["key"]."\n".$SYSNOTE);
  
                // UjalanDetail2::where("id_uj", $model_query->id)
                // ->where("ordinal", $v["key"])->where("p_change",false)->update([
                //     "ordinal"=>$v["ordinal"],
                //     "qty" => $v["qty"],
                //     "amount" => $v["amount"],
                //     "ac_account_id" => $ac_account_id,
                //     "ac_account_name" => $ac_account_name,
                //     "ac_account_code" => $ac_account_code,
                //     "description" => $v["description"],
                //     // "status" => $v["status"],
                //     "p_change"=> true,
                //     "updated_at"=> $t_stamp,
                //     "updated_user"=> $this->admin_id,
                // ]);
  
              }
  
              // $ordinal++;
          } else if ($v["p_status"] == "Add") {
  
              // if (!count(array_intersect(['ap-project_material_item-add'], $scopes)))
              //     throw new Exception('Tambah Project Material Item Tidak diizinkan');
  
              // if (!count(array_intersect(['dp-project_material-manage-item_code'], $scopes))  && $v["item_code"] != "")
              //     throw new Exception('Tidak ada izin mengelola Kode item');
              $temp_amount_details2         += ($v["qty"] * $v["amount"]);
  
              $index_item = array_search($v['ac_account_code'], $listToCheck);
              $ac_account_id   = null;
              $ac_account_name = null;
              $ac_account_code = null;
              if ($index_item !== false){
                $ac_account_id    = $temp_ac_accounts[$index_item]['AccountID'];
                $ac_account_name    = $temp_ac_accounts[$index_item]['AccountName'];
                $ac_account_code    = $temp_ac_accounts[$index_item]['AccountCode'];
              }
              
              array_push( $SYSNOTES ,"Ordinal ".$v["ordinal"]." [Insert]");
  
              UjalanDetail2::insert([
                  'id_uj'             => $model_query->id,
                  'ordinal'           => $v["ordinal"],
                  "qty"               => $v["qty"],
                  "amount"            => $v["amount"],
                  "ac_account_id"     => $ac_account_id,
                  "ac_account_name"   => $ac_account_name,
                  "ac_account_code"   => $ac_account_code,
                  "description"       => $v["description"],
                  "xfor"              => $v["xfor"],
                  // 'status'            => $v['status'],
                  "p_change"          => true,
                  'created_at'        => $t_stamp,
                  'created_user'      => $this->admin_id,
                  'updated_at'        => $t_stamp,
                  'updated_user'      => $this->admin_id,
              ]);

              if($v["xfor"]=="Kernet") $asst_opt = "DENGAN KERNET";

              // $ordinal++;
          }
        }
  
        if($temp_amount_details2 > 0 && $model_query->harga!=$temp_amount_details2)
        throw new \Exception("Total Tidak Cocok harap Periksa Kembali",1);
      }

      if(MyAdmin::checkMultiScope($this->permissions,['ujalan.modify','ujalan.detail2.modify'],true)){
        $model_query->asst_opt = $asst_opt;
        $model_query->save();
      }

      if(MyAdmin::checkMultiScope($this->permissions, ['ujalan.detail.insert','ujalan.detail.modify','ujalan.detail.remove'],true)){
        UjalanDetail::where('id_uj',$model_query->id)->update(["p_change"=>false]);
      }

      if(MyAdmin::checkMultiScope($this->permissions, ['ujalan.detail2.insert','ujalan.detail2.modify','ujalan.detail2.remove'],true)){
        UjalanDetail2::where('id_uj',$model_query->id)->update(["p_change"=>false]);
      }


      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      array_unshift( $SYSNOTES , $SYSNOTE );            
      MyLog::sys("ujalan_mst",$request->id,"update",implode("\n",$SYSNOTES));

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
    MyAdmin::checkScope($this->permissions, 'ujalan.remove');

    DB::beginTransaction();

    try {
      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);
    
      $model_query = Ujalan::where("id",$request->id)->lockForUpdate()->first();
    
      $SYSOLD                     = clone($model_query);
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
      
  
      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("ujalan_mst",$request->id,"delete",$SYSNOTE);

      // UjalanDetail::where("id_uj",$model_query->id)->delete();
      // $model_query->delete();

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

      return response()->json([
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  }

  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['ujalan.val','ujalan.val1']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\Ujalan,id",
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
      $model_query = Ujalan::lockForUpdate()->find($request->id);
      if(MyAdmin::checkScope($this->permissions, 'ujalan.val',true) && $model_query->val){
        throw new \Exception("Data Sudah Tervalidasi",1);
      }

      if(MyAdmin::checkScope($this->permissions, 'ujalan.val1',true) &&  $model_query->val1){
        throw new \Exception("Data Sudah Tervalidasi",1);
      }

      $uj2 = UjalanDetail2::where("id_uj",$request->id)->selectRaw("sum(amount * qty) as total")->groupBy("id_uj")->first();
      if($uj2 && $uj2->total != $model_query->harga)
      throw new \Exception("Total Tidak Cocok",1);
      
      

      $SYSOLD                     = clone($model_query);
      
      if(MyAdmin::checkScope($this->permissions, 'ujalan.val',true) && !$model_query->val){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'ujalan.val1',true) && !$model_query->val1){
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("ujalan_mst",$request->id,"approve",$SYSNOTE);

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
    MyAdmin::checkMultiScope($this->permissions, ['ujalan.unval','ujalan.unval1']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\Ujalan,id",
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
      $model_query = Ujalan::lockForUpdate()->find($request->id);
      if(TrxTrp::where("id_uj",$model_query->id)->first()){
        throw new \Exception("Data Sudah Digunakan",1);
      }
      $SYSOLD                     = clone($model_query);
      
      if(MyAdmin::checkScope($this->permissions, 'ujalan.unval',true) && $model_query->val){
        $model_query->val = 0;
        // $model_query->val_user = $this->admin_id;
        // $model_query->val_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'ujalan.unval1',true) && $model_query->val1){
        $model_query->val1 = 0;
        // $model_query->val1_user = $this->admin_id;
        // $model_query->val1_at = $t_stamp;
      }
      
      $model_query->save();
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 

      MyLog::sys("ujalan_mst",$request->id,"unapprove",$SYSNOTE);

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

  public function downloadExcel(Request $request){
    MyAdmin::checkScope($this->permissions, 'ujalan.download_file');

    set_time_limit(0);
    
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    

    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {
      array_push($newDetails,$value);
    }

    // $filter_model = json_decode($request->filter_model,true);
    // $tanggal = $filter_model['tanggal'];    


    // $date_from=date("d-m-Y",strtotime($tanggal['value_1']));
    // $date_to=date("d-m-Y",strtotime($tanggal['value_2']));

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-ujalan_master';


    // $date = new \DateTime();
    // $filename=$date->format("YmdHis").'-ujalan_master'.'(#'.$request->id.')';

    $mime=MyLib::mime("xlsx");
    // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.ujalan_master'), $mime["exportType"]));

    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }

  public function batasPersenSusut(Request $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['ujalan.batas_persen_susut.full_act']);
    
    $rules = [
      'id' => "required|exists:\App\Models\MySql\Ujalan,id",
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
    $transition_from = $request->transition_from;
    if($transition_from==env("app_name") || !in_array($transition_from,MyLib::$list_pabrik)){
      $transition_from="";
    }
    DB::beginTransaction();
    try {
      $model_query = Ujalan::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);

      if($model_query->deleted==1)
      throw new \Exception("Data Sudah Dihapus",1);
      
      // $model_query->batas_persen_susut  = $request->batas_persen_susut;
      $model_query->batas_persen_susut = MyLib::emptyStrToNull($request->batas_persen_susut);
      $model_query->updated_at          = $t_stamp;
      $model_query->updated_user        = $this->admin_id;  

      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("ujalan_mst",$request->id,"update",$SYSNOTE);

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
}
