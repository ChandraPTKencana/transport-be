<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;

class RptSalaryDtlResource extends JsonResource
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
            'employee'              => new EmployeeResource($this->whenLoaded('employee')),
            'employee_id'           => $this->employee_id,
            'employee_name'         => $this->employee_name,
            'employee_role'         => $this->employee_role,
            'employee_birth_place'  => $this->employee_birth_place,
            'employee_birth_date'   => $this->employee_birth_date,
            'employee_tmk'          => $this->employee_tmk,
            'employee_ktp_no'       => $this->employee_ktp_no,
            'employee_address'      => $this->employee_address,
            'employee_status'       => $this->employee_status,
            'employee_rek_no'       => $this->employee_rek_no,
            'employee_bank_name'    => $this->employee_bank_name,
            // 'standby_nominal'       => $this->standby_nominal,
            'sb_gaji'               => $this->sb_gaji,
            'sb_makan'              => $this->sb_makan,

            'uj_gaji'               => $this->uj_gaji,
            'uj_makan'              => $this->uj_makan,

            'nominal_cut'           => $this->nominal_cut,
            'salary_bonus_nominal'  => $this->salary_bonus_nominal,
        ];
    }
}
