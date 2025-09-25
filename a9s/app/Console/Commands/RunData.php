<?php

namespace App\Console\Commands;

use App\Models\MySql\TrxAbsen;
use App\Models\MySql\TrxTrp;
use App\Models\MySql\Ujalan;
use Illuminate\Console\Command;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
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

        // $ujs =  \App\Models\MySql\Ujalan::where("deleted",0)->get();

        // foreach ($ujs as $key => $uj) {
        //     $there_kernet = 0;
        //     foreach ($uj->details2 as $key1 => $ujd) {
        //         if($ujd->xfor=="Kernet") $there_kernet=1;
        //     }
        //     if($there_kernet==1){
        //         $uj->asst_opt = "DENGAN KERNET";
        //         $uj->save();
        //     }
        // }

        // $standbys =  \App\Models\MySql\StandbyMst::where("deleted",0)->get();

        // foreach ($standbys as $key => $st) {
        //     $there_supir = 0;
        //     $there_kernet = 0;
        //     foreach ($st->details as $key1 => $std) {
        //         if($std->xfor=="Kernet") $there_kernet=1;
        //         if($std->xfor=="Supir") $there_supir=1;
        //     }
        //     if($there_kernet==1 && $there_supir==1){
        //         $st->driver_asst_opt = "SUPIR KERNET";
        //         $st->save();
        //     }elseif ($there_kernet==1 && $there_supir==0) {
        //         $st->driver_asst_opt = "KERNET";
        //         $st->save();
        //     }elseif ($there_kernet==0 && $there_supir==1) {
        //         $st->driver_asst_opt = "SUPIR";
        //         $st->save();
        //     }
        // }


        // $standby_trx_dtl = \App\Models\MySql\StandbyTrxDtl::whereHas("standby_trx",function ($q){
        //     $q->where('val',1);            
        // })->update(['be_paid'=>1]);

        // $trx_trps = \App\Models\MySql\TrxTrp::whereNotNull("pv_id")->update(['pv_complete'=>1]);
        // $extra_moneys = \App\Models\MySql\ExtraMoneyTrx::whereNotNull("pv_id")->update(['pv_complete'=>1]);


        // $trx_absens = TrxAbsen::exclude(['gambar'])->get();
        // foreach ($trx_absens as $key => $value) {
        //     $gmbr = TrxAbsen::select('gambar','gambar_loc','created_at')->where("id",$value->id)->first();
        //     // $this->info(json_encode($gmbr->gambar)."\n ");

        //     if($gmbr->gambar){
        //         $img = "data:image/png;base64,";
        //         if(mb_detect_encoding($gmbr->gambar)===false){
        //             $img.=base64_encode($gmbr->gambar);
        //         }else{
        //             $img.=$gmbr->gambar;        
        //         }
                
        //         $date = new \DateTime();
        //         $timestamp = $date->format("Y-m-d H:i:s.v");
        //         $file_name = md5(preg_replace('/( |-|:)/', '', $timestamp)) . '.' . 'png';

        //         // $location = "/files/trx_absen";
        //         // File::ensureDirectoryExists($location);
        //         // $location = $location."/{$ca_path[0]}";
        //         // File::ensureDirectoryExists($location);
        //         // $location = $location."/{$ca_path[1]}";
        //         // File::ensureDirectoryExists($location);
        //         $ca_path = explode("-",$gmbr->created_at);
        //         $location = "/files/trx_absen/{$ca_path[0]}/{$ca_path[1]}";

        //         $full_lf = $location."/{$file_name}";

        //         File::ensureDirectoryExists(files_path($location));

        //         Image::read($img)->save(files_path($full_lf));

        //         $gmbr->gambar_loc = $location;
        //         // $gmbr->gambar = null;
        //         $gmbr->save();
        //     }
        // }


        // foreach (\App\Models\MySql\StandbyTrx::whereNotNull("ref")->get() as $key => $value) {
        //     $trx_trp =\App\Models\MySql\TrxTrp::where("id",$value->ref)->first();
        //     if($trx_trp){
        //         $value->trx_trp_id = $value->ref;
        //         $value->save();
        //     }else{
        //         $this->info("ID ".$value->id.",ref not found in trx trp \n ");
        //     }
        // }

        // $rpts = \App\Models\MySql\RptSalaryDtl::get();

        // foreach ($rpts as $k => $v) {
        //     $emp = \App\Models\MySql\Employee::selectRaw("id,name,rek_name")->where("id",$v->employee_id)->first();
        //     \App\Models\MySql\RptSalaryDtl::where("employee_id",$v->employee_id)->update([
        //         "employee_rek_name"=>$emp->rek_name
        //     ]);
        //     $this->info($v->id."-".$emp->id."-".$emp->rek_name."-".$v->employee_id."-".$v->employee_name."\n ");
        // }

        // $pm = new \App\Models\MySql\PaymentMethod();
        // $pm->name = 'TRANSFER-DUITKU-5000';
        // $pm->account_code = '01.100.013';
        // $pm->save();

        // $trxtrp= \App\Models\MySql\TrxTrp::where("tanggal","<","2025-08-01")->update(["salary_paid_id"=>1]);

        // \App\Models\MySql\Ujalan::whereIn("jenis",['CPO','PK'])->where(function ($q){
        //     $q->where("deleted",0)->orWhere('deleted_at',">=","2025-08-01 00:00:00");
        // })->update([
        //     'batas_persen_susut'=>-0.3
        // ]);


        // $nonorinet = 30900 - 31000 ;
        // $this->info($nonorinet."\n ");
        // // if($nonorinet<0) $nonorinet*=-1;
        // $pembanding = 31000;
        // $this->info($pembanding."\n ");
        
        // $bps = -0.3;
        // $this->info((($nonorinet/$pembanding)*100)."\n ");

        // $this->info(round(($nonorinet/$pembanding)*100,2)."\n ");

        // $this->info(round(($nonorinet/$pembanding)*100,2) < $bps."\n ");


        // if(round(($nonorinet/$pembanding)*100,2) < $bps ){
        //     $gen_salary_bonus = true;
        // }

        

        // $this->info("ms_".strtolower(env("app_name")));
        // $table1 = DB::table('trx_trp')->selectRaw("concat('A') as jenis, ticket_a_no as ticket_no")->whereNotNull("ticket_a_no");

        // $table2 = DB::table('trx_trp')->selectRaw("concat('B') as jenis, ticket_b_no as ticket_no")->whereNotNull("ticket_b_no");
    
        // $final = $table1->unionAll($table2);
    
        // $querySql = $final->toSql();
        // $this->info("sql|".$querySql."\n ");
         
        // $model_query = DB::table(DB::raw("($querySql) as a"))->mergeBindings($final);
         
        // //Now you can do anything u like:
         
        // // $model_query = $model_query->selectRaw("jenis, ticket_no,count(*) as lebih")->groupBy('ticket_no','jenis')->having('lebih',">",1)->offset($offset)->limit($limit)->get(); 
        // $model_query = $model_query->selectRaw("jenis, ticket_no,count(*) as lebih")->groupBy('ticket_no','jenis');

        // $pabrik = strtolower("KPN");
        // if(strtolower(env("app_name"))==$pabrik){
        //     $model_query = $model_query->having('lebih',">",1)->get();
        // }else{
        //     $model_query = $model_query->get();
        // }

        // if(strtolower(env("app_name"))!=$pabrik){
        //     $this->info($pabrik ."|".strtolower(env("app_name")));

        //     $pabrik = "ms_".$pabrik;

        //     $this->info("new pab|".$pabrik);

        //     $table3 = DB::connection($pabrik)->table('trx_trp')->selectRaw("concat('A') as jenis, ticket_a_no as ticket_no")->whereNotNull("ticket_a_no");
        //     $table4 = DB::connection($pabrik)->table('trx_trp')->selectRaw("concat('B') as jenis, ticket_b_no as ticket_no")->whereNotNull("ticket_b_no");
        
        //     $final = $table3->unionAll($table4);
    
        //     $querySql = $final->toSql();
        //     $this->info("sql|".$querySql."\n ");
            
        //     $model_query2 = DB::connection($pabrik)->table(DB::raw("($querySql) as a"))->mergeBindings($final);
            
        //     //Now you can do anything u like:
            
        //     // $model_query = $model_query->selectRaw("jenis, ticket_no,count(*) as lebih")->groupBy('ticket_no','jenis')->having('lebih',">",1)->offset($offset)->limit($limit)->get(); 
        //     $model_query2 = $model_query2->selectRaw("ticket_no")->groupBy('ticket_no')->pluck("ticket_no")->toArray();

        //     $mq = $model_query->toArray();
        //     $model_query = [];
        //     foreach ($mq as $k => $v) {


        //         // $this->info($v->ticket_no."\n ");
        //         $lebih = $v->lebih;
        //         if(array_search( $v->ticket_no, $model_query2) ===false){

        //         }else{
        //             $lebih+=1;
        //         }
                
        //         array_push($model_query,[
        //             "jenis"=>$v->jenis,
        //             "ticket_no"=>$v->ticket_no,
        //             "lebih"=>$lebih,
        //         ]);
        //     }
            
        //     // $final = $final->unionAll($table3)->unionAll($table4);
        //     $model_query = array_filter($model_query,function ($x){
        //         return $x['lebih'] >= 2;
        //     });
        // }

        // $this->info(json_encode($model_query)."\n ");


        $uj = Ujalan::where("bonus_trip_supir","!=",0)->orWhere('bonus_trip_kernet',"!=",0)->get();

        foreach ($uj as $k => $v) {
         TrxTrp::where("id_uj",$v->id)->update(["bonus_trip_supir"=>$v->bonus_trip_supir,"bonus_trip_supir"=>$v->bonus_trip_kernet]);
        }
        // $this->info(json_encode($uj)."\n ");

        $trp=TrxTrp::where("bonus_trip_supir","!=",0)->orWhere('bonus_trip_kernet',"!=",0)->get();
        $this->info(json_encode($trp)."\n ");

        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
