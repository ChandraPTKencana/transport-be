<?php

namespace App\Http\Controllers\Report;

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
use Excel;

use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\IsUser;
use App\Exports\MyReport;
use App\Models\MySql\Employee;
use App\Models\MySql\StandbyTrx;
use App\Models\MySql\TrxAbsen;
use App\Models\MySql\Ujalan;
use App\Models\MySql\UjalanDetail;
use App\Models\MySql\UjalanDetail2;

class AstNDriverController extends Controller
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

  public function loadData(Request $request){

    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

    $list_xto = \App\Models\MySql\Ujalan::select('xto')->where("deleted",0)->where('val',1)->where('val1',1)->orderBy('xto','asc')->groupBy('xto')->get()->pluck('xto');
    $list_employee = \App\Models\MySql\Employee::where("deleted",0)->orderBy('name','asc')->get();
    $list_vehicle = \App\Models\MySql\Vehicle::where("deleted",0)->orderBy('no_pol','asc')->get();
    return response()->json([
      "list_xto" => $list_xto,
      "list_employee" => $list_employee,
      "list_vehicle" => $list_vehicle,
    ], 200);
  }

  public function index(Request $request)
  {
    $list_xto = json_decode($request->list_xto, true);
    $list_employee = json_decode($request->list_employee, true);
    $list_vehicle = json_decode($request->list_vehicle, true);
    $type= $request->type;

    if($type=='' || array_search($type,["header",'detail'])===false){
      throw new MyException([ "type" => ["Tipe Harus dipilih"] ], 422);
    }

    $uj_trx = new TrxTrp();
    $standby_trx = new StandbyTrx();

    // $model_query = TrxTrp::where('val1',1)->where("deleted",0)->orderBy("xto","asc");
    // // $model_query = TrxTrp::where('val2',1)->where("deleted",0);
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

      $uj_trx = $uj_trx->where("tanggal",">=",$date_from)->where('tanggal',"<=",$date_to);

      $date_from = $date_from." 00:00:00";
      $date_to = $date_to." 23:59:59";
      $standby_trx = $standby_trx->where("standby_trx.created_at",">=",$date_from)->where('standby_trx.created_at',"<=",$date_to);
    }

    if(count($list_xto)>0){
      $uj_trx = $uj_trx->whereIn("xto",$list_xto);
      $standby_trx = $standby_trx->whereIn("xto",$list_xto);
    }

    if(count($list_employee)>0){
      $supir=[];
      $kernet=[];

      $employees = Employee::whereIn("id",$list_employee)->get()->toArray();

      foreach ($employees as $k => $v) {
        if($v['role']=='Supir'){
          array_push($supir,$v['name']);
        }
        
        if($v['role']=='Kernet'){
          array_push($kernet,$v['name']);
        }  
      }

      if(count($supir) > 0 || count($kernet) > 0){
        $uj_trx=$uj_trx->where(function ($q)use($supir,$kernet){         
          if(count($supir) > 0){
            $q->whereIn("supir",$supir);
          }
          if(count($kernet) > 0){
            $q->orWhereIn("kernet",$kernet);
          }
        });

        $standby_trx=$standby_trx->where(function ($q)use($supir,$kernet){         
          if(count($supir) > 0){
            $q->whereIn("supir",$supir);
          }
          if(count($kernet) > 0){
            $q->orWhereIn("kernet",$kernet);
          }
        });

      }
    }

    if(count($list_vehicle)>0){
      $uj_trx = $uj_trx->whereIn("xto",$list_vehicle);
      $standby_trx = $standby_trx->whereIn("xto",$list_vehicle);
    }


    $uj_trx = $uj_trx->selectRaw("'UJ' as tipe,
    trx_trp.id as id,
    trx_trp.tanggal as tanggal,
    cast(trx_trp.pv_datetime as date) as tanggalpv,
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
    ->groupBy('trx_trp.id','trx_trp.tanggal','trx_trp.pv_datetime','trx_trp.no_pol','trx_trp.supir','trx_trp.kernet')->get();
    


    $standby_trx = $standby_trx->selectRaw("'SB' as tipe,
    standby_trx.id as id,
    cast(standby_trx.created_at as date) as tgl,
    cast(standby_trx.pv_datetime as date) as tglpv,
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
    ->groupBy('standby_trx.id','standby_trx.created_at','standby_trx.pv_datetime','standby_trx.no_pol','standby_trx.supir','standby_trx.kernet')->get();  

  


  // // return response()->json([
    // //   "data" => $model_query->toSql(),
    // // ], 200);
    // $model_query = $model_query->get();
    // $all_id_uj = array_map(function($x){
    //   return $x["id_uj"];
    // },$model_query->toArray());

    // $all_uj = UjalanDetail2::whereIn("id_uj",$all_id_uj)->whereIn("xfor",["Supir","Kernet"])->get()->toArray();

    // $data=[];

    // $acc_ugaji = '01.510.001';
    // $acc_umakan = '01.510.005';
    // foreach ($model_query as $k => $v) {

    //   $id_uj = $v->id_uj;
    //   $xto = $v->xto;
    //   $supir = $v->supir;
    //   $kernet = $v->kernet;
    //   $tonase = 0;

    //   switch ($v->jenis) {
    //     case 'TBS':
    //       $tonase = $v->ticket_b_netto;
    //       break;
    //     case 'TBSK':
    //       $tonase = $v->ticket_b_netto;
    //       break;
    //     case 'CPO':
    //       $tonase = $v->ticket_a_netto;
    //       break;
    //     case 'PK':
    //       $tonase = $v->ticket_a_netto;
    //       break;
    //   }
 
    //   $ujalan_pvr_dtls = array_filter($all_uj,function($x)use($id_uj){
    //     return $x["id_uj"] == $id_uj;
    //   });
    //   $u_gaji_supir = 0;
    //   $u_gaji_kernet = 0;
    //   $u_makan_supir = 0;
    //   $u_makan_kernet = 0;
      
    //   foreach ($ujalan_pvr_dtls as $uk => $uv) {
    //     $ttl = $uv['amount'] * $uv['qty'];
    //     if($uv['xfor']=="Supir" && $uv['ac_account_code']==$acc_ugaji){ $u_gaji_supir = $ttl; }
    //     if($uv['xfor']=="Supir" &&  $uv['ac_account_code']==$acc_umakan){ $u_makan_supir = $ttl; }
    //     if($uv['xfor']=="Kernet" && $uv['ac_account_code']==$acc_ugaji){ $u_gaji_kernet = $ttl;}
    //     if($uv['xfor']=="Kernet" && $uv['ac_account_code']==$acc_umakan){ $u_makan_kernet = $ttl; }
    //   }

    //   $data_xtos = array_map(function($x){
    //     return $x["xto"];
    //   },$data);

    //   $index = array_search($xto,$data_xtos);
      
    //   if(count($data)==0 || $index===false){
    //     $dt =[
    //       "xto"=>$xto,
    //       "trip"=>1,
    //       "tonase"=>$tonase,
    //       "supirs"=>[
    //         [
    //           "name" => $supir,
    //           "ugaji" => $u_gaji_supir,
    //           "umakan" => $u_makan_supir,
    //         ]
    //       ]
    //     ];

    //     if($kernet){
    //       $dt["kernets"] =[
    //         [ 
    //         "name"=>$kernet,
    //         "ugaji" => $u_gaji_kernet,
    //         "umakan" => $u_makan_kernet,
    //         ]
    //       ];
    //     }else{
    //       $dt["kernets"]=[];
    //     }

    //     array_push($data,$dt);
    //   }else{
    //     $data[$index]["trip"]+=1;
    //     $data[$index]["tonase"]+=$tonase;

    //     $supir_names = array_map(function($x){
    //       return $x["name"];
    //     },$data[$index]["supirs"]);

    //     $supir_find=array_search($supir,$supir_names);
    //     if(count($data[$index]['supirs'])==0 || $supir_find===false){
    //       array_push($data[$index]['supirs'],[
    //         "name" => $supir,
    //         "ugaji" => $u_gaji_supir,
    //         "umakan" => $u_makan_supir,
    //       ]);
    //     }else{
    //       $data[$index]["supirs"][$supir_find]["ugaji"] += $u_gaji_supir;
    //       $data[$index]["supirs"][$supir_find]["umakan"] += $u_makan_supir;
    //     }

    //     if(!$kernet) continue;
    //     $kernet_names = array_map(function($x){
    //       return $x["name"];
    //     },$data[$index]["kernets"]);
    //     // try {
    //     // } catch (\Throwable $th) {
    //     //   return response()->json([
    //     //     "kernet" => $data[$index]["kernets"],
    //     //   ], 200);
    //     // }
        

    //     $kernet_find=array_search($kernet,$kernet_names);
    //     if(count($data[$index]['kernets'])==0 || $kernet_find===false){
    //       array_push($data[$index]['kernets'],[
    //         "name" => $kernet,
    //         "ugaji" => $u_gaji_kernet,
    //         "umakan" => $u_makan_kernet,
    //       ]);
    //     }else{
    //       $data[$index]["kernets"][$kernet_find]["ugaji"] += $u_gaji_kernet;
    //       $data[$index]["kernets"][$kernet_find]["umakan"] += $u_makan_kernet;
    //     }
    //   }
    // }

    // $info=[
    //   "ttl_supir"=>0,
    //   "ttl_kernet"=>0,
    //   "ttl_gaji_supir"=>0,
    //   "ttl_makan_supir"=>0,
    //   "ttl_gaji_kernet"=>0,
    //   "ttl_makan_kernet"=>0,
    //   "ttl_tonase"=>0,
    //   "ttl_rt_tonase"=>0,
    //   "ttl_trip"=>0,
    //   "from"=>date("d-m-Y",strtotime($request->date_from)),
    //   "to"=>date("d-m-Y",strtotime($request->date_to)),
    //   "now"=>date("d-m-Y H:i:s"),
    // ];

    // $info["from"]=$request->date_from ? date("d-m-Y",strtotime($request->date_from)) : "";
    // $info["to"]=$request->date_from ? date("d-m-Y",strtotime($request->date_to)) : "";
    // $info["now"]=date("d-m-Y H:i:s");

    // foreach ($data as $k => $v) {
    //   $data[$k]["z_rt_tonase"] = $data[$k]["tonase"] / $data[$k]["trip"];
    //   $data[$k]["z_supir"] = count($data[$k]["supirs"]);
    //   $data[$k]["z_kernet"] = count($data[$k]["kernets"]);
    //   $data[$k]["z_gaji_supir"] = array_reduce($data[$k]["supirs"],function($res,$dt){
    //     $res+=$dt["ugaji"];
    //     return $res;
    //   },0);
    //   $data[$k]["z_makan_supir"] = array_reduce($data[$k]["supirs"],function($res,$dt){
    //     $res+=$dt["umakan"];
    //     return $res;
    //   },0);
    //   $data[$k]["z_gaji_kernet"] = array_reduce($data[$k]["kernets"],function($res,$dt){
    //     $res+=$dt["ugaji"];
    //     return $res;
    //   },0);
    //   $data[$k]["z_makan_kernet"] = array_reduce($data[$k]["kernets"],function($res,$dt){
    //     $res+=$dt["umakan"];
    //     return $res;
    //   },0);

    //   $info["ttl_supir"]+=$data[$k]["z_supir"];
    //   $info["ttl_kernet"]+=$data[$k]["z_kernet"];
    //   $info["ttl_gaji_supir"]+=$data[$k]["z_gaji_supir"];
    //   $info["ttl_gaji_kernet"]+=$data[$k]["z_gaji_kernet"];
    //   $info["ttl_makan_supir"]+=$data[$k]["z_makan_supir"];
    //   $info["ttl_makan_kernet"]+=$data[$k]["z_makan_kernet"];
    //   $info["ttl_tonase"]+=$data[$k]["tonase"];
    //   $info["ttl_rt_tonase"]+=$data[$k]["z_rt_tonase"];
    //   $info["ttl_trip"]+=$data[$k]["trip"];
    // }

    return response()->json([
      "data" => $uj_trx->get(),
      "info" => $standby_trx->get()

      // "data" => $data,
      // "info" => $info
    ], 200);
  }

  public function pdfPreview(Request $request){
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

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
      $data[$k]["z_gaji_kernet"] = number_format($data[$k]["z_gaji_kernet"],0,",",".");
      $data[$k]["z_makan_kernet"] = number_format($data[$k]["z_makan_kernet"],0,",",".");
    }

    $info["ttl_gaji_supir"]=number_format($info["ttl_gaji_supir"],0,",",".");
    $info["ttl_gaji_kernet"]=number_format($info["ttl_gaji_kernet"],0,",",".");
    $info["ttl_makan_supir"]=number_format($info["ttl_makan_supir"],0,",",".");
    $info["ttl_makan_kernet"]=number_format($info["ttl_makan_kernet"],0,",",".");
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
    MyAdmin::checkRole($this->role, ['SuperAdmin','Logistic']);

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
