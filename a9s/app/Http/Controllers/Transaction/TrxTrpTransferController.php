<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;

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
use App\Helpers\TrfDuitku;
use App\Models\MySql\Employee;
use App\Models\MySql\ExtraMoneyTrx;

class TrxTrpTransferController extends Controller
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

  public function checkGATimeout(){
    if($this->admin->the_user->ga_timeout==null){
      throw new MyException(["message"=>"Forbidden"],400);
    }

    if(strtotime($this->admin->the_user->ga_timeout) <= strtotime(date("Y-m-d H:i:s.v"))){
      IsUser::where("id",$this->admin->the_user)->update(["ga_timeout"=>null]);
      throw new MyException(["message"=>"Timeout"],400);
    }    
  }
  public function index(Request $request, $download = false)
  {
    if(!$download)
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.transfer.views']);

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
                //   });
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
    
    $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->whereIn('payment_method_id',[2,3])->where('val',1)->where('val1',1)->where('val2',1)->where(function ($q){
      $q->where('val5',1)->orWhere('val6',1);
    })->where('received_payment',0);

    $model_query = $model_query->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','uj',
    // 'trx_absens'=>function($q) {
    //   $q->select('id','trx_trp_id','created_at','updated_at')->where("status","B");
    // }
    ])->get();


    foreach ($model_query as $key => $mq) {
      ExtraMoneyTrx::where(function ($q)use($mq){
        $q->where("employee_id",$mq->supir_id)->orWhere("employee_id",$mq->kernet_id);
      })->whereIn("payment_method_id",[2,3])->where("received_payment",0)->where("deleted",0)->where("req_deleted",0)
      ->where('val1',1)->where('val2',1)->where('val3',1)->where('val4',1)->where('val5',1)->where('val6',1)
      ->update([
        'trx_trp_id'=>$mq->id
      ]);
    }

    return response()->json([
      "data" => TrxTrpResource::collection($model_query),
    ], 200);
  }

  public function show(TrxTrpRequest $request)
  {
    $this->checkGATimeout();
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.transfer.view']);

    $model_query = TrxTrp::where("deleted",0)->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by',
    'val_ticket_by','deleted_by','req_deleted_by','payment_method',
    'uj_details','potongan','uj','extra_money_trxs'=>function ($q){
      $q->with(['employee','extra_money']);
    }
    // ,'trx_absens'=>function($q) {
    //   $q->select('*')->where("status","B");
    // }
    ])->find($request->id);
    return response()->json([
      "data" => new TrxTrpResource($model_query),
    ], 200);
  }

  public function validasiAndTransfer(Request $request){
    $this->checkGATimeout();

    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.transfer.do_transfer']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
    ];

    $messages = [
      'id.required' => 'ID tidak boleh kosong',
      'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->cost_center_code==""){
      //   throw new \Exception("Minta Kasir Untuk Memasukkan Cost Center Code Terlebih Dahulu",1);
      // }

      if($model_query->val==0){
        throw new \Exception("Data Perlu Divalidasi oleh kasir terlebih dahulu",1);
      }

      if($model_query->val1==0){
        throw new \Exception("Data Perlu Divalidasi oleh mandor terlebih dahulu",1);
      }

      if($model_query->val2==0){
        throw new \Exception("Data Perlu Divalidasi oleh W/KTU terlebih dahulu",1);
      }

      if(($model_query->jenis=='CPO' || $model_query->jenis=='PK') && $model_query->val3==0){
        throw new \Exception("Data Perlu Divalidasi oleh marketing terlebih dahulu",1);
      }

      // $app5 = $model_query->val5;
      // $app6 = $model_query->val6;

      // if(MyAdmin::checkScope($this->permissions, 'trp_trx.val5',true)){
      //   $app5 = 1;
      // }
      
      // if(MyAdmin::checkScope($this->permissions, 'trp_trx.val6',true)){
      //   $app6 = 1;
      // }

      // if( $app5==0 && $app6==0 ){
      //   throw new \Exception("Data Perlu Divalidasi oleh SPV atau MGR Logistik terlebih dahulu",1);
      // }

      if(!MyAdmin::checkScope($this->permissions, 'trp_trx.val4',true) && $model_query->val4==0){
        throw new \Exception("Data Perlu Divalidasi oleh Staff Logistik terlebih dahulu",1);
      }

      if(!MyAdmin::checkScope($this->permissions, 'trp_trx.val5',true) && $model_query->val5==0){
        throw new \Exception("Data Perlu Divalidasi oleh SPV Logistik terlebih dahulu",1);
      }

      if(!MyAdmin::checkScope($this->permissions, 'trp_trx.val6',true) && $model_query->val6==0){
        throw new \Exception("Data Perlu Divalidasi oleh MGR Logistik terlebih dahulu",1);
      }
      


      if($model_query->received_payment == 1){
        throw new \Exception("Pembayaran sudah selesai",1);
      }

      $SYSOLD                     = clone($model_query);

      $supir_id   = $model_query->supir_id;
      $kernet_id  = $model_query->kernet_id;
      $ttl_ps     = 0;
      $ttl_pk     = 0;
      $supir_remarks  = "UJ#".$model_query->id;
      $kernet_remarks = "UJ#".$model_query->id;

      $supir_money = 0;
      $kernet_money = 0;
      foreach ($model_query->uj_details2 as $key => $val) {
        if($val->xfor=='Kernet'){
          $kernet_money+=$val->amount*$val->qty;
        }else{
          $supir_money+=$val->amount*$val->qty;
        }
      }

      $ptg_ps_ids = "";
      $ptg_pk_ids = "";
      foreach ($model_query->potongan as $k => $v) {
        if($v->potongan_mst->employee_id == $supir_id){
          $ttl_ps+=$v->nominal_cut;
          $ptg_ps_ids.="#".$v->potongan_mst->id." ";
        }
  
        if($v->potongan_mst->employee_id == $kernet_id){
          $ttl_pk+=$v->nominal_cut;
          $ptg_pk_ids.="#".$v->potongan_mst->id." ";
        }
      }
      
      $supir_money -= $ttl_ps;
      $kernet_money -= $ttl_pk;


      $sem_remarks="";
      $kem_remarks="";
      foreach ($model_query->extra_money_trxs as $k => $emt) {
        if($emt->employee_id == $supir_id){
          $supir_money+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
          if($sem_remarks=="")
            $sem_remarks="EM#".$emt->id;
          else
            $sem_remarks.=",".$emt->id;
        }
  
        if($emt->employee_id == $kernet_id){
          $kernet_money+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
          if($kem_remarks=="")
            $kem_remarks="EM#".$emt->id;
          else
            $kem_remarks.=",".$emt->id;
        }
      }

      if($sem_remarks!="") $supir_remarks.=",".$sem_remarks;
      if($kem_remarks!="") $kernet_remarks.=",".$kem_remarks;

      if($model_query->supir_id){
        $supir = Employee::exclude(['attachment_1','attachment_2'])->with('bank')->find($model_query->supir_id);
        if(!$supir->bank || !$supir->bank->code_duitku)
        throw new \Exception("Bank Belum Memiliki code duitku",1);

        if(!$supir->rek_no)
        throw new \Exception("Supir Belum Memiliki rekening",1);

        if($supir_money==0)
        throw new \Exception("Cek kembali master uang jalan detail PVR apakah xfor tidak ada berisi supir atau semua berisikan kernet",1);

        if($supir_money < 0)
        throw new \Exception("Potongan Supir tidak Diterima Karna Nilai Potongan Lebih Besar Dari Uang Yang Akan Ditransfer kan",1);

      }

      if($model_query->kernet_id){
        $kernet = Employee::exclude(['attachment_1','attachment_2'])->with('bank')->find($model_query->kernet_id);
        if(!$kernet->bank || !$kernet->bank->code_duitku)
        throw new \Exception("Bank Belum Memiliki code duitku",1);

        if(!$kernet->rek_no)
        throw new \Exception("Supir Belum Memiliki rekening",1);

        if($kernet_money==0)
        throw new \Exception("Cek kembali master uang jalan detail PVR apakah xfor tidak ada berisi kernet atau semua berisikan kernet",1);

        if($kernet_money < 0)
        throw new \Exception("Potongan Kernet tidak Diterima Karna Nilai Potongan Lebih Besar Dari Uang Yang Akan Ditransfer kan",1);

      }


      if(isset($supir) && $supir_money > 0){
        if(!$model_query->duitku_supir_disburseId){
          $result = TrfDuitku::generate_invoice($supir->bank->code_duitku,$supir->rek_no,$supir_money,$supir_remarks,'',$model_query->payment_method_id);
          if($result){
            $model_query->duitku_supir_disburseId   = $result['disburseId'];
            $model_query->duitku_supir_inv_res_code = $result['responseCode'];
            $model_query->duitku_supir_inv_res_desc = $result['responseDesc'];
          }
          // MyLog::logging($result);

        }
        
        if($model_query->duitku_supir_disburseId && $model_query->duitku_supir_inv_res_code == "00" && $model_query->duitku_supir_trf_res_code!="00"){
          $result = TrfDuitku::generate_transfer($model_query->duitku_supir_disburseId,$supir->bank->code_duitku,$supir->rek_no,$supir_money,$supir_remarks,'',$model_query->payment_method_id);
          if($result){
            $model_query->duitku_supir_trf_res_code = $result['responseCode'];
            $model_query->duitku_supir_trf_res_desc = $result['responseDesc'];
          }
          // MyLog::logging($result);
        }

        if($model_query->duitku_supir_disburseId && $model_query->duitku_supir_trf_res_code=="00" && !$model_query->rp_supir_user){
          $model_query->rp_supir_user = $this->admin_id;               
          $model_query->rp_supir_at   = $t_stamp;               
        }
      }

      if(isset($kernet) && $kernet_money > 0 && $model_query->duitku_supir_trf_res_code=="00"){
        if(!$model_query->duitku_kernet_disburseId){
          $result = TrfDuitku::generate_invoice($kernet->bank->code_duitku,$kernet->rek_no,$kernet_money,$kernet_remarks,'',$model_query->payment_method_id);
          if($result){
            $model_query->duitku_kernet_disburseId   = $result['disburseId'];
            $model_query->duitku_kernet_inv_res_code = $result['responseCode'];
            $model_query->duitku_kernet_inv_res_desc = $result['responseDesc'];
            // MyLog::logging($result,"disbust");
          }
        }
        
        if($model_query->duitku_kernet_disburseId && $model_query->duitku_kernet_inv_res_code=="00" && $model_query->duitku_kernet_trf_res_code!="00"){
          $result = TrfDuitku::generate_transfer($model_query->duitku_kernet_disburseId,$kernet->bank->code_duitku,$kernet->rek_no,$kernet_money,$kernet_remarks,'',$model_query->payment_method_id);
          if($result){
            $model_query->duitku_kernet_trf_res_code = $result['responseCode'];
            $model_query->duitku_kernet_trf_res_desc = $result['responseDesc'];
            // MyLog::logging($result,"disbust");
          }
        }

        if($model_query->duitku_kernet_disburseId && $model_query->duitku_kernet_trf_res_code=="00" && !$model_query->rp_kernet_user){
          $model_query->rp_kernet_user = $this->admin_id;               
          $model_query->rp_kernet_at   = $t_stamp;    
        }
      }


      if((!isset($kernet) && $model_query->duitku_supir_trf_res_code=="00") || (isset($kernet) && $model_query->duitku_supir_trf_res_code=="00" && $model_query->duitku_kernet_trf_res_code=="00")){
        $model_query->received_payment=1;
        $model_query->supir_rek_no = $supir->rek_no;
        if(isset($kernet)){
          $model_query->kernet_rek_no = $kernet->rek_no;
        }

        foreach ($model_query->extra_money_trxs as $key => $emt) {
          $SYSOLD2                     = clone($emt);
          $emt->received_payment=1;
          if($emt->employee_id == $supir_id){
            $emt->employee_rek_no = $supir->rek_no;
          }
          if(isset($kernet) && $emt->employee_id == $kernet_id){
            $emt->employee_rek_no = $kernet->rek_no;
          }
          
          if(MyAdmin::checkScope($this->permissions, 'trp_trx.val4',true)){
            $emt->val4 = 1;
            $emt->val4_user = $this->admin_id;
            $emt->val4_at = $t_stamp;
          }
  
          if(MyAdmin::checkScope($this->permissions, 'trp_trx.val5',true)){
            $emt->val5 = 1;
            $emt->val5_user = $this->admin_id;
            $emt->val5_at = $t_stamp;
          }
  
          if(MyAdmin::checkScope($this->permissions, 'trp_trx.val6',true)){
            $emt->val6 = 1;
            $emt->val6_user = $this->admin_id;
            $emt->val6_at = $t_stamp;
          }
          
          $emt->save();
          $SYSNOTE2 = MyLib::compareChange($SYSOLD2,$emt);
          MyLog::sys("extra_money_trx",$emt->id,"transfer",$SYSNOTE2);
        }

        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val4',true) && !$model_query->val4){
          $model_query->val4 = 1;
          $model_query->val4_user = $this->admin_id;
          $model_query->val4_at = $t_stamp;
        }

        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val5',true) && !$model_query->val5){
          $model_query->val5 = 1;
          $model_query->val5_user = $this->admin_id;
          $model_query->val5_at = $t_stamp;
        }

        if(MyAdmin::checkScope($this->permissions, 'trp_trx.val6',true) && !$model_query->val6){
          $model_query->val6 = 1;
          $model_query->val6_user = $this->admin_id;
          $model_query->val6_at = $t_stamp;
        }
      }

      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$model_query->id,"transfer",$SYSNOTE);

      DB::commit();

      $error=[];
      if($model_query->duitku_supir_inv_res_code!=="00"){
        $msg = TrfDuitku::info_inv($model_query->duitku_supir_inv_res_code);
        if($msg==""){
          array_push($error,"Supir:Unknown Error");
        }else{
          array_push($error,"Supir:".$msg);
        }
      }

      if($model_query->duitku_supir_trf_res_code!=="00"){
        $msg = TrfDuitku::info_inv($model_query->duitku_supir_trf_res_code);
        if($msg==""){
          array_push($error,"Supir:Unknown Error");
        }else{
          array_push($error,"Supir:".$msg);
        }
      }

      if(isset($kernet) && $model_query->duitku_kernet_inv_res_code!=="00"){
        $msg = TrfDuitku::info_inv($model_query->duitku_kernet_inv_res_code);
        if($msg==""){
          array_push($error,"Kernet:Unknown Error");
        }else{
          array_push($error,"Kernet:".$msg);
        }
      }

      if(isset($kernet) && $model_query->duitku_kernet_trf_res_code!=="00"){
        $msg = TrfDuitku::info_inv($model_query->duitku_kernet_trf_res_code);
        if($msg==""){
          array_push($error,"Kernet:Unknown Error");
        }else{
          array_push($error,"Kernet:".$msg);
        }
      }

      if($error){
        return response()->json([
          "message" => implode(",",$error),
        ], 400);
      }

      return response()->json([
        "message" => "Proses validasi data berhasil",
        "val4"=>$model_query->val4,
        "val4_user"=>$model_query->val4_user,
        "val4_at"=>$model_query->val4_at,
        "val4_by"=>$model_query->val4_user ? new IsUserResource(IsUser::find($model_query->val4_user)) : null,
        "val5"=>$model_query->val5,
        "val5_user"=>$model_query->val5_user,
        "val5_at"=>$model_query->val5_at,
        "val5_by"=>$model_query->val5_user ? new IsUserResource(IsUser::find($model_query->val5_user)) : null,
        "val6"=>$model_query->val6,
        "val6_user"=>$model_query->val6_user,
        "val6_at"=>$model_query->val6_at,
        "val6_by"=>$model_query->val6_user ? new IsUserResource(IsUser::find($model_query->val6_user)) : null,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      return response()->json([
        "message" => "Proses ubah data gagal",
      ], 400);
    }

  }


  public function generateForManual(Request $request){
    $this->checkGATimeout();

    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.transfer.do_transfer']);

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_querys = TrxTrp::where("deleted",0)->where("req_deleted",0)
      ->whereIn('payment_method_id',[2,3])->where('received_payment',0)
      ->where('val',1)->where('val1',1)->where('val2',1)
      ->where('val4',1)->where('val5',1)->where('val6',1)
      ->where(function ($q) {
        $q->whereNotIn('jenis', ['CPO', 'PK']) // Jika bukan CPO atau PK, abaikan kondisi val3
          ->orWhere(function ($q) {
              $q->whereIn('jenis', ['CPO', 'PK'])
              ->where('val3', 1); // Jika CPO atau PK, val3 harus 1
          });
      })
      ->lockForUpdate()->get();

      $data=[];
      foreach ($model_querys as $key => $model_query) {
        $dt_supir=[
          "amount"=>0,
          "bank_code"=>"",
          "bank_name"=>"",
          "bank_account_name"=>"",
          "bank_account_number"=>"",
          "description"=>"",
          "email"=>"",
          // "method"=>"REALTIME",
          "method"=>"BI FAST",
        ];
        $dt_kernet=[
          "amount"=>0,
          "bank_code"=>"",
          "bank_name"=>"",
          "bank_account_name"=>"",
          "bank_account_number"=>"",
          "description"=>"",
          "email"=>"",
          // "method"=>"REALTIME",
          "method"=>"BI FAST",
        ];
        
        $supir_id   = $model_query->supir_id;
        $kernet_id  = $model_query->kernet_id;
        $ttl_ps     = 0;
        $ttl_pk     = 0;
        $supir_remarks  = "UJ#".$model_query->id;
        $kernet_remarks = "UJ#".$model_query->id;

        $supir_money = 0;
        $kernet_money = 0;
        foreach ($model_query->uj_details2 as $key => $val) {
          if($val->xfor=='Kernet'){
            $kernet_money+=$val->amount*$val->qty;
          }else{
            $supir_money+=$val->amount*$val->qty;
          }
        }
  
        $ptg_ps_ids = "";
        $ptg_pk_ids = "";
        foreach ($model_query->potongan as $k => $v) {
          if($v->potongan_mst->employee_id == $supir_id){
            $ttl_ps+=$v->nominal_cut;
            $ptg_ps_ids.="#".$v->potongan_mst->id." ";
          }
    
          if($v->potongan_mst->employee_id == $kernet_id){
            $ttl_pk+=$v->nominal_cut;
            $ptg_pk_ids.="#".$v->potongan_mst->id." ";
          }
        }
        
        $supir_money -= $ttl_ps;
        $kernet_money -= $ttl_pk;
  
        $sem_remarks="";
        $kem_remarks="";
        foreach ($model_query->extra_money_trxs as $k => $emt) {
          if($emt->employee_id == $supir_id){
            $supir_money+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
            if($sem_remarks=="")
              $sem_remarks="EM#".$emt->id;
            else
              $sem_remarks.=",".$emt->id;
          }
    
          if($emt->employee_id == $kernet_id){
            $kernet_money+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
            if($kem_remarks=="")
              $kem_remarks="EM#".$emt->id;
            else
              $kem_remarks.=",".$emt->id;
          }
        }
  
        if($sem_remarks!="") $supir_remarks.=",".$sem_remarks;
        if($kem_remarks!="") $kernet_remarks.=",".$kem_remarks;
  
        if($model_query->supir_id){
          $supir = Employee::exclude(['attachment_1','attachment_2'])->with('bank')->find($model_query->supir_id);
          if(!$supir->bank || !$supir->bank->code_duitku)
          throw new \Exception("Bank Belum Memiliki code duitku",1);
  
          if(!$supir->rek_no)
          throw new \Exception("Supir Belum Memiliki rekening",1);
  
          if($supir_money==0)
          throw new \Exception("Cek kembali master uang jalan detail PVR apakah xfor tidak ada berisi supir atau semua berisikan kernet",1);
  
          if($supir_money < 0)
          throw new \Exception("Potongan Supir tidak Diterima Karna Nilai Potongan Lebih Besar Dari Uang Yang Akan Ditransfer kan",1);
  
        }
  
        if($model_query->kernet_id){
          $kernet = Employee::exclude(['attachment_1','attachment_2'])->with('bank')->find($model_query->kernet_id);
          if(!$kernet->bank || !$kernet->bank->code_duitku)
          throw new \Exception("Bank Belum Memiliki code duitku",1);
  
          if(!$kernet->rek_no)
          throw new \Exception("Supir Belum Memiliki rekening",1);
  
          if($kernet_money==0)
          throw new \Exception("Cek kembali master uang jalan detail PVR apakah xfor tidak ada berisi kernet atau semua berisikan kernet",1);
  
          if($kernet_money < 0)
          throw new \Exception("Potongan Kernet tidak Diterima Karna Nilai Potongan Lebih Besar Dari Uang Yang Akan Ditransfer kan",1);
  
        }


        if(isset($supir) && $supir_money > 0){
          $dt_supir["amount"] = $supir_money;
          $dt_supir["bank_code"] = $supir->bank->code_duitku;
          $dt_supir["bank_name"] = $supir->bank->code;
          $dt_supir["bank_account_name"] = $supir->rek_name;
          $dt_supir["bank_account_number"] = $supir->rek_no;
          $dt_supir["description"] = $supir_remarks;
          array_push($data,$dt_supir);
        }
        if(isset($kernet) && $kernet_money > 0){
          $dt_kernet["amount"] = $kernet_money;
          $dt_kernet["bank_code"] = $kernet->bank->code_duitku;
          $dt_kernet["bank_name"] = $kernet->bank->code;
          $dt_kernet["bank_account_name"] = $kernet->rek_name;
          $dt_kernet["bank_account_number"] = $kernet->rek_no;
          $dt_kernet["description"] = $kernet_remarks;
          array_push($data,$dt_kernet);
        }
      }

      $info = [
        "now"                 => date("d-m-Y H:i:s"),
      ];  
  
      DB::commit();

      $date = new \DateTime();
      $filename=env("app_name").'-manual_tf-'.$date->format("YmdHis");
  
      $mime=MyLib::mime("xlsx");
  
      $blade= 'excel.manual_tf';
  
      $columnFormats = [
        // 'H' => '0',
        // 'G' => '###############',
        // 'G' => '₹#,##0.00',
        // 'J' => '0',
              // 'G' => '0',
        // 'J' => '0',
        // 'G' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT,
        // 'J' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT,
      ];
  
      $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$data,"info"=>$info],$blade, $columnFormats), $mime["exportType"]));
  
      $result = [
        "contentType" => $mime["contentType"],
        "data" => $bs64,
        "dataBase64" => $mime["dataBase64"] . $bs64,
        "filename" => $filename . "." . $mime["ext"],
        // "ex"=>\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT
      ];
      return $result;

    } catch (\Exception $e) {
      DB::rollback();
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      return response()->json([
        "message" => "Proses download data gagal",
      ], 400);
    }

  }

  // public function indexMandiri(Request $request, $download = false)
  // {
  //   if(!$download)
  //   MyAdmin::checkMultiScope($this->permissions, ['trp_trx.transfer.views']);

  //   //======================================================================================================
  //   // Pembatasan Data hanya memerlukan limit dan offset
  //   //======================================================================================================

  //   $limit = 100; // Limit +> Much Data
  //   if (isset($request->limit)) {
  //     if ($request->limit <= 250) {
  //       $limit = $request->limit;
  //     } else {
  //       throw new MyException(["message" => "Max Limit 250"]);
  //     }
  //   }

  //   $offset = isset($request->offset) ? (int) $request->offset : 0; // example offset 400 start from 401

  //   //======================================================================================================
  //   // Jika Halaman Ditentutkan maka $offset akan disesuaikan
  //   //======================================================================================================
  //   if (isset($request->page)) {
  //     $page =  (int) $request->page;
  //     $offset = ($page * $limit) - $limit;
  //   }


  //   //======================================================================================================
  //   // Init Model
  //   //======================================================================================================
  //   $model_query = new TrxTrp();
  //   if (!$download) {
  //     $model_query = $model_query->offset($offset)->limit($limit);
  //   }

  //   $first_row=[];
  //   if($request->first_row){
  //     $first_row 	= json_decode($request->first_row, true);
  //   }

  //   //======================================================================================================
  //   // Model Filter | Example $request->like = "username:%username,role:%role%,name:role%,";
  //   //======================================================================================================

  //   if ($request->like) {
  //     $like_lists = [];

  //     $likes = explode(",", $request->like);
  //     foreach ($likes as $key => $like) {
  //       $side = explode(":", $like);
  //       $side[1] = isset($side[1]) ? $side[1] : '';
  //       $like_lists[$side[0]] = $side[1];
  //     }

  //     if(count($like_lists) > 0){
  //       $model_query = $model_query->where(function ($q)use($like_lists){
          
  //         $list_to_like = ["id","xto","tipe",
  //         "jenis","pv_no","ticket_a_no","ticket_b_no","supir","kernet","no_pol","tanggal",
  //         "cost_center_code","cost_center_desc","pvr_id","pvr_no","transition_target"];
    
  //         foreach ($list_to_like as $key => $v) {
  //           if (isset($like_lists[$v])) {
  //             $q->orWhere($v, "like", $like_lists[$v]);
  //           }
  //         }
    
  //         // if (isset($like_lists["requested_name"])) {
  //         //   $q->orWhereIn("requested_by", function($q2)use($like_lists) {
  //         //     $q2->from('is_users')
  //         //     ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
  //         //   });
  //         // }
    
  //         // if (isset($like_lists["confirmed_name"])) {
  //         //   $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
  //         //     $q2->from('is_users')
  //         //     ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
  //         //   });
  //         // }
  //       });        
  //     }

      
  //   }

  //   //======================================================================================================
  //   // Model Sorting And Filtering
  //   //======================================================================================================

  //   $fm_sorts=[];
  //   if($request->filter_model){
  //     $filter_model = json_decode($request->filter_model,true);
  
  //     foreach ($filter_model as $key => $value) {
  //       if($value["sort_priority"] && $value["sort_type"]){
  //         array_push($fm_sorts,[
  //           "key"    =>$key,
  //           "priority"=>$value["sort_priority"],
  //         ]);
  //       }
  //     }

  //     if(count($fm_sorts)>0){
  //       usort($fm_sorts, function($a, $b) {return (int)$a['priority'] - (int)$b['priority'];});
  //       foreach ($fm_sorts as $key => $value) {
  //         $model_query = $model_query->orderBy($value['key'], $filter_model[$value['key']]["sort_type"]);
  //         if (count($first_row) > 0) {
  //           $sort_symbol = $filter_model[$value['key']]["sort_type"] == "desc" ? "<=" : ">=";
  //           $model_query = $model_query->where($value['key'],$sort_symbol,$first_row[$value['key']]);
  //         }
  //       }
  //     }

  //     $model_query = $model_query->where(function ($q)use($filter_model,$request){

  //       foreach ($filter_model as $key => $value) {
  //         if(!isset($value['type'])) continue;

  //         if(array_search($key,['status'])!==false){
  //           // if(array_search($value['type'],['string','number'])!==false && $value['value_1']){

  //           //   if($value["operator"]=='exactly_same'){
  //           //     $q->Where($key, $value["value_1"]);
  //           //   }
  
  //           //   if($value["operator"]=='exactly_not_same'){
  //           //     $q->Where($key,"!=", $value["value_1"]);
  //           //   }
  
  //           //   if($value["operator"]=='same'){
  //           //     $v_val1=explode(",",$value["value_1"]);
  //           //     $q->where(function ($q1)use($filter_model,$v_val1,$key){
  //           //       foreach ($v_val1 as $k1 => $v1) {
  //           //         $q1->orwhere($key,"like", '%'.$v1.'%');
  //           //       }
  //           //     });
  //           //   }
  
  //           //   if($value["operator"]=='not_same'){
  //           //     $v_val1=explode(",",$value["value_1"]);
  //           //     $q->where(function ($q1)use($filter_model,$v_val1,$key){
  //           //       foreach ($v_val1 as $k1 => $v1) {
  //           //         $q1->orwhere($key,"not like", '%'.$v1.'%');
  //           //       }
  //           //     });
  //           //   }
  
  //           //   if($value["operator"]=='more_then'){
  //           //     $q->Where($key,">", $value["value_1"]);
  //           //   }
              
  //           //   if($value["operator"]=='more_and'){
  //           //     $q->Where($key,">=", $value["value_1"]);
  //           //   }
  
  //           //   if($value["operator"]=='less_then'){
  //           //     $q->Where($key,"<", $value["value_1"]);
  //           //   }
  
  //           //   if($value["operator"]=='less_and'){
  //           //     $q->Where($key,"<=", $value["value_1"]);
  //           //   }
  //           // }
  
  //           // if(array_search($value['type'],['date','datetime'])!==false){
  //           //   if($value['value_1'] || $value['value_2']){
  //           //     $date_from = $value['value_1'];
  //           //     if(!$date_from)
  //           //     throw new MyException([ "message" => "Date From pada ".$value['label']." harus diisi" ], 400);
          
  //           //     if(!strtotime($date_from))
  //           //     throw new MyException(["message"=>"Format Date pada ".$value['label']." From Tidak Cocok"], 400);

              
  //           //     $date_to = $value['value_2'];                
  //           //     if(!$date_to)
  //           //     throw new MyException([ "message" => "Date To pada ".$value['label']." harus diisi" ], 400);
              
  //           //     if(!strtotime($date_to))
  //           //     throw new MyException(["message"=>"Format Date To pada ".$value['label']." Tidak Cocok"], 400);
            
  //           //     $date_from = date($value['type']=='datetime'?"Y-m-d H:i:s.v" :"Y-m-d",strtotime($date_from));
  //           //     $date_to = date($value['type']=='datetime'?"Y-m-d H:i:s.v" :"Y-m-d",strtotime($date_to));
  //           //     // throw new MyException(["message"=>"Format Date To pada ".$date_to." Tidak Cocok".$request->_TimeZoneOffset], 400);

  //           //     $q->whereBetween($key,[$date_from,$date_to]);
  //           //   }
  //           // }

  //           if(array_search($value['type'],['select'])!==false && $value['value_1']){

  //             if(array_search($key,['status'])!==false){
  //               $r_val = $value['value_1'];
  //               if($value["operator"]=='exactly_same'){
  //               }else {
  //                 if($r_val=='Undone'){
  //                   $r_val='Done';
  //                 }else{
  //                   $r_val='Undone';
  //                 };
  //               }

  //               if($r_val=='Done'){
  //                 $q->where("deleted",0)->where("req_deleted",0)->whereNotNull("pv_no")->Where(function ($q1){
  //                     $q1->orWhere(function ($q2){
  //                       $q2->where("jenis","TBS")->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_no");
  //                     });
  //                     $q1->orWhere(function ($q2){
  //                       $q2->where("jenis","TBSK")->whereNotNull("ticket_b_no");
  //                     });
  //                     $q1->orWhere(function ($q2){
  //                       $q2->whereIn("jenis",["CPO","PK"])->whereNotNull("ticket_a_no")->whereNotNull("ticket_b_in_at")->whereNotNull("ticket_b_out_at")->where("ticket_b_bruto",">",1)->where("ticket_b_tara",">",1)->where("ticket_b_netto",">",1);
  //                     });
  //                 });
  //               }else{
  //                 $q->where("deleted",0)->where("req_deleted",0)->whereNull("pv_no")->Where(function ($q1){
  //                     $q1->orWhere(function ($q2){
  //                       $q2->where("jenis","TBS")->where(function($q2){
  //                         $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_no");
  //                       });
  //                     });
  //                     $q1->orWhere(function ($q2){
  //                       $q2->where("jenis","TBSK")->whereNull("ticket_b_no");
  //                     });
  //                     $q1->orWhere(function ($q2){
  //                       $q2->whereIn("jenis",["CPO","PK"])->where(function($q2){
  //                         $q2->whereNull("ticket_a_no")->orWhereNull("ticket_b_in_at")->orWhereNull("ticket_b_out_at")->orWhereNull("ticket_b_bruto")->orWhereNull("ticket_b_tara")->orWhereNull("ticket_b_netto");
  //                       });
  //                     });
  //                 });
  //               }
  //             }
  //           }
  //         }else{
  //           MyLib::queryCheck($value,$key,$q);
  //         }
  //       }
        
         
       
  //       // if (isset($like_lists["requested_name"])) {
  //       //   $q->orWhereIn("requested_by", function($q2)use($like_lists) {
  //       //     $q2->from('is_users')
  //       //     ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
  //       //   });
  //       // }
  
  //       // if (isset($like_lists["confirmed_name"])) {
  //       //   $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
  //       //     $q2->from('is_users')
  //       //     ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
  //       //   });
  //       // }
  //     });  
  //   }
    
  //   if(!$request->filter_model || count($fm_sorts)==0){
  //     $model_query = $model_query->orderBy('tanggal', 'DESC')->orderBy('id','DESC');
  //   }

  //   $filter_status = $request->filter_status;
    
  //   $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where('payment_method_id',2)->where('val',1)->where('val1',1)->where('val2',1)->where('val4',0)->where('val5',0)->where('received_payment',0);

  //   $model_query = $model_query->with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','trx_absens'=>function($q) {
  //     $q->select('id','trx_trp_id','created_at','updated_at')->where("status","B");
  //   }])->get();

  //   return response()->json([
  //     "data" => TrxTrpResource::collection($model_query),
  //   ], 200);
  // }

  // public function validasiAndTransferMandiri(Request $request){
  //   $this->checkGATimeout();

  //   MyAdmin::checkMultiScope($this->permissions, ['trp_trx.transfer.do_transfer']);

  //   $rules = [
  //     'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
  //   ];

  //   $messages = [
  //     'id.required' => 'ID tidak boleh kosong',
  //     'id.exists' => 'ID tidak terdaftar',
  //   ];

  //   $validator = Validator::make($request->all(), $rules, $messages);

  //   if ($validator->fails()) {
  //     throw new ValidationException($validator);
  //   }

  //   $t_stamp = date("Y-m-d H:i:s");
  //   DB::beginTransaction();
  //   try {
  //     $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
  //     // if($model_query->cost_center_code==""){
  //     //   throw new \Exception("Minta Kasir Untuk Memasukkan Cost Center Code Terlebih Dahulu",1);
  //     // }

  //     if($model_query->val==0){
  //       throw new \Exception("Data Perlu Divalidasi oleh kasir terlebih dahulu",1);
  //     }

  //     if($model_query->val1==0){
  //       throw new \Exception("Data Perlu Divalidasi oleh mandor terlebih dahulu",1);
  //     }

  //     if($model_query->val2==0){
  //       throw new \Exception("Data Perlu Divalidasi oleh W/KTU terlebih dahulu",1);
  //     }

  //     // if(($model_query->jenis=='CPO' || $model_query->jenis=='PK') && $model_query->val3==0){
  //     //   throw new \Exception("Data Perlu Divalidasi oleh marketing terlebih dahulu",1);
  //     // }

  //     if($model_query->received_payment == 1){
  //       throw new \Exception("Pembayaran sudah selesai",1);
  //     }

  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.val4',true)){
  //       $model_query->val4 = 1;
  //       $model_query->val4_user = $this->admin_id;
  //       $model_query->val4_at = $t_stamp;
  //     }

  //     if(MyAdmin::checkScope($this->permissions, 'trp_trx.val5',true)){
  //       $model_query->val5 = 1;
  //       $model_query->val5_user = $this->admin_id;
  //       $model_query->val5_at = $t_stamp;
  //     }
  //     // $model_query->received_payment = 1;
  //     $model_query->save();

  //     MyLog::sys("trx_trp",$model_query->id,"transfer");

  //     DB::commit();      

  //     return response()->json([
  //       "message" => "Proses validasi data berhasil",
  //       "val4"=>$model_query->val4,
  //       "val4_user"=>$model_query->val4_user,
  //       "val4_at"=>$model_query->val4_at,
  //       "val4_by"=>$model_query->val4_user ? new IsUserResource(IsUser::find($model_query->val4_user)) : null,
  //       "val5"=>$model_query->val5,
  //       "val5_user"=>$model_query->val5_user,
  //       "val5_at"=>$model_query->val5_at,
  //       "val5_by"=>$model_query->val5_user ? new IsUserResource(IsUser::find($model_query->val5_user)) : null,
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     return response()->json([
  //       "getCode" => $e->getCode(),
  //       "line" => $e->getLine(),
  //       "message" => $e->getMessage(),
  //     ], 400);
  //     return response()->json([
  //       "message" => "Proses ubah data gagal",
  //     ], 400);
  //   }

  // }

  // public function generateCSVMandiri(Request $request){

  //   set_time_limit(0);
  //   $date = new \DateTime();
  //   $filename=$date->format("YmdHis").'-transfer_mandiri';
  //   $t_stamp = date("Y-m-d H:i:s");


  //   $t_stamp = date("Y-m-d H:i:s");
  //   DB::beginTransaction();
  //   try {
  //     $raw_data = TrxTrp::where("payment_method_id",2)
  //     ->where("val",1)
  //     ->where("val1",1)
  //     ->where("val2",1)
  //     ->where(function ($q){
  //       $q->where("val4",1);
  //       $q->orwhere("val5",1);
  //     })
  //     ->whereNull("pvr_no")
  //     ->where("received_payment",0)
  //     ->lockForUpdate()
  //     ->get();
  
  //     // return $raw_data;
  //     $data = [];
  
  //     foreach ($raw_data as $k => $v) {
  //       $supir_id   = $v->supir_id;
  //       $kernet_id  = $v->kernet_id;
        
  //       $ttl_ps     = 0;
  //       $ttl_pk     = 0;
  
  //       $supir_money = 0;
  //       $kernet_money = 0;
  //       foreach ($v->uj_details2 as $key => $val) {
  //         if($val->xfor=='Kernet'){
  //           $kernet_money+=$val->amount*$val->qty;
  //         }else{
  //           $supir_money+=$val->amount*$val->qty;
  //         }
  //       }
  
  //       $ptg_ps_ids = "";
  //       $ptg_pk_ids = "";
  //       foreach ($v->potongan as $k => $val) {
  //         if($val->potongan_mst->employee_id == $supir_id){
  //           $ttl_ps+=$val->nominal_cut;
  //           $ptg_ps_ids.="#".$val->potongan_mst->id." ";
  //         }
    
  //         if($val->potongan_mst->employee_id == $kernet_id){
  //           $ttl_pk+=$val->nominal_cut;
  //           $ptg_pk_ids.="#".$val->potongan_mst->id." ";
  //         }
  //       }
        
  //       $supir_money -= $ttl_ps;
  //       $kernet_money -= $ttl_pk;
  
  //       if($v->supir_id){
  //         $supir = Employee::exclude(['attachment_1','attachment_2'])->with('bank')->find($v->supir_id);
  //         if(!$supir->bank || !$supir->bank->code_duitku)
  //         throw new \Exception("Bank Belum Memiliki code duitku",1);
  
  //         if(!$supir->rek_no)
  //         throw new \Exception("Supir Belum Memiliki rekening",1);
  
  //         if($supir_money==0)
  //         throw new \Exception("Cek kembali master uang jalan detail PVR apakah xfor tidak ada berisi supir atau semua berisikan kernet",1);
  
  //         if($supir_money < 0)
  //         throw new \Exception("Potongan Supir tidak Diterima Karna Nilai Potongan Lebih Besar Dari Uang Yang Akan Ditransfer kan",1);


  //         array_push($data,[
  //           "rek_no"=>$v->supir_rek_no,
  //           "rek_name"=>$v->supir_rek_name,
  //           "nominal"=>$supir_money,
  //           "id"=>$v->id
  //         ]);
  
  //       }
  
  //       if($v->kernet_id){
  //         $kernet = Employee::exclude(['attachment_1','attachment_2'])->with('bank')->find($v->kernet_id);
  //         if(!$kernet->bank || !$kernet->bank->code_duitku)
  //         throw new \Exception("Bank Belum Memiliki code duitku",1);
  
  //         if(!$kernet->rek_no)
  //         throw new \Exception("Supir Belum Memiliki rekening",1);
  
  //         if($kernet_money==0)
  //         throw new \Exception("Cek kembali master uang jalan detail PVR apakah xfor tidak ada berisi kernet atau semua berisikan kernet",1);

  //         if($kernet_money < 0)
  //         throw new \Exception("Potongan Kernet tidak Diterima Karna Nilai Potongan Lebih Besar Dari Uang Yang Akan Ditransfer kan",1);

  //         array_push($data,[
  //           "rek_no"=>$v->kernet_rek_no,
  //           "rek_name"=>$v->kernet_rek_name,
  //           "nominal"=>$kernet_money,
  //           "id"=>$v->id
  //         ]);
  
  //       }
  
  //       $v->received_payment=1;
  
  //       if(MyAdmin::checkScope($this->permissions, 'trp_trx.val4',true)){
  //         $v->val4 = 1;
  //         $v->val4_user = $this->admin_id;
  //         $v->val4_at = $t_stamp;
  //       }
  
  //       if(MyAdmin::checkScope($this->permissions, 'trp_trx.val5',true)){
  //         $v->val5 = 1;
  //         $v->val5_user = $this->admin_id;
  //         $v->val5_at = $t_stamp;
  //       }
  
  //       $v->save();
  //     }
  //     // return $data;
  
  //     $csv_data = "";
  //     foreach ($data as $k => $v) {      
  //       $csv_data .= "{$v['rek_no']},{$v['rek_name']},,,,IDR,{$v['nominal']},,,IBU,,,,,,,N,,,,,Y,,,,,,,,,,,,,,,,,BEN,1,E,,,\n";
  //     }
  
  //     $total = array_reduce($data,function ($carry,$item) {
  //       return $carry+=(int) $item['nominal'];      
  //     });
  
  //     $mime=MyLib::mime("csv");
  
  //     $filePath = 'public/' . $filename . '.' . $mime["ext"];
  
  //     $mandiri_bank_no =env('MANDIRI_BANK_NO');
  //     $records = count($data);
  //     $csv = "P,{$date->format('Ymd')},{$mandiri_bank_no},{$records},{$total}\n";
  
  //     // foreach($data as $k=>$v) {
  //     //   $csv .= str_replace('"', '', $v["supir"]) . "," . str_replace('"', '', $v["kernet"]) . "\n";
  //     // }
  
  //     Storage::put($filePath, $csv.$csv_data);



  //   $remotePath = 'csvf/'.$filename . '.' . $mime["ext"];
  //   try {
  //       // Storage::disk('ftp')->put($remotePath, file_get_contents(Storage::get($file)));
  //       Storage::disk('ftp')->put($remotePath,Storage::get($filePath));
  //   } catch (\Exception $e) {
  //       // Handle the exception
  //       MyLog::logging($e->getMessage());
  //   }
      
  //     DB::commit();

  //     return response()->json([
  //       "message" => "Proses Generate Berhasil",
  //     ], 200);
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     // return response()->json([
  //     //   "getCode" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     //   "message" => $e->getMessage(),
  //     // ], 400);
  //     return response()->json([
  //       "message" => "Proses Generate gagal",
  //     ], 400);
  //   }

  // }
}