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
    
    $model_query = $model_query->where("deleted",0)->get();

    return response()->json([
      "data" => TrxCpoResource::collection($model_query),
    ], 200);
  }

  public function show(TrxCpoRequest $request)
  {
    $model_query = TrxCpo::find($request->id);
    return response()->json([
      "data" => new TrxCpoResource($model_query),
    ], 200);
  }

  public function store(TrxCpoRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);

    DB::beginTransaction();
    try {
      $t_stamp = date("Y-m-d H:i:s");
      // if(TrxCpo::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar");

      $model_query                  = new TrxCpo();      
      $model_query->tanggal         = $request->tanggal;

      $ujalan = \App\Models\MySql\Ujalan::where("jenis","CPO")
      ->where("xto",$request->xto)
      ->where("tipe",$request->tipe)
      ->first();

      if(!$ujalan) 
      throw new \Exception("Data tidak terdaftar",1);

      $model_query->id_uj           = $ujalan->id;
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
        if(\App\Models\MySql\TrxCpo::where("pv_id",$get_data_pv->VoucherID)->first())
        throw new \Exception("Data PV telah digunakan",1);

        $model_query->pv_no =  $get_data_pv->VoucherNo;
        $model_query->pv_total =  $get_data_pv->total_amount;
      }

      $supir="";
      $no_pol="";
      if($request->ticket_id){

        $get_data_ticket = DB::connection('sqlsrv')->table('palm_tickets')
        ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo')
        ->where("TicketID",$request->ticket_id)
        ->first();

        if(!$get_data_ticket) 
        throw new \Exception("Data Ticket tidak terdaftar",1);

        if(\App\Models\MySql\TrxCpo::where("ticket_id",$get_data_ticket->TicketID)->first())
        throw new \Exception("Data Ticket telah digunakan",1);

        $model_query->ticket_id =  $request->ticket_id;
        $model_query->ticket_no =  $get_data_ticket->TicketNo;
        $model_query->ticket_bruto =  $get_data_ticket->Bruto;
        $model_query->ticket_tara =  $get_data_ticket->Tara;
        $model_query->ticket_netto =  $get_data_ticket->Netto;
        $model_query->ticket_supir =  $get_data_ticket->NamaSupir;
        $model_query->ticket_no_pol =  $get_data_ticket->VehicleNo;
        
      }

      $model_query->supir=$request->supir;
      $model_query->no_pol=$request->no_pol;

      $model_query->bruto           = $request->bruto;
      $model_query->tara          = $request->tara;
      $model_query->netto          = $request->netto;
      
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
      return response()->json([
        "message" => "Proses tambah data gagal",
      ], 400);
    }
  }

  public function update(TrxCpoRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);
    
    $t_stamp = date("Y-m-d H:i:s");

    DB::beginTransaction();
    try {
      $model_query             = TrxCpo::where("id",$request->id)->lockForUpdate()->first();
      if($model_query->val==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      $model_query->tanggal         = $request->tanggal;

      $ujalan = \App\Models\MySql\Ujalan::where("jenis","CPO")
      ->where("xto",$request->xto)
      ->where("tipe",$request->tipe)
      ->first();

      if(!$ujalan) 
      throw new \Exception("Data tidak terdaftar",1);

      $model_query->id_uj           = $ujalan->id;
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
        
        if(\App\Models\MySql\TrxCpo::where("pv_id",$get_data_pv->VoucherID)
        ->where("id","!=",$model_query->id)->first())
        throw new \Exception("Data PV telah digunakan",1);


        $model_query->pv_id =  $request->pv_id;
        $model_query->pv_no =  $get_data_pv->VoucherNo;
        $model_query->pv_total =  $get_data_pv->total_amount;
      }

      $supir="";
      $no_pol="";
      if($request->ticket_id){

        $get_data_ticket = DB::connection('sqlsrv')->table('palm_tickets')
        ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo')
        ->where("TicketID",$request->ticket_id)
        ->first();

        if(!$get_data_ticket) 
        throw new \Exception("Data Ticket tidak terdaftar",1);

        if(\App\Models\MySql\TrxCpo::where("ticket_id",$get_data_ticket->TicketID)
        ->where("id","!=",$model_query->id)->first())
        throw new \Exception("Data Ticket telah digunakan",1);

        $model_query->ticket_id =  $request->ticket_id;
        $model_query->ticket_no =  $get_data_ticket->TicketNo;
        $model_query->ticket_bruto =  $get_data_ticket->Bruto;
        $model_query->ticket_tara =  $get_data_ticket->Tara;
        $model_query->ticket_netto =  $get_data_ticket->Netto;
        $model_query->ticket_supir =  $get_data_ticket->NamaSupir;
        $model_query->ticket_no_pol =  $get_data_ticket->VehicleNo;
        
      }

      $model_query->supir=$request->supir;
      $model_query->no_pol=$request->no_pol;

      $model_query->bruto           = $request->bruto;
      $model_query->tara          = $request->tara;
      $model_query->netto          = $request->netto;
      
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

  public function delete(TrxCpoRequest $request)
  {
    // MyAdmin::checkRole($this->role, ['Super Admin','User','ClientPabrik','KTU']);

    DB::beginTransaction();

    try {
      $model_query = TrxCpo::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      
      if($model_query->val==1 || $model_query->deleted==1) 
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

}
