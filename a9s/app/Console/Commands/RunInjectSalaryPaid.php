<?php

namespace App\Console\Commands;

use App\Http\Resources\MySql\TrxTrpAbsenResource;
use App\Models\MySql\Bank;
use App\Models\MySql\Employee;
use App\Models\MySql\PaymentMethod;
use App\Models\MySql\PermissionGroupDetail;
use App\Models\MySql\PermissionGroupUser;
use App\Models\MySql\PermissionList;
use App\Models\MySql\PermissionUserDetail;
use App\Models\MySql\PotonganMst;
use App\Models\MySql\RptSalaryDtl;
use App\Models\MySql\SalaryPaid;
use App\Models\MySql\SalaryPaidDtl;
use App\Models\MySql\StandbyTrxDtl;
use App\Models\MySql\TrxAbsen;
use App\Models\MySql\TrxTrp;
use App\Models\MySql\Ujalan;
use Illuminate\Console\Command;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RunInjectSalaryPaid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_inject_salary_paid';

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
        $t_stamp = date("Y-m-d H:i:s");
        // $employees = [];
        // SalaryPaidDtl::whereNull('employee_name')
        // ->cursor()
        // ->each(function ($dt) use (&$employees) {

        //     $this->info("=========== Process :".date("Y-m-d H:i:s")."===========");
        //     $this->info("=========== Process: Salary Paid ID {$dt->salary_paid_id} | Employee ID {$dt->employee_id} ===========");
        //     // Fetch & cache employee using reference (&$employees) and isset()
        //     if (!isset($employees[$dt->employee_id])) {
        //         $employees[$dt->employee_id] = Employee::with('bank')->find($dt->employee_id);
        //     }

        //     $employee = $employees[$dt->employee_id];

        //     // Ensure employee exists to prevent null reference errors
        //     if ($employee) {
        //         SalaryPaidDtl::where('salary_paid_id', $dt->salary_paid_id)
        //             ->where('employee_id', $dt->employee_id)
        //             ->update([
        //                 'employee_role'      => $employee->role,
        //                 'employee_name'      => $employee->name,
        //                 'employee_ktp_no'    => $employee->ktp_no,
        //                 'employee_rek_no'    => $employee->rek_no,
        //                 'employee_rek_name'  => $employee->rek_name,
        //                 'employee_bank_code' => $employee->bank?->code,
        //                 'payment_status'     => "DONE",
        //                 'payment_total'      => $dt->sb_gaji + $dt->sb_makan + $dt->sb_dinas + $dt->salary_bonus_nominal,
        //             ]);
        //     }
        //     $this->info("=========== Done :".date("Y-m-d H:i:s")."===========");
        // });

        // SalaryPaid::query()->update([
        //     "payment_status"=>"CLOSE",
        //     "val2"=>1,
        //     "val2_user"=>1,
        //     "val2_at"=>$t_stamp,
        // ]);


        RptSalaryDtl::cursor()
        ->each(function ($dt) {
            $this->info("=========== Process :".date("Y-m-d H:i:s")."===========");

            RptSalaryDtl::where('rpt_salary_id', $dt->rpt_salary_id)
            ->where('employee_id', $dt->employee_id)
            ->update([
                'uj_gaji'           => $dt->uj_gaji,
                'uj_gaji_manual'    => $dt->uj_gaji,
                'uj_makan'          => $dt->uj_makan,
                'uj_makan_manual'   => $dt->uj_makan,
                'uj_dinas'          => $dt->uj_dinas,
                'uj_dinas_manual'   => $dt->uj_dinas,
                'payment_status'    => "DONE",
                'payment_total'     => $dt->sb_gaji_2 + $dt->sb_makan_2 + $dt->sb_dinas_2 + $dt->salary_bonus_nominal_2+
                $dt->kerajinan+$dt->salary_bonus_bonus_trip+
                $dt->trip_cpo_bonus_gaji+$dt->trip_cpo_bonus_dinas+
                $dt->trip_pk_bonus_gaji+$dt->trip_pk_bonus_dinas+
                $dt->trip_cangkang_bonus_gaji+$dt->trip_cangkang_bonus_dinas+
                $dt->trip_tbs_bonus_gaji+$dt->trip_tbs_bonus_dinas+
                $dt->trip_tbsk_bonus_gaji+$dt->trip_tbsk_bonus_dinas-
                $dt->potongan_manual,
            ]);

            $this->info("=========== Done :".date("Y-m-d H:i:s")."===========");
        });

        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
