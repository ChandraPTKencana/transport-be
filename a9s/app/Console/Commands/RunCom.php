<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Models\MySql\PaymentMethod;
use App\Models\MySql\Info;
use App\Helpers\MyLog;

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
        
        // $this->info("=====Begin=====\n ");
               
        // Info::insert([
        //     "dkey"=>"company_code",
        //     "dval"=>'KPN',
        // ]);    
        // Info::insert([
        //     "dkey"=>"company_name",
        //     "dval"=>'PT. KENCANA PERSADA NUSANTARA',
        // ]);   
        // Info::insert([
        //     "dkey"=>"company_email",
        //     "dval"=>'KPN@genkagromas.com',
        // ]);   
        // $this->info("=====End=====\n ");

        // PaymentMethodDB::insert('insert into users (id, name) values (?, ?)', [1, 'Dayle'])
        PaymentMethod::insert([
            "name"=>"TRANSFER-DUITKU",
            "account_code"=>"01.111.018",
        ]);

        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
