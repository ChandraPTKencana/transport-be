<?php

namespace App\Console\Commands;

use App\Helpers\MyLog;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Validator;
use App\Models\Stok\Transaction;

class RunCom extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_1';

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
        
        $this->info("=====Begin Trx Trp Transition type To=====\n ");
        $sds = \App\Models\MySql\TrxTrp::whereNotNull("transition_target")->update([
            "transition_type"=>"To"
        ]);         
        $this->info("=====End Trx Trp Transition type To=====\n ");
       
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
