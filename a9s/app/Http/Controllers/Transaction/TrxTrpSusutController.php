<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;
use App\Exceptions\MyException;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\TrxTrp;
use App\Models\MySql\IsUser;
use App\Models\MySql\TrxAbsen;
use App\Models\MySql\Ujalan;
use App\Models\MySql\UjalanDetail;

use App\Http\Requests\MySql\TrxTrpRequest;
use App\Http\Requests\MySql\TrxTrpTicketRequest;

use App\Http\Resources\MySql\TrxTrpResource;
use App\Http\Resources\MySql\IsUserResource;

use App\Exports\MyReport;

class TrxTrpSusutController extends Controller
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

  public function index(Request $request, $download = false)
  {
    if(!$download)
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.views','trp_trx.ticket.views','trp_trx.report.views']);

    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 100; // Limit +> Much Data
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
    $model_query = new TrxTrp();
    if (!$download) {
      $model_query = $model_query->offset($offset)->limit($limit);
    }

    $first_row=[];
    if($request->first_row){
      $first_row 	= json_decode($request->first_row, true);
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

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists){
          
          $list_to_like = ["id","xto","tipe",
          "jenis","pv_no","ticket_a_no","ticket_b_no","supir","kernet","no_pol","tanggal",
          "cost_center_code","cost_center_desc","pvr_id","pvr_no","transition_target"];
    
          foreach ($list_to_like as $key => $v) {
            if (isset($like_lists[$v])) {
              $q->orWhere($v, "like", $like_lists[$v]);
            }
          }

          $list_to_like_uj = [
            ["uj_asst_opt","asst_opt"],
          ];
          foreach ($list_to_like_uj as $key => $v) {
            if (isset($like_lists[$v[0]])) {
              $q->orWhereIn('id_uj', function($q2)use($like_lists,$v) {
                $q2->from('is_uj')
                ->select('id')->where($v[1],'like',$like_lists[$v[0]]);          
              });
            }
          }
    
          // if (isset($like_lists["requested_name"])) {
          //   $q->orWhereIn("requested_by", function($q2)use($like_lists) {
          //     $q2->from('is_users')
          //     ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
          //   });
          // }
    
          // if (isset($like_lists["confirmed_name"])) {
          //   $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
          //     $q2->from('is_users')
          //     ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
          //   });
          // }
        });        
      }

      
    }

    //======================================================================================================
    // Model Sorting And Filtering
    //======================================================================================================

    $fm_sorts=[];
    if($request->filter_model){
      $filter_model = json_decode($request->filter_model,true);
  
      foreach ($filter_model as $key => $value) {
        if($value["sort_priority"] && $value["sort_type"]){
          array_push($fm_sorts,[
            "key"    =>$key,
            "priority"=>$value["sort_priority"],
          ]);
        }
      }

      if(count($fm_sorts)>0){
        usort($fm_sorts, function($a, $b) {return (int)$a['priority'] - (int)$b['priority'];});
        foreach ($fm_sorts as $key => $value) {
          if(array_search($value['key'],['uj_asst_opt'])!==false){
            $model_query = MyLib::queryOrderP1($model_query,"uj","id_uj",$value['key'],$filter_model[$value['key']]["sort_type"],"is_uj");
          } else{
            $model_query = $model_query->orderBy($value['key'], $filter_model[$value['key']]["sort_type"]);
            if (count($first_row) > 0) {
              $sort_symbol = $filter_model[$value['key']]["sort_type"] == "desc" ? "<=" : ">=";
              $model_query = $model_query->where($value['key'],$sort_symbol,$first_row[$value['key']]);
            }
          }
        }
      }

      $model_query = $model_query->where(function ($q)use($filter_model,$request){

        foreach ($filter_model as $key => $value) {
          if(!isset($value['type'])) continue;

          if(array_search($key,['status'])!==false){
            // if(array_search($value['type'],['string','number'])!==false && $value['value_1']){

            //   if($value["operator"]=='exactly_same'){
            //     $q->Where($key, $value["value_1"]);
            //   }
  
            //   if($value["operator"]=='exactly_not_same'){
            //     $q->Where($key,"!=", $value["value_1"]);
            //   }
  
            //   if($value["operator"]=='same'){
            //     $v_val1=explode(",",$value["value_1"]);
            //     $q->where(function ($q1)use($filter_model,$v_val1,$key){
            //       foreach ($v_val1 as $k1 => $v1) {
            //         $q1->orwhere($key,"like", '%'.$v1.'%');
            //       }
            //     });
            //   }
  
            //   if($value["operator"]=='not_same'){
            //     $v_val1=explode(",",$value["value_1"]);
            //     $q->where(function ($q1)use($filter_model,$v_val1,$key){
            //       foreach ($v_val1 as $k1 => $v1) {
            //         $q1->orwhere($key,"not like", '%'.$v1.'%');
            //       }
            //     });
            //   }
  
            //   if($value["operator"]=='more_then'){
            //     $q->Where($key,">", $value["value_1"]);
            //   }
              
            //   if($value["operator"]=='more_and'){
            //     $q->Where($key,">=", $value["value_1"]);
            //   }
  
            //   if($value["operator"]=='less_then'){
            //     $q->Where($key,"<", $value["value_1"]);
            //   }
  
            //   if($value["operator"]=='less_and'){
            //     $q->Where($key,"<=", $value["value_1"]);
            //   }
            // }
  
            // if(array_search($value['type'],['date','datetime'])!==false){
            //   if($value['value_1'] || $value['value_2']){
            //     $date_from = $value['value_1'];
            //     if(!$date_from)
            //     throw new MyException([ "message" => "Date From pada ".$value['label']." harus diisi" ], 400);
          
            //     if(!strtotime($date_from))
            //     throw new MyException(["message"=>"Format Date pada ".$value['label']." From Tidak Cocok"], 400);

              
            //     $date_to = $value['value_2'];                
            //     if(!$date_to)
            //     throw new MyException([ "message" => "Date To pada ".$value['label']." harus diisi" ], 400);
              
            //     if(!strtotime($date_to))
            //     throw new MyException(["message"=>"Format Date To pada ".$value['label']." Tidak Cocok"], 400);
            
            //     $date_from = date($value['type']=='datetime'?"Y-m-d H:i:s.v" :"Y-m-d",strtotime($date_from));
            //     $date_to = date($value['type']=='datetime'?"Y-m-d H:i:s.v" :"Y-m-d",strtotime($date_to));
            //     // throw new MyException(["message"=>"Format Date To pada ".$date_to." Tidak Cocok".$request->_TimeZoneOffset], 400);

            //     $q->whereBetween($key,[$date_from,$date_to]);
            //   }
            // }

            if(array_search($value['type'],['select'])!==false && $value['value_1']){

              if(array_search($key,['status'])!==false){
                $r_val = $value['value_1'];
                if($value["operator"]=='exactly_same'){
                }else {
                  if($r_val=='Undone'){
                    $r_val='Done';
                  }else{
                    $r_val='Undone';
                  };
                }

                // if($r_val=='Done'){
                //   $q->where("deleted",0)->where("req_deleted",0)->whereNotNull("pv_no")->Where(function ($q1){
                //       $q1->orWhere(function ($q2){
                //         $q2->where("jenis","TBS")->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_no");
                //       });
                //       $q1->orWhere(function ($q2){
                //         $q2->where("jenis","TBSK")->whereNotNull("ticket_b_no");
                //       });
                //       $q1->orWhere(function ($q2){
                //         $q2->whereIn("jenis",["CPO","PK"])->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_in_at")->whereNotNull("ticket_b_out_at")->where("ticket_b_bruto",">",1)->where("ticket_b_tara",">",1)->where("ticket_b_netto",">",1);
                //       });
                //   })->where('val_ticket',1);
                // }else{
                //   $q->where("deleted",0)->where("req_deleted",0)->whereNull("pv_no")->Where(function ($q1){
                //       $q1->orWhere(function ($q2){
                //         $q2->where("jenis","TBS")->where(function($q2){
                //           $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_no");
                //         });
                //       });
                //       $q1->orWhere(function ($q2){
                //         $q2->where("jenis","TBSK")->whereNull("ticket_b_no");
                //       });
                //       $q1->orWhere(function ($q2){
                //         $q2->whereIn("jenis",["CPO","PK"])->where(function($q2){
                //           $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_in_at")->orWhereNull("ticket_b_out_at")->orWhereNull("ticket_b_bruto")->orWhereNull("ticket_b_tara")->orWhereNull("ticket_b_netto");
                //         });
                //       });
                //       $q1->orWhere('val_ticket',0);
                //   });
                // }
              }
            }
          }else if(array_search($key,['uj_asst_opt'])!==false){
            MyLib::queryCheckP1Dif("uj",$value,$key,$q,'is_uj',"id_uj");
          }else{
            MyLib::queryCheck($value,$key,$q);
          }
        }
        
         
       
        // if (isset($like_lists["requested_name"])) {
        //   $q->orWhereIn("requested_by", function($q2)use($like_lists) {
        //     $q2->from('is_users')
        //     ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
        //   });
        // }
  
        // if (isset($like_lists["confirmed_name"])) {
        //   $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
        //     $q2->from('is_users')
        //     ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
        //   });
        // }
      });  
    }
    
    if(!$request->filter_model || count($fm_sorts)==0){
      $model_query = $model_query->orderBy('tanggal', 'DESC')->orderBy('id','DESC');
    }

    $filter_status = $request->filter_status;
    // if($filter_status=="ticket_done"){
    //   $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where(function ($q){
    //       $q->orWhere(function ($q1){
    //         $q1->where("jenis","TBS")->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_no");
    //       });
    //       $q->orWhere(function ($q1){
    //         $q1->where("jenis","TBSK")->whereNotNull("ticket_b_no");
    //       });
    //       $q->orWhere(function ($q1){
    //         $q1->whereIn("jenis",["CPO","PK"])->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_in_at")->whereNotNull("ticket_b_out_at")->where("ticket_b_bruto",">",1)->where("ticket_b_tara",">",1)->where("ticket_b_netto",">",1);
    //       });
    //   })->where('val_ticket',1);
    // }

    // if($filter_status=="ticket_not_done"){
    //   $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where(function ($q){
    //       $q->orWhere(function ($q1){
    //         $q1->where("jenis","TBS")->where(function($q2){
    //           $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_no");
    //         });
    //       });
    //       $q->orWhere(function ($q1){
    //         $q1->where("jenis","TBSK")->whereNull("ticket_b_no");
    //       });
    //       $q->orWhere(function ($q1){
    //         $q1->whereIn("jenis",["CPO","PK"])->where(function($q2){
    //           $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_in_at")->orWhereNull("ticket_b_out_at")->orWhereNull("ticket_b_bruto")->orWhereNull("ticket_b_tara")->orWhereNull("ticket_b_netto");
    //         });
    //       });
    //       $q->orWhere('val_ticket',0);
    //   });
    // }

    if($filter_status=="ticket_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where('val_ticket',1);
    }

    if($filter_status=="ticket_not_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where('val_ticket',0);
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    if($filter_status=="req_deleted"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",1);
    }

    $model_query = $model_query->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','uj'])->get();

    return response()->json([
      "data" => TrxTrpResource::collection($model_query),
    ], 200);
  }

  public function reportSusutPDF(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.download_file');

    set_time_limit(0);
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    
    $newDetails = [];
    $total_a_bruto = 0;
    $total_a_tara = 0;
    $total_a_netto = 0;
    $total_b_bruto = 0;
    $total_b_tara = 0;
    $total_b_netto = 0;
    $total_b_a_bruto = 0;
    $total_b_a_tara = 0;
    $total_b_a_netto = 0;
    foreach ($ori["data"] as $key => $value) {
      $ticket_a_bruto = (float)$value["ticket_a_bruto"];
      $ticket_b_bruto = (float)$value["ticket_b_bruto"];
      list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($value["ticket_a_bruto"],$value["ticket_b_bruto"]);
      $ticket_a_tara = (float)$value["ticket_a_tara"];
      $ticket_b_tara = (float)$value["ticket_b_tara"];
      list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($value["ticket_a_tara"],$value["ticket_b_tara"]);
      $ticket_a_netto = (float)$value["ticket_a_netto"];
      $ticket_b_netto = (float)$value["ticket_b_netto"];
      list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($value["ticket_a_netto"],$value["ticket_b_netto"]);

      $total_a_bruto+=$ticket_a_bruto;
      $total_a_tara+=$ticket_a_tara;
      $total_a_netto+=$ticket_a_netto;

      $total_b_bruto+=$ticket_b_bruto;
      $total_b_tara+=$ticket_b_tara;
      $total_b_netto+=$ticket_b_netto;

      $limitSusut = 0.3;

      $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
      $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
      $value['ticket_a_bruto']=number_format($ticket_a_bruto, 0,',','.');
      $value['ticket_b_bruto']=number_format($ticket_b_bruto, 0,',','.');
      $value['ticket_b_a_bruto']=block_negative($ticket_b_a_bruto, 0);
      $value['ticket_b_a_bruto_persen_red']=abs($ticket_b_a_bruto_persen) >= $limitSusut ? 'color:red;' : '';
      $value['ticket_b_a_bruto_persen']=block_negative($ticket_b_a_bruto_persen, 2);
      $value['ticket_a_tara']=number_format($ticket_a_tara, 0,',','.');
      $value['ticket_b_tara']=number_format($ticket_b_tara, 0,',','.');
      $value['ticket_b_a_tara']=block_negative($ticket_b_a_tara, 0);
      $value['ticket_b_a_tara_persen_red']=abs($ticket_b_a_tara_persen) >= $limitSusut ? 'color:red;' : '';
      $value['ticket_b_a_tara_persen']=block_negative($ticket_b_a_tara_persen, 2);
      $value['ticket_a_netto']=number_format($ticket_a_netto, 0,',','.');
      $value['ticket_b_netto']=number_format($ticket_b_netto, 0,',','.');
      $value['ticket_b_a_netto']=block_negative($ticket_b_a_netto, 0);
      $value['ticket_b_a_netto_persen_red']=abs($ticket_b_a_netto_persen) >= $limitSusut ? 'color:red;' : '';
      $value['ticket_b_a_netto_persen']=block_negative($ticket_b_a_netto_persen, 2);
      $value['amount']=number_format((float)$value["amount"], 0,',','.');
      // $value['pv_total']=number_format((float)$value["pv_total"], 0,',','.');
      // $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
      array_push($newDetails,$value);
    }

    list($total_b_a_bruto, $total_b_a_bruto_persen) =  $this->genPersen($total_a_bruto,$total_b_bruto);
    list($total_b_a_tara, $total_b_a_tara_persen) =  $this->genPersen($total_a_tara,$total_b_tara);
    list($total_b_a_netto, $total_b_a_netto_persen) =  $this->genPersen($total_a_netto,$total_b_netto);
    

    $ttl_a_tara=number_format($total_a_tara, 0,',','.');
    $ttl_a_bruto=number_format($total_a_bruto, 0,',','.');
    $ttl_a_netto=number_format($total_a_netto, 0,',','.');

    $ttl_b_tara=number_format($total_b_tara, 0,',','.');
    $ttl_b_bruto=number_format($total_b_bruto, 0,',','.');
    $ttl_b_netto=number_format($total_b_netto, 0,',','.');


    $ttl_b_a_tara=block_negative($total_b_a_tara, 0);
    $ttl_b_a_bruto=block_negative($total_b_a_bruto, 0);
    $ttl_b_a_netto=block_negative($total_b_a_netto, 0);
    
    $ttl_b_a_bruto_persen=block_negative($total_b_a_bruto_persen, 2);
    $ttl_b_a_tara_persen=block_negative($total_b_a_tara_persen, 2);
    $ttl_b_a_netto_persen=block_negative($total_b_a_netto_persen, 2);

    // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

    $filter_model = json_decode($request->filter_model,true);
    $tanggal = $filter_model['tanggal'];    

    $date = new \DateTime();
    $filename = $date->format("YmdHis");
    Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
    $pdf = PDF::loadView('pdf.trx_trp_susut', ["data"=>$newDetails,"info"=>[
      "from"=>date("d-m-Y",strtotime($tanggal['value_1'])),
      "to"=>date("d-m-Y",strtotime($tanggal['value_2'])),
      "now"=>date("d-m-Y H:i:s"),
      "ttl_a_bruto"=>$ttl_a_bruto,
      "ttl_a_tara"=>$ttl_a_tara,
      "ttl_a_netto"=>$ttl_a_netto,
      "ttl_b_bruto"=>$ttl_b_bruto,
      "ttl_b_tara"=>$ttl_b_tara,
      "ttl_b_netto"=>$ttl_b_netto,
      "ttl_b_a_bruto"=>$ttl_b_a_bruto,
      "ttl_b_a_tara"=>$ttl_b_a_tara,
      "ttl_b_a_netto"=>$ttl_b_a_netto,
      // "ttl_b_a_bruto_persen"=>$ttl_b_a_bruto_persen,
      // "ttl_b_a_tara_persen"=>$ttl_b_a_tara_persen,
      // "ttl_b_a_netto_persen"=>$ttl_b_a_netto_persen,
    ]])->setPaper('a4', 'landscape');


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

  public function reportSusutExcel(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.download_file');

    set_time_limit(0);
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    

    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {
      $ticket_a_bruto = (float)$value["ticket_a_bruto"];
      $ticket_b_bruto = (float)$value["ticket_b_bruto"];
      list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($value["ticket_a_bruto"],$value["ticket_b_bruto"]);
      $ticket_a_tara = (float)$value["ticket_a_tara"];
      $ticket_b_tara = (float)$value["ticket_b_tara"];
      list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($value["ticket_a_tara"],$value["ticket_b_tara"]);
      $ticket_a_netto = (float)$value["ticket_a_netto"];
      $ticket_b_netto = (float)$value["ticket_b_netto"];
      list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($value["ticket_a_netto"],$value["ticket_b_netto"]);

      $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
      $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
      $value['ticket_a_bruto']=$ticket_a_bruto;
      $value['ticket_b_bruto']=$ticket_b_bruto;
      $value['ticket_b_a_bruto']=$ticket_b_a_bruto;
      $value['ticket_b_a_bruto_persen']=$ticket_b_a_bruto_persen;
      $value['ticket_a_tara']=$ticket_a_tara;
      $value['ticket_b_tara']=$ticket_b_tara;
      $value['ticket_b_a_tara']=$ticket_b_a_tara;
      $value['ticket_b_a_tara_persen']=$ticket_b_a_tara_persen;
      $value['ticket_a_netto']=$ticket_a_netto;
      $value['ticket_b_netto']=$ticket_b_netto;
      $value['ticket_b_a_netto']=$ticket_b_a_netto;
      $value['ticket_b_a_netto_persen']=$ticket_b_a_netto_persen;
      $value['amount']=$value["amount"];
      // $value['pv_total']=$value["pv_total"];
      // $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
      array_push($newDetails,$value);
    }

    // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

    $filter_model = json_decode($request->filter_model,true);
    $tanggal = $filter_model['tanggal'];    


    $date_from=date("d-m-Y",strtotime($tanggal['value_1']));
    $date_to=date("d-m-Y",strtotime($tanggal['value_2']));

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-trx_trp'."[".$date_from."*".$date_to."]";

    $mime=MyLib::mime("xlsx");
    // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.trx_trp_susut'), $mime["exportType"]));


    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }

  public function genPersen($a,$b){
    $a = (float)$a;
    $b = (float)$b;
    
    $diff=(float)($b-$a);
    $bigger = $diff > 0 ? $b  : $a;

    if($bigger==0) return [$diff,0];

    return [$diff , $diff / $a * 100];
  }
}
