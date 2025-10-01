<?php

namespace App\Console\Commands;

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
class RunPermitCek extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_permit_cek';

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


        //!!! GET USER HAD THIS PERMISSIONS trp_trx.ticket
        $keyword = 'trp_trx.ticket.show_weight';
        // $keyword = 'trp_trx.transfer.do_transfer';

        $puds = PermissionUserDetail::where("permission_list_name",'like','%'.$keyword.'%')->with('user')->get();
        foreach ($puds as $key => $value) {
            $this->info("[Pribadi] Permission [".$value->permission_list_name."] di miliki oleh ".$value->user->username." \n ");
        }

        $pgds = PermissionGroupDetail::where("permission_list_name",'like','%'.$keyword.'%')->with(['permission_group'])->get();
        foreach ($pgds as $key => $value) {
            $usernames=[];
            $pgus = PermissionGroupUser::where("permission_group_id",$value->permission_group_id)->with('user')->get();
            foreach ($pgus as $k => $v) {
                array_push($usernames,$v->user->username);
            }
            $lu = implode(",",$usernames);
            $this->info("[Group : ".$value->permission_group->name."] Permission [".$value->permission_list_name."] di miliki oleh [".$lu."]  \n ");

            // $this->info("[Group : ".$value->permission_group->name."] Permission [".$keyword."] di miliki oleh ".[implode(",",$usernames)]." \n ");
        }
        
        // $pl = PermissionUserDetail::where("name",'like',$keyword)->get();


        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
