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

class TrxLoadDataController extends Controller
{
  private $admin;
  private $role;
  private $admin_id;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->role = $this->admin->the_user->hak_akses;
    $this->admin_id = $this->admin->the_user->id;
  }

  // public function cpo(Request $request)
  // {
  //   $connectionDB = DB::connection('sqlsrv');

  //   // $list_ticket = $connectionDB->table("palm_tickets")
  //   // ->select('*')
  //   // ->first();

  //   // $nlt = [];
  //   // foreach((array)$list_ticket as $key=>$val){
  //   //   $nlt[$key]=mb_convert_encoding($val, 'UTF-8');
  //   // }
    

  //   $list_ticket=[];
  //   $list_pv=[];

  //   $list_ujalan = \App\Models\MySql\Ujalan::where("jenis","CPO")->get();

  //   if($connectionDB->getPdo()){

  //     $date = now()->subDays(7);

  //     $list_ticket = $connectionDB->table("palm_tickets")
  //     // ->select('*')
  //     ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo')
  //     ->whereDate('Date','>=', $date)
  //     ->whereIn('ProductName',["CPO","PK"]) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
  //     // ->whereIn('ProductName',["RTBS","MTBS","CPO","PK"]) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
  //     // ->limit(1)
  //     ->get();

  //     $list_ticket= $list_ticket->map(function ($item) {
  //       return array_map('utf8_encode', (array)$item);
  //     })->toArray();


  //     $list_pv = $connectionDB->table("fi_arap")
  //     // ->select('*')
  //     ->select('fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid',DB::raw('SUM(fi_arapextraitems.Amount) as total_amount'))
  //     ->whereDate('VoucherDate','>=', $date)
  //     ->where('VoucherType',"TRP")
  //     ->where("IsAR",0)
  //     ->leftJoin('fi_arapextraitems', 'fi_arap.VoucherID', '=', 'fi_arapextraitems.VoucherID')
  //     ->groupBy(['fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid'])
  //     ->get();

  //     $list_pv= $list_pv->map(function ($item) {
  //       return array_map('utf8_encode', (array)$item);
  //     })->toArray();

  //     // $list_pv = $connectionDB->table("fi_arapextraitems")
  //     // ->select('*')
  //     // // ->select('VoucherID','VoucherNo','Date','VoucherDate','AmountPaid','Tara','Netto')
  //     // // ->whereDate('VoucherDate','>=', $date)
  //     // // ->where('VoucherType',"TRP")
  //     // ->where('VoucherID',"791")
  //     // ->get();

  //     // $list_pv= $list_pv->map(function ($item) {
  //     //   return array_map('utf8_encode', (array)$item);
  //     // })->toArray();


      
  //   }
  //   return response()->json([
  //     "list_ujalan" => $list_ujalan,
  //     "list_ticket" => $list_ticket,
  //     "list_pv" => $list_pv,
  //   ], 200);
  // }


  public function trp(Request $request)
  {
    MyAdmin::checkRole($this->role, ['SuperAdmin','PabrikTransport','Logistic']);

    $online_status = $request->online_status;
    $connectionDB = DB::connection('sqlsrv');

    $list_ticket=[];
    $list_pv=[];
    $list_cost_center=[];
    $list_ujalan = \App\Models\MySql\Ujalan::where("deleted",0)->get();
    
    $date_from = $request->from;
    $date_to = $request->to;

    if(!$date_from || !$date_to ){
      throw new MyException(["message" => "Please Set Date For Load Data"]);
    }

    if(strtotime($date_from) > strtotime($date_to)) {
      throw new MyException(["message" => "Set Date From Must Before Or Same With Date To."]);
    }

    try {
      if($online_status=="true"){
        $arr_tickets = [];
        $arr_pvs = [];
        $used_ticket_pvs = \App\Models\MySql\TrxTrp::where("created_at",">=",$date_from)->get();
        foreach ($used_ticket_pvs as $key=>$val){
          if($val->pv_no){
            array_push($arr_pvs,$val->pv_no);
          }
    
          if($val->ticket_a_no){
            array_push($arr_tickets,$val->ticket_a_no);
          }
    
          if($val->ticket_b_no){
            array_push($arr_tickets,$val->ticket_b_no);
          }
        }
        $jenis = $request->jenis;
        $product_names = [];
  
        switch ($jenis) {
          case 'TBSK':
            $product_names = ["TBS"];
            break;
          case 'TBS':
            $product_names = ["RTBS","MTBS"];
            break;
          case 'CPO':
            $product_names = ["CPO"];
            break;
          case 'PK':
            $product_names = ["KERNEL"];
            break;
          default:
            # code...
            break;
        }
        $list_ticket = $connectionDB->table("palm_tickets")
        // ->select('*')
        ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo','ProductName','DateTimeIn','DateTimeOut')
        ->whereDate('Date','>=', $date_from)
        ->whereDate('Date','<=', $date_to)
        ->whereIn('ProductName',$product_names) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
        ->whereNotIn('TicketNo',$arr_tickets) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
        // ->limit(1)
        ->get();
  
        // $list_ticket= $list_ticket->map(function ($item) {
        //   return array_map('utf8_encode', (array)$item);
        // })->toArray();
        $list_ticket= MyLib::objsToArray($list_ticket); 
  
        $list_pv = $connectionDB->table("fi_arap")
        // ->select('*')
        ->select('fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid','AssociateName',DB::raw('SUM(fi_arapextraitems.Amount) as total_amount'))
        ->whereDate('VoucherDate','>=', $date_from)
        ->whereDate('VoucherDate','<=', $date_to)
        ->where('VoucherType',"TRP")
        ->where("IsAR",0)
        ->whereNotIn('VoucherNo',$arr_pvs) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
        ->leftJoin('fi_arapextraitems', 'fi_arap.VoucherID', '=', 'fi_arapextraitems.VoucherID')
        ->groupBy(['fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid','AssociateName'])
        ->get();
  
        // $list_pv= $list_pv->map(function ($item) {
        //   return array_map('utf8_encode', (array)$item);
        // })->toArray();    
        $list_pv= MyLib::objsToArray($list_pv); 
        
        $list_cost_center = $connectionDB->table("AC_CostCenterNames")
        ->select('CostCenter','Description')
        ->where('CostCenter','like', '112%')
        ->get();
  
        $list_cost_center= MyLib::objsToArray($list_cost_center); 
      }
    } catch (\Throwable $th) {
      //throw $th;
    }
    
    return response()->json([
      "list_ujalan" => $list_ujalan,
      "list_cost_center" => $list_cost_center,
      "list_ticket" => $list_ticket,
      "list_pv" => $list_pv,
    ], 200);
  }
}
