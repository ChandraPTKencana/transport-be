<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Exceptions\MyException;

use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;

use App\Exports\MyReport;
use App\Helpers\MyLog;
use App\Models\MySql\TrxTrp;
use App\Models\MySql\Ujalan;
use App\Models\MySql\UjalanDetail2;

class DistanceController extends Controller
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

  public function getLocations(Request $request){
    MyAdmin::checkScope($this->permissions, 'report.distance.download_file');

    $list_xto = \App\Models\MySql\Ujalan::select('xto')->where("deleted",0)->where('val',1)->where('val1',1)->groupBy('xto')->get()->pluck('xto');
    return response()->json([
      "list_xto" => $list_xto,
    ], 200);
  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'report.distance.download_file');

    $model_query = TrxTrp::where('val_ticket',1)->where("deleted",0)->orderBy("xto","asc");
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

      $date_from = date("Y-m-d",strtotime($date_from));
      $date_to = date("Y-m-d",strtotime($date_to));

      $model_query = $model_query->whereBetween("tanggal",[$date_from,$date_to]);
    }

    if($request->location){
      if(!Ujalan::where('xto',$request->location)->first())
      throw new MyException(["location"=>["Location Tidak Ditemukan"]], 422);
      
      $model_query = $model_query->where("xto",$request->location);
    }
    
    // return response()->json([
    //   "data" => $model_query->toSql(),
    // ], 200);
    $model_query = $model_query->get();
    $all_id_uj = array_map(function($x){
      return $x["id_uj"];
    },$model_query->toArray());

    $all_uj = UjalanDetail2::whereIn("id_uj",$all_id_uj)->get()->toArray();

    $data=[];

    $acc_ugaji = '01.510.001';
    $acc_umakan = '01.510.005';
    $acc_udinas = '01.575.002';
    $acc_solar_kendaraan = '01.530.001';
    $acc_operasional_trp = '01.575.001';

    foreach ($model_query as $k => $v) {

      $id_uj = $v->id_uj;
      // $xto = $v->xto;
      $supir = $v->supir;
      $kernet = $v->kernet;
      $no_pol = $v->no_pol;
      $distance = $v->uj->km_range;
      $tonase = 0;
      $solar = 0;
      $operasional = 0;
      $lainnya = 0;

      switch ($v->jenis) {
        case 'TBS':
          $tonase = $v->ticket_b_netto;
          break;
        case 'TBSK':
          $tonase = $v->ticket_b_netto;
          break;
        case 'CPO':
          $tonase = $v->ticket_a_netto;
          break;
        case 'PK':
          $tonase = $v->ticket_a_netto;
          break;
      }
 
      $ujalan_pvr_dtls = array_filter($all_uj,function($x)use($id_uj){
        return $x["id_uj"] == $id_uj;
      });

      $u_gaji_supir = 0;
      $u_gaji_kernet = 0;
      $u_dinas_supir = 0;
      $u_makan_supir = 0;
      $u_makan_kernet = 0;
      $u_dinas_kernet = 0;
      $extra_money = 0;
      
      foreach ($ujalan_pvr_dtls as $uk => $uv) {
        $ttl = $uv['amount'] * $uv['qty'];
        if($uv['xfor']=="Supir" && $uv['ac_account_code']==$acc_ugaji){ $u_gaji_supir = $ttl; }
        else if($uv['xfor']=="Supir" &&  $uv['ac_account_code']==$acc_umakan){ $u_makan_supir = $ttl; }
        else if($uv['xfor']=="Supir" &&  $uv['ac_account_code']==$acc_udinas){ $u_dinas_supir = $ttl; }
        else if($uv['xfor']=="Kernet" && $uv['ac_account_code']==$acc_ugaji){ $u_gaji_kernet = $ttl;}
        else if($uv['xfor']=="Kernet" && $uv['ac_account_code']==$acc_umakan){ $u_makan_kernet = $ttl; }
        else if($uv['xfor']=="Kernet" && $uv['ac_account_code']==$acc_udinas){ $u_dinas_kernet = $ttl; }
        else if($uv['ac_account_code']==$acc_solar_kendaraan){ $solar = $ttl; }
        else if($uv['ac_account_code']==$acc_operasional_trp){ $operasional = $ttl; }
        else { $lainnya = $ttl;}
      }


      foreach ($v->extra_money_trxs as $vek => $vev){
        $vettl = $vev->extra_money->qty * $vev->extra_money->nominal;
        if($vev->extra_money->ac_account_code==$acc_solar_kendaraan){$extra_money += $vettl;}
        else if($vev->extra_money->ac_account_code==$acc_operasional_trp){$extra_money += $vettl;}
        else {$extra_money += $vettl;}
      }

      // $data_xtos = array_map(function($x){
      //   return $x["xto"];
      // },$data);


      $data_no_pols = array_map(function($x){
        return $x["no_pol"];
      },$data);

      $index = array_search($no_pol,$data_no_pols);
      
      if(count($data)==0 || $index===false){
        $dt =[
          "no_pol"      => $no_pol,
          "solar"       => $solar,
          "operasional" => $operasional,
          // "trip"=>1,
          "tonase"      => $tonase,
          "distance"    => $distance,
          "supir"       => ($u_gaji_supir + $u_makan_supir + $u_dinas_supir),
          "kernet"      => 0,
          "lainnya"     => $lainnya,
          "extra_money" => $extra_money,
        ];

        if($kernet){
          $dt["kernet"] = ($u_gaji_kernet + $u_makan_kernet + $u_dinas_kernet);
        }

        array_push($data,$dt);
      }else{
        // $data[$index]["trip"]+=1;
        $data[$index]["tonase"]+=$tonase;
        $data[$index]["distance"]+=$distance;
        $data[$index]["solar"]+=$solar;
        $data[$index]["operasional"]+=$operasional;
        $data[$index]["lainnya"]+=$lainnya;
        $data[$index]["extra_money"]+=$extra_money;
        $data[$index]["supir"] += ($u_gaji_supir + $u_makan_supir + $u_dinas_supir);
        
        if(!$kernet) continue;
        $data[$index]["kernet"] += ($u_gaji_kernet + $u_makan_kernet + $u_dinas_kernet);
      }
    }


    $info=[
      "ttl_solar"=>0,
      "ttl_operasional"=>0,
      "ttl_tonase"=>0,
      "ttl_distance"=>0,
      "ttl_supir"=>0,
      "ttl_kernet"=>0,
      "ttl_lainnya"=>0,
      "ttl_extra_money"=>0,
      "from"=>date("d-m-Y",strtotime($request->date_from)),
      "to"=>date("d-m-Y",strtotime($request->date_to)),
      "now"=>date("d-m-Y H:i:s"),
    ];

    $info["from"]=$request->date_from ? date("d-m-Y",strtotime($request->date_from)) : "";
    $info["to"]=$request->date_from ? date("d-m-Y",strtotime($request->date_to)) : "";
    $info["now"]=date("d-m-Y H:i:s");

    foreach ($data as $k => $v) {
      $info["ttl_solar"]+=$data[$k]["solar"];
      $info["ttl_operasional"]+=$data[$k]["operasional"];
      $info["ttl_tonase"]+=$data[$k]["tonase"];
      $info["ttl_distance"]+=$data[$k]["distance"];
      $info["ttl_supir"]+=$data[$k]["supir"];
      $info["ttl_kernet"]+=$data[$k]["kernet"];
      $info["ttl_lainnya"]+=$data[$k]["lainnya"];
      $info["ttl_extra_money"]+=$data[$k]["extra_money"];
    }

    return response()->json([
      "data" => $data,
      "info" => $info
    ], 200);
  }

  public function pdfPreview(Request $request){
    MyAdmin::checkScope($this->permissions, 'report.distance.download_file');

    set_time_limit(0);
    $callGet = $this->index($request);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    $data = $ori["data"];
    $info = $ori["info"];
    
    // return response()->json([
    //   "data" => $data,
    //   "info" => $info
    // ], 200);
    foreach ($data as $k => $v) {
      $data[$k]["distance"] = number_format($data[$k]["distance"],0,",",".");
      $data[$k]["supir"] = number_format($data[$k]["supir"],0,",",".");
      $data[$k]["kernet"] = number_format($data[$k]["kernet"],0,",",".");
      $data[$k]["solar"] = number_format($data[$k]["solar"],2,",",".");
      $data[$k]["operasional"] = number_format($data[$k]["operasional"],0,",",".");
      $data[$k]["tonase"] = number_format($data[$k]["tonase"],0,",",".");
      $data[$k]["lainnya"] = number_format($data[$k]["lainnya"],0,",",".");
    }

    $info["ttl_distance"]=number_format($info["ttl_distance"],0,",",".");
    $info["ttl_supir"]=number_format($info["ttl_supir"],0,",",".");
    $info["ttl_kernet"]=number_format($info["ttl_kernet"],0,",",".");
    $info["ttl_solar"]=number_format($info["ttl_solar"],0,",",".");
    $info["ttl_operasional"]=number_format($info["ttl_operasional"],0,",",".");
    $info["ttl_tonase"]=number_format($info["ttl_tonase"],0,",",".");
    $info["ttl_lainnya"]=number_format($info["ttl_lainnya"],0,",",".");

    $date = new \DateTime();
    $filename = $date->format("YmdHis");
    Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
    $pdf = PDF::loadView('pdf.distance', ["data"=>$data,"info"=>$info])->setPaper('a4', 'portrait');

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
    MyAdmin::checkScope($this->permissions, 'report.distance.download_file');

    set_time_limit(0);
    $callGet = $this->index($request);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    $data = $ori["data"];
    $info = $ori["info"];
    

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-distance'."[".$info["from"]."-".$info["to"]."]";

    $mime=MyLib::mime("xlsx");
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$data],'excel.distance'), $mime["exportType"]));


    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }
}
