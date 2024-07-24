<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Models\MySql\PaymentMethod;
use App\Models\MySql\Info;
use App\Helpers\MyLog;
use App\Models\MySql\TrxTrp;

class RunCom1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert account code to table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Start\n ");
        
        $this->info("=====Begin trx=====\n ");

        DB::update('update trx_trp set val4 = val_ticket , val4_user = val_ticket_user , val4_at = val_ticket_at');

        $this->info("=====End trx=====\n ");

        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
