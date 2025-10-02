<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Exceptions\MyException;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;

class TrxLoadDataController extends Controller
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

  public function cost_center(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'srv.cost_center.views');

    $online_status = $request->online_status;

    $connectionDB = DB::connection('sqlsrv');

    $list_cost_center=[];
    
    try {
      if($online_status=="true"){
        $list_cost_center = $connectionDB->table("AC_CostCenterNames")
        ->select('CostCenter','Description')
        ->where('CostCenter','like', '112%')
        ->get();
  
        $list_cost_center= MyLib::objsToArray($list_cost_center); 
      }
    } catch (\Exception $e) {
      // return response()->json($e->getMessage(), 400);
    }
    
    return response()->json([
      "list_cost_center" => $list_cost_center,
    ], 200);
  }
  public function trp(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'srv.palm_ticket.views');

    $transition_target = $request->transition_target;
    if($transition_target==env("app_name") || !in_array($transition_target,MyLib::$list_pabrik)){
      $transition_target="";
    }

    $connectionDB = DB::connection('sqlsrv');

    $list_ticket=[];
    
    $date_from = $request->from;
    $date_to = $request->to;

    if(!$date_from || !$date_to ){
      throw new MyException(["message" => "Please Set Date For Load Data"]);
    }

    if(strtotime($date_from) > strtotime($date_to)) {
      throw new MyException(["message" => "Set Date From Must Before Or Same With Date To."]);
    }

    try {
      $arr_tickets = [];
      $used_ticket_pvs = \App\Models\MySql\TrxTrp::where("created_at",">=",$date_from)->where("created_at","<=",$date_to)->get();
      foreach ($used_ticket_pvs as $key=>$val){    
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
          $product_names = ["RTBS","MTBS","TBS"];
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
      $list_ticket = $connectionDB->table("palm_tickets");
      // ->select('*')
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)){
        $list_ticket=$list_ticket->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','ProductName','DateTimeIn','DateTimeOut');
      }else{
        $list_ticket=$list_ticket->selectRaw('TicketID,TicketNo,Date,VehicleNo,0 as Bruto,0 as Tara,0 as Netto,NamaSupir,ProductName,DateTimeIn,DateTimeOut');
      }
      $list_ticket=$list_ticket->whereDate('Date','>=', $date_from)
      ->whereDate('Date','<=', $date_to)
      ->whereIn('ProductName',$product_names) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
      ->whereNotIn('TicketNo',$arr_tickets) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
      // ->limit(1)
      ->get();

      // $list_ticket= $list_ticket->map(function ($item) {
      //   return array_map('utf8_encode', (array)$item);
      // })->toArray();
      $list_ticket= MyLib::objsToArray($list_ticket); 

      // $list_pv = $connectionDB->table("fi_arap")
      // // ->select('*')
      // ->select('fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid','AssociateName',DB::raw('SUM(fi_arapextraitems.Amount) as total_amount'))
      // ->whereDate('VoucherDate','>=', $date_from)
      // ->whereDate('VoucherDate','<=', $date_to)
      // ->where('VoucherType',"TRP")
      // ->where("IsAR",0)
      // ->whereNotIn('VoucherNo',$arr_pvs) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
      // ->leftJoin('fi_arapextraitems', 'fi_arap.VoucherID', '=', 'fi_arapextraitems.VoucherID')
      // ->groupBy(['fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid','AssociateName'])
      // ->get();

      // $list_pv= MyLib::objsToArray($list_pv); 


      if($transition_target!=""){
        if($jenis=="TBS" && $transition_target!=""){
          $product_names = ["MTBS","TBS","RTBS"];
        }
        
        $ad_list_ticket = DB::connection($transition_target)->table("palm_tickets");
        // ->select('*')
        if(MyAdmin::checkScope($this->permissions, 'trp_trx.ticket.show_weight',true)){
          $ad_list_ticket=$ad_list_ticket->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','ProductName','DateTimeIn','DateTimeOut');
        }else{
          $ad_list_ticket=$ad_list_ticket->selectRaw('TicketID,TicketNo,Date,VehicleNo,0 as Bruto,0 as Tara,0 as Netto,NamaSupir,ProductName,DateTimeIn,DateTimeOut');
        }
        
        $ad_list_ticket=$ad_list_ticket->whereDate('Date','>=', $date_from)
        ->whereDate('Date','<=', $date_to)
        ->whereIn('ProductName',$product_names) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
        ->whereNotIn('TicketNo',$arr_tickets) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
        // ->limit(1)
        ->get();
        $ad_list_ticket= MyLib::objsToArray($ad_list_ticket);
        $list_ticket = array_merge($list_ticket, $ad_list_ticket);
      }
    } catch (\Exception $e) {
      // return response()->json($e->getMessage(), 400);
    }
    
    return response()->json([
      "list_ticket" => $list_ticket,
    ], 200);
  }


  public function local(Request $request)
  {
    $list_ujalan = \App\Models\MySql\Ujalan::where("deleted",0)->where('val',1)->where('val1',1)->get();
    $list_vehicle = \App\Models\MySql\Vehicle::where("deleted",0)->get();
    $list_employee = \App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->available()->verified()->whereIn("role",['Supir','Kernet','BLANK'])->with('potongan')->get();
    $list_payment_methods = \App\Models\MySql\PaymentMethod::get();
      
    return response()->json([
      "list_ujalan"           => $list_ujalan,
      "list_vehicle"          => $list_vehicle,
      "list_employee"         => $list_employee,
      "list_payment_methods"  => $list_payment_methods,
    ], 200);
  }
}
