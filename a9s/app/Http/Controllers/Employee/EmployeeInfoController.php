<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Helpers\MyLib;
use Maatwebsite\Excel\Facades\Excel;

use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;

use App\Models\MySql\Employee;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\EmployeeRequest;

use App\Http\Resources\MySql\EmployeeResource;
use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\TrxTrp;
use App\PS\PSGroupAcAccount;
use App\Exports\MyReport;

class EmployeeInfoController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'employee_mst';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();
  }

  public function index(Request $request, $download = false)
  {
    MyAdmin::checkScope($this->permissions, 'employee.views');

    $rules = [
      'tanggal_from' => "required|date_format:Y-m-d",
    ];

    $messages = [
      'tanggal_from.required' => 'Tanggal from tidak boleh kosong',
      'tanggal_from.date_format' => 'Tanggal from format salah',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $tanggal_from = $request->tanggal_from;
    $tanggal_to = $request->tanggal_to;
    $data = [];

    $trxtrps = TrxTrp::where("tanggal",">=",$tanggal_from)->where("tanggal","<=",$tanggal_to)->where('req_deleted',0)->where('deleted',0)->with('uj_details2')->get();
    foreach ($trxtrps as $k => $v) {
      
      $psgrp=PSGroupAcAccount::fn_ret($v->uj_details2);
      
      $map_emp = array_map(function($x){
        return $x['employee']['id'];
      },$data);

      $search_supir = array_search($v->supir_id,$map_emp);
      if(count($data)==0 || $search_supir===false){
        $dt = [
          "employee"=>$v->employee_s,
          "location" => [
            [
             "uj"=>$v->uj,
             "jlh_trip" => 1,
             "gaji"=>$psgrp['supir_gaji'],
             "makan"=>$psgrp['supir_makan'],
             "dinas"=>$psgrp['supir_dinas'],
            ]
          ],
          "gaji"=>0,
          "makan"=>0,
          "dinas"=>0,
          "total"=>0,
          "jlh_trip" => 1,
        ];
        $dt['gaji']   +=$psgrp['supir_gaji'];
        $dt['makan']  +=$psgrp['supir_makan'];
        $dt['dinas']  +=$psgrp['supir_dinas'];
        $dt['total']  +=$psgrp['supir_gaji']+$psgrp['supir_makan']+$psgrp['supir_dinas'];

        array_push($data,$dt);

      }else{
        $dt = $data[$search_supir];

        $map_loc = array_map(function($x){
          return $x["uj"]["id"];
        },$dt["location"]);

        $search_loc = array_search($v->uj->id,$map_loc);
        if($search_loc===false){
          array_push($dt["location"],[
             "uj"=>$v->uj,
             "jlh_trip" => 1,
             "gaji"=>$psgrp['supir_gaji'],
             "makan"=>$psgrp['supir_makan'],
             "dinas"=>$psgrp['supir_dinas'],
          ]);
        }else{
          $dt["location"][$search_loc]['jlh_trip']+=1;
        }

        $dt['gaji']   +=$psgrp['supir_gaji'];
        $dt['makan']  +=$psgrp['supir_makan'];
        $dt['dinas']  +=$psgrp['supir_dinas'];
        $dt['total']  +=$psgrp['supir_gaji']+$psgrp['supir_makan']+$psgrp['supir_dinas'];
        $dt['jlh_trip']  +=1;

        $data[$search_supir] = $dt;
      }

      if($v->kernet_id){
        $search_kernet = array_search($v->kernet_id,$map_emp);
        if(count($data)==0 || $search_kernet===false){
          $dt = [
            "employee"=>$v->employee_k,
            "location" => [
              [
                "uj"=>$v->uj,
                "jlh_trip" => 1,
                "gaji"=>$psgrp['kernet_gaji'],
                "makan"=>$psgrp['kernet_makan'],
                "dinas"=>$psgrp['kernet_dinas'],
              ]
            ],
            "gaji"=>0,
            "makan"=>0,
            "dinas"=>0,
            "total"=>0,
            "jlh_trip" => 1,
          ];
          $dt['gaji']   +=$psgrp['kernet_gaji'];
          $dt['makan']  +=$psgrp['kernet_makan'];
          $dt['dinas']  +=$psgrp['kernet_dinas'];
          $dt['total']  +=$psgrp['kernet_gaji']+$psgrp['kernet_makan']+$psgrp['kernet_dinas'];

          array_push($data,$dt);

        }else{
          $dt = $data[$search_kernet];

          $map_loc = array_map(function($x){
            return $x["uj"]["id"];
          },$dt["location"]);

          $search_loc = array_search($v->uj->id,$map_loc);
          if($search_loc===false){
            array_push($dt["location"],[
              "uj"=>$v->uj,
              "jlh_trip" => 1,
              "gaji"=>$psgrp['kernet_gaji'],
              "makan"=>$psgrp['kernet_makan'],
              "dinas"=>$psgrp['kernet_dinas'],
            ]);
          }else{
            $dt["location"][$search_loc]['jlh_trip']+=1;
          }

          $dt['gaji']   +=$psgrp['kernet_gaji'];
          $dt['makan']  +=$psgrp['kernet_makan'];
          $dt['dinas']  +=$psgrp['kernet_dinas'];
          $dt['total']  +=$psgrp['kernet_gaji']+$psgrp['kernet_makan']+$psgrp['kernet_dinas'];
          $dt['jlh_trip']  +=1;

          $data[$search_kernet] = $dt;
        }
      }

    }    

    // // // 1. Extract the column into its own variable first
    // $total_column = array_column($data, 'employee');

    // // // 2. Pass the variable into the sorting function
    // array_multisort($total_column, SORT_ASC, $data);

    foreach ($data as &$dtloc) {
        usort($dtloc['location'], fn($a, $b) => $a['uj']['xto'] <=> $b['uj']['xto']);
    }
    unset($dtloc);


    usort($data, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    // usort($data, function ($a, $b) {
    //   return $a['employee']['name'] <=> $b['employee']['name'];
    // });

    return response()->json([
      // "data"=>EmployeeResource::collection($employees->keyBy->id),
      "data" => $data,
    ], 200);
  }

  public function reportExcel(Request $request){
    // MyAdmin::checkScope($this->permissions, 'trp_trx.download_file');

    set_time_limit(0);
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    

    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {
      // $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      array_push($newDetails,$value);
    }

    // $filter_model = json_decode($request->filter_model,true);
    // $tanggal = $filter_model['tanggal'];    

    $date_from=date("d-m-Y",strtotime($request->tanggal_from));
    $date_to=date("d-m-Y",strtotime($request->tanggal_to));

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-employee_info'."[".$date_from."*".$date_to."]";

    $mime=MyLib::mime("xlsx");
    // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.employee_info'), $mime["exportType"]));

    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }
}
