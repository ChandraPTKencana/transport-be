<?php

namespace App\Console\Commands;

use App\Helpers\MyLog;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Validator;
use App\Models\Stok\Transaction;

class Testing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'testing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate Stock';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        

        $this->info("APP_NAME".env("APP_NAME"). "\n ");
        $this->info("PVR_BANK_ACCOUNT_CODE".env("PVR_BANK_ACCOUNT_CODE"). "\n ");
       

        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");

    }
}
