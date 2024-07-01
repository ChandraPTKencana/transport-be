<?php

namespace App\Console\Commands;

use App\Helpers\MyLog;
use App\Models\MySql\Employee;
use App\Models\MySql\PermissionGroup;
use App\Models\MySql\PermissionList;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Validator;
use App\Models\Stok\Transaction;
use DB;
class RunPermit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Permission List';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Start\n ");
    
        $lists = [
            'ujalan.view',
            'ujalan.create',
            'ujalan.modify',
            'ujalan.remove',
            'ujalan.print',
            // 'ujalan.export',
            'ujalan.val',
            'ujalan.val1',

            'ujalan.detail.view',
            'ujalan.detail.create',
            'ujalan.detail.modify',
            'ujalan.detail.remove',

            'ujalan.detail2.view',
            'ujalan.detail2.create',
            'ujalan.detail2.modify',
            'ujalan.detail2.remove',
        ];

        PermissionList::truncate();

        foreach ($lists as $k => $v) {
            PermissionList::insert([
                'name'=>$v
            ]);
        }


        $lists = [
            'SuperAdmin',
            'ViewOnly',
            'Logistic',
            'Finance',
            'Marketing',
            'MIS',
            'PabrikTransport',
            'Accounting',
            'PabrikMandor'
        ];

        $permissiongroups = PermissionGroup::get();
        foreach ($permissiongroups as $k => $v) {
            $v->delete();
        }

        DB::statement("ALTER TABLE permission_group AUTO_INCREMENT = 1");

        foreach ($lists as $k => $v) {
            PermissionGroup::insert([
                'name'=>$v,
                'created_user'=>1,
                'updated_user'=>1,
            ]);
        }
       
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
