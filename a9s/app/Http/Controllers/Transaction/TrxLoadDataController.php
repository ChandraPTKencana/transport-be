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
    // $this->admin = MyAdmin::user();
  }

  public function index(Request $request)
  {
    $connectionDB = DB::connection('sqlsrv');

    $list_ticket=[];
    $list_pv=[];

    $list_ujalan = \App\Models\MySql\Ujalan::get();

    if($connectionDB->getPdo()){

      $list_ticket = $connectionDB->table("palm_tickets")
      ->select('*')
      ->limit(2)->get()->toArray();

      // foreach($list_ticket as $row){
      //   foreach($row as $key=>$value){
      //     $row->$key=mb_convert_encoding($value,'UTF-8','auto');
      //   }
      // }

      // $encoding = mb_detect_encoding($list_ticket, 'UTF-8', true);

      // if ($encoding !== 'UTF-8') {
      //     $list_ticket = mb_convert_encoding($list_ticket, 'UTF-8', $encoding);
      // }

      // $usersArray = array_map('get_object_vars', $list_ticket);

      // dd(get_object_vars($list_ticket[0]));
      // dd($list_ticket[1]->TicketID);
      // $dataArray = mb_convert_encoding($list_ticket->toArray(), 'UTF-8', 'UTF-8');
      // $dataArray=json_encode($list_ticket->toArray(),true);
      // $x = $this->objectToArray($list_ticket);
      // $x=json_decode(json_encode($list_ticket), true);
      dd(json_encode($list_ticket));
      
    }
    return response()->json([
      // "list_ujalan" => $list_ujalan,
      "list_ticket" => $x,
      // "list_pv" => $list_pv,
    ], 200);
  }


  function objectToArray($d) 
{
    if (is_object($d)) {
        // Gets the properties of the given object
        // with get_object_vars function
        $d = get_object_vars($d);
    }

    if (is_array($d)) {
        /*
        * Return array converted to object
        * Using __FUNCTION__ (Magic constant)
        * for recursive call
        */
        return array_map(__FUNCTION__, $d);
    } else {
        // Return array
        return $d;
    }
}
}
