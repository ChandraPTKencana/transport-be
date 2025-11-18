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
use Illuminate\Support\Facades\Storage;

class FinPaymentReqController extends Controller
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
    MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
 
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
    MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);

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
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

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

      $trx_trps = TrxTrp::where("received_payment","0")->whereIn("id",$trx_trp_ids)->lockForUpdate()->get();

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
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE fin_payment_req AUTO_INCREMENT = $rollback_id");
      
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

  // public function update(FinPaymentReqRequest $request)
  // {
  //   // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
  //   MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic','PabrikTransport']);
  //   $t_stamp = date("Y-m-d H:i:s");

  //   DB::beginTransaction();
  //   try {
  //     $SYSNOTES=[];
  //     $model_query             = FinPaymentReq::where("id",$request->id)->lockForUpdate()->first();
  //     $SYSOLD      = clone($model_query);

  //     if($model_query->deleted==1)
  //     throw new \Exception("Data Sudah Dihapus",1);
      
  //     if(
  //       ($model_query->val==1 && $model_query->val1==1) || 
  //       ($this->role=="Logistic" && $model_query->val == 1) ||
  //       ($this->role=="PabrikTransport" && $model_query->val1 == 1)
  //     ) 
  //     throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

  //     if(MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic'],null,true)){
  //       $details_in = json_decode($request->details, true);
  //       $this->validateItems($details_in);
  //     }
  //     //start for details2
  //     $details_in2 = json_decode($request->details2, true);
  //     $this->validateItems2($details_in2);   
  //     //end for details2
      
  //     //start for details2
  //     $unique_items2 = [];
  //     $unique_acc_code=[];
  //     foreach ($details_in2 as $key => $value) {
  //       $unique_data2 = strtolower(trim($value['ac_account_code']).trim($value['description']));
  //       if (in_array($unique_data2, $unique_items2) == 1) {
  //         throw new \Exception("Maaf terdapat Item yang sama",1);
  //       }
  //       array_push($unique_items2, $unique_data2);
  //       if($value["p_status"]!="Remove")
  //       array_push($unique_acc_code,$value['ac_account_code']);
  //     }
  //     $unique_acc_code = array_unique($unique_acc_code);
  
  //     $temp_ac_accounts = [];
  //     $listToCheck = [];
  //     if(count($unique_acc_code)>0){
  //       $connectionDB = DB::connection('sqlsrv');
  //       $temp_ac_accounts = $connectionDB->table("AC_Accounts")
  //       ->select('AccountID','AccountCode','AccountName')
  //       ->whereIn('AccountCode',$unique_acc_code) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
  //       ->get();
  
  //       $temp_ac_accounts= MyLib::objsToArray($temp_ac_accounts);
  
  //       $listToCheck = array_map(function($x){
  //         return $x["AccountCode"];
  //       },$temp_ac_accounts);

  //       if(count($temp_ac_accounts)!=count($unique_acc_code)){  
  //         $diff = array_diff($unique_acc_code,$listToCheck);
  //         throw new \Exception(implode(",",$diff)."tidak terdaftar",1);
  //       }
  //     }
  //     //end for details2

  //     // if(FinPaymentReq::where("id","!=",$request->id)->where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
  //     // throw new \Exception("List sudah terdaftar",1);

  //     // if($model_query->requested_by != $this->admin_id){
  //     //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan pergantian data",1);
  //     // }

  //     // if($model_query->ref_id!=null){
  //     //   throw new \Exception("Ubah data ditolak. Data berasal dari transfer",1);
  //     // }

  //     // if($model_query->val != null){
  //     //   throw new \Exception("Ubah ditolak. Data sudah di validasi.",1);
  //     // }

  //     // $warehouse_id = $request->warehouse_id;
  //     // if($this->role=='ClientPabrik' || $this->role=='KTU')
  //     // $warehouse_id = MyAdmin::checkReturnOrFailLocation($this->admin->the_user,$model_query->hrm_revisi_lokasi_id);
  
  //     // $dt_before = $this->getLastDataConfirmed($warehouse_id,$request->item_id);
  //     // if($dt_before && $dt_before->id != $model_query->id){
  //     //   throw new \Exception("Ubah ditolak. Hanya data terbaru yang bisa diubah.",1);
  //     // }
  //     if(MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic'],null,true)){
  //       array_push( $SYSNOTES ,"Details: \n");

  //       $model_query->xto             = $request->xto;
  //       $model_query->tipe            = $request->tipe;
  //       $model_query->jenis           = $request->jenis;
  //       $model_query->harga           = 0;
  //       $model_query->note_for_remarks= MyLib::emptyStrToNull($request->note_for_remarks);

  //       // $model_query->status          = $request->status;
    
  //       // $model_query->created_at      = $t_stamp;
  //       // $model_query->created_user    = $this->admin_id;

  //       $model_query->updated_at      = $t_stamp;
  //       $model_query->updated_user    = $this->admin_id;  


  //       $data_from_db = FinPaymentReqDtl::where('id_uj', $model_query->id)
  //       ->orderBy("ordinal", "asc")->lockForUpdate()
  //       ->get()->toArray();
        

  //       $in_keys = array_filter($details_in, function ($x) {
  //           return isset($x["key"]);
  //       });

  //       $in_keys = array_map(function ($x) {
  //           return $x["key"];
  //       }, $in_keys);

  //       $am_ordinal_db = array_map(function ($x) {
  //           return $x["ordinal"];
  //       }, $data_from_db);

  //       if (count(array_diff($in_keys, $am_ordinal_db)) > 0 || count(array_diff($am_ordinal_db, $in_keys)) > 0) {
  //           throw new Exception('Ada ketidak sesuaian data, harap hubungi staff IT atau refresh browser anda',1);
  //       }

  //       $id_items = [];
  //       $ordinal = 0;
  //       $for_deletes = [];
  //       $for_edits = [];
  //       $for_adds = [];
  //       $data_to_processes = [];
  //       foreach ($details_in as $k => $v) {
  //         // $xdesc = $v['xdesc'] ? $v['xdesc'] : "";
          
  //         if (in_array($v["p_status"], ["Add", "Edit"])) {
  //           if (in_array(strtolower($v['xdesc']), $id_items) == 1) {
  //               throw new \Exception("Maaf terdapat Nama Item yang sama",1);
  //           }
  //           array_push($id_items, strtolower($v['xdesc']));
  //         }

  //         if ($v["p_status"] !== "Remove") {
  //           $ordinal++;
  //           $details_in[$k]["ordinal"] = $ordinal;
  //           if ($v["p_status"] == "Edit")
  //               array_unshift($for_edits, $details_in[$k]);
  //           elseif ($v["p_status"] == "Add")
  //               array_push($for_adds, $details_in[$k]);
  //         } else
  //             array_push($for_deletes, $details_in[$k]);
  //       }

  //       if(count($for_adds)==0 && count($for_edits)==0){
  //         throw new \Exception("Item harus Diisi",1);
  //       }

  //       $data_to_processes = array_merge($for_deletes, $for_edits, $for_adds);
  //       // $ordinal = 0;
  //       // MyLog::logging([
  //       //   "data_to_processes"=>$data_to_processes,
  //       //   "data_from_db"=>$data_from_db,
  //       // ]);

  //       // return response()->json([
  //       //   "message" => "test",
  //       // ], 400);
  //       $remarksign=0;

  //       foreach ($data_to_processes as $k => $v) {
  //         $index = false;

  //         if (isset($v["key"])) {
  //             $index = array_search($v["key"], $am_ordinal_db);
  //         }
          
  //         //         if($k==2)
  //         // {        MyLog::logging([
  //         //           "item_name"=>$v["item"]["name"],
  //         //           "key"=>$v["key"],
  //         //           "index"=>$index,
  //         //           "ordinal_arr"=>$am_ordinal_db,
  //         //           "v"=>$v,
  //         //           "w"=>$data_from_db,
  //         //         ]);

  //         //         return response()->json([
  //         //           "message" => "test",
  //         //         ], 400);
  //         // }


  //         if(in_array($v["p_status"],["Add","Edit"])){
  //           // $ordinal++;

  //           // if(($type=="transfer" || $type=="used")){
  //           //   $v['qty_in']=null;
  //           //   if($v['qty_out']==0) 
  //           //     throw new \Exception("Baris #" .$ordinal." Qty Out Tidak Boleh 0",1);
  //           // }

  //           // if($type=="in"){
  //           //   $v['qty_out']=null;
  //           //   if($v['qty_in']==0)
  //           //   throw new \Exception("Baris #" .$ordinal.".Qty In Tidak Boleh 0",1);
  //           // }


  //           // $indexItem = array_search($v['xdesc'], $items_id);
  //           // $qty_reminder = 0;

  //           // if ($indexItem !== false){
  //           //   $qty_reminder = $prev_checks[$indexItem]["qty_reminder"];
  //           // }
    
  //           // if(($type=="used" || $type=="transfer") && $qty_reminder - $v['qty_out'] < 0){
  //           //   // MyLog::logging($prev_checks);

  //           //   // throw new \Exception("Baris #" .$ordinal.".Qty melebihi stok : ".$qty_reminder, 1);
  //           // }
  //         }


  //         // $v["item_code"] = MyLib::emptyStrToNull($v["item_code"]);
  //         // $v["note"] = MyLib::emptyStrToNull($v["note"]);
  //         // $v["qty_assumption"] = MyLib::emptyStrToNull($v["qty_assumption"]);
  //         // $v["qty_realization"] = MyLib::emptyStrToNull($v["qty_realization"]);
  //         // $v["stock"] = MyLib::emptyStrToNull($v["stock"]);
  //         // $v["price_assumption"] = MyLib::emptyStrToNull($v["price_assumption"]);
  //         // $v["price_realization"] = MyLib::emptyStrToNull($v["price_realization"]);

  //         if ($v["p_status"] == "Remove") {

  //             if ($index === false) {
  //                 throw new \Exception("Data yang ingin dihapus tidak ditemukan",1);
  //             } else {
  //                 $dt = $data_from_db[$index];
  //                 // $has_permit = count(array_intersect(['ap-project_material_item-remove'], $scopes));
  //                 // if (!$dt["is_locked"] && $dt["created_by"] == $auth_id && $has_permit) {
  //                 //     ProjectMaterial::where("project_no", $model_query->no)->where("ordinal", $dt["ordinal"])->delete();
  //                 // }
  //                 array_push( $SYSNOTES ,"Ordinal ".$dt["ordinal"]." [Deleted]");
  //                 FinPaymentReqDtl::where("id_uj",$model_query->id)->where("ordinal",$dt["ordinal"])->delete();
  //             }
  //         } else if ($v["p_status"] == "Edit") {

  //             if ($index === false) {
  //                 throw new \Exception("Data yang ingin diubah tidak ditemukan" . $k,1);
  //             } else {
  //                 // $dt = $data_from_db[$index];
  //                 // $has_permit = count(array_intersect(['ap-project_material_item-edit'], $scopes));
  //                 // if (!$has_permit) {
  //                 //     throw new Exception('Ubah Project Material Item Tidak diizinkan');
  //                 // }

  //                 // if ($v["qty_assumption"] != $dt['qty_assumption']) {
  //                 //     $has_value = count(array_intersect(['dp-project_material-manage-qty_assumption'], $scopes));

  //                 //     if ($dt["is_locked"] || !$has_value || $dt["created_by"] != $auth_id)
  //                 //         throw new Exception('Ubah Jumlah Asumsi Tidak diizinkan');
  //                 // }
              

  //               $model_query->harga          += ($v["qty"] * $v["harga"]);

  //               $mq=FinPaymentReqDtl::where("id_uj", $model_query->id)
  //               ->where("ordinal", $v["key"])->where("p_change",false)->lockForUpdate()->first();
                
  //               $mqToCom = clone($mq);

  //               $mq->ordinal      = $v["ordinal"];
  //               $mq->xdesc        = $v["xdesc"];
  //               $mq->qty          = $v["qty"];
  //               $mq->harga        = $v["harga"];
  //               $mq->for_remarks  = $v["for_remarks"];
  //               $mq->p_change     = true;
  //               $mq->updated_at   = $t_stamp;
  //               $mq->updated_user = $this->admin_id;
  //               $mq->save();

  //               $SYSNOTE = MyLib::compareChange($mqToCom,$mq); 
  //               array_push( $SYSNOTES ,"Ordinal ".$v["key"]."\n".$SYSNOTE);

  //               if($v['for_remarks']){
  //                 $remarksign++;
  //               }
  //               // FinPaymentReqDtl::where("id_uj", $model_query->id)
  //               // ->where("ordinal", $v["key"])->where("p_change",false)->update([
  //               //     "ordinal"=>$v["ordinal"],
  //               //     "xdesc" => $v["xdesc"],
  //               //     "qty" => $v["qty"],
  //               //     "harga" => $v["harga"],
  //               //     "for_remarks" => $v["for_remarks"],
  //               //     // "status" => $v["status"],
  //               //     "p_change"=> true,
  //               //     "updated_at"=> $t_stamp,
  //               //     "updated_user"=> $this->admin_id,
  //               // ]);

  //             }

  //             // $ordinal++;
  //         } else if ($v["p_status"] == "Add") {

  //             // if (!count(array_intersect(['ap-project_material_item-add'], $scopes)))
  //             //     throw new Exception('Tambah Project Material Item Tidak diizinkan');

  //             // if (!count(array_intersect(['dp-project_material-manage-item_code'], $scopes))  && $v["item_code"] != "")
  //             //     throw new Exception('Tidak ada izin mengelola Kode item');
  //             array_push( $SYSNOTES ,"Ordinal ".$v["ordinal"]." [Insert]");
              
  //             $model_query->harga          += ($v["qty"] * $v["harga"]);
  //             FinPaymentReqDtl::insert([
  //                 'id_uj'             => $model_query->id,
  //                 'ordinal'           => $v["ordinal"],
  //                 'xdesc'             => $v['xdesc'],
  //                 'qty'               => $v["qty"],
  //                 'harga'             => $v['harga'],
  //                 "for_remarks"       => $v["for_remarks"],
  //                 // 'status'            => $v['status'],
  //                 "p_change"          => true,
  //                 'created_at'        => $t_stamp,
  //                 'created_user'      => $this->admin_id,
  //                 'updated_at'        => $t_stamp,
  //                 'updated_user'      => $this->admin_id,
  //             ]);

  //             if($v['for_remarks']){
  //               $remarksign++;
  //             }
  //             // $ordinal++;
  //         }
  //       }
  //     }

  //     if($remarksign == 0)
  //     throw new \Exception("Minimal Harus Memiliki 1 For Remarks Di Detail",1);

  //     //start for details2
  //     array_push( $SYSNOTES ,"Details PVR: \n");

  //     $data_from_db2 = FinPaymentReqDtl2::where('id_uj', $model_query->id)
  //     ->orderBy("ordinal", "asc")->lockForUpdate()
  //     ->get()->toArray();
      

  //     $in_keys2 = array_filter($details_in2, function ($x) {
  //         return isset($x["key"]);
  //     });

  //     $in_keys2 = array_map(function ($x) {
  //         return $x["key"];
  //     }, $in_keys2);

  //     $am_ordinal_db2 = array_map(function ($x) {
  //         return $x["ordinal"];
  //     }, $data_from_db2);

  //     if (count(array_diff($in_keys2, $am_ordinal_db2)) > 0 || count(array_diff($am_ordinal_db2, $in_keys2)) > 0) {
  //         throw new Exception('Ada ketidak sesuaian data, harap hubungi staff IT atau refresh browser anda',1);
  //     }

  //     $id_items2 = [];
  //     $ordinal2 = 0;
  //     $for_deletes2 = [];
  //     $for_edits2 = [];
  //     $for_adds2 = [];
  //     $data_to_processes2 = [];
  //     foreach ($details_in2 as $k => $v) {
  //       // $xdesc = $v['xdesc'] ? $v['xdesc'] : "";
        
  //       if (in_array($v["p_status"], ["Add", "Edit"])) {
  //         $unique2 = strtolower(trim($v['ac_account_code']).trim($v['description']));
  //         if (in_array($unique2, $id_items2) == 1) {
  //             throw new \Exception("Maaf terdapat Nama Item yang sama",1);
  //         }
  //         array_push($id_items2, $unique2);
  //       }

  //       if ($v["p_status"] !== "Remove") {
  //         $ordinal2++;
  //         $details_in2[$k]["ordinal"] = $ordinal2;
  //         if ($v["p_status"] == "Edit")
  //             array_unshift($for_edits2, $details_in2[$k]);
  //         elseif ($v["p_status"] == "Add")
  //             array_push($for_adds2, $details_in2[$k]);
  //       } else
  //           array_push($for_deletes2, $details_in2[$k]);
  //     }

  //     // if(count($details_in2) > 0 && count($for_adds2)==0 && count($for_edits2)==0){
  //     //   throw new \Exception("Item harus Diisi",1);
  //     // }

  //     $data_to_processes2 = array_merge($for_deletes2, $for_edits2, $for_adds2);
      
  //     $temp_amount_details2=0;

  //     foreach ($data_to_processes2 as $k => $v) {
  //       $index2 = false;

  //       if (isset($v["key"])) {
  //           $index2 = array_search($v["key"], $am_ordinal_db2);
  //       }
        
  //       if(in_array($v["p_status"],["Add","Edit"])){
  //         // $ordinal++;

  //         // if(($type=="transfer" || $type=="used")){
  //         //   $v['qty_in']=null;
  //         //   if($v['qty_out']==0) 
  //         //     throw new \Exception("Baris #" .$ordinal." Qty Out Tidak Boleh 0",1);
  //         // }

  //         // if($type=="in"){
  //         //   $v['qty_out']=null;
  //         //   if($v['qty_in']==0)
  //         //   throw new \Exception("Baris #" .$ordinal.".Qty In Tidak Boleh 0",1);
  //         // }


  //         // $indexItem = array_search($v['xdesc'], $items_id);
  //         // $qty_reminder = 0;

  //         // if ($indexItem !== false){
  //         //   $qty_reminder = $prev_checks[$indexItem]["qty_reminder"];
  //         // }
  
  //         // if(($type=="used" || $type=="transfer") && $qty_reminder - $v['qty_out'] < 0){
  //         //   // MyLog::logging($prev_checks);

  //         //   // throw new \Exception("Baris #" .$ordinal.".Qty melebihi stok : ".$qty_reminder, 1);
  //         // }
  //       }


  //       // $v["item_code"] = MyLib::emptyStrToNull($v["item_code"]);
  //       // $v["note"] = MyLib::emptyStrToNull($v["note"]);
  //       // $v["qty_assumption"] = MyLib::emptyStrToNull($v["qty_assumption"]);
  //       // $v["qty_realization"] = MyLib::emptyStrToNull($v["qty_realization"]);
  //       // $v["stock"] = MyLib::emptyStrToNull($v["stock"]);
  //       // $v["price_assumption"] = MyLib::emptyStrToNull($v["price_assumption"]);
  //       // $v["price_realization"] = MyLib::emptyStrToNull($v["price_realization"]);

  //       if ($v["p_status"] == "Remove") {

  //           if ($index2 === false) {
  //               throw new \Exception("Data yang ingin dihapus tidak ditemukan",1);
  //           } else {
  //               $dt2 = $data_from_db2[$index2];
  //               // $has_permit = count(array_intersect(['ap-project_material_item-remove'], $scopes));
  //               // if (!$dt["is_locked"] && $dt["created_by"] == $auth_id && $has_permit) {
  //               //     ProjectMaterial::where("project_no", $model_query->no)->where("ordinal", $dt["ordinal"])->delete();
  //               // }
  //               array_push( $SYSNOTES ,"Ordinal ".$dt2["ordinal"]." [Deleted]");
  //               FinPaymentReqDtl2::where("id_uj",$model_query->id)->where("ordinal",$dt2["ordinal"])->delete();
  //           }
  //       } else if ($v["p_status"] == "Edit") {

  //           if ($index2 === false) {
  //               throw new \Exception("Data yang ingin diubah tidak ditemukan" . $k,1);
  //           } else {
  //               // $dt = $data_from_db[$index];
  //               // $has_permit = count(array_intersect(['ap-project_material_item-edit'], $scopes));
  //               // if (!$has_permit) {
  //               //     throw new Exception('Ubah Project Material Item Tidak diizinkan');
  //               // }

  //               // if ($v["qty_assumption"] != $dt['qty_assumption']) {
  //               //     $has_value = count(array_intersect(['dp-project_material-manage-qty_assumption'], $scopes));

  //               //     if ($dt["is_locked"] || !$has_value || $dt["created_by"] != $auth_id)
  //               //         throw new Exception('Ubah Jumlah Asumsi Tidak diizinkan');
  //               // }
             
  //             $temp_amount_details2         += ($v["qty"] * $v["amount"]);

  //             $index_item = array_search($v['ac_account_code'], $listToCheck);
  //             $ac_account_id   = null;
  //             $ac_account_name = null;
  //             $ac_account_code = null;
  //             if ($index_item !== false){
  //               $ac_account_id    = $temp_ac_accounts[$index_item]['AccountID'];
  //               $ac_account_name    = $temp_ac_accounts[$index_item]['AccountName'];
  //               $ac_account_code    = $temp_ac_accounts[$index_item]['AccountCode'];
  //             }


  //             $mq=FinPaymentReqDtl2::where("id_uj", $model_query->id)
  //             ->where("ordinal", $v["key"])->where("p_change",false)->lockForUpdate()->first();
              
  //             $mqToCom = clone($mq);

  //             $mq->ordinal            = $v["ordinal"];
  //             $mq->qty                = $v["qty"];
  //             $mq->amount             = $v["amount"];
  //             $mq->ac_account_id      = $ac_account_id;
  //             $mq->ac_account_name    = $ac_account_name;
  //             $mq->ac_account_code    = $ac_account_code;
  //             $mq->description        = $v["description"];
  //             $mq->xfor               = $v["xfor"];
  //             $mq->p_change           = true;
  //             $mq->updated_at         = $t_stamp;
  //             $mq->updated_user       = $this->admin_id;
  //             $mq->save();

  //             $SYSNOTE = MyLib::compareChange($mqToCom,$mq); 
  //             array_push( $SYSNOTES ,"Ordinal ".$v["key"]."\n".$SYSNOTE);

  //             // FinPaymentReqDtl2::where("id_uj", $model_query->id)
  //             // ->where("ordinal", $v["key"])->where("p_change",false)->update([
  //             //     "ordinal"=>$v["ordinal"],
  //             //     "qty" => $v["qty"],
  //             //     "amount" => $v["amount"],
  //             //     "ac_account_id" => $ac_account_id,
  //             //     "ac_account_name" => $ac_account_name,
  //             //     "ac_account_code" => $ac_account_code,
  //             //     "description" => $v["description"],
  //             //     // "status" => $v["status"],
  //             //     "p_change"=> true,
  //             //     "updated_at"=> $t_stamp,
  //             //     "updated_user"=> $this->admin_id,
  //             // ]);

  //           }

  //           // $ordinal++;
  //       } else if ($v["p_status"] == "Add") {

  //           // if (!count(array_intersect(['ap-project_material_item-add'], $scopes)))
  //           //     throw new Exception('Tambah Project Material Item Tidak diizinkan');

  //           // if (!count(array_intersect(['dp-project_material-manage-item_code'], $scopes))  && $v["item_code"] != "")
  //           //     throw new Exception('Tidak ada izin mengelola Kode item');
  //           $temp_amount_details2         += ($v["qty"] * $v["amount"]);

  //           $index_item = array_search($v['ac_account_code'], $listToCheck);
  //           $ac_account_id   = null;
  //           $ac_account_name = null;
  //           $ac_account_code = null;
  //           if ($index_item !== false){
  //             $ac_account_id    = $temp_ac_accounts[$index_item]['AccountID'];
  //             $ac_account_name    = $temp_ac_accounts[$index_item]['AccountName'];
  //             $ac_account_code    = $temp_ac_accounts[$index_item]['AccountCode'];
  //           }
            
  //           array_push( $SYSNOTES ,"Ordinal ".$v["ordinal"]." [Insert]");

  //           FinPaymentReqDtl2::insert([
  //               'id_uj'             => $model_query->id,
  //               'ordinal'           => $v["ordinal"],
  //               "qty"               => $v["qty"],
  //               "amount"            => $v["amount"],
  //               "ac_account_id"     => $ac_account_id,
  //               "ac_account_name"   => $ac_account_name,
  //               "ac_account_code"   => $ac_account_code,
  //               "description"       => $v["description"],
  //               "xfor"              => $v["xfor"],
  //               // 'status'            => $v['status'],
  //               "p_change"          => true,
  //               'created_at'        => $t_stamp,
  //               'created_user'      => $this->admin_id,
  //               'updated_at'        => $t_stamp,
  //               'updated_user'      => $this->admin_id,
  //           ]);
  //           // $ordinal++;
  //       }
  //     }

  //     if($temp_amount_details2 > 0 && $model_query->harga!=$temp_amount_details2)
  //     throw new \Exception("Total Tidak Cocok harap Periksa Kembali",1);

  //   //end for details2
  //   if(MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic'],null,true)){
  //     $model_query->save();
  //     FinPaymentReqDtl::where('id_uj',$model_query->id)->update(["p_change"=>false]);
  //   }
  //     //start for details2
  //   FinPaymentReqDtl2::where('id_uj',$model_query->id)->update(["p_change"=>false]);
  //   //end for details2

  //     $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
  //     array_unshift( $SYSNOTES , $SYSNOTE );            
  //     MyLog::sys("fin_payment_req",$request->id,"update",implode("\n",$SYSNOTES));

  //     DB::commit();
  //     return response()->json([
  //       "message" => "Proses ubah data berhasil",
  //       "updated_at"=>$t_stamp
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     // return response()->json([
  //     //   "getCode" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     //   "message" => $e->getMessage(),
  //     // ], 400);

  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
      
  //     return response()->json([
  //       "message" => "Proses ubah data gagal",
  //     ], 400);
  //   }
  // }

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
    MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);

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
    MyAdmin::checkRole($this->role, ['SuperAdmin','Finance']);
    try {
      $model_query = TrxTrp::where("deleted",0)->where("req_deleted",0)
      ->where('val',1)->where('val1',1)->where('val2',1)->where('val4',1)
      ->where(function ($q){
        $q->where('val5',1)->orWhere('val6',1);
      })
      ->where(function ($q){
        $q->where(function ($q1){
          $q1->whereNotIn('jenis',['CPO','PK']);
        });
        $q->orWhere(function ($q1){
          $q1->whereIn('jenis',['CPO','PK'])->where('val3',1);
        });
      })
      ->where('received_payment',0)->where("payment_method_id",4)->get();
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
      $csv_data = "";
      $records = count($data['details']);

      foreach ($raw_data['details'] as $k => $v) {
        $jumlah = (int) $v['jumlah'];
        $csv_data .= "{$v['rek_no']};{$v['rek_name']};;;;IDR;{$jumlah};;;IBU;;;;;;;N;;;;;Y;;;;;;;;;;;;;;;;;BEN;1;E";
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

      $getFtp = Storage::disk('ftp')->get('Downloads/'.$model_query->filename.'.txt.ack');
      
      if($getFtp==null){
        throw new \Exception("Data Belum Siap Untuk Diambil",1);
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
          if (count($fields) >= 25) {
              $no = $fields[0];
              $nama = $fields[1];
              $status = $fields[24]; // Status ada di index 24
              $reason = $fields[25];
              
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
        }else{
          if($result[$index]['status']=="SUCCESS"){
            $st = "INQUIRY_SUCCESS";
            $stm = null;
          }else{
            $st = 'INQUIRY_FAILED';
            $stm = $result[$index]['reason'];
          }
        }

        // if($k==0){
        //   $had_failed++;
        //   $v->status = 'INQUIRY_FAILED';
        //   $v->failed_reason = 'Update Dl Nama Rek Nya';
        // }else{
        //   $v->status = 'INQUIRY_SUCCESS';
        // }

        $v->status = $st;
        $v->failed_reason = $stm;

        $v->save();
        $SYSNOTE = MyLib::compareChange($SYSOLD,$v);
        array_push($r_data,$v);
        array_push($SYSNOTES,$SYSNOTE);
      }

      if($had_failed==0){
        FinPaymentReqDtl::where('fin_payment_req_id',$request->id)->update([
          "status"=>"TRANSFER_PROCESS"
        ]);

        $r_data = array_map(function ($x) {
          $x->status = 'TRANSFER_PROCESS';
          return $x;
        },$r_data);

        MyLog::sys("fin_payment_req",$request->id,"update details","update status to TRANSFER_PROCESS");

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
        "message" => "Proses update data berhasil",
        "details" => $r_data
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
}
