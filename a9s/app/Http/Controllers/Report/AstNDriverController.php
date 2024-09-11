<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Exceptions\MyException;

use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;

use App\Models\MySql\TrxTrp;
use App\Models\MySql\Employee;
use App\Models\MySql\StandbyTrx;
use App\Models\MySql\Vehicle;

use App\Exports\MyReport;

class AstNDriverController extends Controller
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

  public function loadData(Request $request){

    MyAdmin::checkScope($this->permissions, 'report.ast_n_driver.download_file');

    $list_xto = \App\Models\MySql\Ujalan::select('xto')->where("deleted",0)->where('val',1)->where('val1',1)->orderBy('xto','asc')->groupBy('xto')->get()->pluck('xto');
    $list_employee = \App\Models\MySql\Employee::available()->orderBy('name','asc')->get();
    $list_vehicle = \App\Models\MySql\Vehicle::where("deleted",0)->orderBy('no_pol','asc')->get();
    return response()->json([
      "list_xto" => $list_xto,
      "list_employee" => $list_employee,
      "list_vehicle" => $list_vehicle,
    ], 200);
  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'report.ast_n_driver.download_file');

    $list_xto = json_decode($request->list_xto, true);
    $list_employee = json_decode($request->list_employee, true);
    $list_vehicle = json_decode($request->list_vehicle, true);
    $type   = $request->type;
    $jenis  = $request->jenis;

    if($type=='' || array_search($type,["header",'detail'])===false){
      throw new MyException([ "type" => ["Tipe Harus dipilih"] ], 422);
    }

    $uj_trx = new TrxTrp();
    $standby_trx = new StandbyTrx();

    // $model_query = TrxTrp::where('val1',1)->where("deleted",0)->orderBy("xto","asc");
    // // $model_query = TrxTrp::where('val2',1)->where("deleted",0);
    $date_from = "";
    $date_to = "";
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

      $date_from = date("Y-m-d",strtotime($date_from));
      $date_to = date("Y-m-d",strtotime($date_to));

      $uj_trx = $uj_trx->where("tanggal",">=",$date_from)->where('tanggal',"<=",$date_to);

      $date_from = $date_from." 00:00:00";
      $date_to = $date_to." 23:59:59";
      $standby_trx = $standby_trx->where("standby_trx.created_at",">=",$date_from)->where('standby_trx.created_at',"<=",$date_to);
    }

    if(count($list_xto)>0){
      $uj_trx = $uj_trx->whereIn("trx_trp.xto",$list_xto);
      $standby_trx = $standby_trx->whereIn("standby_trx.xto",$list_xto);
    }

    $supir_list=[];
    $kernet_list=[];
    if(count($list_employee)>0){

      $employees = Employee::whereIn("id",$list_employee)->get()->toArray();

      foreach ($employees as $k => $v) {
        if($v['role']=='Supir'){
          array_push($supir_list,$v['name']);
        }
        
        if($v['role']=='Kernet'){
          array_push($kernet_list,$v['name']);
        }  
      }

      if(count($supir_list) > 0 || count($kernet_list) > 0){
        $uj_trx=$uj_trx->where(function ($q)use($supir_list,$kernet_list){         
          if(count($supir_list) > 0){
            $q->whereIn("supir",$supir_list);
          }
          if(count($kernet_list) > 0){
            $q->orWhereIn("kernet",$kernet_list);
          }
        });

        $standby_trx=$standby_trx->where(function ($q)use($supir_list,$kernet_list){         
          if(count($supir_list) > 0){
            $q->whereIn("supir",$supir_list);
          }
          if(count($kernet_list) > 0){
            $q->orWhereIn("kernet",$kernet_list);
          }
        });

      }
    }

    if(count($list_vehicle)>0){
      $list_no_pol = Vehicle::whereIn("id",$list_vehicle)->get()->pluck("no_pol");

      $uj_trx = $uj_trx->whereIn("no_pol",$list_no_pol);
      $standby_trx = $standby_trx->whereIn("no_pol",$list_no_pol);
    }

    $uj_trx = $uj_trx->selectRaw("'UJ' as tipe,
    trx_trp.id as id,
    trx_trp.tanggal as tanggal,
    cast(trx_trp.pv_datetime as date) as tanggalpv,
    trx_trp.xto as xto,
    trx_trp.no_pol as no_pol,
    trx_trp.supir as supir,
    trx_trp.kernet as kernet,
    sum(if(is_ujdetails2.xfor = 'Supir' and is_ujdetails2.ac_account_code = '01.510.001', is_ujdetails2.qty * is_ujdetails2.amount, 0)) as gajis,
    sum(if(is_ujdetails2.xfor = 'Supir' and is_ujdetails2.ac_account_code = '01.510.005', is_ujdetails2.qty * is_ujdetails2.amount, 0)) as makans,
    sum(if(is_ujdetails2.xfor = 'Kernet' and is_ujdetails2.ac_account_code = '01.510.001', is_ujdetails2.qty * is_ujdetails2.amount, 0)) as gajik,
    sum(if(is_ujdetails2.xfor = 'Kernet' and is_ujdetails2.ac_account_code = '01.510.005', is_ujdetails2.qty * is_ujdetails2.amount, 0)) as makank")
    ->join('is_uj',function ($join){
      $join->on("is_uj.id","trx_trp.id_uj");   
    })
    ->join('is_ujdetails2',function ($join){
      $join->on("is_ujdetails2.id_uj","is_uj.id");   
    })
    ->groupBy('trx_trp.id','trx_trp.tanggal','trx_trp.pv_datetime','trx_trp.xto','trx_trp.no_pol','trx_trp.supir','trx_trp.kernet');
    
    $standby_trx = $standby_trx->selectRaw("'SB' as tipe,
    standby_trx.id as id,
    cast(standby_trx.created_at as date) as tanggal,
    cast(standby_trx.pv_datetime as date) as tanggalpv,
    standby_trx.xto as xto,
    standby_trx.no_pol as no_pol,
    standby_trx.supir as supir,
    standby_trx.kernet as kernet,
    sum(if(standby_trx_dtl.standby_trx_id = standby_trx.id, 1, 0)) as qty,
    sum(if(standby_dtl.xfor = 'Supir' and standby_dtl.ac_account_code = '01.510.001', standby_dtl.amount, 0)) as gajis,
    sum(if(standby_dtl.xfor = 'Supir' and standby_dtl.ac_account_code = '01.510.005', standby_dtl.amount, 0)) as makans,
    sum(if(standby_dtl.xfor = 'Kernet' and standby_dtl.ac_account_code = '01.510.001', standby_dtl.amount, 0)) as gajik,
    sum(if(standby_dtl.xfor = 'Kernet' and standby_dtl.ac_account_code = '01.510.005', standby_dtl.amount, 0)) as makank")
    ->join('standby_trx_dtl',function ($join){
      $join->on("standby_trx_dtl.standby_trx_id","standby_trx.id");   
    })
    ->join('standby_mst',function ($join){
      $join->on("standby_mst.id","standby_trx.standby_mst_id");   
    })
    ->join('standby_dtl',function ($join){
      $join->on("standby_dtl.standby_mst_id","standby_mst.id");   
    })
    ->groupBy('standby_trx.id','standby_trx.created_at','standby_trx.pv_datetime','standby_trx.xto','standby_trx.no_pol','standby_trx.supir','standby_trx.kernet');  

    if($jenis=="" || $jenis == "UJ"){
      $uj_trx = $uj_trx->where('trx_trp.val_ticket',1)->get();
    }else{
      $uj_trx = [];
    }

    if($jenis=="" || $jenis == "SB"){
      $standby_trx = $standby_trx->where('standby_trx.val2',">=",1)->get();
    }else{
      $standby_trx = [];
    }

    $data_all = [];
    $info=[];

    $info["from"]=$request->date_from ? date("d-m-Y",strtotime($request->date_from)) : "";
    $info["to"]=$request->date_from ? date("d-m-Y",strtotime($request->date_to)) : "";
    $info["now"]=date("d-m-Y H:i:s");
    $info["uj_gaji"] = 0;
    $info["uj_makan"] = 0;
    $info["sb_gaji"] = 0;
    $info["sb_makan"] = 0;
    $info["total"] = 0;

    foreach ($uj_trx as $k => $v) {      
      $supir = [
        "tipe"=>$v->tipe,
        "id"=>$v->id,
        "tanggal"=>$v->tanggal,
        "no_pol"=>$v->no_pol,
        "lokasi"=>$v->xto,
        "jabatan"=>"Supir",
        "nama"=>$v->supir,
        "gaji"=>$v->gajis,
        "makan"=>$v->makans,
        "total"=>(int)$v->gajis + (int)$v->makans,
      ];

      $kernet = [
        "tipe"=>$v->tipe,
        "id"=>$v->id,
        "tanggal"=>$v->tanggal,
        "no_pol"=>$v->no_pol,
        "lokasi"=>$v->xto,
        "jabatan"=>"Kernet",
        "nama"=>$v->kernet,
        "gaji"=>$v->gajik,
        "makan"=>$v->makank,
        "total"=>(int)$v->gajik + (int)$v->makank,
      ];

      if($v->supir && ( count($supir_list) == 0 || array_search($v->supir,$supir_list)!==false )){
        $info['uj_gaji'] += (int)$v->gajis;
        $info['uj_makan'] += (int)$v->makans;
      }

      if($v->kernet && ( count($kernet_list) == 0 || array_search($v->kernet,$kernet_list)!==false )){
        $info['uj_gaji'] += (int)$v->gajik;
        $info['uj_makan'] += (int)$v->makank;
      }

      if(count($data_all) == 0 )
      {

        if($v->supir &&( count($supir_list) == 0 || array_search($v->supir,$supir_list)!==false )){
          $data_all[$v->supir] = [$supir];
          
        }
        if($v->kernet && ( count($kernet_list) == 0 || array_search($v->kernet,$kernet_list)!==false )){
          $data_all[$v->kernet] = [$kernet];
          
        }
      }else{
        if($v->supir &&( count($supir_list) == 0 || array_search($v->supir,$supir_list)!==false )){
          if(!isset($data_all[$v->supir])){
            $data_all[$v->supir] = [$supir];
          }else{
            array_push($data_all[$v->supir],$supir);
          }
        }

        if($v->kernet && ( count($kernet_list) == 0 || array_search($v->kernet,$kernet_list)!==false )){
          if(!isset($data_all[$v->kernet])){
            $data_all[$v->kernet] = [$kernet];
          }else{
            array_push($data_all[$v->kernet],$kernet);
          }
        }
      }
    }

    foreach ($standby_trx as $k => $v) {
      $supir = [
        "tipe"=>$v->tipe,
        "id"=>$v->id,
        "tanggal"=>$v->tanggal,
        "no_pol"=>$v->no_pol,
        "lokasi"=>$v->xto,
        "jabatan"=>"Supir",
        "nama"=>$v->supir,
        "gaji"=>$v->gajis,
        "makan"=>$v->makans,
        "total"=>(int)$v->gajis + (int)$v->makans,
      ];

      $kernet = [
        "tipe"=>$v->tipe,
        "id"=>$v->id,
        "tanggal"=>$v->tanggal,
        "no_pol"=>$v->no_pol,
        "lokasi"=>$v->xto,
        "jabatan"=>"Kernet",
        "nama"=>$v->kernet,
        "gaji"=>$v->gajik,
        "makan"=>$v->makank,
        "total"=>(int)$v->gajik + (int)$v->makank,
      ];

      if($v->supir &&( count($supir_list) == 0 || array_search($v->supir,$supir_list)!==false )){
        $info['sb_gaji'] += (int)$v->gajis;
        $info['sb_makan'] += (int)$v->makans;
      }

      if($v->kernet && ( count($kernet_list) == 0 || array_search($v->kernet,$kernet_list)!==false )){
        $info['sb_gaji'] += (int)$v->gajik;
        $info['sb_makan'] += (int)$v->makank;
      }

      if(count($data_all) == 0 )
      {

        if($v->supir &&( count($supir_list) == 0 || array_search($v->supir,$supir_list)!==false )){
          $data_all[$v->supir] = [$supir];
          
        }
        if($v->kernet && ( count($kernet_list) == 0 || array_search($v->kernet,$kernet_list)!==false )){
          $data_all[$v->kernet] = [$kernet];
          
        }
      }else{
        if($v->supir &&( count($supir_list) == 0 || array_search($v->supir,$supir_list)!==false )){
          if(!isset($data_all[$v->supir])){
            $data_all[$v->supir] = [$supir];
          }else{
            array_push($data_all[$v->supir],$supir);
          }
        }

        if($v->kernet && ( count($kernet_list) == 0 || array_search($v->kernet,$kernet_list)!==false )){
          if(!isset($data_all[$v->kernet])){
            $data_all[$v->kernet] = [$kernet];
          }else{
            array_push($data_all[$v->kernet],$kernet);
          }
        }
      }
    }
    $info["total"] = $info['uj_gaji']+$info['uj_makan']+$info['sb_gaji']+$info['sb_makan'];

    ksort($data_all);
    
    $data_processed =[];
    if($type=="detail"){
      foreach ($data_all as $k => $v) {
        usort($data_all[$k],function($a, $b){
          return $a['tanggal'] <=> $a['tanggal'] && $a['id'] <=> $a['id'];
        });
        $dt = $data_all[$k];
        $data_processed=array_merge($data_processed,$dt);
      }
    }else if($type=='header'){
      foreach ($data_all as $k => $v) {

        $dt=[
          "nama"=>"",
          "jabatan"=>"",
          "uj_gaji"=>0,
          "uj_makan"=>0,
          "uj_total"=>0,
          "sb_gaji"=>0,
          "sb_makan"=>0,          
          "sb_total"=>0,
          "total"=>0
        ];

        foreach ($data_all[$k] as $k1 => $v1) {
          if($dt['nama']==""){
            $dt['nama']=$v1['nama'];
            $dt['jabatan']=$v1['jabatan']; 
          }

          if($v1['tipe']=='UJ'){
            $dt['uj_gaji']+=$v1['gaji'];
            $dt['uj_makan']+=$v1['makan'];
            $dt['uj_total']+=$v1['gaji']+$v1['makan'];
          }else{
            $dt['sb_gaji']+=$v1['gaji'];
            $dt['sb_makan']+=$v1['makan'];
            $dt['sb_total']+=$v1['gaji']+$v1['makan'];
          }
        }
        $dt['total'] += $dt['uj_total'] + $dt['sb_total'];
        array_push($data_processed,$dt);
      }
    }
    return response()->json([
      "data" => $data_processed,
      "info" => $info
    ], 200);
  }

  public function pdfPreview(Request $request){
    MyAdmin::checkScope($this->permissions, 'report.ast_n_driver.download_file');

    set_time_limit(0);
    $callGet = $this->index($request);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    $data = $ori["data"];
    $info = $ori["info"];
    
    $type = $request->type;

    if($type=='detail'){
      foreach ($data as $k => $v) {
        $data[$k]["gaji"] = number_format($data[$k]["gaji"],0,",",".");
        $data[$k]["makan"] = number_format($data[$k]["makan"],0,",",".");
        $data[$k]["total"] = number_format($data[$k]["total"],2,",",".");
      }
  
    }else{
      foreach ($data as $k => $v) {
        $data[$k]["uj_gaji"] = number_format($data[$k]["uj_gaji"],0,",",".");
        $data[$k]["uj_makan"] = number_format($data[$k]["uj_makan"],0,",",".");
        $data[$k]["uj_total"] = number_format($data[$k]["uj_total"],2,",",".");
        $data[$k]["sb_gaji"] = number_format($data[$k]["sb_gaji"],0,",",".");
        $data[$k]["sb_makan"] = number_format($data[$k]["sb_makan"],0,",",".");
        $data[$k]["sb_total"] = number_format($data[$k]["sb_total"],0,",",".");
        $data[$k]["total"] = number_format($data[$k]["total"],0,",",".");
      }
    }

    $info["uj_gaji"]=number_format($info["uj_gaji"],0,",",".");
    $info["uj_makan"]=number_format($info["uj_makan"],0,",",".");
    $info["sb_gaji"]=number_format($info["sb_gaji"],0,",",".");
    $info["sb_makan"]=number_format($info["sb_makan"],0,",",".");
    $info["total"]=number_format($info["total"],0,",",".");

    $blade= ($request->type=='detail'?'pdf.asnd_detail':'pdf.asnd_header');

    

    $date = new \DateTime();
    $filename = $date->format("YmdHis");
    PDF::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
    $pdf = PDF::loadView($blade, ["data"=>$data,"info"=>$info])->setPaper('a4', 'portrait');


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

  public function excelDownload(Request $request){
    MyAdmin::checkScope($this->permissions, 'report.ast_n_driver.download_file');

    set_time_limit(0);
    $callGet = $this->index($request);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    $data = $ori["data"];
    $info = $ori["info"];
    

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-ramp_info'."[".$info["from"]."-".$info["to"]."]";

    $mime=MyLib::mime("xlsx");

    $blade= ($request->type=='detail'?'excel.asnd_detail':'excel.asnd_header');

    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$data,"info"=>$info],$blade), $mime["exportType"]));

    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }
}
