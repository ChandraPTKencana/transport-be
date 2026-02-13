<?php

namespace App\Console\Commands;

use App\Helpers\MyLib;
use App\Helpers\MyLog;
use App\Models\MySql\PaymentMethod;
use App\Models\MySql\PermissionGroupDetail;
use App\Models\MySql\PermissionGroupUser;
use App\Models\MySql\PermissionList;
use App\Models\MySql\PermissionUserDetail;
use App\Models\MySql\TrxAbsen;
use App\Models\MySql\TrxTrp;
use App\Models\MySql\Ujalan;
use Illuminate\Console\Command;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
class RunData2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_data2';

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

        // $date2  = "2025-10-30 00:00:00";
        // $date1  = "2025-10-31 00:01:00";

        // $datetime1 = \DateTime::createFromFormat('Y-m-d H:i:s', $date1);
        // $datetime2 = \DateTime::createFromFormat('Y-m-d H:i:s', $date2);

        // $interval = $datetime1->diff($datetime2);
        // $totalHours = ($interval->days * 24) + $interval->h + ($interval->i / 60);

        // echo "Difference: " . $totalHours . " hours";
        
        $date1 = "2025-10-30 00:00:00";
        $date2 = "2025-10-31 01:27:53";


        $this->info(json_encode(MyLib::dateDiff($date1,$date2))."\n ");
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
