<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;
use App\Models\MySql\Employee;

class ExtraMoneyTrxResource extends JsonResource
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
            'id'                => $this->id,

            'tanggal'           => $this->tanggal,
            'transition_target' => $this->transition_target??'',
            'transition_type'   => $this->transition_type??'',
            'employee'          => new EmployeeResource($this->whenLoaded('employee')),

            'employee_id'       => $this->employee_id ?? "",
            'employee_rek_no'   => $this->employee_rek_no ?? "",
            'employee_rek_name' => $this->employee_rek_name ?? "",
            'no_pol'            => $this->no_pol,
            
            'xto'               => $this->xto ?? "",
            'note'              => $this->note ?? '',
            'ref'               => $this->ref ?? '',

            'cost_center_code'  => $this->cost_center_code ?? "",
            'cost_center_desc'  => $this->cost_center_desc ?? "",

            'pvr_id'            => $this->pvr_id ?? "",
            'pvr_no'            => $this->pvr_no ?? "",
            'pvr_total'         => $this->pvr_total ?? "",
            'pvr_had_detail'    => $this->pvr_had_detail ?? "",

            'pv_id'             => $this->pv_id ?? "",
            'pv_no'             => $this->pv_no ?? "",
            'pv_total'          => $this->pv_total ?? "",
            'pv_datetime'       => $this->pv_datetime ?? "",

            'rv_id'            => $this->rv_id ?? "",
            'rv_no'            => $this->rv_no ?? "",
            'rv_total'         => $this->rv_total ?? "",
            'rv_had_detail'    => $this->rv_had_detail ?? "",

            
            'val1'              => $this->val1,
            'val1_user'         => $this->val1_user ?? "",
            'val1_by'           => new IsUserResource($this->whenLoaded('val1_by')),
            'val1_at'           => $this->val1_at ?? "",

            'val2'              => $this->val2,
            'val2_user'         => $this->val2_user ?? "",
            'val2_by'           => new IsUserResource($this->whenLoaded('val2_by')),
            'val2_at'           => $this->val2_at ?? "",

            'val3'              => $this->val3,
            'val3_user'         => $this->val3_user ?? "",
            'val3_by'           => new IsUserResource($this->whenLoaded('val3_by')),
            'val3_at'           => $this->val3_at ?? "",

            'val4'              => $this->val4,
            'val4_user'         => $this->val4_user ?? "",
            'val4_by'           => new IsUserResource($this->whenLoaded('val4_by')),
            'val4_at'           => $this->val4_at ?? "",

            'val5'              => $this->val5,
            'val5_user'         => $this->val5_user ?? "",
            'val5_by'           => new IsUserResource($this->whenLoaded('val5_by')),
            'val5_at'           => $this->val5_at ?? "",

            'val6'              => $this->val6,
            'val6_user'         => $this->val6_user ?? "",
            'val6_by'           => new IsUserResource($this->whenLoaded('val6_by')),
            'val6_at'           => $this->val6_at ?? "",

            'deleted'           => $this->deleted,
            'deleted_user'      => $this->deleted_user ?? "",
            'deleted_at'        => $this->deleted_at ?? "",
            'deleted_by'        => new IsUserResource($this->whenLoaded('deleted_by')),
            'deleted_reason'    => $this->deleted_reason ?? "",

            'req_deleted'       => $this->req_deleted,
            'req_deleted_user'  => $this->req_deleted_user ?? "",
            'req_deleted_at'    => $this->req_deleted_at ?? "",
            'req_deleted_by'    => new IsUserResource($this->whenLoaded('req_deleted_by')),
            'req_deleted_reason'=> $this->req_deleted_reason ?? "",

            'created_user'      => $this->created_user,
            'updated_user'      => $this->updated_user,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,

            'extra_money'       => new ExtraMoneyResource($this->whenLoaded('extra_money')),
        ];
    }
}
