<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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


        $standby_trx_dtl = \App\Models\MySql\StandbyTrxDtl::whereHas("standby_trx",function ($q){
            $q->where('val',1);            
        })->update(['be_paid'=>1]);


        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
