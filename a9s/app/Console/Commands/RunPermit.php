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
            'MANAGER_LOGISTIC'
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
            ["permit"=>'extra_money_trx.transfer.views',"to"=>['SuperAdmin','ViewOnly','Logistic','SPVLogistik','MANAGER_LOGISTIC']],
            ["permit"=>'extra_money_trx.transfer.view',"to"=>['SuperAdmin','ViewOnly','Logistic','SPVLogistik','MANAGER_LOGISTIC']],
            ["permit"=>'extra_money_trx.transfer.do_transfer',"to"=>['SuperAdmin','Logistic','SPVLogistik','MANAGER_LOGISTIC']],
            ["permit"=>'extra_money_trx.val4',"to"=>['SuperAdmin','Logistic','SPVLogistik','MANAGER_LOGISTIC']],
            ["permit"=>'trp_trx.views',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'trp_trx.view',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'trp_trx.val6',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_paid.views',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_paid.view',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_paid.create',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_paid.modify',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_paid.val1',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_paid.val2',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_paid.generate_detail',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_paid.detail.views',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_bonus.views',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_bonus.view',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_bonus.val2',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'salary_bonus.detail.views',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'trp_trx.absen.views',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'trp_trx.absen.view',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'trp_trx.absen.modify',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'extra_money.views',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'extra_money.view',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'extra_money.val2',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'extra_money_trx.views',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'extra_money_trx.view',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'extra_money_trx.val6',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'trp_trx.transfer.views',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'trp_trx.transfer.view',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'trp_trx.transfer.do_transfer',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'trp_trx.absen.val2',"to"=>['MANAGER_LOGISTIC']],
            ["permit"=>'potongan_mst.val1',"to"=>['MANAGER_LOGISTIC']],
        ];

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
        $this->info("pass2\n ");



        // $re_lists = [
        //     ["permit"=>'extra_money.transfer.views',"to"=>[]],
        //     ["permit"=>'extra_money.transfer.view',"to"=>[]],
        //     ["permit"=>'extra_money.transfer.do_transfer',"to"=>[]],
        //     ["permit"=>'extra_money.val4',"to"=>[]],
        // ];

        // foreach ($re_lists as $k => $v) {

        //     PermissionGroupDetail::where('permission_list_name',$v['permit'])->delete();

        //     $pid = PermissionList::where('name',$v['permit'])->first();
        //     if(!$pid){
        //         $this->info("insert permission list".$v['permit']."\n ");
        //         PermissionList::insert([
        //             'name'=>$v['permit']
        //         ]);
        //     }

        //     $prgid = PermissionGroup::whereIn('name',$v['to'])->get()->pluck('id')->toArray();
        //     if(count($prgid) > 0){
        //         foreach ($prgid as $k1 => $v1) {
        //             if(!PermissionGroupDetail::where('permission_group_id',$v1)->where('permission_list_name',$v['permit'])->first()){
        //                 $this->info("insert PermissionGroupDetail".$v['permit']." - ".$v1."\n ");
        //                 PermissionGroupDetail::insert([
        //                     'ordinal'=>count(PermissionGroupDetail::where('permission_group_id',$v1)->get()) + 1,
        //                     'permission_list_name'=>$v['permit'],
        //                     'permission_group_id'=>$v1,
        //                     'created_user'=>1,
        //                     'updated_user'=>1,
        //                 ]);
        //             }                    
        //         }
        //     }



        // }



        // $del_lists = [
        //     ["permit"=>'extra_money.transfer.views',"to"=>[]],
        //     ["permit"=>'extra_money.transfer.view',"to"=>[]],
        //     ["permit"=>'extra_money.transfer.do_transfer',"to"=>[]],
        //     ["permit"=>'extra_money.val4',"to"=>[]],
        // ];

        // foreach ($del_lists as $k => $v) {
        //     PermissionGroupDetail::where('permission_list_name',$v['permit'])->delete();
        //     PermissionList::where('name',$v['permit'])->delete();
        // }
        Schema::enableForeignKeyConstraints();
        $this->info("pass3\n ");

        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
