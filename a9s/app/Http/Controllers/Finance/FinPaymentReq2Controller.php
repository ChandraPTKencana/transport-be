<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Helpers\MyLib;
use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\FacadesValidator;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

use App\Models\MySql\FinPaymentReq2;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Http\Requests\MySql\FinPaymentReq2Request;
use App\Http\Resources\MySql\FinPaymentReq2Resource;
use App\Models\HrmRevisiLokasi;
use Exception;
use Illuminate\Support\Facades\DB;
use Image;
use File;
use PDF;
use App\Http\Resources\IsUserResource;
use App\Models\MySql\Employee;
use App\Models\MySql\IsUser;
use App\Models\MySql\TrxTrp;
use App\Exports\MyReport;
use App\Helpers\TrfMandiri;
use App\Models\MySql\Bank;
use App\Models\MySql\ExtraMoneyTrx;
use App\Models\MySql\FinPaymentReqDtl2;
use App\Models\MySql\Setup;
use App\PS\PSTripSupirKernet;
use Illuminate\Support\Facades\Storage;

class FinPaymentReq2Controller extends Controller
{
  private $admin;
  private $role;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'fin_payment_req_2';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    // $this->role = $this->admin->the_user->hak_akses;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.views');
 
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
    $model_query = FinPaymentReq2::offset($offset)->limit($limit);

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


      if (isset($sort_lists["created_at"])) {
        $model_query = $model_query->orderBy("created_at", $sort_lists["created_at"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("created_at",$sort_symbol,$first_row["created_at"]);
        }
      }

    } else {
      $model_query = $model_query->orderBy('created_at', 'desc');
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

          if (isset($like_lists["status"])) {
            $q->orWhere("status", "like", $like_lists["status"]);
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
    $date_from="";
    $date_to="";
    if($request->date_from || $request->date_to){
      $date_from = $request->date_from;
      if(!$date_from)
      throw new MyException([ "date_from" => ["Date From harus diisi"] ], 422);

      if(!strtotime($date_from))
      throw new MyException(["date_from"=>["Format Date From Tidak Cocok"]], 422);
      
      $date_to = $request->date_to;
      if(!$date_to)
      throw new MyException([ "date_to" => ["Date To harus diisi"] ], 422);

      if(!strtotime($date_to))
      throw new MyException(["date_to"=>["Format Date To Tidak Cocok"]], 422);

      if(strtotime($date_from)>strtotime($date_to))
      throw new MyException(["message"=>"Tanggal Dari Harus Sebelum Tanggal Sampai"], 400);

      $date_from = date("Y-m-d",strtotime($date_from))." 00:00:00";
      $date_to = date("Y-m-d",strtotime($date_to))." 23:59:59";

      $model_query = $model_query->where("created_at",">=",$date_from)->where('created_at',"<=",$date_to);
    }

    $model_query = $model_query->where("deleted",0)->get();

    return response()->json([
      "data" => FinPaymentReq2Resource::collection($model_query),
    ], 200);
  }

  public function show(FinPaymentReq2Request $request,$for_gen=false)
  {
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.view');

    $model_query = FinPaymentReq2::with([
    'details'=>function($q){
      $q->with(["trx_trp","extra_money_trx"=>function ($q1) {
        $q1->with('prev_trx_trp');
      }]);
    },])
    ->with(['val_by'])->where("deleted",0)->find($request->id);

    $lists = [];
    $no=0;
    foreach ($model_query->details as $k => $v) {
      $no++;

      $trx_trp = $v->trx_trp;

      $extra_money_trx = $v->extra_money_trx;

      $source = ($trx_trp)?'TRIP':'EM';
      if($trx_trp){
        $tt = $trx_trp;
      }else{
        $tt = $extra_money_trx->prev_trx_trp;        
      }

      $tujuan = $tt->xto;
      $produk = $tt->jenis;
      $no_pol = $tt->no_pol;
      
      array_push($lists,[
        "id"=>$v->id,
        "no"=>$no,
        "tujuan"=>$tujuan,
        "produk"=>$produk,
        "no_pol"=>$no_pol,
        "source"=>$source,
        "source_id"=>$source=='TRIP'?$trx_trp->id:$extra_money_trx->id,
        "payment_method_id"=>$source=='TRIP'?$trx_trp->payment_method_id:$extra_money_trx->payment_method_id,
        "employee_id"=>$v->employee_id,
        "jabatan"=>$v->employee_role,
        "nama"=>$v->employee_name,
        "rek_no"=>$v->employee_rek_no,
        "rek_name"=>$v->employee_rek_name,
        "bank_code"=>$v->employee_bank_code,
        "nom_source"=>$source=='TRIP'?$v->nom_trx_trp:$v->nom_extra_money_trx,
        "nom_potongan_trxs"=>$v->nom_potongan_trxs,
        "nom_final"=>$v->nom_final,
        "status"=>$v->status,
        "failed_reason"=>$v->failed_reason,
      ]);
    }
    $data=[
      'id'          => $model_query->id,
      'details'     => $lists,
      'created_at'  => $model_query->created_at,
      'updated_at'  => $model_query->updated_at,
      'batch_no'    => $model_query->batch_no,
      'status'      => $model_query->status,
      'steps'       => $model_query->steps,
      'val'         => $model_query->val,
      'val_user'    => $model_query->val_user ?? "",
      'val_by'      => $model_query->val_by ? new IsUserResource($model_query->val_by) : '',
      'val_at'      => $model_query->val_at ?? "",
      'pabrik'      => env('app_name')
    ];

    // if($for_gen){
    //   $data['filename'] = $model_query->filename;
    // }
    return response()->json([
      "data" => $data,
    ], 200);
  }

  public function validateTrxTrps($details_in){
    $rules = [
      'details'                          => 'required|array',
      'details.*.id'             => 'required|exists:\App\Models\MySql\TrxTrp',
    ];

    $messages = [
      'details.required'  => 'Item harus di isi',
      'details.array'     => 'Format Pengambilan Barang Salah',
    ];

    // // Replace :index with the actual index value in the custom error messages
    foreach ($details_in as $index => $msg) {
      $messages["details.{$index}.id.required"]          = "Baris #" . ($index + 1) . ". ID Transaksi yang diminta tidak boleh kosong.";
      $messages["details.{$index}.id.exists"]            = "Baris #" . ($index + 1) . ". ID Transaksi yang diminta harus dipilih";
    }

    $validator = Validator::make(['details' => $details_in], $rules, $messages);

    // Check if validation fails
    if ($validator->fails()) {
      foreach ($validator->messages()->all() as $k => $v) {
        throw new MyException(["message" => $v], 400);
      }
    }
  }

  public function validateExtraMoneyTrxs($details_in){
    $rules = [
      'details'                          => 'required|array',
      'details.*.id'             => 'required|exists:\App\Models\MySql\ExtraMoneyTrx',
    ];

    $messages = [
      'details.required'  => 'Item harus di isi',
      'details.array'     => 'Format Pengambilan Barang Salah',
    ];

    // // Replace :index with the actual index value in the custom error messages
    foreach ($details_in as $index => $msg) {
      $messages["details.{$index}.id.required"]          = "Baris #" . ($index + 1) . ". ID Transaksi yang diminta tidak boleh kosong.";
      $messages["details.{$index}.id.exists"]            = "Baris #" . ($index + 1) . ". ID Transaksi yang diminta harus dipilih";
    }

    $validator = Validator::make(['details' => $details_in], $rules, $messages);

    // Check if validation fails
    if ($validator->fails()) {
      foreach ($validator->messages()->all() as $k => $v) {
        throw new MyException(["message" => $v], 400);
      }
    }
  }

  public $step_default = [
    "EMPTY"=>-1,
    "READY"=>-1,
    "SEND"=>-1,
    "FAILED"=>-1,
    "SUCCESS"=>-1,
    "SET_BATCH"=>-1,
    "SET_PAID_DONE"=>-1,
  ];
 
  public function storeTrxTrp(FinPaymentReq2Request $request)
  {
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.create');

    $trx_trp_in = json_decode($request->trx_trps, true);
    $this->validateTrxTrps($trx_trp_in);

    $rollback_id = -1;
    DB::beginTransaction();
    try {
      if(FinPaymentReq2::where('val',0)->first())
      throw new \Exception("Ada Data Yang Belum Divalidasi",1);

      $steps = $this->step_default;

      $t_stamp = date("Y-m-d H:i:s");

      $model_query                  = new FinPaymentReq2();      
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->save();
      $rollback_id = $model_query->id - 1;

      
      $trx_trp_ids = array_map(function($x){
        return $x["id"];
      },$trx_trp_in);


      $trx_trps = TrxTrp::where("received_payment","0")->whereIn("id",$trx_trp_ids)->whereDoesntHave('fin_payment_req_dtl_2')->lockForUpdate()->get();

      // $ordinal=0;
      $is_empty = true;
      foreach ($trx_trps as $key => $trx_trp) {
        // $trx_trp = TrxTrp::where("id",$value['id'])->first();
        $ps_result                    =PSTripSupirKernet::fn_supir_kernet_for_transfer_2($trx_trp);
        $supir_money                  =$ps_result['supir_money'];
        $kernet_money                 =$ps_result['kernet_money'];
        $supir_potongan_trx_money     =$ps_result['supir_potongan_trx_money'];
        $supir_potongan_trx_ids       =$ps_result['supir_potongan_trx_ids'];
        $kernet_potongan_trx_money    =$ps_result['kernet_potongan_trx_money'];
        $kernet_potongan_trx_ids      =$ps_result['kernet_potongan_trx_ids'];
        
        $detail                       = new FinPaymentReqDtl2();
        $detail->fin_payment_req_2_id   = $model_query->id;
        $detail->trx_trp_id           = $trx_trp->id;
        $detail->employee_role        = "SUPIR";
        $detail->employee_id          = $trx_trp->employee_s->id;
        $detail->employee_name        = $trx_trp->employee_s->name;
        $detail->employee_rek_no      = $trx_trp->employee_s->rek_no;
        $detail->employee_rek_name    = $trx_trp->employee_s->rek_name;
        $detail->employee_bank_code   = $trx_trp->employee_s->bank->code;
        $detail->nom_trx_trp          = $supir_money;
        $detail->potongan_trx_ids     = $supir_potongan_trx_ids;
        $detail->nom_potongan_trxs     = $supir_potongan_trx_money;
        $detail->nom_final            = $supir_money - $supir_potongan_trx_money;
        $detail->status               = "READY";
        
        $detail->created_at         = $t_stamp;
        $detail->created_user       = $this->admin_id;
  
        $detail->updated_at         = $t_stamp;
        $detail->updated_user       = $this->admin_id;  
      
        $detail->save();
        if($trx_trp->kernet_id){
          $detail                       = new FinPaymentReqDtl2();
          $detail->fin_payment_req_2_id   = $model_query->id;
          $detail->trx_trp_id           = $trx_trp->id;
          $detail->employee_role        = "KERNET";
          $detail->employee_id          = $trx_trp->employee_k->id;
          $detail->employee_name        = $trx_trp->employee_k->name;
          $detail->employee_rek_no      = $trx_trp->employee_k->rek_no;
          $detail->employee_rek_name    = $trx_trp->employee_k->rek_name;
          $detail->employee_bank_code   = $trx_trp->employee_k->bank->code;
          $detail->nom_trx_trp          = $kernet_money;
          $detail->potongan_trx_ids     = $kernet_potongan_trx_ids;
          $detail->nom_potongan_trxs     = $kernet_potongan_trx_money;
          $detail->nom_final            = $kernet_money - $kernet_potongan_trx_money;
          $detail->status               = "READY";
          
          $detail->created_at         = $t_stamp;
          $detail->created_user       = $this->admin_id;
    
          $detail->updated_at         = $t_stamp;
          $detail->updated_user       = $this->admin_id;  
        
          $detail->save();
        }

        $is_empty = false;
      }

      if($is_empty){
        $steps['EMPTY']=1;
        $model_query->status          = "EMPTY";
      }else{
        $steps['READY']=1;
        $steps['SEND']=0;
        $model_query->status          = "READY";
      }

      $model_query->steps = $steps;
      $model_query->save();
      MyLog::sys($this->syslog_db,$model_query->id,"insert");

      DB::commit();
      $callGet = $this->show( new FinPaymentReq2Request($model_query->toArray()));
      if ($callGet->getStatusCode() != 200) return $callGet;
      $ori = json_decode(json_encode($callGet), true)["original"];
      $data = $ori["data"];  

      return response()->json([
        "message" => "Proses tambah data berhasil",
        "data"=>$data,
        "steps"=>$steps
        // "created_at" => $t_stamp,
        // "updated_at" => $t_stamp,
        
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE fin_payment_req_2 AUTO_INCREMENT = $rollback_id");
      
      // return response()->json([
      //   "message" => $e->getMessage(),
      // ], 400);

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
        "message" => "Proses tambah data gagal",
      ], 400);
    }
  }

  public function storeExtraMoneyTrx(FinPaymentReq2Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.create');

    $extra_money_trx_in = json_decode($request->extra_money_trxs, true);
    $this->validateExtraMoneyTrxs($extra_money_trx_in);

    $rollback_id = -1;
    DB::beginTransaction();
    try {
      if(FinPaymentReq2::where('val',0)->first())
      throw new \Exception("Ada Data Yang Belum Divalidasi",1);

      $steps = $this->step_default;

      $t_stamp = date("Y-m-d H:i:s");

      $model_query                  = new FinPaymentReq2();      
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->save();
      $rollback_id = $model_query->id - 1;


      $extra_money_trx_ids = array_map(function($x){
        return $x["id"];
      },$extra_money_trx_in);

      $extra_money_trxs = ExtraMoneyTrx::where("received_payment","0")->whereIn("id",$extra_money_trx_ids)->whereDoesntHave('fin_payment_req_dtl_2')->lockForUpdate()->get();

      // $ordinal=0;
      $is_empty = true;   

      foreach ($extra_money_trxs as $key => $emt) {        
        $dmoney = $emt->extra_money->nominal * $emt->extra_money->qty;

        $detail                       = new FinPaymentReqDtl2();
        $detail->fin_payment_req_2_id   = $model_query->id;
        $detail->extra_money_trx_id   = $emt->id;
        $detail->employee_role        = $emt->employee->role;
        $detail->employee_id          = $emt->employee->id;
        $detail->employee_name        = $emt->employee->name;
        $detail->employee_rek_no      = $emt->employee->rek_no;
        $detail->employee_rek_name    = $emt->employee->rek_name;
        $detail->employee_bank_code   = $emt->employee->bank->code;
        $detail->nom_extra_money_trx  = $dmoney;
        $detail->nom_final            = $dmoney;
        $detail->status               = "READY";
        
        $detail->created_at         = $t_stamp;
        $detail->created_user       = $this->admin_id;
  
        $detail->updated_at         = $t_stamp;
        $detail->updated_user       = $this->admin_id;  
      
        $detail->save();

        $is_empty = false;
      }

      if($is_empty){
        $steps['EMPTY']=1;
        $model_query->status          = "EMPTY";
      }else{
        $steps['READY']=1;
        $steps['SEND']=0;
        $model_query->status          = "READY";
      }

      $model_query->steps = $steps;
      $model_query->save();
      MyLog::sys($this->syslog_db,$model_query->id,"insert");

      DB::commit();
      $callGet = $this->show( new FinPaymentReq2Request($model_query->toArray()));
      if ($callGet->getStatusCode() != 200) return $callGet;
      $ori = json_decode(json_encode($callGet), true)["original"];
      $data = $ori["data"];  

      return response()->json([
        "message" => "Proses tambah data berhasil",
        "data"=>$data,
        "steps"=>$steps
        // "created_at" => $t_stamp,
        // "updated_at" => $t_stamp,
        
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE fin_payment_req_2 AUTO_INCREMENT = $rollback_id");
      
      // return response()->json([
      //   "message" => $e->getMessage(),
      // ], 400);

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

  public function updateTrxTrp(FinPaymentReq2Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.modify');
    
    $trx_trp_in = json_decode($request->trx_trps, true);
    $this->validateTrxTrps($trx_trp_in);

    $t_stamp = date("Y-m-d H:i:s");

    DB::beginTransaction();
    try {
      $SYSNOTES=[];
      $model_query             = FinPaymentReq2::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD                  = clone($model_query);

      $checkDetails = FinPaymentReqDtl2::where("fin_payment_req_2_id",$model_query->id)->pluck('status')->toArray();

      if(count($checkDetails)>0 && !in_array('READY',$checkDetails)){
        throw new \Exception("Data Sudah Tidak Dapat Ditambahkan",1);
      }
      // $t_stamp = date("Y-m-d H:i:s");
      
      // $date = new \DateTime();
      // $model_query->filename        = env('MCM_ID').'14'.$date->format("Ymd").$date->format("His");
      
      $trx_trp_ids = array_map(function($x){
        return $x["id"];
      },$trx_trp_in);

      $trx_trps = TrxTrp::where("received_payment","0")->whereIn("id",$trx_trp_ids)->whereDoesntHave('fin_payment_req_dtl_2')->lockForUpdate()->get();

      // $ordinal=0;
      $is_empty = true;
      foreach ($trx_trps as $key => $trx_trp) {
        // $trx_trp = TrxTrp::where("id",$value['id'])->first();
        $ps_result                    =PSTripSupirKernet::fn_supir_kernet_for_transfer_2($trx_trp);
        $supir_money                  =$ps_result['supir_money'];
        $kernet_money                 =$ps_result['kernet_money'];
        $supir_potongan_trx_money     =$ps_result['supir_potongan_trx_money'];
        $supir_potongan_trx_ids       =$ps_result['supir_potongan_trx_ids'];
        $kernet_potongan_trx_money    =$ps_result['kernet_potongan_trx_money'];
        $kernet_potongan_trx_ids      =$ps_result['kernet_potongan_trx_ids'];

        $detail                       = new FinPaymentReqDtl2();
        $detail->fin_payment_req_2_id = $model_query->id;
        $detail->trx_trp_id           = $trx_trp->id;
        $detail->employee_role        = "SUPIR";
        $detail->employee_id          = $trx_trp->employee_s->id;
        $detail->employee_name        = $trx_trp->employee_s->name;
        $detail->employee_rek_no      = $trx_trp->employee_s->rek_no;
        $detail->employee_rek_name    = $trx_trp->employee_s->rek_name;
        $detail->employee_bank_code   = $trx_trp->employee_s->bank->code;
        $detail->nom_trx_trp          = $supir_money;
        $detail->potongan_trx_ids     = $supir_potongan_trx_ids;
        $detail->nom_potongan_trxs    = $supir_potongan_trx_money;
        $detail->nom_final            = $supir_money - $supir_potongan_trx_money;
        $detail->status               = "READY";
        
        $detail->created_at         = $t_stamp;
        $detail->created_user       = $this->admin_id;
  
        $detail->updated_at         = $t_stamp;
        $detail->updated_user       = $this->admin_id;  
      
        $detail->save();
        if($trx_trp->kernet_id){
          $detail                       = new FinPaymentReqDtl2();
          $detail->fin_payment_req_2_id   = $model_query->id;
          $detail->trx_trp_id           = $trx_trp->id;
          $detail->employee_role        = "KERNET";
          $detail->employee_id          = $trx_trp->employee_k->id;
          $detail->employee_name        = $trx_trp->employee_k->name;
          $detail->employee_rek_no      = $trx_trp->employee_k->rek_no;
          $detail->employee_rek_name    = $trx_trp->employee_k->rek_name;
          $detail->employee_bank_code   = $trx_trp->employee_k->bank->code;
          $detail->nom_trx_trp          = $kernet_money;
          $detail->potongan_trx_ids     = $kernet_potongan_trx_ids;
          $detail->nom_potongan_trxs     = $kernet_potongan_trx_money;
          $detail->nom_final            = $kernet_money - $kernet_potongan_trx_money;
          $detail->status               = "READY";
          
          $detail->created_at         = $t_stamp;
          $detail->created_user       = $this->admin_id;
    
          $detail->updated_at         = $t_stamp;
          $detail->updated_user       = $this->admin_id;  
        
          $detail->save();
        }
        $is_empty = false;
      }

      $steps = $this->step_default;
      if($is_empty){
        $steps['EMPTY']=1;
        $model_query->status          = "EMPTY";
      }else{
        $steps['READY']=1;
        $steps['SEND']=0;
        $model_query->status          = "READY";
      }
      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->steps = $steps;
      $model_query->save();
      MyLog::sys($this->syslog_db,$model_query->id,"insert");
      // $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      // array_unshift( $SYSNOTES , $SYSNOTE );            
      // MyLog::sys($this->syslog_db,$request->id,"update",implode("\n",$SYSNOTES));

      DB::commit();

      $callGet = $this->show( new FinPaymentReq2Request($model_query->toArray()));
      if ($callGet->getStatusCode() != 200) return $callGet;
      $ori = json_decode(json_encode($callGet), true)["original"];
      $data = $ori["data"];


      return response()->json([
        "message" => "Proses ubah data berhasil",
        // "updated_at"=>$t_stamp,
        "data"=>$data,
        // "steps"=>$steps,
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

  public function updateExtraMoneyTrx(FinPaymentReq2Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.modify');
    
    $extra_money_trx_in = json_decode($request->extra_money_trxs, true);
    $this->validateExtraMoneyTrxs($extra_money_trx_in);

    $t_stamp = date("Y-m-d H:i:s");

    DB::beginTransaction();
    try {
      $SYSNOTES=[];
      $model_query             = FinPaymentReq2::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD                  = clone($model_query);

      $checkDetails = FinPaymentReqDtl2::where("fin_payment_req_2_id",$model_query->id)->pluck('status')->toArray();

      if(count($checkDetails)>0 && !in_array('READY',$checkDetails)){
        throw new \Exception("Data Sudah Tidak Dapat Ditambahkan",1);
      }
      // $t_stamp = date("Y-m-d H:i:s");
      
      // $date = new \DateTime();
      // $model_query->filename        = env('MCM_ID').'14'.$date->format("Ymd").$date->format("His");
      
      $extra_money_trx_ids = array_map(function($x){
        return $x["id"];
      },$extra_money_trx_in);

      $extra_money_trxs = ExtraMoneyTrx::where("received_payment","0")->whereIn("id",$extra_money_trx_ids)->whereDoesntHave('fin_payment_req_dtl_2')->lockForUpdate()->get();

      // $ordinal=0;
      $is_empty = true;
      foreach ($extra_money_trxs as $key => $emt) {        
        $dmoney = $emt->extra_money->nominal * $emt->extra_money->qty;

        $detail                       = new FinPaymentReqDtl2();
        $detail->fin_payment_req_2_id = $model_query->id;
        $detail->extra_money_trx_id   = $emt->id;
        $detail->employee_role        = $emt->employee->role;
        $detail->employee_id          = $emt->employee->id;
        $detail->employee_name        = $emt->employee->name;
        $detail->employee_rek_no      = $emt->employee->rek_no;
        $detail->employee_rek_name    = $emt->employee->rek_name;
        $detail->employee_bank_code   = $emt->employee->bank->code;
        $detail->nom_extra_money_trx  = $dmoney;
        $detail->nom_final            = $dmoney;
        $detail->status               = "READY";
        
        $detail->created_at         = $t_stamp;
        $detail->created_user       = $this->admin_id;
  
        $detail->updated_at         = $t_stamp;
        $detail->updated_user       = $this->admin_id;  
      
        $detail->save();

        $is_empty = false;
      }

      $steps = $this->step_default;
      if($is_empty){
        $steps['EMPTY']=1;
        $model_query->status          = "EMPTY";
      }else{
        $steps['READY']=1;
        $steps['SEND']=0;
        $model_query->status          = "READY";
      }
      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->steps = $steps;
      $model_query->save();
      MyLog::sys($this->syslog_db,$model_query->id,"insert");
      // $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      // array_unshift( $SYSNOTES , $SYSNOTE );            
      // MyLog::sys($this->syslog_db,$request->id,"update",implode("\n",$SYSNOTES));

      DB::commit();

      $callGet = $this->show( new FinPaymentReq2Request($model_query->toArray()));
      if ($callGet->getStatusCode() != 200) return $callGet;
      $ori = json_decode(json_encode($callGet), true)["original"];
      $data = $ori["data"];


      return response()->json([
        "message" => "Proses ubah data berhasil",
        // "updated_at"=>$t_stamp,
        "data"=>$data,
        // "steps"=>$steps,
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

  public function validasi(Request $request){
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.val');
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\FinPaymentReq2,id",
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
      $model_query = FinPaymentReq2::with('details')->find($request->id);
      if($model_query->status != 'CLOSE'){
        throw new \Exception("Selesaikan dulu Transaksi nya sebelum validasi",1);
      }
     
      // $fin_payment_req_details = $model_query->details->toArray();
      
      // $trx_trp_ids = array_map(function($x){
      //   return $x['trx_trp_id'];
      // },$fin_payment_req_details);

      // $trx_trps = TrxTrp::where("fin_status","P")->whereIn("id",$trx_trp_ids)->lockForUpdate()->get();
      // foreach ($trx_trps as $key => $value) {
      //   $value->fin_status = 'V';
      //   $value->save();
      // }

      $model_query->val = 1;
      $model_query->val_user = $this->admin_id;
      $model_query->val_at = $t_stamp;
      $model_query->save();

      MyLog::sys($this->syslog_db,$request->id,"approve");

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


  public function get_trx_trp_unprocessed(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['fin_payment_req.create','fin_payment_req.modify']);
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    try {
      $model_query = TrxTrp::where("deleted",0)->where("req_deleted",0)
      ->where('val',1)->where('val1',1)->where('val2',1)->where('val4',1)->where('val5',1)->Where('val6',1)
      ->where(function ($q){
        $q->where(function ($q1){
          $q1->whereNotIn('jenis',['CPO','PK','CANGKANG']);
        });
        $q->orWhere(function ($q1){
          $q1->whereIn('jenis',['CPO','PK','CANGKANG'])->where('val3',1);
        });
      })
      ->where('received_payment',0)->whereIn("payment_method_id",[4,5])
      ->whereDoesntHave('fin_payment_req_dtl_2')
      ->with('uj')->get();

      $data=[];
      foreach ($model_query as $mqk => $mqv) {
        array_push($data,[
          "id"=>$mqv->id,
          "no_pol"=>$mqv->no_pol,
          "xto"=>$mqv->xto,
          "info"=>$mqv->uj->asst_opt,
          "tipe"=>$mqv->tipe,
          "jenis"=>$mqv->jenis,
          "amount"=>$mqv->amount,
          "supir"=>$mqv->supir,
          "kernet"=>$mqv->kernet,
          "mqv"=>$mqv
        ]);
      }

      return response()->json([
        "data" => $data,
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


  public function get_extra_money_trx_unprocessed(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['fin_payment_req.create','fin_payment_req.modify']);
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    try {
      $model_query = ExtraMoneyTrx::where("deleted",0)->where("req_deleted",0)
      ->where('val1',1)->where('val2',1)->where('val3',1)->where('val4',1)->where('val5',1)->Where('val6',1)
      ->where('received_payment',0)->whereIn("payment_method_id",[4,5])
      ->whereDoesntHave('fin_payment_req_dtl_2')
      ->with(['prev_trx_trp'=>function($q){
        $q->with('uj');
      },'extra_money'])
      ->get();

      $data=[];
      foreach ($model_query as $mqk => $mqv) {
        array_push($data,[
          "id"=>$mqv->id,
          "no_pol"=>$mqv->prev_trx_trp->no_pol,
          "xto"=>$mqv->prev_trx_trp->xto,
          "info"=>$mqv->prev_trx_trp->uj->asst_opt,
          "tipe"=>$mqv->prev_trx_trp->tipe,
          "jenis"=>$mqv->prev_trx_trp->jenis,
          "amount"=>$mqv->extra_money->nominal * $mqv->extra_money->qty,
          "employee_name"=>$mqv->employee->name,
        ]);
      }

      return response()->json([
        "data" => $data,
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

  public function excelDownload(Request $request){
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.download');
    
    set_time_limit(0);
    $callGet = $this->show( new FinPaymentReq2Request($request->toArray()));
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    $data = $ori["data"];  
    
    // $newDetails = [];

    // foreach ($ori["data"] as $key => $value) {
    //   $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
    //   array_push($newDetails,$value);
    // }

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-payment_request';

    $mime=MyLib::mime("xlsx");
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$data],'excel.fin_payment_req'), $mime["exportType"]));

    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }
  

  public function generateCSVMandiriAndSend(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['fin_payment_req.create','fin_payment_req.modify']);
    set_time_limit(0);
    $date = new \DateTime();
    // $filename=$date->format("YmdHis").'-'.env('APP_NAME');

    $date = new \DateTime();
    $filename = env('MCM_ID').'14'.env('MCM_LOCAL_ID').'01'.$date->format("ymd").$date->format("Hi");

    $t_stamp = date("Y-m-d H:i:s");

    set_time_limit(0);
    $callGet = $this->show( new FinPaymentReq2Request($request->toArray()),true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    $raw_data = $ori["data"];  

    
    DB::beginTransaction();
    try {
      $data = $raw_data;

      $checkDetails = FinPaymentReqDtl2::where("fin_payment_req_2_id",$data['id'])->pluck('status')->toArray();

      if(!in_array('READY',$checkDetails)){
        throw new \Exception("Data Tidak Dapat Dikirim",1);
      }  
      
      // $csv_data = "";
      // $records = count($data['details']);


      $mandiri_bank_no =Setup::first()->mandiri_no_rek;
      $trfMandiri = new TrfMandiri($mandiri_bank_no);

      foreach ($raw_data['details'] as $k => $v) {
        $jumlah = (int) $v['nom_final'];
        if($jumlah == 0) continue;
        $payment_method_id = $v['payment_method_id'];
        $jenis_rek = $v['bank_code']=='Mandiri'?'IBU':($payment_method_id==4?'BAU':'OBU');
        $dbank = Bank::where('code',$v['bank_code'])->first();
        $code_beda_bank = $dbank->code_duitku;
        $ket = ($v['source']=='TRIP'?'':'EM').("#".$v['source_id']);
        
        $trfMandiri->addRow($v['rek_no'],$v['rek_name'],$jumlah,$jenis_rek,$code_beda_bank,$ket);
      }

      $trfMandiri->generateBody();
      $trfMandiri->generateHead();
  
      $mime = ["ext"=>'txt'];
  
      $filePath = 'public/' .$filename . '.' . $mime["ext"];
      Storage::put($filePath, $trfMandiri->getCSV());
      $remotePath = "Upload/Users/MCM_BatchUpload/".$filename. '.' . $mime["ext"];
            
      try {
        Storage::disk('ftp')->put($remotePath,Storage::get($filePath));

          // Storage::disk('ftp')->put($remotePath, file_get_contents(Storage::get($file)));
          // MyLog::logging("kirim berhasil","kirim");
      } catch (\Exception $e) {
          // Handle the exception
        MyLog::logging($e->getMessage(),"kirim gagal");
        throw new \Exception("Data Gagal Dikirim",1);
      }

      $finpaymentreq2=FinPaymentReq2::where('id',$data['id'])->first();
      $finpaymentreq2->filename = $filename;

      $steps = $finpaymentreq2->steps;
      
      $steps['SEND'] = 1;
      $steps['FAILED'] = 0;
      $steps['SUCCESS'] = 0;

      $finpaymentreq2->status          = "SEND";
      $finpaymentreq2->updated_at      = $t_stamp;
      $finpaymentreq2->updated_user    = $this->admin_id;
      $finpaymentreq2->steps = $steps;
      $finpaymentreq2->save();

      FinPaymentReqDtl2::where('fin_payment_req_2_id',$data['id'])->update([
        "status"=>"INQUIRY_PROCESS"
      ]);

      MyLog::sys($this->syslog_db,$data['id'],"update details","update status to INQUIRY_PROCESS");
      
      DB::commit();

      return response()->json([
        "message" => "Proses Generate Berhasil",
        "steps" => $steps
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
        "message" => "Proses Generate gagal",
      ], 400);
    }

  }

  
  public function getUpdate(Request $request){
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    MyAdmin::checkMultiScope($this->permissions, ['fin_payment_req.create','fin_payment_req.modify']);
    $rules = [
      'id' => "required|exists:\App\Models\MySql\FinPaymentReq2,id",
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
      
      $model_query = FinPaymentReq2::with('details')->find($request->id);

      $checkDetails = FinPaymentReqDtl2::where("fin_payment_req_2_id",$request->id)->pluck('status')->toArray();

      //masih perlu di telusuri
      if(!in_array('INQUIRY_PROCESS',$checkDetails)){
        throw new \Exception("Data Sudah Tidak Dapat Ditambahkan",1);
      }

      $getFtp = Storage::disk('ftp')->get('Downloads/'.$model_query->filename.'.txt.nack');
      
      if($getFtp==null){
        $getFtp = Storage::disk('ftp')->get('Downloads/'.$model_query->filename.'.txt.ack');
        if($getFtp==null){
          throw new \Exception("Data Belum Siap Untuk Diambil",1);
        }
      }

      $lines = explode("\r\n", trim($getFtp));
      $result = [];

      foreach ($lines as $index => $line) {
          // Skip baris pertama (header) dan baris kosong
          if ($index === 0 || empty(trim($line))) {
              continue;
          }
          
          $fields = explode(';', $line);
          
          // Pastikan ada cukup field sebelum mengakses
          if (count($fields) >= 43) {
              $no = $fields[0];
              $nama = $fields[1];
              $status = $fields[42]; // Status ada di index 24
              $reason = $fields[43];
              
              array_push($result,[
                'no' => $no,
                'nama' => $nama,
                'status' => $status,
                'reason' => $reason
              ]);
          }
      }
      $nos = array_map(function ($o){
        return $o['no']; 
      },$result);

      $fin_payment_req_details = $model_query->details;
      $r_data = [];
      $SYSNOTES = [];

      $had_failed = 0;
      foreach ($fin_payment_req_details as $k => $v) {
        $SYSOLD                     = clone($model_query);

        $index = array_search($v->employee_rek_no,$nos);
        if($index===false){
          // $st="INQUIRY_FAILED";
          // $stm = "NOT REGISTERED";
          // $had_failed++;
          $st = "READY";
          $stm = null;
        }else{
          if($result[$index]['status']=="SUCCESS"){
            $st = "READY";
            $stm = null;
          }else{
            $st = 'INQUIRY_FAILED';
            $stm = $result[$index]['reason'];
            $had_failed++;
          }
        }

        $v->status             = $st;
        $v->failed_reason      = $stm;
        $v->updated_at         = $t_stamp;
        $v->updated_user       = $this->admin_id;
        $v->save();
        $SYSNOTE = MyLib::compareChange($SYSOLD,$v);
        array_push($r_data,$v);
        array_push($SYSNOTES,$SYSNOTE);
      }

      // foreach ($fin_payment_req_details as $k => $v) {
      //   $SYSOLD                     = clone($model_query);


      //   // $st = 'INQUIRY_FAILED';
      //   // $stm = "error test";
      //   // $had_failed++;

      //   $st = "READY";
      //   $stm = null;

      //   $v->status             = $st;
      //   $v->failed_reason      = $stm;
      //   $v->updated_at         = $t_stamp;
      //   $v->updated_user       = $this->admin_id;
      //   $v->save();

      //   array_push($r_data,$v);

      // }

      $steps = $model_query->steps;

      if($had_failed==0){
        FinPaymentReqDtl2::where('fin_payment_req_2_id',$request->id)
        ->update([
          "status"=>"TRANSFER_PROCESS"
        ]);

        $steps['FAILED']=-1;
        $steps['SUCCESS']=1;
        $steps['SET_BATCH']=0;
        $model_query->steps=$steps;
        $model_query->status="SUCCESS";
        $model_query->wait_at=$t_stamp;

        $r_data = array_map(function ($x) {
          $x->status = 'TRANSFER_PROCESS';
          return $x;
        },$r_data);

        MyLog::sys("fin_payment_req_dtl_2",$request->id,"update details","update details with fin_payment_req_2_id = '".$request->id."' to TRANSFER_PROCESS");
        MyLog::sys($this->syslog_db,$request->id,"update master","update status to CLOSE");
      }
      else{
        $steps['FAILED']=1;
        $steps['SUCCESS']=-1;
        $model_query->steps=$steps;
        $model_query->status="FAILED";
      }
      
      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->save();

      
      MyLog::sys("fin_payment_req_dtl_2",null,"update status",implode(",",$SYSNOTES));
      
      DB::commit();
      return response()->json([
        "message" => "Proses cek status berhasil",
        "details" => $r_data,
        "status"  => $model_query->status,
        "steps" => $steps
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

  public function setToReady(Request $request){
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    MyAdmin::checkMultiScope($this->permissions, ['fin_payment_req.create','fin_payment_req.modify']);
    $rules = [
      'id' => "required|exists:\App\Models\MySql\FinPaymentReq2,id",
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

      $model_query            = FinPaymentReq2::with(['details'=>function ($q){ $q->with('trx_trp','extra_money_trx');}])
      ->where("id",$request->id)->lockForUpdate()->first();

      $datediff = MyLib::dateDiff($model_query->updated_at,$t_stamp);
      if($datediff['minutes'] < 10){
        throw new \Exception("Tunggu ".(10 - $datediff['minutes'])." menit lagi untuk set ke READY",1);
      }

      if($model_query->status!=='SEND'){
        throw new \Exception("Data Tidak Dapat Diset ke READY",1);
      }
            
      $SYSNOTES1=[];
      $steps = $this->step_default;

      
      foreach ($model_query->details as $k => $v) {
        $SYSOLD_dtl            = clone($v);
        $v->status             = 'READY';
        $v->updated_at         = $t_stamp;
        $v->updated_user       = $this->admin_id;
        $v->save();
        $SYSNOTE1 = MyLib::compareChange($SYSOLD_dtl,$v);
        array_unshift( $SYSNOTES1 , $SYSNOTE1 ); 
      }

      if(count($model_query->details)==0){
        $steps['EMPTY']=1;
        $model_query->status          = "EMPTY";
      }else{
        $steps['READY']=1;
        $steps['SEND']=0;
        $model_query->status          = "READY";
      }
      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->steps = $steps;
      $model_query->save();

      MyLog::sys($this->syslog_db,$request->id,"update detail",implode("\n",$SYSNOTES1));

      DB::commit();
      return response()->json([
        "message" => "Proses SET TO READY berhasil",
        "steps"=>$steps,
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

  public function renewData(Request $request){
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    MyAdmin::checkMultiScope($this->permissions, ['fin_payment_req.create','fin_payment_req.modify']);
    $rules = [
      'detail_id' => "required|exists:\App\Models\MySql\FinPaymentReqDtl2,id",
    ];

    $messages = [
      'detail_id.required' => 'ID tidak boleh kosong',
      'detail_id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $SYSNOTES = [];

      $model_query = FinPaymentReqDtl2::with(['trx_trp','extra_money_trx','employee'])->lockForUpdate()->where("id",$request->detail_id)->first();
      $SYSOLD                     = clone($model_query);

      if($model_query->status!='INQUIRY_FAILED'){
        throw new \Exception("Data Tidak Dapat Diperbaharui",1);
      }

      $model_query->employee_rek_no = $model_query->employee->rek_no;
      $model_query->employee_rek_name = $model_query->employee->rek_name;
      $model_query->employee_bank_code = $model_query->employee->bank->code;
      
      if($model_query->trx_trp){
        $SYSOLD1                     = clone($model_query->trx_trp);
        if($model_query->employee_role=="SUPIR"){
  
          $model_query->trx_trp->supir_rek_no = $model_query->employee_rek_no;
          $model_query->trx_trp->supir_rek_name = $model_query->employee_rek_name;
        }else{
          $model_query->trx_trp->kernet_rek_no = $model_query->employee_rek_no;
          $model_query->trx_trp->kernet_rek_name = $model_query->employee_rek_name;
        }
        $model_query->trx_trp->updated_at         = $t_stamp;
        $model_query->trx_trp->updated_user       = $this->admin_id;
        $model_query->trx_trp->save(); 
        $SYSNOTE1 = MyLib::compareChange($SYSOLD1,$model_query->trx_trp);
        array_push($SYSNOTES,"Trx Trp:".$SYSNOTE1);
      }else{
        $SYSOLD2                     = clone($model_query->extra_money_trx);
        $model_query->extra_money_trx->employee_rek_no    = $model_query->employee_rek_no;
        $model_query->extra_money_trx->employee_rek_name  = $model_query->employee_rek_name;
        $model_query->extra_money_trx->updated_at         = $t_stamp;
        $model_query->extra_money_trx->updated_user       = $this->admin_id;    
        $model_query->extra_money_trx->save();    
        $SYSNOTE2 = MyLib::compareChange($SYSOLD2,$model_query->extra_money_trx);
        array_push($SYSNOTES,"Extra Money Trx:".$SYSNOTE2);
      }
      $model_query->updated_at        = $t_stamp;
      $model_query->updated_user      = $this->admin_id;  
      $model_query->status            = 'READY';
      $model_query->failed_reason     = '';
      $model_query->save(); 
      
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      array_unshift($SYSNOTES,"Detail:".$SYSNOTE);

      $fpr_id = $model_query->fin_payment_req_2_id;
      $fprds=FinPaymentReqDtl2::where('fin_payment_req_2_id',$fpr_id)->get()->pluck('status')->toArray();
      $fpr = FinPaymentReq2::where("id",$fpr_id)->first();

      $steps=$fpr->steps;

      if(count($fprds)>0 && count(array_filter($fprds,fn($x)=>$x=='READY'))==count($fprds)){
        $steps=$this->step_default;
        $steps['READY']=1;
        $steps['SEND']=0;
        $fpr->status='READY';
        $fpr->steps=$steps;
        $fpr->updated_at      = $t_stamp;
        $fpr->updated_user    = $this->admin_id;
        $fpr->save();
      }
      
      MyLog::sys("fin_payment_req_dtl_2",null,"renew data",implode(",",$SYSNOTES));

      DB::commit();
      return response()->json([
        "message" => "Proses update data berhasil",
        "employee_rek_no"=>$model_query->employee_rek_no,
        "employee_rek_name"=>$model_query->employee_rek_name,
        "employee_bank_code"=>$model_query->employee_bank_code,
        "status"=>$model_query->status,
        "failed_reason"=>$model_query->failed_reason,
        "steps"=>$steps,

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

  public function deleteData(Request $request){
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    MyAdmin::checkMultiScope($this->permissions, ['fin_payment_req.create','fin_payment_req.modify']);
    $rules = [
      'trx_trp_id' => "nullable|exists:\App\Models\MySql\FinPaymentReqDtl2,trx_trp_id",
      'extra_money_trx_id' => "nullable|exists:\App\Models\MySql\FinPaymentReqDtl2,extra_money_trx_id",
    ];

    $messages = [
      'trx_trp_id.required' => 'Trx Trp ID tidak boleh kosong',
      'trx_trp_id.exists' => 'Trx Trp ID tidak terdaftar',

      'extra_money_trx_id.required' => 'Extra Money Trx ID tidak boleh kosong',
      'extra_money_trx_id.exists' => 'Extra Money Trx ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {

      $trx_trp_id = $request->trx_trp_id; 
      $extra_money_trx_id = $request->extra_money_trx_id; 

      $model_query = FinPaymentReqDtl2::with(['fin_payment_req2'=>function ($q){
        $q->where("status","READY");
      }]);

      if($trx_trp_id){
        $model_query=$model_query->where("trx_trp_id",$trx_trp_id);
      }else{
        $model_query=$model_query->where("extra_money_trx_id",$extra_money_trx_id);
      }

      $model_query=$model_query->lockForUpdate()->first();

      $fin_payment_req_2_id = $model_query->fin_payment_req_2_id;
      $id = $model_query->id;
      
      $pluck = $model_query->pluck("status")->toArray();
      
      if(count($pluck)==0){
        throw new \Exception("Data Sudah Tidak Dapat Di Hapus",1);
      }

      if(!(in_array("READY",$pluck) || in_array("INQUIRY_FAILED",$pluck))){
        throw new \Exception("Data Tidak Diizinkan untuk di Hapus".json_encode($pluck),1);
      }

      // FinPaymentReqDtl2::with(['trx_trp','employee','fin_payment_req'=>function ($q){
      //   $q->where("status","READY");
      // }]);
      
      // if($trx_trp_id){
      //   $text="";
      //   $model_query=$model_query->where("trx_trp_id",$trx_trp_id);
      // }else{
      //   $text="";
      //   $model_query=$model_query->where("extra_money_trx_id",$extra_money_trx_id);
      // }
      $model_query->delete();

      $fprds = FinPaymentReqDtl2::where('fin_payment_req_2_id',$fin_payment_req_2_id)->get()->pluck('status')->toArray();
      $fpr = FinPaymentReq2::where('id',$fin_payment_req_2_id)->first();
      $steps = $fpr->steps;

      if(count($fprds)>0 && count(array_filter($fprds,fn($x)=>$x=='READY'))==count($fprds)){
        $steps=$this->step_default;
        $steps['READY']=1;
        $steps['SEND']=0;
        $fpr->status='READY';
        $fpr->steps=$steps;
        $fpr->updated_at      = $t_stamp;
        $fpr->updated_user    = $this->admin_id;
        $fpr->save();
      }else if(count($fprds)==0 ){
        $steps=$this->step_default;
        $steps['EMPTY']=1;
        $fpr->status='EMPTY';
        $fpr->steps=$steps;
        $fpr->updated_at      = $t_stamp;
        $fpr->updated_user    = $this->admin_id;
        $fpr->save();
      }

      MyLog::sys("fin_payment_req_dtl_2",null,"delete data","delete data which id:".$id);
      DB::commit();
      return response()->json([
        "message" => "Proses delete data berhasil",
        "steps"=>$steps,
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

  public function setBatchNo(Request $request){
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    MyAdmin::checkMultiScope($this->permissions, ['fin_payment_req.create','fin_payment_req.modify']);
    $rules = [
      'id' => "required|exists:\App\Models\MySql\FinPaymentReq2,id",
      'batch_no' => "required",
    ];

    $messages = [
      'id.required' => 'ID tidak boleh kosong',
      'id.exists' => 'ID tidak terdaftar',
      'batch_no.required' => 'No Batch tidak boleh kosong',
      // 'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {

      $model_query                = FinPaymentReq2::where("id",$request->id)->lockForUpdate()->first();
      
      if($model_query->status=='CLOSE'){
        throw new \Exception("Data Sudah Tidak Dapat Diset",1);
      }
      
      $SYSOLD                     = clone($model_query);
      $steps                      = $model_query->steps;
      $steps['SET_BATCH']         = 1;
      $steps['SET_PAID_DONE']     = 0;

      $model_query->steps         = $steps;
      $model_query->status        = "SET BATCH";
      $model_query->batch_no      = $request->batch_no;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;  
      $model_query->save(); 
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      MyLog::sys("fin_payment_req_dtl_2",null,"renew data",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses Set No Batch berhasil",
        "steps"   => $steps
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

  public function setPaidDone(Request $request){
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    MyAdmin::checkMultiScope($this->permissions, ['fin_payment_req.create','fin_payment_req.modify']);
    $rules = [
      'id' => "required|exists:\App\Models\MySql\FinPaymentReq2,id",
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

      $model_query            = FinPaymentReq2::with(['details'=>function ($q){ $q->with(['trx_trp','extra_money_trx']);}])
      ->where("id",$request->id)->lockForUpdate()->first();

      if($model_query->status=='CLOSE'){
        throw new \Exception("Data Sudah Tidak Dapat Diset",1);
      }

      if($model_query->batch_no==0){
        throw new \Exception("Sebelum validasi pastikan no batch sudah benar",1);
      }

      $SYSOLD                 = clone($model_query);
            
      $SYSNOTES1=[];
      $SYSNOTES2=[];
      $SYSNOTES3=[];
      foreach ($model_query->details as $k => $v) {
        $SYSOLD1            = clone($v);
        $v->status             = 'DONE';
        $v->created_at         = $t_stamp;
        $v->created_user       = $this->admin_id;
        $v->save();
        $SYSNOTE1 = MyLib::compareChange($SYSOLD1,$v);
        array_unshift( $SYSNOTES1 , $SYSNOTE1 ); 
        if($v->trx_trp){
          $SYSOLD2                 = clone($v->trx_trp);
          $v->trx_trp->received_payment = 1;
          if($v->trx_trp->supir_id){
            $v->trx_trp->rp_supir_at = $t_stamp;
            $v->trx_trp->rp_supir_user = $this->admin_id;           
          }
  
          if($v->trx_trp->kernet_id){
            $v->trx_trp->rp_kernet_at = $t_stamp;
            $v->trx_trp->rp_kernet_user = $this->admin_id;           
          }
          $v->trx_trp->updated_at         = $t_stamp;
          $v->trx_trp->updated_user       = $this->admin_id;
          $v->trx_trp->save();
          $SYSNOTE2 = MyLib::compareChange($SYSOLD2,$v->trx_trp);
          array_unshift( $SYSNOTES2 , $SYSNOTE2 ); 
        }

        if($v->extra_money_trx) {
          $SYSOLD3                  = clone($v->extra_money_trx);
          $v->extra_money_trx->received_payment    = 1;
          $v->extra_money_trx->rp_employee_at      = $t_stamp;
          $v->extra_money_trx->rp_employee_user    = $this->admin_id;      
          $v->extra_money_trx->updated_at          = $t_stamp;
          $v->extra_money_trx->updated_user        = $this->admin_id;
          $v->extra_money_trx->save();
          $SYSNOTE3 = MyLib::compareChange($SYSOLD3,$v->extra_money_trx);
          array_unshift( $SYSNOTES3 , $SYSNOTE3 ); 
        }

      }
      $steps=$model_query->steps;
      $steps['SET_PAID_DONE']     = 1;
      $steps['CLOSE']             = 1;
      $model_query->status        = "CLOSE";
      $model_query->steps         = $steps;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id; 
      $model_query->save(); 

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      MyLog::sys($this->syslog_db,$request->id,"set paid done",$SYSNOTE);
      MyLog::sys($this->syslog_db,$request->id,"update details",implode("\n",$SYSNOTES1));
      MyLog::sys($this->syslog_db,$request->id,"update trx_trp",implode("\n",$SYSNOTES2));
      MyLog::sys($this->syslog_db,$request->id,"update em",implode("\n",$SYSNOTES3));

      DB::commit();
      return response()->json([
        "message" => "Proses delete data berhasil",
        "steps" => $steps
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
}
