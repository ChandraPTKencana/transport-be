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

use App\Models\MySql\FinPaymentReq;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Http\Requests\MySql\FinPaymentReqRequest;
use App\Http\Resources\MySql\FinPaymentReqResource;
use App\Models\HrmRevisiLokasi;
use App\Models\MySql\FinPaymentReqDtl;
use App\Models\MySql\FinPaymentReqDtl2;
use Exception;
use Illuminate\Support\Facades\DB;
use Image;
use File;
use PDF;
use App\Http\Resources\IsUserResource;
use App\Http\Resources\MySql\TrxTrpForFinPaymentReqResource;
use App\Models\MySql\Employee;
use App\Models\MySql\IsUser;
use App\Models\MySql\TrxTrp;
use App\Exports\MyReport;
use App\Models\MySql\Bank;
use Illuminate\Support\Facades\Storage;

class FinPaymentReqController extends Controller
{
  private $admin;
  private $role;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'fin_payment_req';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    // $this->role = $this->admin->the_user->hak_akses;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function index(Request $request)
  {
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
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
    $model_query = FinPaymentReq::offset($offset)->limit($limit);

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
      "data" => FinPaymentReqResource::collection($model_query),
    ], 200);
  }

  public function show(FinPaymentReqRequest $request,$for_gen=false)
  {
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.view');

    $model_query = FinPaymentReq::with([
    'details'=>function($q){
      $q->with("trx_trp");
    },])
    ->with(['val_by'])->where("deleted",0)->find($request->id);

    $lists = [];
    $no=0;
    foreach ($model_query->details as $k => $v) {
      $no++;
      $trx_trp = $v->trx_trp;
    
      $tujuan = $trx_trp->uj->xto;
      $produk = $trx_trp->jenis;
      $no_pol = $trx_trp->no_pol;
      

      array_push($lists,[
        "id"=>$v->id,
        "no"=>$no,
        "tujuan"=>$tujuan,
        "produk"=>$produk,
        "trx_trp_id"=>$trx_trp->id,
        "no_pol"=>$no_pol,
        "employee_id"=>$v->employee_id,
        "jabatan"=>$v->employee_role,
        "nama"=>$v->employee_name,
        "rek_no"=>$v->employee_rek_no,
        "rek_name"=>$v->employee_rek_name,
        "bank_code"=>$v->employee_bank_code,
        "nominal"=>$v->nominal,
        "potongan_trx_ttl"=>$v->potongan_trx_ttl,
        "extra_money_trx_ttl"=>$v->extra_money_trx_ttl,
        "jumlah"=>$v->jumlah,
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

  public function validateItems($details_in){
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


  public function store(FinPaymentReqRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.create');

    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);

    $rollback_id = -1;
    DB::beginTransaction();
    try {
      $t_stamp = date("Y-m-d H:i:s");

      $model_query                  = new FinPaymentReq();      
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->status          = "OPEN";
      // $date = new \DateTime();
      // $model_query->filename        = env('MCM_ID').'14'.$date->format("Ymd").$date->format("His");
      $model_query->save();
      $rollback_id = $model_query->id - 1;

      
      $trx_trp_ids = array_map(function($x){
        return $x["id"];
      },$details_in);

      $trx_trps = TrxTrp::where("received_payment","0")->whereIn("id",$trx_trp_ids)->whereDoesntHave('fin_payment_req_dtl')->lockForUpdate()->get();

      // $ordinal=0;
      foreach ($trx_trps as $key => $trx_trp) {
        // $trx_trp = TrxTrp::where("id",$value['id'])->first();
        
        $uj_details2= $trx_trp->uj->details2;
        $supir_money=0;
        $kernet_money=0;
  
        foreach ($uj_details2 as $k1 => $v1) {
          if($v1->xfor=='Kernet'){
            $kernet_money += $v1->qty * $v1->amount;
          }else{
            $supir_money += $v1->qty * $v1->amount;
          }
        }
  
        $supir_potongan_trx_money  = 0;
        $supir_potongan_trx_ids    = "";
        $kernet_potongan_trx_money = 0;
        $kernet_potongan_trx_ids   = "";
        foreach ($trx_trp->potongan as $kx => $v2) {
          if($v2->potongan_mst->employee_id == $trx_trp->supir_id){
            $supir_potongan_trx_money+=$v2->nominal_cut;
            $supir_potongan_trx_ids.="#".$v2->potongan_mst->id." ";
          }
    
          if($v2->potongan_mst->employee_id == $trx_trp->kernet_id){
            $kernet_potongan_trx_money+=$v2->nominal_cut;
            $kernet_potongan_trx_ids.="#".$v2->potongan_mst->id." ";
          }
        }
        
        $supir_extra_money_trx_money  = 0;
        $supir_extra_money_trx_ids    = "";
        $kernet_extra_money_trx_money = 0;
        $kernet_extra_money_trx_ids   = "";
        foreach ($trx_trp->extra_money_trxs as $k => $emt) {
          if($emt->employee_id == $trx_trp->supir_id){
            $supir_extra_money_trx_money+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
            $supir_extra_money_trx_ids.="#".$emt->id." ";
          }

          if($emt->employee_id == $trx_trp->kernet_id){
            $kernet_extra_money_trx_money+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
            $kernet_extra_money_trx_ids.="#".$emt->id." ";
          }
        }


        $detail                       = new FinPaymentReqDtl();
        $detail->fin_payment_req_id   = $model_query->id;
        $detail->trx_trp_id           = $trx_trp->id;
        $detail->employee_role        = "SUPIR";
        $detail->employee_id          = $trx_trp->employee_s->id;
        $detail->employee_name        = $trx_trp->employee_s->name;
        $detail->employee_rek_no      = $trx_trp->employee_s->rek_no;
        $detail->employee_rek_name    = $trx_trp->employee_s->rek_name;
        $detail->employee_bank_code   = $trx_trp->employee_s->bank->code;
        $detail->nominal              = $supir_money;
        $detail->potongan_trx_ids     = $supir_potongan_trx_ids;
        $detail->potongan_trx_ttl     = $supir_potongan_trx_money;
        $detail->extra_money_trx_ids  = $supir_extra_money_trx_ids;
        $detail->extra_money_trx_ttl  = $supir_extra_money_trx_money;
        $detail->jumlah               = $supir_money + $supir_extra_money_trx_money - $supir_potongan_trx_money;
        $detail->status               = "READY";
        
        $detail->created_at         = $t_stamp;
        $detail->created_user       = $this->admin_id;
  
        $detail->updated_at         = $t_stamp;
        $detail->updated_user       = $this->admin_id;  
      
        $detail->save();
        if($trx_trp->kernet_id){
          $detail                       = new FinPaymentReqDtl();
          $detail->fin_payment_req_id   = $model_query->id;
          $detail->trx_trp_id           = $trx_trp->id;
          $detail->employee_role        = "KERNET";
          $detail->employee_id          = $trx_trp->employee_k->id;
          $detail->employee_name        = $trx_trp->employee_k->name;
          $detail->employee_rek_no      = $trx_trp->employee_k->rek_no;
          $detail->employee_rek_name    = $trx_trp->employee_k->rek_name;
          $detail->employee_bank_code   = $trx_trp->employee_k->bank->code;
          $detail->nominal              = $kernet_money;
          $detail->potongan_trx_ids     = $kernet_potongan_trx_ids;
          $detail->potongan_trx_ttl     = $kernet_potongan_trx_money;
          $detail->extra_money_trx_ids  = $kernet_extra_money_trx_ids;
          $detail->extra_money_trx_ttl  = $kernet_extra_money_trx_money;
          $detail->jumlah               = $kernet_money + $kernet_extra_money_trx_money - $kernet_potongan_trx_money;
          $detail->status               = "READY";
          
          $detail->created_at         = $t_stamp;
          $detail->created_user       = $this->admin_id;
    
          $detail->updated_at         = $t_stamp;
          $detail->updated_user       = $this->admin_id;  
        
          $detail->save();
        }
      }

      // foreach ($trx_trps as $key => $value) {
      //   $value->fin_status = 'P';
      //   $value->save();
      // }

      $model_query->save();
      MyLog::sys("fin_payment_req",$model_query->id,"insert");

      DB::commit();
      $callGet = $this->show( new FinPaymentReqRequest($model_query->toArray()));
      if ($callGet->getStatusCode() != 200) return $callGet;
      $ori = json_decode(json_encode($callGet), true)["original"];
      $data = $ori["data"];  

      return response()->json([
        "message" => "Proses tambah data berhasil",
        "data"=>$data,
        // "created_at" => $t_stamp,
        // "updated_at" => $t_stamp,
        
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE fin_payment_req AUTO_INCREMENT = $rollback_id");
      
      // return response()->json([
      //   "message" => $e->getMessage(),
      // ], 400);

      // if ($e->getCode() == 1) {
      //   return response()->json([
      //     "message" => $e->getMessage(),
      //   ], 400);
      // }

      return response()->json([
        "message" => "Proses tambah data gagal",
      ], 400);
    }
  }

  public function update(FinPaymentReqRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic','PabrikTransport']);
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.modify');
    
    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);

    $t_stamp = date("Y-m-d H:i:s");

    DB::beginTransaction();
    try {
      $SYSNOTES=[];
      $model_query             = FinPaymentReq::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD                  = clone($model_query);


      $checkDetails = FinPaymentReqDtl::where("fin_payment_req_id",$model_query->id)->pluck('status')->toArray();

      if(!in_array('READY',$checkDetails)){
        throw new \Exception("Data Sudah Tidak Dapat Ditambahkan",1);
      }
      // $t_stamp = date("Y-m-d H:i:s");
      
      // $date = new \DateTime();
      // $model_query->filename        = env('MCM_ID').'14'.$date->format("Ymd").$date->format("His");
      
      $trx_trp_ids = array_map(function($x){
        return $x["id"];
      },$details_in);

      $trx_trps = TrxTrp::where("received_payment","0")->whereIn("id",$trx_trp_ids)->whereDoesntHave('fin_payment_req_dtl')->lockForUpdate()->get();

      // $ordinal=0;
      foreach ($trx_trps as $key => $trx_trp) {
        // $trx_trp = TrxTrp::where("id",$value['id'])->first();
        
        $uj_details2= $trx_trp->uj->details2;
        $supir_money=0;
        $kernet_money=0;
  
        foreach ($uj_details2 as $k1 => $v1) {
          if($v1->xfor=='Kernet'){
            $kernet_money += $v1->qty * $v1->amount;
          }else{
            $supir_money += $v1->qty * $v1->amount;
          }
        }
  
        $supir_potongan_trx_money  = 0;
        $supir_potongan_trx_ids    = "";
        $kernet_potongan_trx_money = 0;
        $kernet_potongan_trx_ids   = "";
        foreach ($trx_trp->potongan as $kx => $v2) {
          if($v2->potongan_mst->employee_id == $trx_trp->supir_id){
            $supir_potongan_trx_money+=$v2->nominal_cut;
            $supir_potongan_trx_ids.="#".$v2->potongan_mst->id." ";
          }
    
          if($v2->potongan_mst->employee_id == $trx_trp->kernet_id){
            $kernet_potongan_trx_money+=$v2->nominal_cut;
            $kernet_potongan_trx_ids.="#".$v2->potongan_mst->id." ";
          }
        }
        
        $supir_extra_money_trx_money  = 0;
        $supir_extra_money_trx_ids    = "";
        $kernet_extra_money_trx_money = 0;
        $kernet_extra_money_trx_ids   = "";
        foreach ($trx_trp->extra_money_trxs as $k => $emt) {
          if($emt->employee_id == $trx_trp->supir_id){
            $supir_extra_money_trx_money+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
            $supir_extra_money_trx_ids.="#".$emt->id." ";
          }

          if($emt->employee_id == $trx_trp->kernet_id){
            $kernet_extra_money_trx_money+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
            $kernet_extra_money_trx_ids.="#".$emt->id." ";
          }
        }


        $detail                       = new FinPaymentReqDtl();
        $detail->fin_payment_req_id   = $model_query->id;
        $detail->trx_trp_id           = $trx_trp->id;
        $detail->employee_role        = "SUPIR";
        $detail->employee_id          = $trx_trp->employee_s->id;
        $detail->employee_name        = $trx_trp->employee_s->name;
        $detail->employee_rek_no      = $trx_trp->employee_s->rek_no;
        $detail->employee_rek_name    = $trx_trp->employee_s->rek_name;
        $detail->employee_bank_code   = $trx_trp->employee_s->bank->code;
        $detail->nominal              = $supir_money;
        $detail->potongan_trx_ids     = $supir_potongan_trx_ids;
        $detail->potongan_trx_ttl     = $supir_potongan_trx_money;
        $detail->extra_money_trx_ids  = $supir_extra_money_trx_ids;
        $detail->extra_money_trx_ttl  = $supir_extra_money_trx_money;
        $detail->jumlah               = $supir_money + $supir_extra_money_trx_money - $supir_potongan_trx_money;
        $detail->status               = "READY";
        
        $detail->created_at         = $t_stamp;
        $detail->created_user       = $this->admin_id;
  
        $detail->updated_at         = $t_stamp;
        $detail->updated_user       = $this->admin_id;  
      
        $detail->save();
        if($trx_trp->kernet_id){
          $detail                       = new FinPaymentReqDtl();
          $detail->fin_payment_req_id   = $model_query->id;
          $detail->trx_trp_id           = $trx_trp->id;
          $detail->employee_role        = "KERNET";
          $detail->employee_id          = $trx_trp->employee_k->id;
          $detail->employee_name        = $trx_trp->employee_k->name;
          $detail->employee_rek_no      = $trx_trp->employee_k->rek_no;
          $detail->employee_rek_name    = $trx_trp->employee_k->rek_name;
          $detail->employee_bank_code   = $trx_trp->employee_k->bank->code;
          $detail->nominal              = $kernet_money;
          $detail->potongan_trx_ids     = $kernet_potongan_trx_ids;
          $detail->potongan_trx_ttl     = $kernet_potongan_trx_money;
          $detail->extra_money_trx_ids  = $kernet_extra_money_trx_ids;
          $detail->extra_money_trx_ttl  = $kernet_extra_money_trx_money;
          $detail->jumlah               = $kernet_money + $kernet_extra_money_trx_money - $kernet_potongan_trx_money;
          $detail->status               = "READY";
          
          $detail->created_at         = $t_stamp;
          $detail->created_user       = $this->admin_id;
    
          $detail->updated_at         = $t_stamp;
          $detail->updated_user       = $this->admin_id;  
        
          $detail->save();
        }
      }

      // foreach ($trx_trps as $key => $value) {
      //   $value->fin_status = 'P';
      //   $value->save();
      // }

      $model_query->save();
      // MyLog::sys("fin_payment_req",$model_query->id,"insert");
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      array_unshift( $SYSNOTES , $SYSNOTE );            
      MyLog::sys("fin_payment_req",$request->id,"update",implode("\n",$SYSNOTES));

      DB::commit();

      $callGet = $this->show( new FinPaymentReqRequest($model_query->toArray()));
      if ($callGet->getStatusCode() != 200) return $callGet;
      $ori = json_decode(json_encode($callGet), true)["original"];
      $data = $ori["data"];


      return response()->json([
        "message" => "Proses ubah data berhasil",
        // "updated_at"=>$t_stamp,
        "data"=>$data,
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

  // public function delete(Request $request)
  // {
  //   // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
  //   MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

  //   DB::beginTransaction();

  //   try {
  //     $deleted_reason = $request->deleted_reason;
  //     if(!$deleted_reason)
  //     throw new \Exception("Sertakan Alasan Penghapusan",1);
    
  //     $model_query = FinPaymentReq::where("id",$request->id)->lockForUpdate()->first();
  //     // if($model_query->requested_by != $this->admin_id){
  //     //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
  //     // }
      
  //     // $model_querys = FinPaymentReqDtl::where("id_uj",$model_query->id)->lockForUpdate()->get();

  //     if (!$model_query) {
  //       throw new \Exception("Data tidak terdaftar", 1);
  //     }

  //     // if($model_query->val==1 || $model_query->deleted==1) 
  //     // throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);


  //     // if($model_query->ref_id != null){
  //     //   throw new \Exception("Hapus data ditolak. Data berasal dari transfer",1);
  //     // }

  //     // if($model_query->confirmed_by != null){
  //     //   throw new \Exception("Hapus data ditolak. Data sudah dikonfirmasi",1);
  //     // }
      
  //     // if($this->role=='ClientPabrik' || $this->role=='KTU')
  //     // MyAdmin::checkReturnOrFailLocation($this->admin->the_user,$model_query->hrm_revisi_lokasi_id);
  
  //     $model_query->deleted = 1;
  //     $model_query->deleted_user = $this->admin_id;
  //     $model_query->deleted_at = date("Y-m-d H:i:s");
  //     $model_query->deleted_reason = $deleted_reason;
  //     $model_query->save();
  //     MyLog::sys("fin_payment_req",$request->id,"delete");

  //     // FinPaymentReqDtl::where("id_uj",$model_query->id)->delete();
  //     // $model_query->delete();

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses Hapus data berhasil",
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
  // }

  public function validasi(Request $request){
    MyAdmin::checkScope($this->permissions, 'fin_payment_req.val');
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\FinPaymentReq,id",
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
      $model_query = FinPaymentReq::with('details')->find($request->id);
     
      $fin_payment_req_details = $model_query->details->toArray();
      
      $trx_trp_ids = array_map(function($x){
        return $x['trx_trp_id'];
      },$fin_payment_req_details);

      $trx_trps = TrxTrp::where("fin_status","P")->whereIn("id",$trx_trp_ids)->lockForUpdate()->get();
      foreach ($trx_trps as $key => $value) {
        $value->fin_status = 'V';
        $value->save();
      }

      $model_query->val = 1;
      $model_query->val_user = $this->admin_id;
      $model_query->val_at = $t_stamp;
      $model_query->save();

      MyLog::sys("fin_payment_req",$request->id,"approve");

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


  public function get_trx_trp_unprocessed(Request $request){
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    try {
      $model_query = TrxTrp::where("deleted",0)->where("req_deleted",0)
      ->where('val',1)->where('val1',1)->where('val2',1)->where('val4',1)->where('val5',1)->Where('val6',1)
      ->where(function ($q){
        $q->where(function ($q1){
          $q1->whereNotIn('jenis',['CPO','PK']);
        });
        $q->orWhere(function ($q1){
          $q1->whereIn('jenis',['CPO','PK'])->where('val3',1);
        });
      })
      ->where('received_payment',0)->where("payment_method_id",4)
      ->whereDoesntHave('fin_payment_req_dtl')
      ->with('uj')->get();
      return response()->json([
        "data" => TrxTrpForFinPaymentReqResource::collection($model_query),
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
    MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    
    set_time_limit(0);
    $callGet = $this->show( new FinPaymentReqRequest($request->toArray()));
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

    set_time_limit(0);
    $date = new \DateTime();
    // $filename=$date->format("YmdHis").'-'.env('APP_NAME');

    $date = new \DateTime();
    $filename        = env('MCM_ID').'14'.$date->format("Ymd").$date->format("His");

    $t_stamp = date("Y-m-d H:i:s");

    set_time_limit(0);
    $callGet = $this->show( new FinPaymentReqRequest($request->toArray()),true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    $raw_data = $ori["data"];  

    
    DB::beginTransaction();
    try {
      $data = $raw_data;

      $checkDetails = FinPaymentReqDtl::where("fin_payment_req_id",$data['id'])->pluck('status')->toArray();

      if(!in_array('READY',$checkDetails)){
        throw new \Exception("Data Tidak Dapat Dikirim",1);
      }  
      
      $csv_data = "";
      $records = count($data['details']);

      foreach ($raw_data['details'] as $k => $v) {
        $jumlah = (int) $v['jumlah'];
        $jenis_rek = $v['bank_code']=='Mandiri'?'IBU':'BAU';
        $dbank = Bank::where('code',$v['bank_code'])->first();
        $code_beda_bank = $dbank->code_duitku;
        $csv_data .= "{$v['rek_no']};{$v['rek_name']};;;;IDR;{$jumlah};;;{$jenis_rek};{$code_beda_bank};;;;;;N;;;;;Y;;;;;;;;;;;;;;;;;BEN;1;E";
        if($k<$records-1)
        $csv_data .= "\r\n";
        // $csv_data .= "{$v['rek_no']},{$v['rek_name']},,,,IDR,{$v['jumlah']},,,IBU,,,,,,,N,,,,,Y,,,,,,,,,,,,,,,,,BEN,1,E,,,\n";
      }
  
      $total = array_reduce($data['details'],function ($carry,$item) {
        return $carry+=(int) $item['jumlah'];      
      });
  
      // $mime = MyLib::mime("csv");
      $mime = ["ext"=>'txt'];
  
      $filePath = 'public/' .$filename . '.' . $mime["ext"];
  
      $mandiri_bank_no =env('MANDIRI_BANK_NO');
      $csv = "P;{$date->format('Ymd')};{$mandiri_bank_no};{$records};{$total}\r\n";
  
      // foreach($data as $k=>$v) {
      //   $csv .= str_replace('"', '', $v["supir"]) . "," . str_replace('"', '', $v["kernet"]) . "\n";
      // }
      
  
      Storage::put($filePath, $csv.$csv_data);

      

      // // $remotePath = 'csvf/'.$filePath . '.' . $mime["ext"];
      // // $remotePath = $filePath . '.' . $mime["ext"];
      // // $remotePath = "Upload/Users/MCM_BatchUpload/".$data['filename'] . '.' . $mime["ext"];
      $remotePath = "Upload/Users/MCM_BatchUpload/".$filename. '.' . $mime["ext"];
      Storage::disk('ftp')->put($remotePath,Storage::get($filePath));
      // try {
      //     // Storage::disk('ftp')->put($remotePath, file_get_contents(Storage::get($file)));
      //     // MyLog::logging("kirim berhasil","kirim");
      // } catch (\Exception $e) {
      //     // Handle the exception
      //     // MyLog::logging($e->getMessage(),"kirim");
      //   throw new \Exception("Data Gagal Dikirim",1);
      // }

      FinPaymentReq::where('id',$data['id'])->update([
        "filename"=>$filename
      ]);

      FinPaymentReqDtl::where('fin_payment_req_id',$data['id'])->update([
        "status"=>"INQUIRY_PROCESS"
      ]);

      MyLog::sys("fin_payment_req",$data['id'],"update details","update status to INQUIRY_PROCESS");
      
      DB::commit();

      return response()->json([
        "message" => "Proses Generate Berhasil",
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
        "message" => "Proses Generate gagal",
      ], 400);
    }

  }

  
  public function getUpdate(Request $request){
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\FinPaymentReq,id",
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
      
      $model_query = FinPaymentReq::with('details')->find($request->id);

      $checkDetails = FinPaymentReqDtl::where("fin_payment_req_id",$request->id)->pluck('status')->toArray();

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
          $st="INQUIRY_FAILED";
          $stm = "NOT REGISTERED";
          $had_failed++;
        }else{
          if($result[$index]['status']=="SUCCESS"){
            $st = "INQUIRY_SUCCESS";
            $stm = null;
          }else{
            $st = 'INQUIRY_FAILED';
            $stm = $result[$index]['reason'];
            $had_failed++;
          }
        }

        // if($k==0){
        //   $had_failed++;
        //   $v->status = 'INQUIRY_FAILED';
        //   $v->failed_reason = 'Update Dl Nama Rek Nya';
        // }else{
        //   $v->status = 'INQUIRY_SUCCESS';
        // }

        $v->status             = $st;
        $v->failed_reason      = $stm;
        $v->updated_at         = $t_stamp;
        $v->updated_user       = $this->admin_id;
        $v->save();
        $SYSNOTE = MyLib::compareChange($SYSOLD,$v);
        array_push($r_data,$v);
        array_push($SYSNOTES,$SYSNOTE);
      }

      // if($had_failed==0){
      //   FinPaymentReqDtl::where('fin_payment_req_id',$request->id)->update([
      //     "status"=>"TRANSFER_PROCESS"
      //   ]);

      //   $r_data = array_map(function ($x) {
      //     $x->status = 'TRANSFER_PROCESS';
      //     return $x;
      //   },$r_data);

      //   MyLog::sys("fin_payment_req",$request->id,"update details","update status to TRANSFER_PROCESS");

      // }


      if($had_failed==0){
        FinPaymentReqDtl::where('fin_payment_req_id',$request->id)->update([
          "status"=>"TRANSFER_PROCESS"
        ]);

        FinPaymentReq::where('id',$request->id)->update([
          "status"=>"WAIT",
          'wait_at'=>$t_stamp
        ]);

        $r_data = array_map(function ($x) {
          $x->status = 'TRANSFER_PROCESS';
          return $x;
        },$r_data);



        MyLog::sys("fin_payment_req_dtl",$request->id,"update details","update details with fin_payment_req_id = '".$request->id."' to TRANSFER_PROCESS");
        MyLog::sys("fin_payment_req",$request->id,"update master","update status to CLOSE");
      }
      // $trx_trp_ids = array_map(function($x){
      //   return $x['trx_trp_id'];
      // },$fin_payment_req_details);

      // $trx_trps = TrxTrp::where("fin_status","P")->whereIn("id",$trx_trp_ids)->lockForUpdate()->get();
      // foreach ($trx_trps as $key => $value) {
      //   $value->fin_status = 'V';
      //   $value->save();
      // }

      // $model_query->val = 1;
      // $model_query->val_user = $this->admin_id;
      // $model_query->val_at = $t_stamp;
      // $model_query->save();
      MyLog::sys("fin_payment_req_dtl",null,"update status",implode(",",$SYSNOTES));
      // MyLog::sys("fin_payment_req_dtl",$request->id,"update status");

      DB::commit();
      return response()->json([
        "message" => "Proses cek status berhasil",
        "details" => $r_data,
        // "result"=>$fields
        // "val"=>$model_query->val,
        // "val_user"=>$model_query->val_user,
        // "val_at"=>$model_query->val_at,
        // "val_by"=>$model_query->val_user ? new IsUserResource(IsUser::find($model_query->val_user)) : null,
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

    $rules = [
      'detail_id' => "required|exists:\App\Models\MySql\FinPaymentReqDtl,id",
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

      $model_query = FinPaymentReqDtl::with(['trx_trp','employee'])->lockForUpdate()->where("id",$request->detail_id)->first();
      $SYSOLD                     = clone($model_query);

      if($model_query->status!='INQUIRY_FAILED'){
        throw new \Exception("Data Tidak Dapat Diperbaharui",1);
      }

      $model_query->employee_rek_no = $model_query->employee->rek_no;
      $model_query->employee_rek_name = $model_query->employee->rek_name;
      $model_query->employee_bank_code = $model_query->employee->bank->code;
      
      $SYSOLD1                     = clone($model_query->trx_trp);
      if($model_query->employee_role=="SUPIR"){

        $model_query->trx_trp->supir_rek_no = $model_query->employee_rek_no;
        $model_query->trx_trp->supir_rek_name = $model_query->employee_rek_name;
      }else{
        $model_query->trx_trp->kernet_rek_no = $model_query->employee_rek_no;
        $model_query->trx_trp->kernet_rek_name = $model_query->employee_rek_name;
      }
      $model_query->updated_at         = $t_stamp;
      $model_query->updated_user       = $this->admin_id;  

      $model_query->trx_trp->updated_at         = $t_stamp;
      $model_query->trx_trp->updated_user       = $this->admin_id;  

      $model_query->status = 'READY';
      $model_query->failed_reason = '';
      $SYSNOTE1 = MyLib::compareChange($SYSOLD1,$model_query->trx_trp);
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      $model_query->trx_trp->save(); 
      $model_query->save(); 


      array_push($SYSNOTES,"Detail:".$SYSNOTE);
      array_push($SYSNOTES,"Trx Trp:".$SYSNOTE1);

      MyLog::sys("fin_payment_req_dtl",null,"renew data",implode(",",$SYSNOTES));
      DB::commit();
      return response()->json([
        "message" => "Proses update data berhasil",
        "employee_rek_no"=>$model_query->employee_rek_no,
        "employee_rek_name"=>$model_query->employee_rek_name,
        "employee_bank_code"=>$model_query->employee_bank_code,
        "status"=>$model_query->status,
        "failed_reason"=>$model_query->failed_reason,
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

  public function deleteData(Request $request){
    // MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);

    $rules = [
      'trx_trp_id' => "required|exists:\App\Models\MySql\FinPaymentReqDtl,trx_trp_id",
    ];

    $messages = [
      'trx_trp_id.required' => 'Trx Trp ID tidak boleh kosong',
      'trx_trp_id.exists' => 'Trx Trp ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $SYSNOTES = [];

      $model_query = FinPaymentReqDtl::with(['fin_payment_req'=>function ($q){
        $q->where("status","OPEN");
      }])->where("trx_trp_id",$request->trx_trp_id)->lockForUpdate()->pluck("status")->toArray();

      if(count($model_query)==0){
        throw new \Exception("Data Sudah Tidak Dapat Di Hapus",1);
      }

      if(!in_array("READY",$model_query)){
        throw new \Exception("Data Tidak Diizinkan untuk di Hapus",1);
      }

      FinPaymentReqDtl::with(['trx_trp','employee','fin_payment_req'=>function ($q){
        $q->where("status","OPEN");
      }])->where("trx_trp_id",$request->trx_trp_id)->delete();

      // $SYSNOTE1 = MyLib::compareChange($SYSOLD1,$model_query->trx_trp);
      // $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      // $model_query->trx_trp->save(); 
      // $model_query->save(); 


      // array_push($SYSNOTES,"Detail:".$SYSNOTE);
      // array_push($SYSNOTES,"Trx Trp:".$SYSNOTE1);

      MyLog::sys("fin_payment_req_dtl",null,"delete data","delete data which have trx_trp_id:".$request->trx_trp_id);
      DB::commit();
      return response()->json([
        "message" => "Proses delete data berhasil",
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

    $rules = [
      'id' => "required|exists:\App\Models\MySql\FinPaymentReq,id",
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

      $model_query                = FinPaymentReq::where("id",$request->id)->lockForUpdate()->first();
      
      if($model_query->status=='CLOSE'){
        throw new \Exception("Data Sudah Tidak Dapat Diset",1);
      }
      
      $SYSOLD                     = clone($model_query);
      $model_query->batch_no      = $request->batch_no;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;  

      $model_query->save(); 
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      MyLog::sys("fin_payment_req_dtl",null,"renew data",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses delete data berhasil",
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

    $rules = [
      'id' => "required|exists:\App\Models\MySql\FinPaymentReq,id",
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

      $model_query            = FinPaymentReq::with(['details'=>function ($q){ $q->with('trx_trp');}])
      ->where("id",$request->id)->lockForUpdate()->first();

      if($model_query->status=='CLOSE'){
        throw new \Exception("Data Sudah Tidak Dapat Diset",1);
      }

      $SYSOLD                 = clone($model_query);
      

      
      $SYSNOTES1=[];
      $SYSNOTES2=[];
      foreach ($model_query->details as $k => $v) {
        $SYSOLD_dtl            = clone($v);
        $v->status             = 'DONE';
        $v->created_at         = $t_stamp;
        $v->created_user       = $this->admin_id;
        $v->save();
        $SYSNOTE1 = MyLib::compareChange($SYSOLD_dtl,$v);
        array_unshift( $SYSNOTES1 , $SYSNOTE1 ); 

        $SYSOLD_trx_trp                 = clone($v->trx_trp);
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
        $SYSNOTE2 = MyLib::compareChange($SYSOLD_trx_trp,$v->trx_trp);
        array_unshift( $SYSNOTES2 , $SYSNOTE2 ); 
      }

      $model_query->status        = "CLOSE";
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id; 
      $model_query->save(); 

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      MyLog::sys("fin_payment_req",$request->id,"set paid done",$SYSNOTE);
      MyLog::sys("fin_payment_req",$request->id,"update detail",implode("\n",$SYSNOTES1));
      MyLog::sys("fin_payment_req",$request->id,"update trx_trp",implode("\n",$SYSNOTES2));

      DB::commit();
      return response()->json([
        "message" => "Proses delete data berhasil",
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
