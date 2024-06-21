<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;

class TrxTrpResource extends JsonResource
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

            'id_uj'             => $this->id_uj,
            'jenis'             => $this->jenis,
            'xto'               => $this->xto,
            'tipe'              => $this->tipe,
            'amount'            => $this->amount,

            'pv_id'             => $this->pv_id ?? "",
            'pv_no'             => $this->pv_no ?? "",
            'pv_total'          => $this->pv_total ?? "",
            'pv_datetime'       => $this->pv_datetime ?? "",

            'ticket_a_id'       => $this->ticket_a_id ?? "",
            'ticket_a_no'       => $this->ticket_a_no ?? "",
            'ticket_a_bruto'    => $this->ticket_a_bruto ?? "",
            'ticket_a_tara'     => $this->ticket_a_tara ?? "",
            'ticket_a_netto'    => $this->ticket_a_netto ?? "",
            'ticket_a_supir'    => $this->ticket_a_supir ?? "",
            'ticket_a_no_pol'   => $this->ticket_a_no_pol ?? "",
            'ticket_a_in_at'    => $this->ticket_a_in_at ?? "",
            'ticket_a_out_at'   => $this->ticket_a_out_at ?? "",

            'ticket_b_id'       => $this->ticket_b_id ?? "",
            'ticket_b_no'       => $this->ticket_b_no ?? "",
            'ticket_b_bruto'    => $this->ticket_b_bruto ?? "",
            'ticket_b_tara'     => $this->ticket_b_tara ?? "",
            'ticket_b_netto'    => $this->ticket_b_netto ?? "",
            'ticket_b_supir'    => $this->ticket_b_supir ?? "",
            'ticket_b_no_pol'   => $this->ticket_b_no_pol ?? "",
            'ticket_b_in_at'    => $this->ticket_b_in_at ?? "",
            'ticket_b_out_at'   => $this->ticket_b_out_at ?? "",

            'supir'             => $this->supir,
            'kernet'            => $this->kernet ?? "",
            'no_pol'            => $this->no_pol,

            'val'               => $this->val,
            'val_user'          => $this->val_user ?? "",
            'val_by'            => new IsUserResource($this->whenLoaded('val_by')),
            'val_at'            => $this->val_at ?? "",
            
            'val1'              => $this->val1,
            'val1_user'         => $this->val1_user ?? "",
            'val1_by'           => new IsUserResource($this->whenLoaded('val1_by')),
            'val1_at'           => $this->val1_at ?? "",

            'val2'              => $this->val2,
            'val2_user'         => $this->val2_user ?? "",
            'val2_by'           => new IsUserResource($this->whenLoaded('val2_by')),
            'val2_at'           => $this->val2_at ?? "",

            'created_user'      => $this->created_user,
            'updated_user'      => $this->updated_user,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,

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

            'cost_center_code'  => $this->cost_center_code ?? "",
            'cost_center_desc'  => $this->cost_center_desc ?? "",

            'pvr_id'            => $this->pvr_id ?? "",
            'pvr_no'            => $this->pvr_no ?? "",
            'pvr_total'         => $this->pvr_total ?? "",
            'pvr_had_detail'    => $this->pvr_had_detail ?? "",
            'transition_target' => $this->transition_target ?? "",
            'transition_type'   => $this->transition_type ?? "",
            'trx_absens'        => TrxAbsenResource::collection($this->whenLoaded('trx_absens')),

            'ritase_leave_at'   => $this->ritase_leave_at ?? "",
            'ritase_arrive_at'  => $this->ritase_arrive_at ?? "",
            'ritase_return_at'  => $this->ritase_return_at ?? "",
            'ritase_till_at'    => $this->ritase_till_at ?? "",
            'ritase_note'       => $this->ritase_note ?? "",
            
            'ritase_val'        => $this->ritase_val,
            'ritase_val_user'   => $this->ritase_val_user ?? "",
            'ritase_val_by'     => new IsUserResource($this->whenLoaded('ritase_val_by')),
            'ritase_val_at'     => $this->ritase_val_at ?? "",

            'ritase_val1'       => $this->ritase_val1,
            'ritase_val1_user'  => $this->ritase_val1_user ?? "",
            'ritase_val1_by'    => new IsUserResource($this->whenLoaded('ritase_val1_by')),
            'ritase_val1_at'    => $this->ritase_val1_at ?? "",


            'details_uj'        => UjalanDetailResource::collection($this->whenLoaded('uj_details')),

        ];
    }
}
