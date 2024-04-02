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

use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\IsUser;

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

    DB::beginTransaction();
    try {
      $t_stamp = date("Y-m-d H:i:s");
      // if(TrxTrp::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar");

      $model_query                  = new TrxTrp();      
      $model_query->tanggal         = $request->tanggal;

      $ujalan = \App\Models\MySql\Ujalan::where("jenis",$request->jenis)
      ->where("xto",$request->xto)
      ->where("tipe",$request->tipe)
      ->lockForUpdate()
      ->first();

      if(!$ujalan) 
      throw new \Exception("Silahkan pilih To atau Tipe yang telah di sediakan",1);

      $model_query->id_uj           = $ujalan->id;
      $model_query->jenis           = $ujalan->jenis;
      $model_query->xto             = $ujalan->xto;
      $model_query->tipe            = $ujalan->tipe;
      $model_query->amount          = $ujalan->harga;

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
        ->where("TicketID",$request->ticket_b_id)
        ->where('ProductName',"RTBS")
        ->first();

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

      $model_query->supir=$request->supir;
      $model_query->kernet=MyLib::emptyStrToNull($request->kernet);
      $model_query->no_pol=$request->no_pol;
      
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

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
      return response()->json([
        "message" => $e->getMessage(),
        "code" => $e->getCode(),
        "line" => $e->getLine(),
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

  public function update(TrxTrpRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport','Logistic']);
    
    $t_stamp = date("Y-m-d H:i:s");

    DB::beginTransaction();
    try {
      $model_query             = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      if($model_query->val==1 || $model_query->val1==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      $model_query->tanggal         = $request->tanggal;

      $ujalan = \App\Models\MySql\Ujalan::where("jenis",$request->jenis)
      ->where("xto",$request->xto)
      ->where("tipe",$request->tipe)
      ->lockForUpdate()
      ->first();

      if(!$ujalan) 
      throw new \Exception("Silahkan pilih To atau Tipe yang telah di sediakan",1);

      $model_query->id_uj           = $ujalan->id;
      $model_query->jenis           = $ujalan->jenis;
      $model_query->xto             = $ujalan->xto;
      $model_query->tipe            = $ujalan->tipe;
      $model_query->amount          = $ujalan->harga;

      if($request->pv_id){

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
          $get_data_ticket =$get_data_ticket->whereIn('ProductName',"KERNEL");
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
      }

      if($request->ticket_b_id){

        $get_data_ticket = DB::connection('sqlsrv')->table('palm_tickets')
        ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo','DateTimeIn','DateTimeOut')
        ->where("TicketID",$request->ticket_b_id)
        ->where('ProductName',"RTBS")
        ->first();

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
        $model_query->ticket_b_bruto =  MyLib::emptyStrToNull($request->ticket_b_bruto);
        $model_query->ticket_b_tara =  MyLib::emptyStrToNull($request->ticket_b_tara);
        $model_query->ticket_b_netto =  MyLib::emptyStrToNull($request->ticket_b_bruto - $request->ticket_b_tara);
        $model_query->ticket_b_in_at =  MyLib::emptyStrToNull($request->ticket_b_in_at);
        $model_query->ticket_b_out_at =  MyLib::emptyStrToNull($request->ticket_b_out_at);
      }

      $model_query->supir=$request->supir;
      $model_query->kernet=MyLib::emptyStrToNull($request->kernet);
      $model_query->no_pol=$request->no_pol;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->save();

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

  public function delete(TrxTrpRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport','Logistic']);

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
    $details = \App\Models\MySql\UjalanDetail::where("id_uj",$trx_trp->id_uj)->orderBy("ordinal","asc")->get();
    $total = 0;

    foreach ($details as $key => $value) {
      $total += $value["qty"] * $value["harga"];
    }

    $sendData = [
      "id"=>$trx_trp->id,
      "id_uj"=>$trx_trp->id_uj,
      "no_pol"=>$trx_trp->no_pol,
      "supir"=>$trx_trp->supir,
      "kernet"=>$trx_trp->kernet,
      "tanggal"=>$trx_trp->tanggal,
      "created_at"=>$trx_trp->created_at,
      "asal"=>"KAS",
      "xto"=>$trx_trp->xto,
      "jenis"=>$trx_trp->jenis,
      "details"=>$details,
      "total"=>$total,
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
    MyAdmin::checkRole($this->role, ['SuperAdmin','Finance','Logistic','MIS']);

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
    $date = new \DateTime();
    $filename = $date->format("YmdHis");
    Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
    $pdf = PDF::loadView('pdf.trx_trp', $ori)->setPaper('a4', 'landscape');


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
}
