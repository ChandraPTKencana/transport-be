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


    $connectionDB = DB::connection('sqlsrv');

    $list_ticket=[];
    $list_pv=[];

    $list_ujalan = \App\Models\MySql\Ujalan::where("deleted",0)->get();

    if($connectionDB->getPdo()){

      $date = now()->subDays(7);

      $list_ticket = $connectionDB->table("palm_tickets")
      // ->select('*')
      ->select('TicketID','TicketNo','Date','VehicleNo','Bruto','Tara','Netto','NamaSupir','VehicleNo','ProductName','DateTimeIn','DateTimeOut')
      ->whereDate('Date','>=', $date)
      ->whereIn('ProductName',["RTBS","MTBS","CPO","KERNEL"]) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
      // ->limit(1)
      ->get();

      $list_ticket= $list_ticket->map(function ($item) {
        return array_map('utf8_encode', (array)$item);
      })->toArray();

      $list_pv = $connectionDB->table("fi_arap")
      // ->select('*')
      ->select('fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid','AssociateName',DB::raw('SUM(fi_arapextraitems.Amount) as total_amount'))
      ->whereDate('VoucherDate','>=', $date)
      ->where('VoucherType',"TRP")
      ->where("IsAR",0)
      ->leftJoin('fi_arapextraitems', 'fi_arap.VoucherID', '=', 'fi_arapextraitems.VoucherID')
      ->groupBy(['fi_arap.VoucherID','VoucherNo','VoucherDate','AmountPaid','AssociateName'])
      ->get();

      $list_pv= $list_pv->map(function ($item) {
        return array_map('utf8_encode', (array)$item);
      })->toArray();     
    }
    return response()->json([
      "list_ujalan" => $list_ujalan,
      "list_ticket" => $list_ticket,
      "list_pv" => $list_pv,
    ], 200);
  }
}
