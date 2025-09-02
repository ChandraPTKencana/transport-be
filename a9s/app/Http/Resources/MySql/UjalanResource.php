<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;
use App\Helpers\MyAdmin;

class UjalanResource extends JsonResource
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
        $data = [
            'id'                => $this->id,
            'xto'               => $this->xto,
            'km_range'          => $this->km_range,
            'tipe'              => $this->tipe,
            'asst_opt'          => $this->asst_opt,
            'jenis'             => $this->jenis,
            'harga'             => $this->harga,
            'bonus_trip_supir'  => $this->bonus_trip_supir,
            'bonus_trip_kernet' => $this->bonus_trip_kernet,
            'note_for_remarks'  => $this->note_for_remarks ?? '' ,
            'transition_from'   => $this->transition_from ?? '' ,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'created_user'      => $this->created_user,
            'updated_user'      => $this->updated_user,
            'details'           => UjalanDetailResource::collection($this->whenLoaded('details')),
            'details2'          => UjalanDetail2Resource::collection($this->whenLoaded('details2')),
            
            'val'               => $this->val,
            'val_user'          => $this->val_user ?? "",
            'val_by'            => new IsUserResource($this->whenLoaded('val_by')),
            'val_at'            => $this->val_at ?? "",

            'val1'              => $this->val1,
            'val1_user'         => $this->val1_user ?? "",
            'val1_by'           => new IsUserResource($this->whenLoaded('val1_by')),
            'val1_at'           => $this->val1_at ?? "",
        
            'deleted'           => $this->deleted,
            'deleted_user'      => $this->deleted_user ?? "",
            'deleted_at'        => $this->deleted_at ?? "",
            'deleted_by'        => new IsUserResource($this->whenLoaded('deleted_by')),
            'deleted_reason'    => $this->deleted_reason ?? "",
        ];

        $admin = MyAdmin::user();
        $permissions = $admin->the_user->listPermissions();
        if(MyAdmin::checkScope($permissions, 'ujalan.batas_persen_susut.full_act',true)){
            $data['batas_persen_susut'] = $this->batas_persen_susut ?? '';
        }
        
        $data['status_batas_susut'] = $this->batas_persen_susut ? 'Terisi' : 'Kosong';

        return $data;
    }
}
