<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;
use Illuminate\Support\Facades\Storage;

class TrxTrpTimbangInfoResource extends JsonResource
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

            'supir_id'          => $this->supir_id ?? "",
            'supir'             => $this->supir,
            'supir_rek_no'      => $this->supir_rek_no ?? "",
            'supir_rek_name'    => $this->supir_rek_name ?? "",
            'kernet_id'         => $this->kernet_id ?? "",
            'kernet'            => $this->kernet ?? "",
            'kernet_rek_no'     => $this->kernet_rek_no ?? "",
            'kernet_rek_name'   => $this->kernet_rek_name ?? "",
            'no_pol'            => $this->no_pol,
            
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

            'transition_target' => $this->transition_target ?? "",
            'transition_type'   => $this->transition_type ?? "",
            'trx_absens'        => TrxAbsenResource::collection($this->whenLoaded('trx_absens')),

            'timbang_a_img_in_exists'  => $this->timbang_a_img_in_loc ? true: false,
            'timbang_a_img_out_exists' => $this->timbang_a_img_out_loc ? true: false,
            'timbang_b_img_in_exists'  => $this->timbang_b_img_in_loc ? true: false,
            'timbang_b_img_out_exists' => $this->timbang_b_img_out_loc ? true: false,

            'timbang_note'       => $this->timbang_note,
           
            'timbang_val1'       => $this->timbang_val1,
            'timbang_val1_user'  => $this->timbang_val1_user ?? "",
            'timbang_val1_by'    => new IsUserResource($this->whenLoaded('timbang_val1_by')),
            'timbang_val1_at'    => $this->timbang_val1_at ?? "",

            'uj'                => new UjalanResource($this->whenLoaded('uj')),
        ];
    }
}
