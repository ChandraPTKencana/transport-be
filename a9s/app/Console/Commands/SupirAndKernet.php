<?php

namespace App\Console\Commands;

use App\Helpers\MyLog;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Validator;
use App\Models\Stok\Transaction;

class SupirAndKernet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snk';

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
        
        $vehicles = \App\Models\MySql\TrxTrp::select("no_pol")->groupBy("no_pol")->orderby("no_pol")->get();
         
        foreach ($vehicles as $key => $value) {
            $single = trim($value->no_pol);

            if(!preg_match('/[a-zA-Z0-9]/', $single))
            continue;

            if(\App\Models\MySql\Vehicle::where("no_pol",$single)->first())
            continue;

            $this->info("=====Vehicle Loop Start".($key + 1)."=====\n ");
            // $this->info("------------------------------------------------------------------------------------------\n ");
            $toin = new \App\Models\MySql\Vehicle();
            $toin->no_pol = $single;
            $toin->save();
            $this->info("=====Vehicle Loop End".($key + 1)."=====\n ");
        }

        
        $supirs = \App\Models\MySql\TrxTrp::select("supir")->groupBy("supir")->orderby("supir")->get();
        foreach ($supirs as $key => $value) {
            $single = trim($value->supir);

            if(!preg_match('/[a-zA-Z0-9]/', $single))
            continue;

            if(\App\Models\MySql\Employee::where("name",$single)->where("role","Supir")->first())
            continue;

            $this->info("=====Supir Loop Start".($key + 1)."=====\n ");
            // $this->info("------------------------------------------------------------------------------------------\n ");
            $toin = new \App\Models\MySql\Employee();
            $toin->name = $single;
            $toin->role = "Supir";
            $toin->save();
            $this->info("=====Supir Loop End".($key + 1)."=====\n ");
        }

        $kernets = \App\Models\MySql\TrxTrp::select("kernet")->groupBy("kernet")->orderby("kernet")->get();
        foreach ($kernets as $key => $value) {
            $single = trim($value->kernet);

            if(!preg_match('/[a-zA-Z0-9]/', $single))
            continue;

            if(\App\Models\MySql\Employee::where("name",$single)->where("role","Kernet")->first())
            continue;

            $this->info("=====Kernet Loop Start".($key + 1)."=====\n ");
            // $this->info("------------------------------------------------------------------------------------------\n ");
            $toin = new \App\Models\MySql\Employee();
            $toin->name = $single;
            $toin->role = "Kernet";
            $toin->save();
            $this->info("=====Kernet Loop End".($key + 1)."=====\n ");
        }

       
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");


    }
}
