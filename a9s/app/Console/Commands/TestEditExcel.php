<?php

namespace App\Console\Commands;

use App\Imports\DuitkuBD;
use Illuminate\Console\Command;

use Maatwebsite\Excel\Facades\Excel;

class TestEditExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dup_duitku';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Excel Editing';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Start\n ");

        $array = Excel::import(new DuitkuBD(), './resources/views/duitku/TEMP.xlsx');


        $this->info(json_encode($array));
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
