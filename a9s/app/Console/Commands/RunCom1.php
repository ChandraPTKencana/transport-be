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

        DB::update('UPDATE trx_trp set `rp_kernet_user` = `val5_user` , `rp_kernet_at` = `val5_at` where kernet_id is not null and payment_method_id =2 and received_payment=1');
        DB::update('UPDATE trx_trp set `rp_supir_user` = `val5_user` , `rp_supir_at` = `val5_at` where payment_method_id =2 and received_payment=1');

        $this->info("=====End trx=====\n ");

        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
