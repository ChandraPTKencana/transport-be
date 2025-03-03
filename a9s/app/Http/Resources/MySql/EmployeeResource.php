<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\File;

class EmployeeResource extends JsonResource
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
            'val'                   => $this->val,
            'val_user'              => $this->val_user ?? "",
            'val_by'                => new IsUserResource($this->whenLoaded('val_by')),
            'val_at'                => $this->val_at ?? "",
            'id'                    => $this->id,
            'name'                  => $this->name,
            'role'                  => $this->role,
            'ktp_no'                => $this->ktp_no ?? '',
            'sim_no'                => $this->sim_no ?? '',
            'bank'                  => new BankResource($this->whenLoaded('bank')),
            'bank_id'               => $this->bank_id ?? '',
            'rek_no'                => $this->rek_no ?? '',
            'rek_name'              => $this->rek_name ?? '',
            'phone_number'          => $this->phone_number ?? '',
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
            'created_user'          => $this->created_user,
            'updated_user'          => $this->updated_user,
            'attachment_1'          => null,
            'attachment_1_preview'  => $this->attachment_1 ? "data:".$this->attachment_1_type.";base64,".$this->attachment_1 : "",
            'attachment_1_type'     => $this->attachment_1_type,
            'birth_date'            => $this->birth_date ?? "",
            'birth_place'           => $this->birth_place ?? "",
            'tmk'                   => $this->tmk ?? "",
            'address'               => $this->address ?? "",
            'status'                => $this->status ?? "",

            'deleted'               => $this->deleted,
            'deleted_user'          => $this->deleted_user ?? "",
            'deleted_at'            => $this->deleted_at ?? "",
            'deleted_by'            => new IsUserResource($this->whenLoaded('deleted_by')),
            'deleted_reason'        => $this->deleted_reason ?? "",
            
            'bpjs_kesehatan'        => $this->bpjs_kesehatan ?? "",
            'bpjs_jamsos'           => $this->bpjs_jamsos ?? "",
            'religion'              => $this->religion ?? "",
            // 'm_dekey'               => $this->m_dekey ?? "",
            'm_enkey'               => $this->m_enkey ?? "",
            'username'              => $this->username ?? "",
            'password'              => "",
            'm_face_login'          => $this->m_face_login ? 1 : 0,
            // 'face_loc_target'       => $this->face_loc_target ?? "",
            // 'face_loc_type'         => $this->face_loc_type ?? "",

            'face_loc'          => null,
            'face_loc_preview'  => $this->face_loc_target && File::exists(files_path($this->face_loc_target)) ? "data:".$this->face_loc_type.";base64,".base64_encode(File::get(files_path($this->face_loc_target))) :"",
            'face_loc_type'     => $this->face_loc_type,


        ];
    }
}
