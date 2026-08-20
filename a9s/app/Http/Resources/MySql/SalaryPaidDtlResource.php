<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;

class SalaryPaidDtlResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // return parent::toArray($request);
        return [
            // 'salary_paid_id'     => $this->salary_paid_id,
            'employee_id'           => $this->employee_id,
            'employee_name'         => $this->employee_name,
            'employee_role'         => $this->employee_role,
            'employee_ktp_no'       => $this->employee_ktp_no,
            'employee_rek_no'       => $this->employee_rek_no,
            'employee_rek_name'     => $this->employee_rek_name,
            'employee_bank_code'    => $this->employee_bank_code,
            'payment_total'         => $this->payment_total,
            'payment_status'        => $this->payment_status,
            'payment_failed_reason' => $this->payment_failed_reason,

            'employee'              => new EmployeeResource($this->whenLoaded('employee')),
            // 'standby_nominal'       => $this->standby_nominal,
            'sb_gaji'               => $this->sb_gaji,
            'sb_makan'              => $this->sb_makan,
            'sb_dinas'              => $this->sb_dinas,
            'salary_bonus_nominal'  => $this->salary_bonus_nominal,
        ];
    }
}
