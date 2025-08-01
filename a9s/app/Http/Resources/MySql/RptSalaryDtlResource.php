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
            'employee'                  => new EmployeeResource($this->whenLoaded('employee')),
            'employee_id'               => $this->employee_id,
            'employee_name'             => $this->employee_name,
            'employee_role'             => $this->employee_role,
            'employee_religion'         => $this->employee_religion,
            'employee_birth_place'      => $this->employee_birth_place,
            'employee_birth_date'       => $this->employee_birth_date,
            'employee_tmk'              => $this->employee_tmk,
            'employee_ktp_no'           => $this->employee_ktp_no,
            'employee_address'          => $this->employee_address,
            'employee_status'           => $this->employee_status,
            'employee_rek_no'           => $this->employee_rek_no,
            'employee_rek_name'         => $this->employee_rek_name,
            'employee_bank_name'        => $this->employee_bank_name,
            'employee_bpjs_kesehatan'   => $this->employee_bpjs_kesehatan,
            'employee_bpjs_jamsos'      => $this->employee_bpjs_jamsos,
            // 'standby_nominal'       => $this->standby_nominal,
            'sb_gaji'                   => $this->sb_gaji,
            'sb_makan'                  => $this->sb_makan,
            'sb_dinas'                  => $this->sb_dinas,

            'sb_gaji_2'                 => $this->sb_gaji_2,
            'sb_makan_2'                => $this->sb_makan_2,
            'sb_dinas_2'                => $this->sb_dinas_2,

            'uj_gaji'                   => $this->uj_gaji,
            'uj_makan'                  => $this->uj_makan,
            'uj_dinas'                  => $this->uj_dinas,

            'nominal_cut'               => $this->nominal_cut,
            'salary_bonus_nominal'      => $this->salary_bonus_nominal,
            'salary_bonus_nominal_2'    => $this->salary_bonus_nominal_2,
            'kerajinan'                 => $this->kerajinan,
            'trip_cpo'                  => $this->trip_cpo,
            'trip_cpo_bonus_gaji'       => $this->trip_cpo_bonus_gaji,
            'trip_cpo_bonus_dinas'      => $this->trip_cpo_bonus_dinas,
            'trip_pk'                   => $this->trip_pk,
            'trip_pk_bonus_gaji'        => $this->trip_pk_bonus_gaji,
            'trip_pk_bonus_dinas'       => $this->trip_pk_bonus_dinas,
            'trip_tbs'                  => $this->trip_tbs,
            'trip_tbs_bonus_gaji'       => $this->trip_tbs_bonus_gaji,
            'trip_tbs_bonus_dinas'      => $this->trip_tbs_bonus_dinas,
            'trip_tbsk'                 => $this->trip_tbsk,
            'trip_tbsk_bonus_gaji'      => $this->trip_tbsk_bonus_gaji,
            'trip_tbsk_bonus_dinas'     => $this->trip_tbsk_bonus_dinas,
            'trip_lain'                 => $this->trip_lain,
            'trip_lain_gaji'            => $this->trip_lain_gaji,
            'trip_lain_makan'           => $this->trip_lain_makan,
            'trip_lain_dinas'           => $this->trip_lain_dinas,
            'trip_tunggu'               => $this->trip_tunggu,
            'trip_tunggu_gaji'          => $this->trip_tunggu_gaji,
            'trip_tunggu_dinas'         => $this->trip_tunggu_dinas,
            'salary_bonus_bonus_trip'   => $this->salary_bonus_bonus_trip,
        ];
    }
}
