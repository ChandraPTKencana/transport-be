<?php

namespace App\Console\Commands;

use App\Http\Resources\MySql\TrxTrpAbsenResource;
use App\Models\MySql\Bank;
use App\Models\MySql\Employee;
use App\Models\MySql\PaymentMethod;
use App\Models\MySql\PermissionGroupDetail;
use App\Models\MySql\PermissionGroupUser;
use App\Models\MySql\PermissionList;
use App\Models\MySql\PermissionUserDetail;
use App\Models\MySql\PotonganMst;
use App\Models\MySql\StandbyTrxDtl;
use App\Models\MySql\TrxAbsen;
use App\Models\MySql\TrxTrp;
use App\Models\MySql\Ujalan;
use Illuminate\Console\Command;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RunData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RUN DATA';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {

        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Start\n ");

        // Employee::whereNull('attachment_1_loc')
        // ->whereNotNull('attachment_1')
        // ->chunkById(10, function ($employees) {

        //     foreach ($employees as $em) {
        //         $binary = base64_decode($em->attachment_1);

        //         $ext = str_starts_with($em->attachment_1_type, 'image/') ? 'png' : 'pdf';
        //         $file_name = "{$em->id}_att1_" . Str::uuid() . '.' . $ext;
        //         $path = "employees/{$file_name}";

        //         Storage::disk('public')->put($path, $binary, 'private');

        //         $em->update([
        //             'attachment_1_loc' => $path,
        //             'attachment_1' => null, // optional
        //         ]);
        //     }
        // });

        // StandbyTrxDtl::whereNull('attachment_1_loc')
        // ->whereNotNull('attachment_1')
        // ->chunkById(10, function ($stds) {

        //     foreach ($stds as $std) {
        //         $binary = base64_decode($std->attachment_1);

        //         $att_type = $std->attachment_1_type;
        //         if(str_starts_with($att_type, 'image/')){
        //             $ext = explode("/",$att_type)[1];
        //         }else{
        //             $ext = 'pdf';
        //         }
            
        //         $file_name = "{$std->standby_trx_id}_att1_" . Str::uuid() . '.' . $ext;
        //         $path = "standby_trxs/{$file_name}";

        //         Storage::disk('public')->put($path, $binary, 'private');

        //         $std->update([
        //             'attachment_1_loc' => $path,
        //             'attachment_1' => null, // optional
        //         ]);
        //     }
        // });

        PotonganMst::whereNull('attachment_1_loc')
        ->whereNotNull('attachment_1')
        ->chunkById(10, function ($ptgs) {

            foreach ($ptgs as $ptg) {
                $binary = base64_decode($ptg->attachment_1);

                $att_type = $ptg->attachment_1_type;
                if(str_starts_with($att_type, 'image/')){
                    $ext = explode("/",$att_type)[1];
                }else{
                    $ext = 'pdf';
                }
            
                $file_name = "{$ptg->standby_trx_id}_att1_" . Str::uuid() . '.' . $ext;
                $path = "potongan_msts/{$file_name}";

                Storage::disk('public')->put($path, $binary, 'private');

                $ptg->update([
                    'attachment_1_loc' => $path,
                    'attachment_1' => null, // optional
                ]);
            }
        });

        PotonganMst::whereNull('attachment_2_loc')
        ->whereNotNull('attachment_2')
        ->chunkById(10, function ($ptgs) {

            foreach ($ptgs as $ptg) {
                $binary = base64_decode($ptg->attachment_2);

                $att_type = $ptg->attachment_2_type;
                if(str_starts_with($att_type, 'image/')){
                    $ext = explode("/",$att_type)[1];
                }else{
                    $ext = 'pdf';
                }
            
                $file_name = "{$ptg->standby_trx_id}_att2_" . Str::uuid() . '.' . $ext;
                $path = "potongan_msts/{$file_name}";

                Storage::disk('public')->put($path, $binary, 'private');

                $ptg->update([
                    'attachment_2_loc' => $path,
                    'attachment_2' => null, // optional
                ]);
            }
        });




        // $date = new \DateTime();

        // $timestamp = $date->format("Y-m-d H:i:s");
        // $id = 14;
        // $bankExists = Bank::where('code',"BCA")->first();
        // if(!$bankExists){
        //     $banknew = new Bank();
        //     $banknew->code='BCA';
        //     $banknew->name = 'Bank Central Asia';
        //     $banknew->code_duitku = '014';
        //     $banknew->save();
        // }

        // DB::statement("create view v_maintenance as select v.no_pol AS no_pol,count(t.id) AS total_trip,ifnull(sum(u.km_range),0) AS total_km,s.reminder_service AS reminder_service,case when ifnull(sum(u.km_range),0) >= s.reminder_service then 'WARNING SERVICE' else 'AMAN' end AS status_service,ms.terakhir_service AS terakhir_service,ms.terakhir_service_id AS terakhir_service_id from ((((logistik.vehicle_mst v left join (select m1.no_pol AS no_pol,m1.id AS terakhir_service_id,m1.tanggal AS terakhir_service from (logistik.trx_maintenance m1 join (select logistik.trx_maintenance.no_pol AS no_pol,max(logistik.trx_maintenance.id) AS max_id from logistik.trx_maintenance where logistik.trx_maintenance.status = 'Y' group by logistik.trx_maintenance.no_pol) m2 on(m2.max_id = m1.id))) ms on(ms.no_pol = v.no_pol)) left join logistik.trx_trp t on(t.no_pol = v.no_pol and t.tanggal >= ifnull(ms.terakhir_service,'1970-01-01'))) left join logistik.is_uj u on(u.id = t.id_uj)) join logistik.setup s on(1 = 1)) where v.deleted = 0 group by v.no_pol,s.reminder_service,ms.terakhir_service,ms.terakhir_service_id order by v.no_pol");

        // DB::statement("DROP VIEW v_maintenance");


        // $data = [];
        // $temp = [
        //   "rpt_salary_id"           => 0,
        //   "employee_id"             => 0,
        //   "sb_gaji"                 => 0,
        //   "sb_makan"                => 0,
        //   "sb_dinas"                => 0,
        //   "sb_gaji_2"               => 0,
        //   "sb_makan_2"              => 0,
        //   "sb_dinas_2"              => 0,
        //   "uj_gaji"                 => 0,
        //   "uj_makan"                => 0,
        //   "uj_dinas"                => 0,
        //   "nominal_cut"             => 0,
        //   "salary_bonus_nominal"    => 0,
        //   "salary_bonus_nominal_2"  => 0,
        //   "trip_cpo"                => 0,
        //   "trip_cpo_bonus_gaji"     => 0,
        //   "trip_cpo_bonus_dinas"    => 0,
        //   "trip_pk"                 => 0,
        //   "trip_pk_bonus_gaji"      => 0,
        //   "trip_pk_bonus_dinas"     => 0,
        //   "trip_tbs"                => 0,
        //   "trip_tbs_bonus_gaji"     => 0,
        //   "trip_tbs_bonus_dinas"    => 0,
        //   "trip_tbsk"               => 0,
        //   "trip_tbsk_bonus_gaji"    => 0,
        //   "trip_tbsk_bonus_dinas"   => 0,
        //   "trip_lain"               => 0,
        //   "trip_lain_gaji"          => 0,
        //   "trip_lain_makan"         => 0,
        //   "trip_lain_dinas"         => 0,
        //   "trip_tunggu"             => 0,
        //   "trip_tunggu_gaji"        => 0,
        //   "trip_tunggu_dinas"       => 0,
        // ];

        // $period_end = '2025-11-30';
        // $smp_bulan = '2025-11';

        // $tt = TrxTrp::whereNotNull("pv_id")
        // ->where("req_deleted",0)
        // ->where("deleted",0)
        // ->where('val',1)
        // ->where('val1',1)
        // ->where('val2',1)
        // ->where(function ($q){
        //     $q->where("supir_id",1001);
        //     $q->orWhere("kernet_id",1001);
        // })
        // ->where(function ($q) use($period_end,$smp_bulan) {
        //   $q->where(function ($q1)use($period_end,$smp_bulan){
        //     $q1->where("payment_method_id",1);       
        //     $q1->where("received_payment",0);                  
        //     $q1->where("tanggal",">=",$smp_bulan."-01");                  
        //     $q1->where("tanggal","<=",$period_end);                  
        //   });
    
        //   $q->orWhere(function ($q1)use($period_end,$smp_bulan){
        //     $q1->whereIn("payment_method_id",[2,3]);
        //     $q1->where(function ($q2)use($period_end,$smp_bulan){
        //       // supir dan kernet dipisah krn asumsi di tf di waktu atau bahkan hari yang berbeda
        //       $q2->where(function ($q3) use($period_end,$smp_bulan) {            
        //         $q3->where("rp_supir_at",">=",$smp_bulan."-01 00:00:00");                  
        //         $q3->where("rp_supir_at","<=",$period_end." 23:59:59");                  
        //       });
        //       $q2->orWhere(function ($q3) use($period_end,$smp_bulan) {
        //         $q3->where("rp_kernet_at",">=",$smp_bulan."-01 00:00:00");                  
        //         $q3->where("rp_kernet_at","<=",$period_end." 23:59:59");                  
        //       });
        //     });                         
        //   });
        // })->get();
    
    
        // foreach ($tt as $k => $v) {
        //   $smd = $v->uj;
        //   $smd2 = $v->uj_details2;
    
        //   $nominal_s = 0;
        //   $uj_gaji_s = 0;
        //   $uj_makan_s = 0;
        //   $uj_dinas_s = 0;
    
        //   $nominal_k = 0;
        //   $uj_gaji_k = 0;
        //   $uj_makan_k = 0;
        //   $uj_dinas_k = 0;
    
        //   $trip_tunggu_gaji_s = 0;
        //   $trip_tunggu_dinas_s = 0;
    
        //   $trip_tunggu_gaji_k = 0;
        //   $trip_tunggu_dinas_k = 0;
          
        //   $trip_lain_gaji_s = 0;
        //   $trip_lain_makan_s = 0;
        //   $trip_lain_dinas_s = 0;
    
        //   $trip_lain_gaji_k = 0;
        //   $trip_lain_makan_k = 0;
        //   $trip_lain_dinas_k = 0;
    
        //   foreach ($smd2 as $k1 => $v1) {
        //     $amount = $v1->amount * $v1->qty;
        //     if($v1->xfor == 'Supir'){
        //       $nominal_s += $amount;
        //       if($v1->ac_account_code=='01.510.001' && in_array($smd->jenis,["CPO","PK","TBS","TBSK"])) $uj_gaji_s += $amount;
        //       if($v1->ac_account_code=='01.510.005' && in_array($smd->jenis,["CPO","PK","TBS","TBSK"])) $uj_makan_s += $amount;
        //       if($v1->ac_account_code=='01.575.002'  && in_array($smd->jenis,["CPO","PK","TBS","TBSK"])) $uj_dinas_s += $amount;
              
        //       if($v1->ac_account_code=='01.510.001' && in_array($smd->jenis,["TUNGGU"])) $trip_tunggu_gaji_s += $amount;
        //       if($v1->ac_account_code=='01.575.002'  && in_array($smd->jenis,["TUNGGU"])) $trip_tunggu_dinas_s += $amount;
    
        //       if($v1->ac_account_code=='01.510.001' && in_array($smd->jenis,["LAIN"])) $trip_lain_gaji_s += $amount;
        //       if($v1->ac_account_code=='01.510.005' && in_array($smd->jenis,["LAIN"])) $trip_lain_makan_s += $amount;
        //       if($v1->ac_account_code=='01.575.002'  && in_array($smd->jenis,["LAIN"])) $trip_lain_dinas_s += $amount;
    
        //     }
    
        //     if($v1->xfor == 'Kernet'){
        //       $nominal_k += $amount;
        //       if($v1->ac_account_code=='01.510.001' && in_array($smd->jenis,["CPO","PK","TBS","TBSK"])) $uj_gaji_k += $amount;
        //       if($v1->ac_account_code=='01.510.005' && in_array($smd->jenis,["CPO","PK","TBS","TBSK"])) $uj_makan_k += $amount;
        //       if($v1->ac_account_code=='01.575.002'  && in_array($smd->jenis,["CPO","PK","TBS","TBSK"])) $uj_dinas_k += $amount;
              
        //       if($v1->ac_account_code=='01.510.001' && in_array($smd->jenis,["TUNGGU"])) $trip_tunggu_gaji_k += $amount;
        //       if($v1->ac_account_code=='01.575.002'  && in_array($smd->jenis,["TUNGGU"])) $trip_tunggu_dinas_k += $amount;
    
        //       if($v1->ac_account_code=='01.510.001' && in_array($smd->jenis,["LAIN"])) $trip_lain_gaji_k += $amount;
        //       if($v1->ac_account_code=='01.510.005' && in_array($smd->jenis,["LAIN"])) $trip_lain_makan_k += $amount;
        //       if($v1->ac_account_code=='01.575.002'  && in_array($smd->jenis,["LAIN"])) $trip_lain_dinas_k += $amount;
        //     }
        //   }
    
    
        //   if($v->supir_id){
    
        //     $map_s = array_map(function($x){
        //       return $x['employee_id'];
        //     },$data);
    
        //     $search = array_search($v->supir_id,$map_s);
    
        //     if(count($data)==0 || $search===false){
    
        //       $emp = $v->employee_s;
        //       $newData = $temp;
        //       $newData["rpt_salary_id"]=$id;
        //       $newData["employee_id"]=$emp->id;
        //       $newData["uj_gaji"]=$uj_gaji_s;
        //       $newData["uj_makan"]=$uj_makan_s;
        //       $newData["uj_dinas"]=$uj_dinas_s;
    
        //       $newData["trip_tunggu_gaji"]=$trip_tunggu_gaji_s;
        //       $newData["trip_tunggu_dinas"]=$trip_tunggu_dinas_s;
    
        //       $newData["trip_lain_gaji"]=$trip_lain_gaji_s;
        //       $newData["trip_lain_makan"]=$trip_lain_makan_s;
        //       $newData["trip_lain_dinas"]=$trip_lain_dinas_s;
    
        //       if($smd->jenis=='CPO'){
        //         $newData["trip_cpo"]=1;
        //       }elseif ($smd->jenis=='PK') {
        //         $newData["trip_pk"]=1;
        //       }elseif ($smd->jenis=='TBS') {
        //         $newData["trip_tbs"]=1;
        //       }elseif ($smd->jenis=='TBSK') {
        //         $newData["trip_tbsk"]=1;
        //       }elseif ($smd->jenis=='LAIN') {
        //         $newData["trip_lain"]=1;
        //       }elseif ($smd->jenis=='TUNGGU') {
        //         $newData["trip_tunggu"]=1;
        //       }
    
        //       array_push($data,$newData);
        //     }else{
        //       // $dt_dtl[$search]['standby_nominal']+=$nominal_s;
        //       $data[$search]['uj_gaji']+=$uj_gaji_s;
        //       $data[$search]['uj_makan']+=$uj_makan_s;
        //       $data[$search]['uj_dinas']+=$uj_dinas_s;
    
        //       $data[$search]["trip_tunggu_gaji"]+=$trip_tunggu_gaji_s;
        //       $data[$search]["trip_tunggu_dinas"]+=$trip_tunggu_dinas_s;
    
        //       $data[$search]["trip_lain_gaji"]+=$trip_lain_gaji_s;
        //       $data[$search]["trip_lain_makan"]+=$trip_lain_makan_s;
        //       $data[$search]["trip_lain_dinas"]+=$trip_lain_dinas_s;
    
        //       if($smd->jenis=='CPO'){
        //         $data[$search]["trip_cpo"]+=1;
        //       }elseif ($smd->jenis=='PK') {
        //         $data[$search]["trip_pk"]+=1;
        //       }elseif ($smd->jenis=='TBS') {
        //         $data[$search]["trip_tbs"]+=1;
        //       }elseif ($smd->jenis=='TBSK') {
        //         $data[$search]["trip_tbsk"]+=1;
        //       }elseif ($smd->jenis=='LAIN') {
        //         $data[$search]["trip_lain"]+=1;
        //       }elseif ($smd->jenis=='TUNGGU') {
        //         $data[$search]["trip_tunggu"]+=1;
        //       }
        //     }
        //   }
    
        //   if($v->kernet_id){
    
        //     $map_k = array_map(function($x){
        //       return $x['employee_id'];
        //     },$data);
    
        //     $search = array_search($v->kernet_id,$map_k);
    
        //     if(count($data)==0 || $search===false){
        //       $emp = $v->employee_k;
        //       $newData = $temp;
        //       $newData["rpt_salary_id"]=$id;
        //       $newData["employee_id"]=$emp->id;
        //       $newData["uj_gaji"]=$uj_gaji_k;
        //       $newData["uj_makan"]=$uj_makan_k;
        //       $newData["uj_dinas"]=$uj_dinas_k;
    
        //       $newData["trip_tunggu_gaji"]=$trip_tunggu_gaji_k;
        //       $newData["trip_tunggu_dinas"]=$trip_tunggu_dinas_k;
    
        //       $newData["trip_lain_gaji"]=$trip_lain_gaji_k;
        //       $newData["trip_lain_makan"]=$trip_lain_makan_k;
        //       $newData["trip_lain_dinas"]=$trip_lain_dinas_k;
    
        //       if($smd->jenis=='CPO'){
        //         $newData["trip_cpo"]=1;
        //       }elseif ($smd->jenis=='PK') {
        //         $newData["trip_pk"]=1;
        //       }elseif ($smd->jenis=='TBS') {
        //         $newData["trip_tbs"]=1;
        //       }elseif ($smd->jenis=='TBSK') {
        //         $newData["trip_tbsk"]=1;
        //       }elseif ($smd->jenis=='LAIN') {
        //         $newData["trip_lain"]=1;
        //       }elseif ($smd->jenis=='TUNGGU') {
        //         $newData["trip_tunggu"]=1;
        //       }
    
        //       array_push($data,$newData);
  
        //     }else{
        //       // $dt_dtl[$search]['standby_nominal']+=$nominal_k;
        //       $data[$search]['uj_gaji']+=$uj_gaji_k;
        //       $data[$search]['uj_makan']+=$uj_makan_k;
        //       $data[$search]['uj_dinas']+=$uj_dinas_k;
    
        //       $data[$search]["trip_tunggu_gaji"]+=$trip_tunggu_gaji_k;
        //       $data[$search]["trip_tunggu_dinas"]+=$trip_tunggu_dinas_k;
    
        //       $data[$search]["trip_lain_gaji"]+=$trip_lain_gaji_k;
        //       $data[$search]["trip_lain_makan"]+=$trip_lain_makan_k;
        //       $data[$search]["trip_lain_dinas"]+=$trip_lain_dinas_k;
    
    
        //       if($smd->jenis=='CPO'){
        //         $data[$search]["trip_cpo"]+=1;
        //       }elseif ($smd->jenis=='PK') {
        //         $data[$search]["trip_pk"]+=1;
        //       }elseif ($smd->jenis=='TBS') {
        //         $data[$search]["trip_tbs"]+=1;
        //       }elseif ($smd->jenis=='TBSK') {
        //         $data[$search]["trip_tbsk"]+=1;
        //       }elseif ($smd->jenis=='LAIN') {
        //         $data[$search]["trip_lain"]+=1;
        //       }elseif ($smd->jenis=='TUNGGU') {
        //         $data[$search]["trip_tunggu"]+=1;
        //       }
    
        //       // if($v->kernet_id==1120){
        //       //   MyLog::logging([
        //       //     "data"=>"add",
        //       //     "kernet_id"=>1120,
        //       //     "kernet_ug"=>$uj_gaji_k,
        //       //     "trip"=>$v->id,
        //       //     "export"=>$data[$search]
        //       //   ],"reportcheck");
        //       // }
        //     }
        //   }
        // }

        // $this->info(json_encode($data)."\n ");
        

        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
