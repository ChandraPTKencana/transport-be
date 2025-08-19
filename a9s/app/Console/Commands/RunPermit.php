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

        // 'SuperAdmin','ViewOnly','Logistic','Finance','Marketing','MIS','PabrikTransport','Accounting','PabrikMandor'
        // $lists = [
        //     'MANAGER_LOGISTIC'
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
        //     ["permit"=>'rpt_salary.views',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'rpt_salary.view',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'rpt_salary.create',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'rpt_salary.modify',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'rpt_salary.val1',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'rpt_salary.generate_detail',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'rpt_salary.detail.views',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'rpt_salary.preview_file',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],

        //     ["permit"=>'salary_paid.views',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_paid.view',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_paid.create',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_paid.modify',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     // ["permit"=>'salary_paid.remove',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_paid.val1',"to"=>['SuperAdmin','Logistic']],
        //     ["permit"=>'salary_paid.val2',"to"=>['SuperAdmin','SPVlogistik']],
        //     ["permit"=>'salary_paid.val3',"to"=>['SuperAdmin','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_paid.generate_detail',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_paid.preview_file',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_paid.detail.views',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],

        //     ["permit"=>'salary_bonus.views',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_bonus.view',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_bonus.create',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_bonus.modify',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_bonus.remove',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
        //     ["permit"=>'salary_bonus.val1',"to"=>['SuperAdmin','Logistic']],
        //     ["permit"=>'salary_bonus.val2',"to"=>['SuperAdmin','SPVlogistik','MANAGER_LOGISTIC']],

        //     ["permit"=>'salary_bonus.detail.views',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
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
            // ["permit"=>'trp_trx.absen.clear_valval1',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
            // ["permit"=>'standby_trx.detail.decide_paid',"to"=>['SuperAdmin','Logistic','SPVlogistik','MANAGER_LOGISTIC']],
            // ["permit"=>'user.remove',"to"=>['SUPERADMIN']],
            // ["permit"=>'extra_money_trx.generate_pv',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],

            // ["permit"=>'ujalan.unval',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            // ["permit"=>'ujalan.unval1',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            // ["permit"=>'standby_mst.unval',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            // ["permit"=>'standby_mst.unval1',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            // ["permit"=>'standby_trx.unval',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            // ["permit"=>'standby_trx.unval1',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            // ["permit"=>'standby_trx.unval2',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            // ["permit"=>'employee.unval',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            // ["permit"=>'trp_trx.ticket.unval_ticket',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            
            // ["permit"=>'trp_trx.unval1',"to"=>['SUPERADMIN','TRANSPORT_MANDOR']],
            // ["permit"=>'trp_trx.unval2',"to"=>['SUPERADMIN','KTU','WAKIL_KTU']],
            // ["permit"=>'trp_trx.unval3',"to"=>['SUPERADMIN','MARKETING']],
            // ["permit"=>'trp_trx.unval4',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            // ["permit"=>'trp_trx.unval5',"to"=>['SUPERADMIN','LOGISTIC_SPV']],
            // ["permit"=>'trp_trx.unval6',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            // ["permit"=>'employee.unremove',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            // ["permit"=>'report.distance.download_file',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF']],            
            // ["permit"=>'report.distance.views',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF']],
            // ["permit"=>'ujalan.download_file',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF','VIEW_ONLY']],            
            // ["permit"=>'standby_trx.download_file',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF','VIEW_ONLY']],            
            ["permit"=>'trp_trx_ticket.download_file',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF','VIEW_ONLY']],            

            // tidak jadi ["permit"=>'employee.transfer_data',"to"=>['SUPERADMIN','LOGISTIC_SPV','LOGISTIC_MANAGER']],

        ];

        foreach ($re_lists as $k => $v) {

            PermissionGroupDetail::where('permission_list_name',$v['permit'])->delete();

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
