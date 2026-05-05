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
    protected $signature = 'run_permit';

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

        // 'SUPERADMIN','ViewOnly','LOGISTIC_STAFF','Finance','Marketing','MIS','PabrikTransport','Accounting','PabrikMandor'
        // $lists = [
        //     'PRODUKSI'
        // ];

        // foreach ($lists as $k => $v) {
        //     if(!PermissionGroup::where('name',$v)->first()){
        //         $this->info("insert permission group".$v."\n ");
    
        //         PermissionGroup::insert([
        //             'name'=>$v,
        //             'created_user'=>1,
        //             'updated_user'=>1,
        //         ]);
        //     }
        // }
        // $this->info("pass1\n ");

        // $lists = [
        //     ["permit"=>'rpt_salary.views',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
        //     ["permit"=>'rpt_salary.view',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
        //     ["permit"=>'rpt_salary.create',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
        //     ["permit"=>'rpt_salary.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
        //     ["permit"=>'rpt_salary.val1',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
        // ];

        // foreach ($lists as $k => $v) {
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
        // $this->info("pass2\n ");



        $re_lists = [
            // tidak jadi ["permit"=>'employee.transfer_data',"to"=>['SUPERADMIN','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.timbang_info.views',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.timbang_info.view',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.timbang_info.create',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.timbang_info.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.timbang_info.val1',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
        ];

        foreach ($re_lists as $k => $v) {

            PermissionGroupDetail::where('permission_list_name',$v['permit'])->delete();

            $pid = PermissionList::where('name',$v['permit'])->first();
            if(!$pid){
                $this->info("insert permission list ".$v['permit']."\n ");
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
