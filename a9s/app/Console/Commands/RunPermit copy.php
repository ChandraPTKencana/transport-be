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
    protected $signature = 'runx_2';

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
            'SuperAdmin',
            'ViewOnly',
            'Logistic',
            'Finance',
            'Marketing',
            'MIS',
            'PabrikTransport',
            'Accounting',
            'PabrikMandor',
            'WakilKTU',
            'KTU',
            'SPVLogistik',
            'HR'
        ];

        // $permissiongroups = PermissionGroup::get();
        // foreach ($permissiongroups as $k => $v) {
        //     $v->delete();
        // }
        // DB::statement("ALTER TABLE permission_group AUTO_INCREMENT = 1");
        PermissionGroupDetail::truncate();
        PermissionGroupUser::truncate();
        // PermissionGroup::truncate();
        PermissionList::truncate();

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

            ["permit"=>'permission_list.views', "to"=>['SuperAdmin','ViewOnly']],

            ["permit"=>'permission_user.views', "to"=>['SuperAdmin','ViewOnly']],
            ["permit"=>'permission_user.insert',"to"=>['SuperAdmin']],
            // ["permit"=>'permission_user.modify',"to"=>['SuperAdmin']],
            ["permit"=>'permission_user.remove',"to"=>['SuperAdmin','ViewOnly']],

            ["permit"=>'permission_group.views', "to"=>['SuperAdmin','ViewOnly']],
            ["permit"=>'permission_group.view',"to"=>['SuperAdmin','ViewOnly']],
            ["permit"=>'permission_group.create',"to"=>['SuperAdmin']],
            ["permit"=>'permission_group.modify',"to"=>['SuperAdmin']],

            ["permit"=>'permission_group_detail.views', "to"=>['SuperAdmin','ViewOnly']],
            ["permit"=>'permission_group_detail.insert',"to"=>['SuperAdmin']],
            // ["permit"=>'permission_group_detail.modify',"to"=>['SuperAdmin']],
            ["permit"=>'permission_group_detail.remove',"to"=>['SuperAdmin','ViewOnly']],

            ["permit"=>'permission_group_user.views', "to"=>['SuperAdmin','ViewOnly']],
            ["permit"=>'permission_group_user.insert',"to"=>['SuperAdmin']],
            // ["permit"=>'permission_group_user.modify',"to"=>['SuperAdmin']],
            ["permit"=>'permission_group_user.remove',"to"=>['SuperAdmin','ViewOnly']],

            ["permit"=>'employee.views', "to"=>['SuperAdmin','ViewOnly','Logistic']],
            ["permit"=>'employee.view',"to"=>['SuperAdmin','ViewOnly','Logistic']],
            ["permit"=>'employee.create',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'employee.modify',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'employee.remove',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'employee.val',"to"=>['SuperAdmin','Logistic']],

            ["permit"=>'vehicle.views', "to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport']],
            ["permit"=>'vehicle.view',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport']],
            ["permit"=>'vehicle.create',"to"=>['SuperAdmin','Logistic','PabrikTransport']],
            ["permit"=>'vehicle.modify',"to"=>['SuperAdmin','Logistic','PabrikTransport']],
            ["permit"=>'vehicle.remove',"to"=>['SuperAdmin','Logistic']],

            ["permit"=>'report.ast_n_driver.views', "to"=>['SuperAdmin','ViewOnly','Logistic']],
            ["permit"=>'report.ast_n_driver.download_file', "to"=>['SuperAdmin','ViewOnly','Logistic']],

            ["permit"=>'report.ramp.views', "to"=>['SuperAdmin','ViewOnly','Logistic']],
            ["permit"=>'report.ramp.download_file', "to"=>['SuperAdmin','ViewOnly','Logistic']],

            ["permit"=>'standby_mst.views',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport']],
            ["permit"=>'standby_mst.view',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport']],
            ["permit"=>'standby_mst.create',"to"=>['SuperAdmin','PabrikTransport']],
            ["permit"=>'standby_mst.modify',"to"=>['SuperAdmin','PabrikTransport']],
            ["permit"=>'standby_mst.remove',"to"=>['SuperAdmin','PabrikTransport']],
            ["permit"=>'standby_mst.val',"to"=>['SuperAdmin','PabrikTransport']],
            ["permit"=>'standby_mst.val1',"to"=>['SuperAdmin','Logistic']],

            ["permit"=>'standby_mst.detail.views',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport']],
            ["permit"=>'standby_mst.detail.insert',"to"=>['SuperAdmin','PabrikTransport']],
            ["permit"=>'standby_mst.detail.modify',"to"=>['SuperAdmin','PabrikTransport']],
            ["permit"=>'standby_mst.detail.remove',"to"=>['SuperAdmin','PabrikTransport']],

            ["permit"=>'standby_trx.views',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.view',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.create',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.modify',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.remove',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.val',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.val1',"to"=>['SuperAdmin','PabrikMandor']],
            ["permit"=>'standby_trx.val2',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'standby_trx.request_remove',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.approve_request_remove',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'standby_trx.preview_file',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.generate_pvr',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.get_pv',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],


            ["permit"=>'standby_trx.detail.views',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.detail.insert',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.detail.modify',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'standby_trx.detail.remove',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],



            ["permit"=>'salary_paid.views',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','SPVLogistik','HR']],
            ["permit"=>'salary_paid.view',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','SPVLogistik','HR']],
            ["permit"=>'salary_paid.create',"to"=>['SuperAdmin','PabrikTransport','SPVLogistik']],
            ["permit"=>'salary_paid.modify',"to"=>['SuperAdmin','PabrikTransport','SPVLogistik']],
            // ["permit"=>'salary_paid.remove',"to"=>['SuperAdmin','PabrikTransport','SPVLogistik']],
            ["permit"=>'salary_paid.val1',"to"=>['SuperAdmin','PabrikTransport','SPVLogistik']],
            ["permit"=>'salary_paid.val2',"to"=>['SuperAdmin','SPVLogistik']],
            ["permit"=>'salary_paid.val3',"to"=>['SuperAdmin','HR']],

            ["permit"=>'salary_paid.generate_detail',"to"=>['SuperAdmin','PabrikTransport','SPVLogistik']],
            ["permit"=>'salary_paid.preview_file',"to"=>['SuperAdmin','HR']],

            ["permit"=>'salary_paid.detail.views',"to"=>['SuperAdmin','PabrikTransport','SPVLogistik','HR']],

            ["permit"=>'ujalan.views',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport']],
            ["permit"=>'ujalan.view',"to"=>['SuperAdmin','ViewOnly','Logistic','PabrikTransport','Finance']],
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
            ["permit"=>'ujalan.detail2.insert',"to"=>['SuperAdmin','Logistic','PabrikTransport']],
            ["permit"=>'ujalan.detail2.modify',"to"=>['SuperAdmin','Logistic','PabrikTransport']],
            ["permit"=>'ujalan.detail2.remove',"to"=>['SuperAdmin','Logistic','PabrikTransport']],

            ["permit"=>'trp_trx.views',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','PabrikMandor','SPVLogistik','WakilKTU','KTU','Marketing']],
            ["permit"=>'trp_trx.view',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','PabrikMandor','SPVLogistik','WakilKTU','KTU','Marketing']],
            ["permit"=>'trp_trx.create',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'trp_trx.modify',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'trp_trx.remove',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'trp_trx.val',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'trp_trx.val1',"to"=>['SuperAdmin','PabrikMandor']],
            ["permit"=>'trp_trx.val2',"to"=>['SuperAdmin','WakilKTU','KTU']],
            ["permit"=>'trp_trx.val3',"to"=>['SuperAdmin','Marketing']],
            ["permit"=>'trp_trx.val4',"to"=>['SuperAdmin','Logistik']],
            ["permit"=>'trp_trx.val5',"to"=>['SuperAdmin','SPVLogistik']],
            ["permit"=>'trp_trx.request_remove',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'trp_trx.approve_request_remove',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'trp_trx.preview_file',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'trp_trx.report.views',"to"=>['SuperAdmin','ViewOnly','Logistic','Finance','Marketing','MIS','Accounting']],
            ["permit"=>'trp_trx.download_file',"to"=>['SuperAdmin','ViewOnly','Logistic','Finance','Marketing','MIS','Accounting']],
            ["permit"=>'trp_trx.generate_pvr',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'trp_trx.get_pv',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'trp_trx.get_ticket',"to"=>['SuperAdmin','PabrikTransport','Logistic']],

            ["permit"=>'trp_trx.ticket.views',"to"=>['SuperAdmin','Logistic','PabrikTransport']],
            ["permit"=>'trp_trx.ticket.view',"to"=>['SuperAdmin','Logistic','PabrikTransport']],
            ["permit"=>'trp_trx.ticket.modify',"to"=>['SuperAdmin','Logistic','PabrikTransport']],
            ["permit"=>'trp_trx.ticket.val_ticket',"to"=>['SuperAdmin','Logistic']],

            ["permit"=>'trp_trx.ritase.views',"to"=>['SuperAdmin','PabrikMandor']],
            ["permit"=>'trp_trx.ritase.view',"to"=>['SuperAdmin','PabrikMandor']],

            ["permit"=>'trp_trx.absen.remove',"to"=>['SuperAdmin','PabrikMandor','PabrikTransport']],

            ["permit"=>'trp_trx.transfer.views',"to"=>['SuperAdmin','ViewOnly','SPVLogistik']],
            ["permit"=>'trp_trx.transfer.view',"to"=>['SuperAdmin','ViewOnly','SPVLogistik']],
            ["permit"=>'trp_trx.transfer.do_transfer',"to"=>['SuperAdmin','SPVLogistik']],


            ["permit"=>'srv.cost_center.views',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'srv.palm_ticket.views',"to"=>['SuperAdmin','Logistic','PabrikTransport','PabrikMandor']],


            ["permit"=>'potongan_mst.views',"to"=>['SuperAdmin','ViewOnly','Logistic']],
            ["permit"=>'potongan_mst.view',"to"=>['SuperAdmin','ViewOnly','Logistic']],
            ["permit"=>'potongan_mst.create',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'potongan_mst.modify',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'potongan_mst.remove',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'potongan_mst.val',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'potongan_mst.val1',"to"=>['SuperAdmin','Logistic']],

            
            ["permit"=>'potongan_trx.views',"to"=>['SuperAdmin','ViewOnly','Logistic']],
            ["permit"=>'potongan_trx.view',"to"=>['SuperAdmin','ViewOnly','Logistic']],
            ["permit"=>'potongan_trx.create',"to"=>['SuperAdmin','Logistic','PabrikTransport']],
            ["permit"=>'potongan_trx.modify',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'potongan_trx.remove',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'potongan_trx.val',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'potongan_trx.val1',"to"=>['SuperAdmin','Logistic']],

            // stage 2

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

            ["permit"=>'trp_trx.views',"to"=>['Logistic']],
            ["permit"=>'trp_trx.view',"to"=>['Logistic']],
            
            ["permit"=>'trp_trx.absen.views',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','Logistic','SPVLogistik']],
            ["permit"=>'trp_trx.absen.view',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','Logistic','SPVLogistik']],
            ["permit"=>'trp_trx.absen.modify',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor','Logistic','SPVLogistik']],
            ["permit"=>'trp_trx.absen.val1',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'trp_trx.absen.val2',"to"=>['SuperAdmin','Logistic','SPVLogistik']],
        
            ["permit"=>'trp_trx.absen.val',"to"=>['SuperAdmin','PabrikMandor','PabrikTransport']],
            ["permit"=>'trp_trx.absen.val1',"to"=>['SuperAdmin','Logistic','SPVLogistik']],

            ["permit"=>'extra_money.views',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','PabrikMandor','SPVLogistik','Logistic']],
            ["permit"=>'extra_money.view',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','PabrikMandor','SPVLogistik','Logistic']],
            ["permit"=>'extra_money.create',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money.modify',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money.remove',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money.val1',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money.val2',"to"=>['SuperAdmin','Logistic','SPVLogistik']],

            ["permit"=>'extra_money_trx.views',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','PabrikMandor','WKTU','KTU','SPVLogistik','Logistic','Marketing']],
            ["permit"=>'extra_money_trx.view',"to"=>['SuperAdmin','ViewOnly','PabrikTransport','PabrikMandor','WKTU','KTU','SPVLogistik','Logistic','Marketing']],
            ["permit"=>'extra_money_trx.create',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money_trx.modify',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money_trx.remove',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money_trx.val1',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money_trx.val2',"to"=>['SuperAdmin','PabrikMandor']],
            ["permit"=>'extra_money_trx.val3',"to"=>['SuperAdmin','WakilKTU','KTU']],
            ["permit"=>'extra_money_trx.val4',"to"=>['SuperAdmin','Marketing']],
            ["permit"=>'extra_money_trx.val5',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'extra_money_trx.val6',"to"=>['SuperAdmin','SPVLogistik']],
            ["permit"=>'extra_money_trx.request_remove',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money_trx.approve_request_remove',"to"=>['SuperAdmin','Logistic']],
            ["permit"=>'extra_money_trx.preview_file',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money_trx.generate_pvr',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],
            ["permit"=>'extra_money_trx.get_pv',"to"=>['SuperAdmin','PabrikTransport','PabrikMandor']],


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
        Schema::enableForeignKeyConstraints();
        $this->info("pass4\n ");

        
       
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
