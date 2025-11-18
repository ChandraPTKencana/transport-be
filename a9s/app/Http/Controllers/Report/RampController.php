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

use App\Models\MySql\TrxTrp;
use App\Models\MySql\Ujalan;
use App\Models\MySql\UjalanDetail2;

class RampController extends Controller
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
    MyAdmin::checkScope($this->permissions, 'report.ramp.download_file');

    $list_xto = \App\Models\MySql\Ujalan::select('xto')->where("deleted",0)->where('val',1)->where('val1',1)->where('val2',1)->where('val3',1)->groupBy('xto')->get()->pluck('xto');
    return response()->json([
      "list_xto" => $list_xto,
    ], 200);
  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'report.ramp.download_file');

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

    $all_uj = UjalanDetail2::whereIn("id_uj",$all_id_uj)->whereIn("xfor",["Supir","Kernet"])->get()->toArray();

    $data=[];

    $acc_ugaji = '01.510.001';
    $acc_umakan = '01.510.005';
    $acc_udinas = '01.575.002';
    foreach ($model_query as $k => $v) {

      $id_uj = $v->id_uj;
      $xto = $v->xto;
      $supir = $v->supir;
      $kernet = $v->kernet;
      $tonase = 0;

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
      
      foreach ($ujalan_pvr_dtls as $uk => $uv) {
        $ttl = $uv['amount'] * $uv['qty'];
        if($uv['xfor']=="Supir" && $uv['ac_account_code']==$acc_ugaji){ $u_gaji_supir = $ttl; }
        if($uv['xfor']=="Supir" &&  $uv['ac_account_code']==$acc_umakan){ $u_makan_supir = $ttl; }
        if($uv['xfor']=="Supir" &&  $uv['ac_account_code']==$acc_udinas){ $u_dinas_supir = $ttl; }
        if($uv['xfor']=="Kernet" && $uv['ac_account_code']==$acc_ugaji){ $u_gaji_kernet = $ttl;}
        if($uv['xfor']=="Kernet" && $uv['ac_account_code']==$acc_umakan){ $u_makan_kernet = $ttl; }
        if($uv['xfor']=="Kernet" && $uv['ac_account_code']==$acc_udinas){ $u_dinas_kernet = $ttl; }
      }

      $data_xtos = array_map(function($x){
        return $x["xto"];
      },$data);

      $index = array_search($xto,$data_xtos);
      
      if(count($data)==0 || $index===false){
        $dt =[
          "xto"=>$xto,
          "trip"=>1,
          "tonase"=>$tonase,
          "supirs"=>[
            [
              "name" => $supir,
              "ugaji" => $u_gaji_supir,
              "umakan" => $u_makan_supir,
              "udinas" => $u_dinas_supir,
            ]
          ]
        ];

        if($kernet){
          $dt["kernets"] =[
            [ 
            "name"=>$kernet,
            "ugaji" => $u_gaji_kernet,
            "umakan" => $u_makan_kernet,
            "udinas" => $u_dinas_kernet,
            ]
          ];
        }else{
          $dt["kernets"]=[];
        }

        array_push($data,$dt);
      }else{
        $data[$index]["trip"]+=1;
        $data[$index]["tonase"]+=$tonase;

        $supir_names = array_map(function($x){
          return $x["name"];
        },$data[$index]["supirs"]);

        $supir_find=array_search($supir,$supir_names);
        if(count($data[$index]['supirs'])==0 || $supir_find===false){
          array_push($data[$index]['supirs'],[
            "name" => $supir,
            "ugaji" => $u_gaji_supir,
            "umakan" => $u_makan_supir,
            "udinas" => $u_dinas_supir,
          ]);
        }else{
          $data[$index]["supirs"][$supir_find]["ugaji"] += $u_gaji_supir;
          $data[$index]["supirs"][$supir_find]["umakan"] += $u_makan_supir;
          $data[$index]["supirs"][$supir_find]["udinas"] += $u_dinas_supir;
        }

        if(!$kernet) continue;
        $kernet_names = array_map(function($x){
          return $x["name"];
        },$data[$index]["kernets"]);
        // try {
        // } catch (\Throwable $th) {
        //   return response()->json([
        //     "kernet" => $data[$index]["kernets"],
        //   ], 200);
        // }
        

        $kernet_find=array_search($kernet,$kernet_names);
        if(count($data[$index]['kernets'])==0 || $kernet_find===false){
          array_push($data[$index]['kernets'],[
            "name" => $kernet,
            "ugaji" => $u_gaji_kernet,
            "umakan" => $u_makan_kernet,
            "udinas" => $u_dinas_kernet,
          ]);
        }else{
          $data[$index]["kernets"][$kernet_find]["ugaji"] += $u_gaji_kernet;
          $data[$index]["kernets"][$kernet_find]["umakan"] += $u_makan_kernet;
          $data[$index]["kernets"][$kernet_find]["udinas"] += $u_dinas_kernet;
        }
      }
    }

    $info=[
      "ttl_supir"=>0,
      "ttl_kernet"=>0,
      "ttl_gaji_supir"=>0,
      "ttl_makan_supir"=>0,
      "ttl_dinas_supir"=>0,
      "ttl_gaji_kernet"=>0,
      "ttl_makan_kernet"=>0,
      "ttl_dinas_kernet"=>0,
      "ttl_tonase"=>0,
      "ttl_rt_tonase"=>0,
      "ttl_trip"=>0,
      "from"=>date("d-m-Y",strtotime($request->date_from)),
      "to"=>date("d-m-Y",strtotime($request->date_to)),
      "now"=>date("d-m-Y H:i:s"),
    ];

    $info["from"]=$request->date_from ? date("d-m-Y",strtotime($request->date_from)) : "";
    $info["to"]=$request->date_from ? date("d-m-Y",strtotime($request->date_to)) : "";
    $info["now"]=date("d-m-Y H:i:s");

    foreach ($data as $k => $v) {
      $data[$k]["z_rt_tonase"] = $data[$k]["tonase"] / $data[$k]["trip"];
      $data[$k]["z_supir"] = count($data[$k]["supirs"]);
      $data[$k]["z_kernet"] = count($data[$k]["kernets"]);
      $data[$k]["z_gaji_supir"] = array_reduce($data[$k]["supirs"],function($res,$dt){
        $res+=$dt["ugaji"];
        return $res;
      },0);
      $data[$k]["z_makan_supir"] = array_reduce($data[$k]["supirs"],function($res,$dt){
        $res+=$dt["umakan"];
        return $res;
      },0);
      $data[$k]["z_dinas_supir"] = array_reduce($data[$k]["supirs"],function($res,$dt){
        $res+=$dt["udinas"];
        return $res;
      },0);
      $data[$k]["z_gaji_kernet"] = array_reduce($data[$k]["kernets"],function($res,$dt){
        $res+=$dt["ugaji"];
        return $res;
      },0);
      $data[$k]["z_makan_kernet"] = array_reduce($data[$k]["kernets"],function($res,$dt){
        $res+=$dt["umakan"];
        return $res;
      },0);
      $data[$k]["z_dinas_kernet"] = array_reduce($data[$k]["kernets"],function($res,$dt){
        $res+=$dt["udinas"];
        return $res;
      },0);

      $info["ttl_supir"]+=$data[$k]["z_supir"];
      $info["ttl_kernet"]+=$data[$k]["z_kernet"];
      $info["ttl_gaji_supir"]+=$data[$k]["z_gaji_supir"];
      $info["ttl_gaji_kernet"]+=$data[$k]["z_gaji_kernet"];
      $info["ttl_makan_supir"]+=$data[$k]["z_makan_supir"];
      $info["ttl_makan_kernet"]+=$data[$k]["z_makan_kernet"];
      $info["ttl_dinas_supir"]+=$data[$k]["z_dinas_supir"];
      $info["ttl_dinas_kernet"]+=$data[$k]["z_dinas_kernet"];
      $info["ttl_tonase"]+=$data[$k]["tonase"];
      $info["ttl_rt_tonase"]+=$data[$k]["z_rt_tonase"];
      $info["ttl_trip"]+=$data[$k]["trip"];
    }

    return response()->json([
      "data" => $data,
      "info" => $info
    ], 200);
  }

  public function pdfPreview(Request $request){
    MyAdmin::checkScope($this->permissions, 'report.ramp.download_file');

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
      $data[$k]["trip"] = number_format($data[$k]["trip"],0,",",".");
      $data[$k]["tonase"] = number_format($data[$k]["tonase"],0,",",".");
      $data[$k]["z_rt_tonase"] = number_format($data[$k]["z_rt_tonase"],2,",",".");
      $data[$k]["z_supir"] = number_format($data[$k]["z_supir"],0,",",".");
      $data[$k]["z_kernet"] = number_format($data[$k]["z_kernet"],0,",",".");
      $data[$k]["z_gaji_supir"] = number_format($data[$k]["z_gaji_supir"],0,",",".");
      $data[$k]["z_makan_supir"] = number_format($data[$k]["z_makan_supir"],0,",",".");
      $data[$k]["z_dinas_supir"] = number_format($data[$k]["z_dinas_supir"],0,",",".");
      $data[$k]["z_gaji_kernet"] = number_format($data[$k]["z_gaji_kernet"],0,",",".");
      $data[$k]["z_makan_kernet"] = number_format($data[$k]["z_makan_kernet"],0,",",".");
      $data[$k]["z_dinas_kernet"] = number_format($data[$k]["z_dinas_kernet"],0,",",".");
    }

    $info["ttl_gaji_supir"]=number_format($info["ttl_gaji_supir"],0,",",".");
    $info["ttl_gaji_kernet"]=number_format($info["ttl_gaji_kernet"],0,",",".");
    $info["ttl_makan_supir"]=number_format($info["ttl_makan_supir"],0,",",".");
    $info["ttl_makan_kernet"]=number_format($info["ttl_makan_kernet"],0,",",".");
    $info["ttl_dinas_supir"]=number_format($info["ttl_dinas_supir"],0,",",".");
    $info["ttl_dinas_kernet"]=number_format($info["ttl_dinas_kernet"],0,",",".");
    $info["ttl_tonase"]=number_format($info["ttl_tonase"],0,",",".");
    $info["ttl_rt_tonase"]=number_format($info["ttl_rt_tonase"],2,",",".");
    $info["ttl_trip"]=number_format($info["ttl_trip"],0,",",".");



    $date = new \DateTime();
    $filename = $date->format("YmdHis");
    Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
    $pdf = PDF::loadView('pdf.ramp_info', ["data"=>$data,"info"=>$info])->setPaper('a4', 'portrait');


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
    MyAdmin::checkScope($this->permissions, 'report.ramp.download_file');

    set_time_limit(0);
    $callGet = $this->index($request);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    $data = $ori["data"];
    $info = $ori["info"];
    

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-ramp_info'."[".$info["from"]."-".$info["to"]."]";

    $mime=MyLib::mime("xlsx");
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$data],'excel.ramp_info'), $mime["exportType"]));


    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }
}
