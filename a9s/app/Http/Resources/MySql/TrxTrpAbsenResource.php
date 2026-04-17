<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;

class TrxTrpAbsenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        $absens = $this->trx_absens ?? collect();
        $img_leaves = [];

        $img_leave = null;
        $img_leave_preview = null;
        $img_leave_latitude = null;
        $img_leave_longitude = null;
        $img_leave_is_manual = null;
    
        $img_arrive = null;
        $img_arrive_preview = null;
        $img_arrive_is_manual = null;
        $img_arrive_latitude = null;
        $img_arrive_longitude= null;
    
        $img_return = null;
        $img_return_preview = null;
        $img_return_is_manual = null;
        $img_return_latitude = null;
        $img_return_longitude = null;
    
        $img_till = null;
        $img_till_preview = null;
        $img_till_is_manual = null;
        $img_till_latitude = null;
        $img_till_longitude = null;

        foreach ($absens as $v) {
            if ($v->status == "B") {
                $img_leave = $v->gambar;
                $img_leave_preview = $v->gambar_preview;
                $img_leave_preview = $v->gambar_preview;
                $img_leave_is_manual = $v->is_manual;
                $img_leave_latitude = $v->latitude;
                $img_leave_longitude = $v->longitude;
    
                $img_leaves[] = [
                    "id" => $v->id,
                    "gambar" => $v->gambar,
                    "gambar_preview" => $v->gambar_preview,
                    "is_manual" => $v->is_manual,
                    "latitude" => $v->latitude,
                    "longitude" => $v->longitude,
                ];
            }
    
            if ($v->status == "T") {
                $img_arrive = $v->gambar;
                $img_arrive_preview = $v->gambar_preview;
                $img_arrive_is_manual = $v->is_manual;
                $img_arrive_latitude = $v->latitude;
                $img_arrive_longitude = $v->longitude;
            }
    
            if ($v->status == "K") {
                $img_return = $v->gambar;
                $img_return_preview = $v->gambar_preview;
                $img_return_is_manual = $v->is_manual;
                $img_return_latitude = $v->latitude;
                $img_return_longitude = $v->longitude;
            }
    
            if ($v->status == "S") {
                $img_till = $v->gambar;
                $img_till_preview = $v->gambar_preview;
                $img_till_is_manual = $v->is_manual;
                $img_till_latitude = $v->latitude;
                $img_till_longitude = $v->longitude;
            }
        }


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

            'img_leave_ts' => $this->ritase_leave_at,
            'img_arrive_ts' => $this->ritase_arrive_at,
            'img_return_ts' => $this->ritase_return_at,
            'img_till_ts' => $this->ritase_till_at,
    
            // hasil mapping
            'img_leave' => $img_leave,
            'img_leave_preview' => $img_leave_preview,
            'img_leave_is_manual' => $img_leave_is_manual,
            'img_leave_latitude' => $img_leave_latitude ?? "",
            'img_leave_longitude' => $img_leave_longitude ?? "",

            'img_arrive' => $img_arrive,
            'img_arrive_preview' => $img_arrive_preview,
            'img_arrive_is_manual' => $img_arrive_is_manual,
            'img_arrive_latitude' => $img_arrive_latitude ?? "",
            'img_arrive_longitude' => $img_arrive_longitude ?? "",
    
            'img_return' => $img_return,
            'img_return_preview' => $img_return_preview,
            'img_return_is_manual' => $img_return_is_manual,
            'img_return_latitude' => $img_return_latitude ?? "",
            'img_return_longitude' => $img_return_longitude ?? "",
    
            'img_till' => $img_till,
            'img_till_preview' => $img_till_preview,
            'img_till_is_manual' => $img_till_is_manual,
            'img_till_latitude' => $img_till_latitude ?? "",
            'img_till_longitude' => $img_till_longitude ?? "",
    
            'img_leaves' => $img_leaves,

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

            'ritase_val2'       => $this->ritase_val2,
            'ritase_val2_user'  => $this->ritase_val2_user ?? "",
            'ritase_val2_by'    => new IsUserResource($this->whenLoaded('ritase_val2_by')),
            'ritase_val2_at'    => $this->ritase_val2_at ?? "",

            'uj'                => new UjalanResource($this->whenLoaded('uj')),

        ];
    }
}
