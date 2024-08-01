<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\MySql\IsUser;
use App\Models\MySql\PermissionGroup;
use App\Models\MySql\PermissionGroupDetail;
use App\Models\MySql\PermissionGroupUser;
use App\Models\MySql\PermissionList;

use Illuminate\Support\Facades\Schema;
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
        Schema::disableForeignKeyConstraints();

        // 'SuperAdmin','ViewOnly','Logistic','Finance','Marketing','MIS','PabrikTransport','Accounting','PabrikMandor'
        $lists = [
            'HR'
        ];

        foreach ($lists as $k => $v) {
            if(!PermissionGroup::where('name',$v)->first()){
                $this->info("insert permission group".$v."\n ");
    
                PermissionGroup::insert([
                    'name'=>$v,
                    'created_user'=>1,
                    'updated_user'=>1,
                ]);
            }
        }
        $this->info("pass1\n ");

        $lists = [
            ["permit"=>'salary_paid.views',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','PabrikMandor','SPVLogistik','HR']],
            ["permit"=>'salary_paid.view',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','PabrikMandor','SPVLogistik','HR']],
            ["permit"=>'salary_paid.create',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','SPVLogistik']],
            ["permit"=>'salary_paid.modify',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','SPVLogistik']],
            // ["permit"=>'salary_paid.remove',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','SPVLogistik']],
            ["permit"=>'salary_paid.val1',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','SPVLogistik']],
            ["permit"=>'salary_paid.val2',"to"=>['SuperAdmin','SPVLogistik']],
            ["permit"=>'salary_paid.val3',"to"=>['SuperAdmin','HR']],

            ["permit"=>'salary_paid.generate_detail',"to"=>['SuperAdmin','PabrikTransport',"PabrikMandor",'SPVLogistik']],
            ["permit"=>'salary_paid.preview_file',"to"=>['SuperAdmin','HR']],

            ["permit"=>'salary_paid.detail.views',"to"=>['SuperAdmin','PabrikTransport',"PabrikMandor",'SPVLogistik','HR']],

            ["permit"=>'salary_bonus.views',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','PabrikMandor','SPVLogistik','HR']],
            ["permit"=>'salary_bonus.view',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','PabrikMandor','SPVLogistik','HR']],
            ["permit"=>'salary_bonus.create',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'salary_bonus.modify',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'salary_bonus.remove',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'salary_bonus.val1',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'salary_bonus.val2',"to"=>['SuperAdmin','SPVLogistik']],

            ["permit"=>'salary_bonus.detail.views',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','SPVLogistik']],

            ["permit"=>'trp_trx.absen.views',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','Logistic','SPVLogistik']],
            ["permit"=>'trp_trx.absen.view',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','Logistic','SPVLogistik']],
            ["permit"=>'trp_trx.absen.modify',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','Logistic','SPVLogistik']],
            ["permit"=>'trp_trx.absen.val1',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'trp_trx.absen.val2',"to"=>['SuperAdmin','Logistic','SPVLogistik']],
        
            ["permit"=>'trp_trx.absen.val',"to"=>['SuperAdmin','PabrikMandor','PabrikTransport']],
            ["permit"=>'trp_trx.absen.val1',"to"=>['SuperAdmin','Logistik','SPVLogistic']],

        ];
        $this->info("pass3\n ");

        foreach ($lists as $k => $v) {
            $pid = PermissionList::where('name',$v['permit'])->first();
            if(!$pid){
                $this->info("insert permission list".$v['permit']."\n ");
                PermissionList::insert([
                    'name'=>$v['permit']
                ]);
            }

            $prgid = PermissionGroup::whereIn('name',$v['to'])->get()->pluck('id')->toArray();
            if(count($prgid) > 0){
                foreach ($prgid as $k1 => $v1) {
                    if(!PermissionGroupDetail::where('permission_group_id',$v1)->where('permission_list_name',$v['permit'])->first()){
                        $this->info("insert PermissionGroupDetail".$v['permit']." - ".$v1."\n ");
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
        Schema::enableForeignKeyConstraints();
        $this->info("pass4\n ");

        
       
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
