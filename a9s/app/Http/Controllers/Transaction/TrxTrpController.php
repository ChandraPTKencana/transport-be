<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Helpers\MyLib;
use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;
use App\Models\MySql\TrxTrp;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Http\Requests\MySql\TrxTrpRequest;
use App\Http\Resources\MySql\TrxTrpResource;
use App\Models\HrmRevisiLokasi;
use App\Models\Stok\Item;
use App\Models\MySql\TrxTrpDetail;
use Exception;
use Illuminate\Support\Facades\DB;
use Image;
use File;
use PDF;
use Excel;

use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\IsUser;
use App\Exports\MyReport;
use App\Models\MySql\Ujalan;
use App\Models\MySql\UjalanDetail;

class TrxTrpController extends Controller
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

  public function index(Request $request, $download = false)
  {
 
    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 100; // Limit +> Much Data
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
    $model_query = new TrxTrp();
    if (!$download) {
      $model_query = $model_query->offset($offset)->limit($limit);
    }

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

      if (isset($sort_lists["pv_no"])) {
        $model_query = $model_query->orderBy("pv_no", $sort_lists["pv_no"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("pv_no",$sort_symbol,$first_row["pv_no"]);
        }
      }

      if (isset($sort_lists["ticket_a_no"])) {
        $model_query = $model_query->orderBy("ticket_a_no", $sort_lists["ticket_a_no"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("ticket_a_no",$sort_symbol,$first_row["ticket_a_no"]);
        }
      }

      if (isset($sort_lists["ticket_b_no"])) {
        $model_query = $model_query->orderBy("ticket_b_no", $sort_lists["ticket_b_no"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("ticket_b_no",$sort_symbol,$first_row["ticket_b_no"]);
        }
      }

      if (isset($sort_lists["supir"])) {
        $model_query = $model_query->orderBy("supir", $sort_lists["supir"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("supir",$sort_symbol,$first_row["supir"]);
        }
      }

      if (isset($sort_lists["kernet"])) {
        $model_query = $model_query->orderBy("kernet", $sort_lists["kernet"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("kernet",$sort_symbol,$first_row["kernet"]);
        }
      }

      if (isset($sort_lists["no_pol"])) {
        $model_query = $model_query->orderBy("no_pol", $sort_lists["no_pol"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("no_pol",$sort_symbol,$first_row["no_pol"]);
        }
      }

      if (isset($sort_lists["tanggal"])) {
        $model_query = $model_query->orderBy("tanggal", $sort_lists["tanggal"])->orderBy('id','DESC');
        if (count($first_row) > 0) {
          $model_query = $model_query->where("tanggal",$sort_symbol,$first_row["tanggal"])->orderBy('id','DESC');
        }
      }

      if (isset($sort_lists["cost_center_code"])) {
        $model_query = $model_query->orderBy("cost_center_code", $sort_lists["cost_center_code"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("cost_center_code",$sort_symbol,$first_row["cost_center_code"]);
        }
      }
      if (isset($sort_lists["cost_center_desc"])) {
        $model_query = $model_query->orderBy("cost_center_desc", $sort_lists["cost_center_desc"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("cost_center_desc",$sort_symbol,$first_row["cost_center_desc"]);
        }
      }
      if (isset($sort_lists["pvr_id"])) {
        $model_query = $model_query->orderBy("pvr_id", $sort_lists["pvr_id"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("pvr_id",$sort_symbol,$first_row["pvr_id"]);
        }
      }
      if (isset($sort_lists["pvr_no"])) {
        $model_query = $model_query->orderBy("pvr_no", $sort_lists["pvr_no"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("pvr_no",$sort_symbol,$first_row["pvr_no"]);
        }
      }

      
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
      $model_query = $model_query->orderBy('tanggal', 'DESC')->orderBy('id','DESC');
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

          if (isset($like_lists["pv_no"])) {
            $q->orWhere("pv_no", "like", $like_lists["pv_no"]);
          }

          if (isset($like_lists["ticket_a_no"])) {
            $q->orWhere("ticket_a_no", "like", $like_lists["ticket_a_no"]);
          }

          if (isset($like_lists["ticket_b_no"])) {
            $q->orWhere("ticket_b_no", "like", $like_lists["ticket_b_no"]);
          }

          if (isset($like_lists["supir"])) {
            $q->orWhere("supir", "like", $like_lists["supir"]);
          }
          if (isset($like_lists["kernet"])) {
            $q->orWhere("kernet", "like", $like_lists["kernet"]);
          }
          if (isset($like_lists["no_pol"])) {
            $q->orWhere("no_pol", "like", $like_lists["no_pol"]);
          }
          if (isset($like_lists["tanggal"])) {
            $q->orWhere("tanggal", "like", $like_lists["tanggal"]);
          }
          if (isset($like_lists["cost_center_code"])) {
            $q->orWhere("cost_center_code", "like", $like_lists["cost_center_code"]);
          }
          if (isset($like_lists["cost_center_desc"])) {
            $q->orWhere("cost_center_desc", "like", $like_lists["cost_center_desc"]);
          }
          if (isset($like_lists["pvr_id"])) {
            $q->orWhere("pvr_id", "like", $like_lists["pvr_id"]);
          }
          if (isset($like_lists["pvr_no"])) {
            $q->orWhere("pvr_no", "like", $like_lists["pvr_no"]);
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
      
      $model_query = $model_query->whereBetween("tanggal",[$request->date_from,$request->date_to]);
    }

    $model_query = $model_query->where("deleted",0)->with(['val_by','val1_by'])->get();

    return response()->json([
      "data" => TrxTrpResource::collection($model_query),
    ], 200);
  }

  public function show(TrxTrpRequest $request)
  {
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport','Logistic']);

    $model_query = TrxTrp::where("deleted",0)->with(['val_by','val1_by'])->find($request->id);
    return response()->json([
      "data" => new TrxTrpResource($model_query),
    ], 200);
  }

  public function store(TrxTrpRequest $request)
  {
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport','Logistic']);
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);

    $t_stamp = date("Y-m-d H:i:s");
    $online_status=$request->online_status;
    DB::beginTransaction();
    try {
      // if(TrxTrp::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar");

      $model_query                  = new TrxTrp();      
      $model_query->tanggal         = $request->tanggal;

      $rejenis = ($request->jenis=="TBSK" ? "TBS" : $request->jenis );
      $ujalan = \App\Models\MySql\Ujalan::where("id",$request->id_uj)
      ->where("jenis",$rejenis)
      ->where("deleted",0)
      ->lockForUpdate()
      ->first();

      if(!$ujalan) 
      throw new \Exception("Silahkan Isi Data Ujalan Dengan Benar",1);

      $model_query->id_uj           = $ujalan->id;
      $model_query->jenis           = $request->jenis;
      $model_query->xto             = $ujalan->xto;
      $model_query->tipe            = $ujalan->tipe;
      $model_query->amount          = $ujalan->harga;
      
      if($online_status=="true"){
        if($request->pv_id){

          $get_data_pv = DB::connection('sqlsrv')->table('fi_arap')
          ->select('fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid',DB::raw('SUM(fi_arapextraitems.Amount) as total_amount'))
          ->where("fi_arap.VoucherID",$request->pv_id)
          ->leftJoin('fi_arapextraitems', 'fi_arap.VoucherID', '=', 'fi_arapextraitems.VoucherID')
          ->groupBy(['fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid'])
          ->first();

          if(!$get_data_pv) 
          throw new \Exception("Data PV tidak terdaftar",1);
          
          $model_query->pv_id =  $request->pv_id;
          if(\App\Models\MySql\TrxTrp::where("pv_id",$get_data_pv->VoucherID)->first())
          throw new \Exception("Data PV telah digunakan",1);

          $model_query->pv_no =  $get_data_pv->VoucherNo;
          $model_query->pv_total =  $get_data_pv->total_amount;
        }

        if($request->ticket_a_id){

          $get_data_ticket = DB::connection('sqlsrv')->table('palm_tickets')
          ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo','DateTimeIn','DateTimeOut')
          ->where("TicketID",$request->ticket_a_id);

          if($request->jenis=="CPO"){
            $get_data_ticket =$get_data_ticket->where('ProductName',"CPO");
          }else if($request->jenis=="PK"){
            $get_data_ticket =$get_data_ticket->where('ProductName',"KERNEL");
          }else{ 
            $get_data_ticket =$get_data_ticket->where('ProductName',"MTBS");
          }
          $get_data_ticket =$get_data_ticket->first();

          if(!$get_data_ticket) 
          throw new \Exception("Data Ticket tidak terdaftar",1);

          if(\App\Models\MySql\TrxTrp::where("ticket_a_id",$get_data_ticket->TicketID)->first())
          throw new \Exception("Data Ticket telah digunakan",1);

          $model_query->ticket_a_id =  $request->ticket_a_id;
          $model_query->ticket_a_no =  $get_data_ticket->TicketNo;
          $model_query->ticket_a_bruto =  $get_data_ticket->Bruto;
          $model_query->ticket_a_tara =  $get_data_ticket->Tara;
          $model_query->ticket_a_netto =  $get_data_ticket->Bruto - $get_data_ticket->Tara;
          $model_query->ticket_a_supir =  $get_data_ticket->NamaSupir;
          $model_query->ticket_a_no_pol =  $get_data_ticket->VehicleNo;
          $model_query->ticket_a_in_at =  $get_data_ticket->DateTimeIn;
          $model_query->ticket_a_out_at =  $get_data_ticket->DateTimeOut;
        }

        if($request->ticket_b_id){

          $get_data_ticket = DB::connection('sqlsrv')->table('palm_tickets')
          ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo','DateTimeIn','DateTimeOut')
          ->where("TicketID",$request->ticket_b_id);
          
          if($request->jenis=="TBS"){
            $get_data_ticket=$get_data_ticket->where('ProductName',"RTBS");
          }else {
            $get_data_ticket=$get_data_ticket->where('ProductName',"TBS");
          }

          $get_data_ticket=$get_data_ticket->first();

          if(!$get_data_ticket) 
          throw new \Exception("Data Ticket tidak terdaftar",1);

          if(\App\Models\MySql\TrxTrp::where("ticket_b_id",$get_data_ticket->TicketID)->first())
          throw new \Exception("Data Ticket telah digunakan",1);

          $model_query->ticket_b_id =  $request->ticket_b_id;
          $model_query->ticket_b_no =  $get_data_ticket->TicketNo;
          $model_query->ticket_b_bruto =  $get_data_ticket->Bruto;
          $model_query->ticket_b_tara =  $get_data_ticket->Tara;
          $model_query->ticket_b_netto =  $get_data_ticket->Bruto - $get_data_ticket->Tara;
          $model_query->ticket_b_supir =  $get_data_ticket->NamaSupir;
          $model_query->ticket_b_no_pol =  $get_data_ticket->VehicleNo;
          $model_query->ticket_b_in_at =  $get_data_ticket->DateTimeIn;
          $model_query->ticket_b_out_at =  $get_data_ticket->DateTimeOut;
        }else{
          $model_query->ticket_b_bruto =  MyLib::emptyStrToNull($request->ticket_b_bruto);
          $model_query->ticket_b_tara =  MyLib::emptyStrToNull($request->ticket_b_tara);
          $model_query->ticket_b_netto =  MyLib::emptyStrToNull($request->ticket_b_bruto - $request->ticket_b_tara);
          $model_query->ticket_b_in_at =  MyLib::emptyStrToNull($request->ticket_b_in_at);
          $model_query->ticket_b_out_at =  MyLib::emptyStrToNull($request->ticket_b_out_at);
        }

        if($request->cost_center_code){
          $list_cost_center = DB::connection('sqlsrv')->table("AC_CostCenterNames")
          ->select('CostCenter','Description')
          ->where('CostCenter',$request->cost_center_code)
          ->first();
          if(!$list_cost_center)
          throw new \Exception(json_encode(["cost_center_code"=>["Cost Center Code Tidak Ditemukan"]]), 422);
        
          $model_query->cost_center_code = $list_cost_center->CostCenter;
          $model_query->cost_center_desc = $list_cost_center->Description;
        }
      }

      $model_query->supir=$request->supir;
      $model_query->kernet=MyLib::emptyStrToNull($request->kernet);
      $model_query->no_pol=$request->no_pol;
      
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

      $model_query->save();

      $miniError="";
      $callGet=[
        "pvr_id" => "",
        "pvr_no" => "",
        "pvr_total" => 0,
        "pvr_had_detail" => "",
        "updated_at"=>$t_stamp
      ];
      try {
        if($request->cost_center_code && $online_status=="true"){
          $callGet=$this->genPVR($model_query->id);
          $miniError="PVR Berhasil Dibuat.";
        }
      } catch (\Exception $e) {
        if ($e->getCode() == 1) {
          $miniError=". Namun PVR Batal Dibuat: ".$e->getMessage();
        }else{
          $miniError=". Namun PVR Batal Dibuat. Akses Jaringan Gagal";
        }
      }

      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil".$miniError,
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "pvr_id" => $callGet["pvr_id"],
        "pvr_no" => $callGet["pvr_no"],
        "pvr_total" => $callGet["pvr_total"],
        "pvr_had_detail" => $callGet["pvr_had_detail"],
        "updated_at"=>$callGet["updated_at"]
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      // return response()->json([
      //   "message" => $e->getMessage(),
      //   "code" => $e->getCode(),
      //   "line" => $e->getLine(),
      // ], 400);
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      if ($e->getCode() == 422) {
        return response()->json(json_decode($e->getMessage()), 422);
      }
      return response()->json([
        "message" => "Proses tambah data gagal",
      ], 400);
    }
  }

  public function update(TrxTrpRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport','Logistic']);
    
    $t_stamp = date("Y-m-d H:i:s");
    $online_status=$request->online_status;

    DB::beginTransaction();
    try {
      $model_query             = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      if($model_query->val==1 || $model_query->val1==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      $model_query->tanggal         = $request->tanggal;

      $rejenis = ($request->jenis=="TBSK" ? "TBS" : $request->jenis );
      $ujalan = \App\Models\MySql\Ujalan::where("id",$request->id_uj)
      ->where("jenis",$rejenis)
      ->where("deleted",0)
      ->lockForUpdate()
      ->first();

      if(!$ujalan) 
      throw new \Exception("Silahkan Isi Data Ujalan Dengan Benar",1);

      if($ujalan->xto!=$request->xto)
      throw new \Exception("Silahkan Isi Tipe Ujalan Dengan Benar",1);

      $model_query->id_uj           = $ujalan->id;
      $model_query->jenis           = $request->jenis;
      $model_query->xto             = $ujalan->xto;
      $model_query->tipe            = $ujalan->tipe;
      $model_query->amount          = $ujalan->harga;
      if($online_status=="true"){
        if($request->pv_id && $model_query->pvr_id==null){

          $get_data_pv = DB::connection('sqlsrv')->table('fi_arap')
          ->select('fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid',DB::raw('SUM(fi_arapextraitems.Amount) as total_amount'))
          ->where("fi_arap.VoucherID",$request->pv_id)
          ->leftJoin('fi_arapextraitems', 'fi_arap.VoucherID', '=', 'fi_arapextraitems.VoucherID')
          ->groupBy(['fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid'])
          ->first();

          if(!$get_data_pv) 
          throw new \Exception("Data PV tidak terdaftar",1);
          
          if(\App\Models\MySql\TrxTrp::where("pv_id",$get_data_pv->VoucherID)
          ->where("id","!=",$model_query->id)->first())
          throw new \Exception("Data PV telah digunakan",1);


          $model_query->pv_id =  $request->pv_id;
          $model_query->pv_no =  $get_data_pv->VoucherNo;
          $model_query->pv_total =  $get_data_pv->total_amount;
        }

        if($request->ticket_a_id){

          $get_data_ticket = DB::connection('sqlsrv')->table('palm_tickets')
          ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo','DateTimeIn','DateTimeOut')
          ->where("TicketID",$request->ticket_a_id);
          if($request->jenis=="CPO"){
            $get_data_ticket =$get_data_ticket->where('ProductName',"CPO");
          }else if($request->jenis=="PK"){
            $get_data_ticket =$get_data_ticket->where('ProductName',"KERNEL");
          }else{ 
            $get_data_ticket =$get_data_ticket->where('ProductName',"MTBS");
          }
          $get_data_ticket =$get_data_ticket->first();


          if(!$get_data_ticket) 
          throw new \Exception("Data Ticket tidak terdaftar",1);

          if(\App\Models\MySql\TrxTrp::where("ticket_a_id",$get_data_ticket->TicketID)
          ->where("id","!=",$model_query->id)->first())
          throw new \Exception("Data Ticket telah digunakan",1);

          $model_query->ticket_a_id =  $request->ticket_a_id;
          $model_query->ticket_a_no =  $get_data_ticket->TicketNo;
          $model_query->ticket_a_bruto =  $get_data_ticket->Bruto;
          $model_query->ticket_a_tara =  $get_data_ticket->Tara;
          $model_query->ticket_a_netto =  $get_data_ticket->Bruto - $get_data_ticket->Tara;
          $model_query->ticket_a_supir =  $get_data_ticket->NamaSupir;
          $model_query->ticket_a_no_pol =  $get_data_ticket->VehicleNo;
          $model_query->ticket_a_in_at =  $get_data_ticket->DateTimeIn;
          $model_query->ticket_a_out_at =  $get_data_ticket->DateTimeOut;       
        }else{
          $model_query->ticket_a_id =  null;
          $model_query->ticket_a_no =  null;
          $model_query->ticket_a_bruto =  null;
          $model_query->ticket_a_tara =  null;
          $model_query->ticket_a_netto =  null;
          $model_query->ticket_a_supir =  null;
          $model_query->ticket_a_no_pol =  null;
          $model_query->ticket_a_in_at =  null;
          $model_query->ticket_a_out_at =  null;
        }

        if($request->ticket_b_id){

          $get_data_ticket = DB::connection('sqlsrv')->table('palm_tickets')
          ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo','DateTimeIn','DateTimeOut')
          ->where("TicketID",$request->ticket_b_id);
          
          if($request->jenis=="TBS"){
            $get_data_ticket=$get_data_ticket->where('ProductName',"RTBS");
          }else {
            $get_data_ticket=$get_data_ticket->where('ProductName',"TBS");
          }

          $get_data_ticket=$get_data_ticket->first();

          if(!$get_data_ticket) 
          throw new \Exception("Data Ticket tidak terdaftar",1);

          if(\App\Models\MySql\TrxTrp::where("ticket_b_id",$get_data_ticket->TicketID)
          ->where("id","!=",$model_query->id)->first())
          throw new \Exception("Data Ticket telah digunakan",1);

          $model_query->ticket_b_id =  $request->ticket_b_id;
          $model_query->ticket_b_no =  $get_data_ticket->TicketNo;
          $model_query->ticket_b_bruto =  $get_data_ticket->Bruto;
          $model_query->ticket_b_tara =  $get_data_ticket->Tara;
          $model_query->ticket_b_netto =  $get_data_ticket->Bruto - $get_data_ticket->Tara;
          $model_query->ticket_b_supir =  $get_data_ticket->NamaSupir;
          $model_query->ticket_b_no_pol =  $get_data_ticket->VehicleNo;
          $model_query->ticket_b_in_at =  $get_data_ticket->DateTimeIn;
          $model_query->ticket_b_out_at =  $get_data_ticket->DateTimeOut;
        }else{
          $model_query->ticket_b_id =  null;
          $model_query->ticket_b_no =  null;
          $model_query->ticket_b_bruto =  MyLib::emptyStrToNull($request->ticket_b_bruto);
          $model_query->ticket_b_tara =  MyLib::emptyStrToNull($request->ticket_b_tara);
          $model_query->ticket_b_netto =  MyLib::emptyStrToNull($request->ticket_b_bruto - $request->ticket_b_tara);
          $model_query->ticket_b_supir =  null;
          $model_query->ticket_b_no_pol =  null;
          $model_query->ticket_b_in_at =  MyLib::emptyStrToNull($request->ticket_b_in_at);
          $model_query->ticket_b_out_at =  MyLib::emptyStrToNull($request->ticket_b_out_at);
        }

        if($model_query->pvr_id==null){
          if($request->cost_center_code){  
            $list_cost_center = DB::connection('sqlsrv')->table("AC_CostCenterNames")
            ->select('CostCenter','Description')
            ->where('CostCenter',$request->cost_center_code)
            ->first();
            if(!$list_cost_center)
            throw new \Exception(json_encode(["cost_center_code"=>["Cost Center Code Tidak Ditemukan"]]), 422);
          
            $model_query->cost_center_code = $list_cost_center->CostCenter;
            $model_query->cost_center_desc = $list_cost_center->Description;
          }else{
            $model_query->cost_center_code =null;
            $model_query->cost_center_desc =null;
          } 
        }
      }

      $model_query->supir=$request->supir;
      $model_query->kernet=MyLib::emptyStrToNull($request->kernet);
      $model_query->no_pol=$request->no_pol;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->save();

      $miniError="";
      $callGet=[
        "pvr_id" => "",
        "pvr_no" => "",
        "pvr_total" => 0,
        "pvr_had_detail" => "",
        "updated_at"=>$t_stamp
      ];
      try {
        if($model_query->cost_center_code && $model_query->pvr_id==null && $online_status=="true"){
          $callGet=$this->genPVR($model_query->id);
          $miniError="PVR Berhasil Dibuat.";
        }
      } catch (\Exception $e) {
        if ($e->getCode() == 1) {
          $miniError=". Namun PVR Batal Dibuat: ".$e->getMessage();
        }else{
          $miniError=". Namun PVR Batal Dibuat. Akses Jaringan Gagal";
        }
        //throw $th;
      }

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil".$miniError,
        "pvr_id" => $callGet["pvr_id"],
        "pvr_no" => $callGet["pvr_no"],
        "pvr_total" => $callGet["pvr_total"],
        "pvr_had_detail" => $callGet["pvr_had_detail"],
        "updated_at"=>$callGet["updated_at"]
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
      if ($e->getCode() == 422) {
        return response()->json(json_decode($e->getMessage()), 422);
      }
      return response()->json([
        "message" => "Proses ubah data gagal",
      ], 400);
    }
  }

  public function delete(TrxTrpRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

    DB::beginTransaction();

    try {
      $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      
      if($model_query->val==1 || $model_query->val1==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);


      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
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
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      return response()->json([
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  }

  function previewFile(Request $request){
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport','Logistic']);

    set_time_limit(0);

    $trx_trp = TrxTrp::find($request->id);
    $ujalan = \App\Models\MySql\Ujalan::where("id",$trx_trp->id_uj)->first();
    $details = \App\Models\MySql\UjalanDetail::where("id_uj",$trx_trp->id_uj)->orderBy("ordinal","asc")->get();
    // $total = 0;

    // foreach ($details as $key => $value) {
    //   $total += $value["qty"] * $value["harga"];
    // }

    $sendData = [
      "id"=>$trx_trp->id,
      "id_uj"=>$trx_trp->id_uj,
      "no_pol"=>$trx_trp->no_pol,
      "supir"=>$trx_trp->supir,
      "kernet"=>$trx_trp->kernet,
      "tanggal"=>$trx_trp->tanggal,
      "created_at"=>$trx_trp->created_at,
      "asal"=>env("app_name"),
      "xto"=>$trx_trp->xto,
      "jenis"=>$trx_trp->jenis,
      "details"=>$details,
      "total"=>$ujalan->harga,
      "user_1"=>$this->admin->the_user->username,
    ];   
    
    // $date = new \DateTime();
    // $filename = $date->format("YmdHis");
    // Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
    // $pdf = PDF::loadView('pdf.trx_trp_ujalan', $sendData)->setPaper('a4', 'portrait');
    
    $html = view("html.trx_trp_ujalan",$sendData);
  
    // $mime = MyLib::mime("pdf");
    // $bs64 = base64_encode($pdf->download($filename . "." . $mime["ext"]));
  
    $result = [
      // "contentType" => $mime["contentType"],
      // "data" => $bs64,
      // "dataBase64" => $mime["dataBase64"] . $bs64,
      // "filename" => $filename . "." . $mime["ext"],
      "html"=>$html->render()
    ];
    return $result;
  }

  function previewFiles(Request $request){
    MyAdmin::checkRole($this->role, ['SuperAdmin','Finance','Marketing','Logistic','MIS']);

    // set_time_limit(0);

    // $rules = [
    //   'date_from' => "required|date_format:Y-m-d H:i:s",
    // ];

    // $messages = [
    //   'date_from.required' => 'Date From is required',
    //   'date_from.date_format' => 'Please Select Date From',
    // ];

    // $validator = \Validator::make($request->all(), $rules, $messages);

    // if ($validator->fails()) {
    //   throw new ValidationException($validator);
    // }


    // // Change some request value
    // $request['period'] = "Daily";

    // $date_from = $request->date_from;
    // $d_from = date("Y-m", MyLib::manualMillis($date_from) / 1000) . "-01 00:00:00";
    // $date_f = new \DateTime($d_from);

    // $start = clone $date_f;
    // $start->add(new \DateInterval('P1M'));
    // $start->sub(new \DateInterval('P1D'));
    // $x = $start->format("Y-m-d H:i:s");

    // $request['date_from'] = $d_from;
    // $request['date_to'] = $x;
    // return response()->json(["data"=>[$d_from,$x]],200);

    set_time_limit(0);
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    $data = $ori["data"];
    
    // $additional = $ori["additional"];


    // $date = new \DateTime();
    // $filename = $date->format("YmdHis") . "-" . $additional["company_name"] . "[" . $additional["date_from"] . "-" . $additional["date_to"] . "]";
    // // $filename=$date->format("YmdHis");

    // // return response()->json(["message"=>$filename],200);

    // $mime = MyLib::mime("csv");
    // $bs64 = base64_encode(Excel::raw(new MyReport($data, 'report.sensor_get_data_by_location'), $mime["exportType"]));
    // $mime = MyLib::mime("xlsx");
    // $bs64 = base64_encode(Excel::raw(new MyReport($data, 'report.tracking_info2'), $mime["exportType"]));

    

    // $sendData = [
    //   'pag_no'  => $pag->no,
    //   'created_at'    => $pag->created_at,
    //   'updated_at'    => $pag->updated_at,
    //   'proyek'  => $pag->project ?? "",
    //   'need'    => $pag->need,
    //   'part'    => $pag->part,
    //   'datas'   => $pag->pag_details,
    //   'title'   => "PENGAMBILAN BARANG GUDANG (PAG)"
    // ];
    // dd($sendData);

    $shows=["tanggal","no_pol","jenis","xto","amount","pv_total"];
    if($this->role != "Finance"){
      $shows = array_merge($shows,[
        'ticket_a_out_at','ticket_b_in_at',
        'ticket_a_bruto','ticket_b_bruto','ticket_b_a_bruto','ticket_b_a_bruto_persen',
        'ticket_a_tara','ticket_b_tara','ticket_b_a_tara','ticket_b_a_tara_persen',
        'ticket_a_netto','ticket_b_netto','ticket_b_a_netto','ticket_b_a_netto_persen',
      ]);
    }
    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {
      $ticket_a_bruto = (float)$value["ticket_a_bruto"];
      $ticket_b_bruto = (float)$value["ticket_b_bruto"];
      list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($value["ticket_a_bruto"],$value["ticket_b_bruto"]);
      $ticket_a_tara = (float)$value["ticket_a_tara"];
      $ticket_b_tara = (float)$value["ticket_b_tara"];
      list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($value["ticket_a_tara"],$value["ticket_b_tara"]);
      $ticket_a_netto = (float)$value["ticket_a_netto"];
      $ticket_b_netto = (float)$value["ticket_b_netto"];
      list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($value["ticket_a_netto"],$value["ticket_b_netto"]);

      $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
      $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
      $value['ticket_a_bruto']=number_format($ticket_a_bruto, 0,',','.');
      $value['ticket_b_bruto']=number_format($ticket_b_bruto, 0,',','.');
      $value['ticket_b_a_bruto']=block_negative(number_format($ticket_b_a_bruto, 0,',','.'));
      $value['ticket_b_a_bruto_persen']=number_format($ticket_b_a_bruto_persen, 2,',','.');
      $value['ticket_a_tara']=number_format($ticket_a_tara, 0,',','.');
      $value['ticket_b_tara']=number_format($ticket_b_tara, 0,',','.');
      $value['ticket_b_a_tara']=block_negative(number_format($ticket_b_a_tara, 0,',','.'));
      $value['ticket_b_a_tara_persen']=number_format($ticket_b_a_tara_persen, 2,',','.');
      $value['ticket_a_netto']=number_format($ticket_a_netto, 0,',','.');
      $value['ticket_b_netto']=number_format($ticket_b_netto, 0,',','.');
      $value['ticket_b_a_netto']=block_negative(number_format($ticket_b_a_netto, 0,',','.'));
      $value['ticket_b_a_netto_persen']=number_format($ticket_b_a_netto_persen, 2,',','.');
      $value['amount']=number_format((float)$value["amount"], 0,',','.');
      $value['pv_total']=number_format((float)$value["pv_total"], 0,',','.');
      array_push($newDetails,$value);
    }

    // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

    $date = new \DateTime();
    $filename = $date->format("YmdHis");
    Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
    $pdf = PDF::loadView('pdf.trx_trp', ["data"=>$newDetails,"shows"=>$shows])->setPaper('a4', 'landscape');


    $mime = MyLib::mime("pdf");
    $bs64 = base64_encode($pdf->download($filename . "." . $mime["ext"]));

    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }

  function downloadExcel(Request $request){
    MyAdmin::checkRole($this->role, ['SuperAdmin','Finance','Marketing','Logistic','MIS']);

    set_time_limit(0);
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    $data = $ori["data"];
    
    $shows=["tanggal","no_pol","jenis","xto","amount","pv_total"];
    if($this->role != "Finance"){
      $shows = array_merge($shows,[
        'ticket_a_out_at','ticket_b_in_at',
        'ticket_a_bruto','ticket_b_bruto','ticket_b_a_bruto','ticket_b_a_bruto_persen',
        'ticket_a_tara','ticket_b_tara','ticket_b_a_tara','ticket_b_a_tara_persen',
        'ticket_a_netto','ticket_b_netto','ticket_b_a_netto','ticket_b_a_netto_persen',
      ]);
    }

    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {
      $ticket_a_bruto = (float)$value["ticket_a_bruto"];
      $ticket_b_bruto = (float)$value["ticket_b_bruto"];
      list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($value["ticket_a_bruto"],$value["ticket_b_bruto"]);
      $ticket_a_tara = (float)$value["ticket_a_tara"];
      $ticket_b_tara = (float)$value["ticket_b_tara"];
      list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($value["ticket_a_tara"],$value["ticket_b_tara"]);
      $ticket_a_netto = (float)$value["ticket_a_netto"];
      $ticket_b_netto = (float)$value["ticket_b_netto"];
      list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($value["ticket_a_netto"],$value["ticket_b_netto"]);

      $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
      $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
      $value['ticket_a_bruto']=$ticket_a_bruto;
      $value['ticket_b_bruto']=$ticket_b_bruto;
      $value['ticket_b_a_bruto']=$ticket_b_a_bruto;
      $value['ticket_b_a_bruto_persen']=$ticket_b_a_bruto_persen;
      $value['ticket_a_tara']=$ticket_a_tara;
      $value['ticket_b_tara']=$ticket_b_tara;
      $value['ticket_b_a_tara']=$ticket_b_a_tara;
      $value['ticket_b_a_tara_persen']=$ticket_b_a_tara_persen;
      $value['ticket_a_netto']=$ticket_a_netto;
      $value['ticket_b_netto']=$ticket_b_netto;
      $value['ticket_b_a_netto']=$ticket_b_a_netto;
      $value['ticket_b_a_netto_persen']=$ticket_b_a_netto_persen;
      $value['amount']=$value["amount"];
      $value['pv_total']=$value["pv_total"];
      array_push($newDetails,$value);
    }

    // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-trx_trp'."[".$request["date_from"]."-".$request["date_to"]."]";

    $mime=MyLib::mime("xlsx");
    // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails,"shows"=>$shows],'excel.trx_trp'), $mime["exportType"]));


    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }


  function validasi(Request $request){
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport','Logistic']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
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
      $model_query = TrxTrp::find($request->id);
      if($model_query->val && $model_query->val1){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }
  
      $index_item = array_search($this->role, ["SuperAdmin","Logistic"]);    
      if ($index_item !== false){
        if(!$model_query->val1){
          $model_query->val1 = 1;
          $model_query->val1_user = $this->admin_id;
          $model_query->val1_at = $t_stamp;
        }
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

  function genPersen($a,$b){
    $a = (float)$a;
    $b = (float)$b;
    
    $diff=(float)($b-$a);
    $bigger = $diff > 0 ? $b  : $a;

    if($bigger==0) return [$diff,0];

    return [$diff , $diff / $bigger * 100];
  }

  public function doGenPVR(Request $request){
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport','Logistic']);
    $rules = [
      'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
      'online_status' => "required",
    ];

    $messages = [
      'id.required' => 'ID tidak boleh kosong',
      'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = \Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }
    $online_status=$request->online_status;
    if($online_status!="true")
    return response()->json([
      "message" => "Mode Harus Online",
    ], 400);

    $miniError="";
    try {
      $callGet = $this->genPVR($request->id);
      return response()->json($callGet, 200);
    } catch (\Exception $e) {
      if ($e->getCode() == 1) {
        $miniError="PVR Batal Dibuat: ".$e->getMessage();
      }else{
        $miniError="PVR Batal Dibuat. Akses Jaringan Gagal";
      }
      return response()->json([
        "message" => $miniError,
      ], 400);
    }
  }

  public function genPVR($trx_trp_id){
    $t_stamp = date("Y-m-d H:i:s");
    $trx_trp = TrxTrp::where("id",$trx_trp_id)->first();
    if(!$trx_trp){
      throw new \Exception("Karna Transaksi tidak ditemukan",1);
    }

    if($trx_trp->pvr_had_detail==1) throw new \Exception("Karna PVR sudah selesai dibuat",1);
    if($trx_trp->cost_center_code==null) throw new \Exception("Cost Center Code belum diisi",1);
    if($trx_trp->pv_id!=null) throw new \Exception("Karna PV sudah diisi",1);
      
    $supir = $trx_trp->supir;
    $no_pol = $trx_trp->no_pol;
    $kernet = $trx_trp->kernet;
    $associate_name="TESTING SAJA ".$no_pol."(S)".$supir.($kernet?"(K)".$kernet:"(Tanpa Kernet)"); // max 80char

    $remarks = $associate_name."BIAYA UANG JALAN ".$trx_trp->jenis." ".env("app_name")."-".$trx_trp->xto." P/".date("d-m-y",strtotime($trx_trp->tanggal))." Rincian:";
    // $ujalan=Ujalan::where("id",$trx_trp->id_uj)->first();
    $ujalan_detail = UjalanDetail::where("id_uj",$trx_trp->id_uj)->where("ordinal",1)->first();
    if(!$ujalan_detail)
    throw new \Exception("Detail Ujalan Harus diisi terlebih dahulu",1);
    
    $ujalan_details2 = \App\Models\MySql\UjalanDetail2::where("id_uj",$trx_trp->id_uj)->get();
    if(count($ujalan_details2)==0)
    throw new \Exception("Detail PVR Harus diisi terlebih dahulu",1);

    $remarks.=" ".$ujalan_detail->xdesc." ".number_format($ujalan_detail->qty, 0,',','.')."x".number_format($ujalan_detail->harga, 0,',','.')."=".number_format($ujalan_detail->qty*$ujalan_detail->harga, 0,',','.').";";

    if(strlen($associate_name)>80){
      $associate_name = substr($associate_name,0,80);
    }

    $bank_account_code="01.100.005";
    
    $get_data_pv = DB::connection('sqlsrv')->table('FI_BankAccounts')
    ->select('BankAccountID')
    ->where("bankaccountcode",$bank_account_code)
    ->first();

    $bank_account_id = $get_data_pv->BankAccountID;
    
    // @VoucherID INT = 0,
    $voucher_no = "(AUTO)";
    $voucher_type = "TRP";
    $voucher_date = date("Y-m-d");

    $income_or_expense = 1;
    $currency_id = 1;
    $payment_method="Cash";
    $check_no=$bank_name=$account_no= '';
    $check_due_date= null;

    $sql = \App\Models\MySql\UjalanDetail2::selectRaw('SUM(qty*amount) as total')->where("id_uj",$trx_trp->id_uj)->first();
    $amount_paid = $sql->total; // call from child
    $exclude_in_ARAP = 0;
    $login_name = $this->admin->the_user->username;
    $expense_or_revenue_type_id=0;
    $confidential=1;
    $PVR_source = 'gtsource'; // digenerate melalui program
    $PVR_source_id = $trx_trp_id; //ambil id trx
      // DB::select("exec USP_FI_APRequest_Update(0,'(AUTO)','TRP',1,1,1,0,)",array($ts,$param2));
    $VoucherID = -1;

    $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
    ->select('VoucherID','VoucherNo','AmountPaid')
    ->where("PVRSource",$PVR_source)
    ->where("PVRSourceID",$trx_trp->id)
    ->where("Void",0)
    ->first();

    if(!$pvr){
      // $myData = DB::connection('sqlsrv')->update("SET NOCOUNT ON;exec USP_FI_APRequest_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
      $myData = DB::connection('sqlsrv')->update("exec USP_FI_APRequest_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
      @VoucherDate=:voucher_date,@IncomeOrExpense=:income_or_expense,@CurrencyID=:currency_id,@AssociateName=:associate_name,
      @BankAccountID=:bank_account_id,@PaymentMethod=:payment_method,@CheckNo=:check_no,@CheckDueDate=:check_due_date,
      @BankName=:bank_name,@AmountPaid=:amount_paid,@AccountNo=:account_no,@Remarks=:remarks,@ExcludeInARAP=:exclude_in_ARAP,
      @LoginName=:login_name,@ExpenseOrRevenueTypeID=:expense_or_revenue_type_id,@Confidential=:confidential,
      @PVRSource=:PVR_source,@PVRSourceID=:PVR_source_id",[
        ":voucher_no"=>$voucher_no,
        ":voucher_type"=>$voucher_type,
        ":voucher_date"=>$voucher_date,
        ":income_or_expense"=>$income_or_expense,
        ":currency_id"=>$currency_id,
        ":associate_name"=>$associate_name,
        ":bank_account_id"=>$bank_account_id,
        ":payment_method"=>$payment_method,
        ":check_no"=>$check_no,
        ":check_due_date"=>$check_due_date,
        ":bank_name"=>$bank_name,
        ":amount_paid"=>$amount_paid,
        ":account_no"=>$account_no,
        ":remarks"=>$remarks,
        ":exclude_in_ARAP"=>$exclude_in_ARAP,
        ":login_name"=>$login_name,
        ":expense_or_revenue_type_id"=>$expense_or_revenue_type_id,
        ":confidential"=>$confidential,
        ":PVR_source"=>$PVR_source,
        ":PVR_source_id"=>$PVR_source_id,
      ]);
      if(!$myData)
      throw new \Exception("Data yang diperlukan tidak terpenuhi",1);
    
      $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
      ->select('VoucherID','VoucherNo','AmountPaid')
      ->where("PVRSource",$PVR_source)
      ->where("PVRSourceID",$trx_trp->id)
      ->where("Void",0)
      ->first();
      if(!$pvr)
      throw new \Exception("Akses Ke Jaringan Gagal",1);
    }

    $trx_trp->pvr_id = $pvr->VoucherID;
    $trx_trp->pvr_no = $pvr->VoucherNo;
    $trx_trp->pvr_total = $pvr->AmountPaid;
    $trx_trp->save();
    
    $d_voucher_id = $pvr->VoucherID;
    $d_voucher_extra_item_id = 0;
    $d_type = 0;

    $pvr_detail= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
    ->select('VoucherID')
    ->where("VoucherID",$d_voucher_id)
    ->get();

    if(count($pvr_detail)==0 || count($pvr_detail) < count($ujalan_details2)){
      $start = count($pvr_detail);
      foreach ($ujalan_details2 as $key => $v) {
        if($key < $start){ continue; }
        $d_description = $v->description;
        $d_amount = $v->qty * $v->amount;
        $d_account_id = $v->ac_account_id;
        $d_dept = $trx_trp->cost_center_code;
        $d_qty=$v->qty;
        $d_unit_price=$v->amount;
        $details = DB::connection('sqlsrv')->update("exec 
        USP_FI_APRequestExtraItems_Update @VoucherID=:d_voucher_id,
        @VoucherExtraItemID=:d_voucher_extra_item_id,
        @Description=:d_description,@Amount=:d_amount,
        @AccountID=:d_account_id,@TypeID=:d_type,
        @Department=:d_dept,@LoginName=:login_name,
        @Qty=:d_qty,@UnitPrice=:d_unit_price",[
          ":d_voucher_id"=>$d_voucher_id,
          ":d_voucher_extra_item_id"=>$d_voucher_extra_item_id,
          ":d_description"=>$d_description,
          ":d_amount"=>$d_amount,
          ":d_account_id"=>$d_account_id,
          ":d_type"=>$d_type,
          ":d_dept"=>$d_dept,
          ":login_name"=>$login_name,
          ":d_qty"=>$d_qty,
          ":d_unit_price"=>$d_unit_price
        ]);
      }
    }

    $trx_trp->pvr_had_detail = 1;
    $trx_trp->save();

    return [
      "message" => "PVR berhasil dibuat",
      "pvr_id" => $trx_trp->pvr_id,
      "pvr_no" => $trx_trp->pvr_no,
      "pvr_total" => $trx_trp->pvr_total,
      "pvr_had_detail" => $trx_trp->pvr_had_detail,
      "updated_at"=>$t_stamp
    ];
  }

  public function doUpdatePV(Request $request){
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport','Logistic']);
    $rules = [
      'online_status' => "required",
    ];

    $messages = [
      'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = \Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }
    $online_status=$request->online_status;
    if($online_status!="true")
    return response()->json([
      "message" => "Mode Harus Online",
    ], 400);

    $miniError="";
    try {
      $t_stamp = date("Y-m-d H:i:s");
      $trx_trps = TrxTrp::whereNotNull("pvr_id")->whereNull("pv_id")->get();
      if(count($trx_trps)==0){
        throw new \Exception("Semua PVR yang ada ,PV ny sudah terisi",1);
      }

      $pvr_nos=$trx_trps->pluck('pvr_no');
      // $pvr_nos=['KPN/PV-R/2404/0951','KPN/PV-R/2404/1000'];
      $get_data_pvs = DB::connection('sqlsrv')->table('FI_ARAPINFO')
      ->selectRaw('fi_arap.VoucherID,Sources,fi_arap.VoucherNo,FI_APRequest.PVRSourceID,fi_arap.AmountPaid')
      ->join('fi_arap',function ($join){
        $join->on("fi_arap.VoucherID","FI_ARAPINFO.VoucherID");        
      })
      ->join('FI_APRequest',function ($join){
        $join->on("FI_APRequest.VoucherNo","FI_ARAPINFO.Sources");        
      })
      ->whereIn("sources",$pvr_nos)
      ->get();

      $get_data_pvs=MyLib::objsToArray($get_data_pvs);
      $changes=[];
      foreach ($get_data_pvs as $key => $v) {
        $ud_trx_trp=TrxTrp::where("id", $v["PVRSourceID"])->where("pvr_no", $v["Sources"])->first();
        if(!$ud_trx_trp) continue;
        $ud_trx_trp->pv_id=$v["VoucherID"];
        $ud_trx_trp->pv_no=$v["VoucherNo"];
        $ud_trx_trp->pv_total=$v["AmountPaid"];
        $ud_trx_trp->updated_at=$t_stamp;
        $ud_trx_trp->save();
        array_push($changes,[
          "id"=>$ud_trx_trp->id,
          "pv_id"=>$ud_trx_trp->pv_id,
          "pv_no"=>$ud_trx_trp->pv_no,
          "pv_total"=>$ud_trx_trp->pv_total,
          "updated_at"=>$t_stamp
        ]);
      }

      if(count($changes)==0)
      throw new \Exception("PV Tidak ada yang di Update",1);

      return response()->json([
        "message" => "PV Berhasil di Update",
        "data" => $changes,
      ], 200);
      
    } catch (\Exception $e) {
      if ($e->getCode() == 1) {
        $miniError="PV Batal Update: ".$e->getMessage();
      }else{
        $miniError="PV Batal Update. Akses Jaringan Gagal";
      }
      return response()->json([
        "message" => $miniError,
      ], 400);
    }
  }
}
