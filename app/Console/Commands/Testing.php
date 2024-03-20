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
        $banding = ("2024-01-01 00:00:01" < "2024-01-01 00:00:02") ? 't' : 'f';
        $banding1 = ("2024-01-01 00:00:01" < "2024-01-01 00:00:11") ? 't' : 'f';
        $banding2 = ("2024-01-01 00:00:02" < "2024-01-01 00:00:11") ? 't' : 'f';


        $this->info($banding. "\n ");
        $this->info($banding1. "\n ");
        $this->info($banding2. "\n ");
       

        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");

    }
}
