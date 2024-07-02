<?php

namespace App\Console\Commands;

use App\Helpers\MyLog;
use App\Models\MySql\Employee;
use App\Models\MySql\IsUser;
use App\Models\MySql\PermissionGroup;
use App\Models\MySql\PermissionGroupDetail;
use App\Models\MySql\PermissionGroupUser;
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

        // $permissiongroups = PermissionGroup::get();
        // foreach ($permissiongroups as $k => $v) {
        //     $v->delete();
        // }

        // DB::statement("ALTER TABLE permission_group AUTO_INCREMENT = 1");

        foreach ($lists as $k => $v) {
            if(!PermissionGroup::where('name',$v)->first())
            PermissionGroup::insert([
                'name'=>$v,
                'created_user'=>1,
                'updated_user'=>1,
            ]);
        }
        $this->info("pass1\n ");


        $users=IsUser::get();
        foreach ($users as $k => $v) {
            $prgid = PermissionGroup::where('name',$v->hak_akses)->first();
            if($prgid && !PermissionGroupUser::where('permission_group_id',$prgid->id)->where('user_id',$v->id)->first()){
                $len = PermissionGroupUser::where('permission_group_id',$prgid->id)->get();
                PermissionGroupUser::insert([
                    "ordinal" => count($len)+1,
                    'permission_group_id'=>$prgid->id,
                    'user_id'=>$v->id,
                    'created_user'=>1,
                    'updated_user'=>1,
                ]);
            }
        }
        $this->info("pass2\n ");

        $lists = [

            ["permit"=>'user.views', "to"=>['SuperAdmin','ViewOnly']],
            ["permit"=>'user.view',"to"=>['SuperAdmin','ViewOnly']],
            ["permit"=>'user.create',"to"=>['SuperAdmin']],
            ["permit"=>'user.modify',"to"=>['SuperAdmin']],

            ["permit"=>'user_permission.views', "to"=>['SuperAdmin','ViewOnly']],
            ["permit"=>'user_permission.view',"to"=>['SuperAdmin','ViewOnly']],
            ["permit"=>'user_permission.create',"to"=>['SuperAdmin']],
            ["permit"=>'user_permission.modify',"to"=>['SuperAdmin']],

            ["permit"=>'employee.views', "to"=>['SuperAdmin','ViewOnly','Logistic']],
            ["permit"=>'employee.view',"to"=>['SuperAdmin','ViewOnly','Logistic']],
            ["permit"=>'employee.create',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'employee.modify',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'employee.remove',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'employee.val',"to"=>['SuperAdmin','Logistic']],

            ["permit"=>'ujalan.views',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport']],
            ["permit"=>'ujalan.view',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport']],
            ["permit"=>'ujalan.create',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'ujalan.modify',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'ujalan.remove',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'ujalan.val',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'ujalan.val1',"to"=>['SuperAdmin','PabrikTransport']],

            ["permit"=>'ujalan.detail.views',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport']],
            ["permit"=>'ujalan.detail.insert',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'ujalan.detail.modify',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'ujalan.detail.remove',"to"=>['SuperAdmin','Logistic']],
            
            ["permit"=>'ujalan.detail2.views',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport']],
            ["permit"=>'ujalan.detail2.insert',"to"=>['SuperAdmin','PabrikTransport']],
            ["permit"=>'ujalan.detail2.modify',"to"=>['SuperAdmin','PabrikTransport']],
            ["permit"=>'ujalan.detail2.remove',"to"=>['SuperAdmin','PabrikTransport']],
        ];

        // PermissionList::truncate();
        $this->info("pass3\n ");

        foreach ($lists as $k => $v) {
            $pid = PermissionList::where('name',$v['permit'])->first();

            if(!$pid)
            PermissionList::insert([
                'name'=>$v['permit']
            ]);


            $prgid = PermissionGroup::whereIn('name',$v['to'])->get()->pluck('id')->toArray();
            if(count($prgid) > 0){
                foreach ($prgid as $k1 => $v1) {
                    if(!PermissionGroupDetail::where('permission_group_id',$v1)->where('permission_list_name',$v['permit'])->first()){
                        PermissionGroupDetail::insert([
                            'ordinal'=>count(PermissionGroupDetail::where('permission_group_id',$v1)->get()) + 1,
                            'permission_list_name'=>$v['permit'],
                            'permission_group_id'=>$v1,
                            'created_user'=>1,
                            'updated_user'=>1,
                        ]);
                    }                    
                }
            }



        }

        $this->info("pass4\n ");

        
       
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
