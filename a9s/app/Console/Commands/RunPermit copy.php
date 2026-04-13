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

        // 'SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','Finance','Marketing','MIS','TRANSPORT_KASIR','Accounting','TRANSPORT_MANDOR'

        $lists = [
            'SUPERADMIN',
            'VIEW_ONLY',
            'LOGISTIC_STAFF',
            'Finance',
            'Marketing',
            'MIS',
            'TRANSPORT_KASIR',
            'Accounting',
            'TRANSPORT_MANDOR',
            'WAKIL_KTU',
            'KTU',
            'LOGISTIC_SPV',
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

            ["permit"=>'user.views', "to"=>['SUPERADMIN','VIEW_ONLY']],
            ["permit"=>'user.view',"to"=>['SUPERADMIN','VIEW_ONLY']],
            ["permit"=>'user.create',"to"=>['SUPERADMIN']],
            ["permit"=>'user.modify',"to"=>['SUPERADMIN']],

            ["permit"=>'permission_list.views', "to"=>['SUPERADMIN','VIEW_ONLY']],

            ["permit"=>'permission_user.views', "to"=>['SUPERADMIN','VIEW_ONLY']],
            ["permit"=>'permission_user.insert',"to"=>['SUPERADMIN']],
            // ["permit"=>'permission_user.modify',"to"=>['SUPERADMIN']],
            ["permit"=>'permission_user.remove',"to"=>['SUPERADMIN','VIEW_ONLY']],

            ["permit"=>'permission_group.views', "to"=>['SUPERADMIN','VIEW_ONLY']],
            ["permit"=>'permission_group.view',"to"=>['SUPERADMIN','VIEW_ONLY']],
            ["permit"=>'permission_group.create',"to"=>['SUPERADMIN']],
            ["permit"=>'permission_group.modify',"to"=>['SUPERADMIN']],

            ["permit"=>'permission_group_detail.views', "to"=>['SUPERADMIN','VIEW_ONLY']],
            ["permit"=>'permission_group_detail.insert',"to"=>['SUPERADMIN']],
            // ["permit"=>'permission_group_detail.modify',"to"=>['SUPERADMIN']],
            ["permit"=>'permission_group_detail.remove',"to"=>['SUPERADMIN','VIEW_ONLY']],

            ["permit"=>'permission_group_user.views', "to"=>['SUPERADMIN','VIEW_ONLY']],
            ["permit"=>'permission_group_user.insert',"to"=>['SUPERADMIN']],
            // ["permit"=>'permission_group_user.modify',"to"=>['SUPERADMIN']],
            ["permit"=>'permission_group_user.remove',"to"=>['SUPERADMIN','VIEW_ONLY']],

            ["permit"=>'employee.views', "to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF']],
            ["permit"=>'employee.view',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF']],
            ["permit"=>'employee.create',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'employee.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'employee.remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'employee.val',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],

            ["permit"=>'vehicle.views', "to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'vehicle.view',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'vehicle.create',"to"=>['SUPERADMIN','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'vehicle.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'vehicle.remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],

            ["permit"=>'report.ast_n_driver.views', "to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF']],
            ["permit"=>'report.ast_n_driver.download_file', "to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF']],

            ["permit"=>'report.ramp.views', "to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF']],
            ["permit"=>'report.ramp.download_file', "to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF']],

            ["permit"=>'standby_mst.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'standby_mst.view',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'standby_mst.create',"to"=>['SUPERADMIN','TRANSPORT_KASIR']],
            ["permit"=>'standby_mst.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR']],
            ["permit"=>'standby_mst.remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR']],
            ["permit"=>'standby_mst.val',"to"=>['SUPERADMIN','TRANSPORT_KASIR']],
            ["permit"=>'standby_mst.val1',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],

            ["permit"=>'standby_mst.detail.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'standby_mst.detail.insert',"to"=>['SUPERADMIN','TRANSPORT_KASIR']],
            ["permit"=>'standby_mst.detail.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR']],
            ["permit"=>'standby_mst.detail.remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR']],

            ["permit"=>'standby_trx.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.view',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.create',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.val',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.val1',"to"=>['SUPERADMIN','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.val2',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'standby_trx.request_remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.approve_request_remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'standby_trx.preview_file',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.generate_pvr',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.get_pv',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],


            ["permit"=>'standby_trx.detail.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.detail.insert',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.detail.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'standby_trx.detail.remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],



            ["permit"=>'salary_paid.views',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','LOGISTIC_SPV','HR']],
            ["permit"=>'salary_paid.view',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','LOGISTIC_SPV','HR']],
            ["permit"=>'salary_paid.create',"to"=>['SUPERADMIN','TRANSPORT_KASIR','LOGISTIC_SPV']],
            ["permit"=>'salary_paid.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR','LOGISTIC_SPV']],
            // ["permit"=>'salary_paid.remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','LOGISTIC_SPV']],
            ["permit"=>'salary_paid.val1',"to"=>['SUPERADMIN','TRANSPORT_KASIR','LOGISTIC_SPV']],
            ["permit"=>'salary_paid.val2',"to"=>['SUPERADMIN','LOGISTIC_SPV']],
            ["permit"=>'salary_paid.val3',"to"=>['SUPERADMIN','HR']],

            ["permit"=>'salary_paid.generate_detail',"to"=>['SUPERADMIN','TRANSPORT_KASIR','LOGISTIC_SPV']],
            ["permit"=>'salary_paid.preview_file',"to"=>['SUPERADMIN','HR']],

            ["permit"=>'salary_paid.detail.views',"to"=>['SUPERADMIN','TRANSPORT_KASIR','LOGISTIC_SPV','HR']],

            ["permit"=>'ujalan.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'ujalan.view',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR','Finance']],
            ["permit"=>'ujalan.create',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'ujalan.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'ujalan.remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'ujalan.val',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'ujalan.val1',"to"=>['SUPERADMIN','TRANSPORT_KASIR']],

            ["permit"=>'ujalan.detail.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'ujalan.detail.insert',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'ujalan.detail.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'ujalan.detail.remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            
            ["permit"=>'ujalan.detail2.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'ujalan.detail2.insert',"to"=>['SUPERADMIN','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'ujalan.detail2.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'ujalan.detail2.remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF','TRANSPORT_KASIR']],

            ["permit"=>'trp_trx.views',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV','WAKIL_KTU','KTU','Marketing']],
            ["permit"=>'trp_trx.view',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV','WAKIL_KTU','KTU','Marketing']],
            ["permit"=>'trp_trx.create',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.val',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.val1',"to"=>['SUPERADMIN','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.val2',"to"=>['SUPERADMIN','WAKIL_KTU','KTU']],
            ["permit"=>'trp_trx.val3',"to"=>['SUPERADMIN','Marketing']],
            ["permit"=>'trp_trx.val4',"to"=>['SUPERADMIN','Logistik']],
            ["permit"=>'trp_trx.val5',"to"=>['SUPERADMIN','LOGISTIC_SPV']],
            ["permit"=>'trp_trx.request_remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.approve_request_remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'trp_trx.preview_file',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.report.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','Finance','Marketing','MIS','Accounting']],
            ["permit"=>'trp_trx.download_file',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','Finance','Marketing','MIS','Accounting']],
            ["permit"=>'trp_trx.generate_pvr',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.get_pv',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.get_ticket',"to"=>['SUPERADMIN','TRANSPORT_KASIR','LOGISTIC_STAFF']],

            ["permit"=>'trp_trx.ticket.views',"to"=>['SUPERADMIN','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'trp_trx.ticket.view',"to"=>['SUPERADMIN','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'trp_trx.ticket.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'trp_trx.ticket.val_ticket',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],

            ["permit"=>'trp_trx.ritase.views',"to"=>['SUPERADMIN','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.ritase.view',"to"=>['SUPERADMIN','TRANSPORT_MANDOR']],

            ["permit"=>'trp_trx.absen.remove',"to"=>['SUPERADMIN','TRANSPORT_MANDOR','TRANSPORT_KASIR']],

            ["permit"=>'trp_trx.transfer.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_SPV']],
            ["permit"=>'trp_trx.transfer.view',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_SPV']],
            ["permit"=>'trp_trx.transfer.do_transfer',"to"=>['SUPERADMIN','LOGISTIC_SPV']],


            ["permit"=>'srv.cost_center.views',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'srv.palm_ticket.views',"to"=>['SUPERADMIN','LOGISTIC_STAFF','TRANSPORT_KASIR','TRANSPORT_MANDOR']],


            ["permit"=>'potongan_mst.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF']],
            ["permit"=>'potongan_mst.view',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF']],
            ["permit"=>'potongan_mst.create',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'potongan_mst.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'potongan_mst.remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'potongan_mst.val',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'potongan_mst.val1',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],

            
            ["permit"=>'potongan_trx.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF']],
            ["permit"=>'potongan_trx.view',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF']],
            ["permit"=>'potongan_trx.create',"to"=>['SUPERADMIN','LOGISTIC_STAFF','TRANSPORT_KASIR']],
            ["permit"=>'potongan_trx.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'potongan_trx.remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'potongan_trx.val',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'potongan_trx.val1',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],

            // stage 2

            ["permit"=>'salary_paid.views',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV','HR']],
            ["permit"=>'salary_paid.view',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV','HR']],
            ["permit"=>'salary_paid.create',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV']],
            ["permit"=>'salary_paid.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV']],
            // ["permit"=>'salary_paid.remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV']],
            ["permit"=>'salary_paid.val1',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV']],
            ["permit"=>'salary_paid.val2',"to"=>['SUPERADMIN','LOGISTIC_SPV']],
            ["permit"=>'salary_paid.val3',"to"=>['SUPERADMIN','HR']],

            ["permit"=>'salary_paid.generate_detail',"to"=>['SUPERADMIN','TRANSPORT_KASIR',"TRANSPORT_MANDOR",'LOGISTIC_SPV']],
            ["permit"=>'salary_paid.preview_file',"to"=>['SUPERADMIN','HR']],

            ["permit"=>'salary_paid.detail.views',"to"=>['SUPERADMIN','TRANSPORT_KASIR',"TRANSPORT_MANDOR",'LOGISTIC_SPV','HR']],

            ["permit"=>'salary_bonus.views',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV','HR']],
            ["permit"=>'salary_bonus.view',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV','HR']],
            ["permit"=>'salary_bonus.create',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'salary_bonus.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'salary_bonus.remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'salary_bonus.val1',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'salary_bonus.val2',"to"=>['SUPERADMIN','LOGISTIC_SPV']],

            ["permit"=>'salary_bonus.detail.views',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV']],

            ["permit"=>'trp_trx.views',"to"=>['LOGISTIC_STAFF']],
            ["permit"=>'trp_trx.view',"to"=>['LOGISTIC_STAFF']],
            
            ["permit"=>'trp_trx.absen.views',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_STAFF','LOGISTIC_SPV']],
            ["permit"=>'trp_trx.absen.view',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_STAFF','LOGISTIC_SPV']],
            ["permit"=>'trp_trx.absen.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_STAFF','LOGISTIC_SPV']],
            ["permit"=>'trp_trx.absen.val1',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.absen.val2',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV']],
        
            ["permit"=>'trp_trx.absen.val',"to"=>['SUPERADMIN','TRANSPORT_MANDOR','TRANSPORT_KASIR']],
            ["permit"=>'trp_trx.absen.val1',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV']],

            ["permit"=>'extra_money.views',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV','LOGISTIC_STAFF']],
            ["permit"=>'extra_money.view',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','TRANSPORT_MANDOR','LOGISTIC_SPV','LOGISTIC_STAFF']],
            ["permit"=>'extra_money.create',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money.remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money.val1',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money.val2',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV']],

            ["permit"=>'extra_money_trx.views',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','TRANSPORT_MANDOR','WAKIL_KTU','KTU','LOGISTIC_SPV','LOGISTIC_STAFF','Marketing']],
            ["permit"=>'extra_money_trx.view',"to"=>['SUPERADMIN','VIEW_ONLY','TRANSPORT_KASIR','TRANSPORT_MANDOR','WAKIL_KTU','KTU','LOGISTIC_SPV','LOGISTIC_STAFF','Marketing']],
            ["permit"=>'extra_money_trx.create',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money_trx.modify',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money_trx.remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money_trx.val1',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money_trx.val2',"to"=>['SUPERADMIN','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money_trx.val3',"to"=>['SUPERADMIN','WAKIL_KTU','KTU']],
            ["permit"=>'extra_money_trx.val4',"to"=>['SUPERADMIN','Marketing']],
            ["permit"=>'extra_money_trx.val5',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'extra_money_trx.val6',"to"=>['SUPERADMIN','LOGISTIC_SPV']],
            ["permit"=>'extra_money_trx.request_remove',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money_trx.approve_request_remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'extra_money_trx.preview_file',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money_trx.generate_pvr',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money_trx.get_pv',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],


            ["permit"=>'extra_money_trx.transfer.views',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'extra_money_trx.transfer.view',"to"=>['SUPERADMIN','VIEW_ONLY','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'extra_money_trx.transfer.do_transfer',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'extra_money_trx.val4',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.views',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.view',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.val6',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.views',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.view',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.create',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.modify',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.val1',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.val2',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.generate_detail',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.detail.views',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_bonus.views',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_bonus.view',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_bonus.val2',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'salary_bonus.detail.views',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.absen.views',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.absen.view',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.absen.modify',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'extra_money.views',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'extra_money.view',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'extra_money.val2',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'extra_money_trx.views',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'extra_money_trx.view',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'extra_money_trx.val6',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.transfer.views',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.transfer.view',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.transfer.do_transfer',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.absen.val2',"to"=>['LOGISTIC_MANAGER']],
            ["permit"=>'potongan_mst.val1',"to"=>['LOGISTIC_MANAGER']],

            ["permit"=>'rpt_salary.views',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'rpt_salary.view',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'rpt_salary.create',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'rpt_salary.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'rpt_salary.val1',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'rpt_salary.generate_detail',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'rpt_salary.detail.views',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'rpt_salary.preview_file',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],

            ["permit"=>'salary_paid.views',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.view',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.create',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            // ["permit"=>'salary_paid.remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.val1',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'salary_paid.val2',"to"=>['SUPERADMIN','LOGISTIC_SPV']],
            ["permit"=>'salary_paid.val3',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.generate_detail',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.preview_file',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_paid.detail.views',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],

            ["permit"=>'salary_bonus.views',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_bonus.view',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_bonus.create',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_bonus.modify',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_bonus.remove',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'salary_bonus.val1',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'salary_bonus.val2',"to"=>['SUPERADMIN','LOGISTIC_SPV','LOGISTIC_MANAGER']],

            ["permit"=>'salary_bonus.detail.views',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
        
            ["permit"=>'trp_trx.absen.clear_valval1',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'standby_trx.detail.decide_paid',"to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'user.remove',"to"=>['SUPERADMIN']],
            ["permit"=>'extra_money_trx.generate_pv',"to"=>['SUPERADMIN','TRANSPORT_KASIR','TRANSPORT_MANDOR']],

            ["permit"=>'ujalan.unval',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'ujalan.unval1',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'standby_mst.unval',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'standby_mst.unval1',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'standby_trx.unval',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'standby_trx.unval1',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'standby_trx.unval2',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'employee.unval',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.ticket.unval_ticket',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            
            ["permit"=>'trp_trx.unval1',"to"=>['SUPERADMIN','TRANSPORT_MANDOR']],
            ["permit"=>'trp_trx.unval2',"to"=>['SUPERADMIN','KTU','WAKIL_KTU']],
            ["permit"=>'trp_trx.unval3',"to"=>['SUPERADMIN','MARKETING']],
            ["permit"=>'trp_trx.unval4',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'trp_trx.unval5',"to"=>['SUPERADMIN','LOGISTIC_SPV']],
            ["permit"=>'trp_trx.unval6',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'employee.unremove',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'report.distance.download_file',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF']],            
            ["permit"=>'report.distance.views',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF']],
            ["permit"=>'ujalan.download_file',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF','VIEW_ONLY']],            
            ["permit"=>'standby_trx.download_file',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF','VIEW_ONLY']],            
            ["permit"=>'trp_trx_ticket.download_file',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF','VIEW_ONLY']],            

            ["permit"=>'salary_bonus.download_file',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF','VIEW_ONLY']],
            ["permit"=>'ujalan.batas_persen_susut.full_act',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'trp_trx.absen.download_file',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_STAFF','VIEW_ONLY']],


             ["permit"=>'standby_trx.val3',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_ADM']],
            ["permit"=>'standby_trx.val4',"to"=>['SUPERADMIN','LOGISTIC_MANAGER','LOGISTIC_SPV']],
            ["permit"=>'standby_trx.val5',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],

            ["permit"=>'standby_trx.unval3',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'standby_trx.unval4',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'standby_trx.unval5',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],

            ["permit"=>'trp_trx.ticket.show_weight',"to"=>[
                'SUPERADMIN','VIEW_ONLY','MARKETING','MIS','WAKIL_KTU','KTU',
                'LOGISTIC_MANAGER','LOGISTIC_SPV','LOGISTIC_ADM','LOGISTIC_STAFF'
            ]],


            ["permit"=>'extra_money_trx.unval1',"to"=>['SUPERADMIN','TRANSPORT_KASIR']],
            ["permit"=>'extra_money_trx.unval2',"to"=>['SUPERADMIN','TRANSPORT_MANDOR']],
            ["permit"=>'extra_money_trx.unval3',"to"=>['SUPERADMIN','WAKIL_KTU','KTU']],
            ["permit"=>'extra_money_trx.unval4',"to"=>['SUPERADMIN','LOGISTIC_STAFF']],
            ["permit"=>'extra_money_trx.unval5',"to"=>['SUPERADMIN','LOGISTIC_SPV']],
            ["permit"=>'extra_money_trx.unval6',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
        
            ["permit"=>'destination_location.views', "to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'destination_location.view',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'destination_location.create',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'destination_location.modify',"to"=>['SUPERADMIN','LOGISTIC_MANAGER']],

            ["permit"=>'ujalan.val2', "to"=>['SUPERADMIN','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'ujalan.val3', "to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'ujalan.unval2', "to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'ujalan.unval3', "to"=>['SUPERADMIN']],
            
            ["permit"=>'fin_payment_req.views', "to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'fin_payment_req.view', "to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'fin_payment_req.create', "to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'fin_payment_req.modify', "to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'fin_payment_req.delete', "to"=>['SUPERADMIN','LOGISTIC_STAFF','LOGISTIC_SPV','LOGISTIC_MANAGER']],
            ["permit"=>'fin_payment_req.val', "to"=>['SUPERADMIN','LOGISTIC_STAFF']],

            ["permit"=>'potongan_mst.remove', "to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'potongan_mst.unremove', "to"=>['SUPERADMIN','LOGISTIC_MANAGER']],
            ["permit"=>'employee.val1', "to"=>['SUPERADMIN','PRODUKSI']],
            ["permit"=>'employee.unval1', "to"=>['SUPERADMIN','PRODUKSI']]

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
