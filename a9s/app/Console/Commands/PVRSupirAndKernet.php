<?php

namespace App\Console\Commands;

use App\Helpers\MyLog;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Validator;
use App\Models\Stok\Transaction;

class PVRSupirAndKernet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'p_snk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Supir And Kernet Name From Server';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Start\n ");
        
        $this->info("=====StandbyDtl Supir Start=====\n ");
        $sds = \App\Models\MySql\StandbyDtl::where("description","like","%supir%")->get();         
        foreach ($sds as $key => $value) {
            $value->xfor = "Supir";
            $value->save();
        }
        $this->info("=====StandbyDtl Supir End=====\n ");

        $this->info("=====StandbyDtl Kernet Start=====\n ");
        $sds = \App\Models\MySql\StandbyDtl::where("description","like","%kernet%")->get();         
        foreach ($sds as $key => $value) {
            $value->xfor = "Kernet";
            $value->save();
        }
        $this->info("=====StandbyDtl Kernet End=====\n ");

        $this->info("=====StandbyDtl Supir Start=====\n ");
        $sds = \App\Models\MySql\StandbyDtl::where("description","like","%supir%")->get();         
        foreach ($sds as $key => $value) {
            $value->xfor = "Supir";
            $value->save();
        }
        $this->info("=====StandbyDtl Supir End=====\n ");

        $this->info("=====UjalanDetail2 Supir Start=====\n ");
        $sds = \App\Models\MySql\UjalanDetail2::where("description","like","%supir%")->get();         
        foreach ($sds as $key => $value) {
            $value->xfor = "Supir";
            $value->save();
        }
        $this->info("=====UjalanDetail2 Supir End=====\n ");

        $this->info("=====UjalanDetail2 Kernet Start=====\n ");
        $sds = \App\Models\MySql\UjalanDetail2::where("description","like","%kernet%")->get();         
        foreach ($sds as $key => $value) {
            $value->xfor = "Kernet";
            $value->save();
        }
        $this->info("=====UjalanDetail2 Kernet End=====\n ");
       
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
