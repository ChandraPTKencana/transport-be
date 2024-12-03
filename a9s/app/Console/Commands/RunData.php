<?php

namespace App\Console\Commands;

use App\Models\MySql\TrxAbsen;
use App\Models\MySql\TrxTrp;
use Illuminate\Console\Command;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;
use Illuminate\Support\Facades\File;

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

        $rpts = \App\Models\MySql\RptSalaryDtl::get();

        foreach ($rpts as $k => $v) {
            $emp = \App\Models\MySql\Employee::selectRaw("id,name,rek_name")->where("id",$v->employee_id)->first();
            \App\Models\MySql\RptSalaryDtl::where("employee_id",$v->employee_id)->update([
                "employee_rek_name"=>$emp->rek_name
            ]);
            $this->info($v->id."-".$emp->id."-".$emp->rek_name."-".$v->employee_id."-".$v->employee_name."\n ");
        }
        
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
