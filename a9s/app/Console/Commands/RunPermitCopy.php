<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\MySql\IsUser;
use App\Models\MySql\PermissionGroup;
use App\Models\MySql\PermissionGroupDetail;
use App\Models\MySql\PermissionGroupUser;
use App\Models\MySql\PermissionList;
use App\Models\MySql\TrxTrp;
use Illuminate\Support\Facades\Schema;
class RunPermitCopy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_permit_copy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy Permission List';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Start\n ");
        // Schema::disableForeignKeyConstraints();

        $from_name = "LOGISTIC_STAFF";
        $to_name = ['LOGISTIC_SPV','LOGISTIC_MANAGER'];


        $from_id = PermissionGroup::where('name', $from_name)->first()->id;
        $from_pgd = PermissionGroupDetail::where('permission_group_id',$from_id)->where("permission_list_name","!=","trp_trx.val4")->get()->pluck('permission_list_name')->toArray();
        $this->info(json_encode($from_pgd)."\n ");

        foreach ($to_name as $key => $value) {
           
            $to_id = PermissionGroup::where('name', $value)->first()->id;
            $to_pgd = PermissionGroupDetail::where('permission_group_id',$to_id)->get()->pluck('permission_list_name')->toArray();
            // $diff = array_diff($from_pgd,$to_pgd);
            $diff = array_diff($from_pgd,$to_pgd);
            // $this->info(json_encode($to_pgd)."\n ");
            // $this->info(json_encode($diff)."\n ");
            $ordinal = count($to_pgd);

            foreach ($diff as $k => $v) {
                $ordinal++;
                PermissionGroupDetail::insert([
                    "ordinal"=>$ordinal,
                    "permission_group_id"=>$to_id,
                    "permission_list_name"=>$v,
                    "created_user"=>1,
                    "updated_user"=>1,
                ]);
            }
            
        }

        // Schema::enableForeignKeyConstraints();

        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
