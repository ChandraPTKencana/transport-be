<?php

namespace App\Http\Controllers\Potongan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Helpers\MyLib;

use App\Models\MySql\PotonganTrx;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\PotonganTrxRequest;

use App\Http\Resources\MySql\PotonganTrxResource;
use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\PotonganMst;

class PotonganTrxController extends Controller
{
  private $admin;
  private $admin_id;
  private $role;
  private $permissions;
  private $syslog_db = 'potongan_trx';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->role = $this->admin->the_user->hak_akses;
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();
  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_trx.views');

    $potongan_mst_id = $request->potongan_mst_id;

    if(!$potongan_mst_id) 
    throw new MyException(["message" => "Silahkan refresh halaman terlebih dahulu"], 400);

    if(!PotonganMst::where('id',$potongan_mst_id)->first())
    throw new MyException(["message" => "Ada data yang tidak sesuai"], 400);


    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 250; // Limit +> Much Data
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
    // $model_query = PotonganTrx::offset($offset)->limit($limit);
    $model_query = new PotonganTrx();

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

      if (isset($sort_lists["id_uj"])) {
        $model_query = $model_query->orderBy("id_uj", $sort_lists["id_uj"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("id_uj",$sort_symbol,$first_row["id_uj"]);
        }
      }

      // if (isset($sort_lists["role"])) {
      //   $model_query = $model_query->orderBy(function($q){
      //     $q->from("internal.roles")
      //     ->select("name")
      //     ->whereColumn("id","auths.role_id");
      //   },$sort_lists["role"]);
      // }

      // if (isset($sort_lists["auth"])) {
      //   $model_query = $model_query->orderBy(function($q){
      //     $q->from("users as u")
      //     ->select("u.username")
      //     ->whereColumn("u.id","users.id");
      //   },$sort_lists["auth"]);
      // }
    } else {
      $model_query = $model_query->orderBy('id', 'desc');
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

      if (isset($like_lists["id"])) {
        $model_query = $model_query->orWhere("id", "like", $like_lists["id"]);
      }

      if (isset($like_lists["id_uj"])) {
        $model_query = $model_query->orWhere("id_uj", "like", $like_lists["id_uj"]);
      }

    }

    // ==============
    // Model Filter
    // ==============

    if (isset($request->id)) {
      $model_query = $model_query->where("id", 'like', '%' . $request->id . '%');
    }

    if (isset($request->id_uj)) {
      $model_query = $model_query->where("id_uj", 'like', '%' . $request->id_uj . '%');
    }


    $model_query = $model_query->where("potongan_mst_id",$potongan_mst_id)->with('deleted_by')->get();

    return response()->json([
      // "data"=>PotonganTrxResource::collection($potongan_trxs->keyBy->id),
      "data" => PotonganTrxResource::collection($model_query),
    ], 200);
  }

  public function show(PotonganTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_trx.view');
    
    $model_query = PotonganTrx::find($request->id);
    return response()->json([
      "data" => new PotonganTrxResource($model_query),
    ], 200);
  }

  public function store(PotonganTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_trx.create');

    DB::beginTransaction();
    $t_stamp = date("Y-m-d H:i:s");
    try {
      $model_query1                = PotonganMst::where("id",$request->potongan_mst_id)->lockForUpdate()->first();
      $SYSOLD                      = clone($model_query1);
      if($model_query1->remaining_cut < $request->nominal_cut)
      {
        throw new \Exception("Potongan Melebihi Sisa Potongan",1);
      }
      $model_query                  = new PotonganTrx();
      $model_query->potongan_mst_id = $request->potongan_mst_id;
      $model_query->note            = $request->note;
      $model_query->nominal_cut     = $request->nominal_cut;
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;
      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      $model_query->save();

      $model_query1->remaining_cut  = $model_query1->remaining_cut - $request->nominal_cut;
      $model_query1->updated_at     = $t_stamp;
      $model_query1->save();

      MyLog::sys($this->syslog_db,$model_query->id,"insert");
      
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query1); 
      MyLog::sys("potongan_mst",$model_query1->id,"update",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
        "remaining_cut" =>$model_query1->remaining_cut
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }

      return response()->json([
        "message"=>$e->getMessage(),
      ],400);

      return response()->json([
        "message" => "Proses tambah data gagal"
      ], 400);
    }
  }

  public function update(PotonganTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_trx.modify');

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query1               = PotonganMst::where("id",$request->potongan_mst_id)->lockForUpdate()->first();
      $SYSOLD1                    = clone($model_query1);

      $model_query                = PotonganTrx::where("id",$request->id)->lockForUpdate()->first();
      if($model_query1->remaining_cut + $model_query->nominal_cut < $request->nominal_cut)
      {
        throw new \Exception("Potongan Melebihi Sisa Potongan",1);
      }
      
      $model_query1->remaining_cut = $model_query1->remaining_cut + $model_query->nominal_cut - $request->nominal_cut;
      
      if($model_query->id_uj){
        throw new \Exception("Izin Ubah Ditolak",1);
      }

      if($model_query->val==1)
      throw new \Exception("Data sudah tervalidasi",1);

      $SYSOLD                     = clone($model_query);

      $model_query->note          = $request->note;
      $model_query->nominal_cut   = $request->nominal_cut;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;
      $model_query->save();

      $model_query1->updated_at     = $t_stamp;
      $model_query1->save();

      
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys($this->syslog_db,$request->id,"update",$SYSNOTE);

      $SYSNOTE1 = MyLib::compareChange($SYSOLD1,$model_query1); 
      MyLog::sys("potongan_mst",$model_query1->id,"update",$SYSNOTE1);

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at" => $t_stamp,
        "remaining_cut" =>$model_query1->remaining_cut
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      return response()->json([
        "message" => $e->getMessage(),
      ], 400);
      return response()->json([
        "message" => "Proses ubah data gagal"
      ], 400);
    }
  }


  public function delete(PotonganTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_trx.remove');

    DB::beginTransaction();
    try {
      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query = PotonganTrx::where("id",$request->id)->lockForUpdate()->first();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      if ($model_query->id_uj) {
        throw new \Exception("Izin Hapus Ditolak", 1);
      }
  
      $model_query->deleted         = 1;
      $model_query->deleted_user    = $this->admin_id;
      $model_query->deleted_at      = date("Y-m-d H:i:s");
      $model_query->deleted_reason  = $deleted_reason;
      $model_query->save();

      MyLog::sys($this->syslog_db,$request->id,"delete");

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
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
    // if ($model_query->delete()) {
    //     return response()->json([
    //         "message"=>"Proses ubah data berhasil",
    //     ],200);
    // }

    // return response()->json([
    //     "message"=>"Proses ubah data gagal",
    // ],400);
  }


  public function recalculate(Request $request)
  {
    $id = $request->potongan_mst_id;
    if(!$id) 
    throw new MyException(["message" => "Silahkan refresh halaman terlebih dahulu"], 400);
    
    if(!PotonganMst::where('id',$id)->first())
    throw new MyException(["message" => "Ada data yang tidak sesuai"], 400);
    $t_stamp=date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      

      $model_query = PotonganMst::where("id",$id)->lockForUpdate()->first();
      $model_query1 = PotonganTrx::selectRaw('sum(nominal_cut) as paid')->where("potongan_mst_id",$id)->where("deleted",0)->lockForUpdate()->first();
      $paid = 0; 
      if($model_query1){
        $paid = $model_query1->paid;
      }

      $model_query->remaining_cut = $model_query->nominal - $paid;

      $model_query->updated_user = $this->admin_id;
      $model_query->updated_at = $t_stamp;
      $model_query->save();

      // MyLog::sys($this->syslog_db,$request->id,"recalculate");
      MyLog::sys("potongan_mst",$request->id,"recalculate");

      DB::commit();
      return response()->json([
        "message"       => "Proses ubah data berhasil",
        "updated_at"    => $t_stamp,
        "remaining_cut" => $model_query->remaining_cut

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
    // if ($model_query->delete()) {
    //     return response()->json([
    //         "message"=>"Proses ubah data berhasil",
    //     ],200);
    // }

    // return response()->json([
    //     "message"=>"Proses ubah data gagal",
    // ],400);
  }

  public function validasi(Request $request){
    MyAdmin::checkScope($this->permissions, 'potongan_trx.val');

    $rules = [
      'id' => "required|exists:\App\Models\MySql\PotonganTrx,id",
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
      $model_query = PotonganTrx::lockForUpdate()->find($request->id);
      if($model_query->val){
        throw new \Exception("Data Sudah Tervalidasi",1);
      }
      
      if(!$model_query->val){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
      }

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



  public function doGenRV(Request $request){
    MyAdmin::checkScope($this->permissions, 'potongan_trx.generate_pvr');
    $rules = [
      // 'id' => "required|exists:\App\Models\MySql\StandbyTrx,id",
      'online_status' => "required",
    ];

    $messages = [
      // 'id.required' => 'ID tidak boleh kosong',
      // 'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }
    $online_status=$request->online_status;
    if($online_status!="true")
    return response()->json([
      "message" => "Mode Harus Online",
    ], 400);

    $miniError="";
    $id="";
    try {
      $potongan_trxs = StandbyTrx::where(function($q1){$q1->where('pvr_had_detail',0)->orWhereNull("pvr_id");})->whereNull("pv_id")->where("req_deleted",0)->where("deleted",0)->where('val1',1)->get();
      if(count($potongan_trxs)==0){
        throw new \Exception("Semua PVR sudah terisi",1);
      }
      $changes=[];
      foreach ($potongan_trxs as $key => $tt) {
        $id=$tt->id;
        $callGet = $this->genPVR($id);
        array_push($changes,$callGet);
      }
      if(count($changes)>0){
        $ids = array_map(function ($x) {
          return $x["id"];
        },$changes);
        MyLog::sys("potongan_trx",null,"generate_pvr",implode(",",$ids));
      }
      return response()->json($changes, 200);
    } catch (\Exception $e) {
      if(isset($changes) && count($changes)>0){
        $ids = array_map(function ($x) {
          return $x["id"];
        },$changes);
        MyLog::sys("potongan_trx",null,"generate_pvr",implode(",",$ids));
      }

      if ($e->getCode() == 1) {
        if($id!=""){
          $miniError.="Trx-".$id.".";
        }
        $miniError.="PVR Batal Dibuat: ".$e->getMessage();
      }else{
        if($id!=""){
          $miniError.="Trx-".$id.".";
        }
        $miniError.="PVR Batal Dibuat. Akses Jaringan Gagal";
      }
      return response()->json([
        "message" => $miniError,
      ], 400);
    }
  }

  public function genRV($potongan_trx_id){

    $t_stamp = date("Y-m-d H:i:s");

    $time = microtime(true);
    $mSecs = sprintf('%03d', ($time - floor($time)) * 1000);
    $t_stamp_ms = date("Y-m-d H:i:s",strtotime($t_stamp)).".".$mSecs;

    $potongan_trx = StandbyTrx::where("id",$potongan_trx_id)->first();
    if(!$potongan_trx){
      throw new \Exception("Karna Transaksi tidak ditemukan",1);
    }

    if($potongan_trx->pvr_had_detail==1) throw new \Exception("Karna PVR sudah selesai dibuat",1);
    if($potongan_trx->cost_center_code==null) throw new \Exception("Cost Center Code belum diisi",1);
    if($potongan_trx->pv_id!=null) throw new \Exception("Karna PV sudah diisi",1);
      
    $supir = $potongan_trx->supir;
    $no_pol = $potongan_trx->no_pol;
    $kernet = $potongan_trx->kernet;
    $associate_name=($supir?"(S) ".$supir." ":"(Tanpa Supir) ").($kernet?"(K) ".$kernet." ":"(Tanpa Kernet) ").$no_pol; // max 80char

    $standby_mst = StandbyMst::where("id",$potongan_trx->standby_mst_id)->first();
    $standby_mst_dtl = StandbyDtl::where("standby_mst_id",$standby_mst->id)->get();
    if(count($standby_mst_dtl)==0)
    throw new \Exception("Master Standby Detail Harus diisi terlebih dahulu",1);
    
    $potongan_trx_dtl = StandbyTrxDtl::where("potongan_trx_id",$potongan_trx->id)->get();
    if(count($potongan_trx_dtl)==0)
    throw new \Exception("Transaksi Standby Detail Harus diisi terlebih dahulu",1);

    $arrRemarks = [];
    array_push($arrRemarks,"#".$potongan_trx->id.($potongan_trx->transition_type!=''?" (P) " : " ").$associate_name.".");
    array_push($arrRemarks,$standby_mst->name." ".($potongan_trx->xto ? env("app_name")."-".$potongan_trx->xto : "")).".";
    $pertanggal = "";
    foreach ($potongan_trx_dtl as $key => $value) {
      if($key > 0) $pertanggal .= ",";

      $pertanggal .= " P/".date("d-m-y",strtotime($value->tanggal));
    }
    array_push($arrRemarks,$pertanggal);

    if($potongan_trx->note_for_remarks!=null){
      $note_for_remarks_arr = preg_split('/\r\n|\r|\n/', $potongan_trx->note_for_remarks);
      $arrRemarks = array_merge($arrRemarks,$note_for_remarks_arr);
    }
    
    $remarks = implode(chr(10),$arrRemarks);
    array_push($arrRemarks,";");

    if(strlen($associate_name)>80){
      $associate_name = substr($associate_name,0,80);
    }

    $bank_account_code=env("PVR_BANK_ACCOUNT_CODE");
    
    $bank_acccount_db = DB::connection('sqlsrv')->table('FI_BankAccounts')
    ->select('BankAccountID')
    ->where("bankaccountcode",$bank_account_code)
    ->first();
    if(!$bank_acccount_db) throw new \Exception("Bank account code tidak terdaftar ,segera infokan ke tim IT",1);

    $bank_account_id = $bank_acccount_db->BankAccountID;
    
    // @VoucherID INT = 0,
    $voucher_no = "(AUTO)";
    $voucher_type = "TRP";
    $voucher_date = date("Y-m-d");

    $income_or_expense = 1;
    $currency_id = 1;
    $payment_method="Cash";
    $check_no=$bank_name=$account_no= '';
    $check_due_date= null;

    $amount_paid = $standby_mst->amount * count($potongan_trx_dtl); // call from child
    $exclude_in_ARAP = 0;
    $login_name = $this->admin->the_user->username;
    $expense_or_revenue_type_id=0;
    $confidential=1;
    $PVR_source = 'gt_standby'; // digenerate melalui program
    $PVR_source_id = $potongan_trx_id; //ambil id trx
      // DB::select("exec USP_FI_APRequest_Update(0,'(AUTO)','TRP',1,1,1,0,)",array($ts,$param2));
    $VoucherID = -1;

    $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
    ->select('VoucherID','VoucherNo','AmountPaid')
    ->where("PVRSource",$PVR_source)
    ->where("PVRSourceID",$potongan_trx->id)
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
      ->where("PVRSourceID",$potongan_trx->id)
      ->where("Void",0)
      ->first();
      if(!$pvr)
      throw new \Exception("Akses Ke Jaringan Gagal",1);
    }

    $potongan_trx->pvr_id = $pvr->VoucherID;
    $potongan_trx->pvr_no = $pvr->VoucherNo;
    $potongan_trx->pvr_total = $pvr->AmountPaid;
    $potongan_trx->save();
    
    $d_voucher_id = $pvr->VoucherID;
    $d_voucher_extra_item_id = 0;
    $d_type = 0;

    $pvr_detail= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
    ->select('VoucherID')
    ->where("VoucherID",$d_voucher_id)
    ->get();

    if(count($pvr_detail)==0 || count($pvr_detail) < count($standby_mst_dtl)){
      $start = count($pvr_detail);
      foreach ($standby_mst_dtl as $key => $v) {
        if($key < $start){ continue; }
        $d_description = $v->description;
        $d_amount = count($potongan_trx_dtl) * $v->amount;
        $d_account_id = $v->ac_account_id;
        $d_dept = $potongan_trx->cost_center_code;
        $d_qty=count($potongan_trx_dtl);
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

    $tocheck = DB::connection('sqlsrv')->table('FI_APRequest')->where("VoucherID",$d_voucher_id)->first();

    if(!$tocheck)
    throw new \Exception("Voucher Tidak terdaftar",1);

    $checked2 = IsUser::where("id",$potongan_trx->val1_user)->first();
    if(!$checked2)
    throw new \Exception("User Tidak terdaftar",1);

    DB::connection('sqlsrv')->update("exec USP_FI_APRequest_DoCheck @VoucherID=:voucher_no,
    @CheckedBy=:login_name",[
      ":voucher_no"=>$d_voucher_id,
      ":login_name"=>$login_name,
    ]);

    DB::connection('sqlsrv')->update("exec USP_FI_APRequest_DoApprove @VoucherID=:voucher_no,
    @ApprovedBy=:login_name",[
      ":voucher_no"=>$d_voucher_id,
      ":login_name"=>$checked2->username,
    ]);

    $potongan_trx->pvr_had_detail = 1;
    $potongan_trx->save();

    return [
      "message" => "PVR berhasil dibuat",
      "id"=>$potongan_trx->id,
      "pvr_id" => $potongan_trx->pvr_id,
      "pvr_no" => $potongan_trx->pvr_no,
      "pvr_total" => $potongan_trx->pvr_total,
      "pvr_had_detail" => $potongan_trx->pvr_had_detail,
      "updated_at"=>$t_stamp
    ];
  }
}
