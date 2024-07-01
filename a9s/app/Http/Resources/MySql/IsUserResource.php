<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;

class IsUserResource extends JsonResource
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
            'id'                  => $this->id,
            'username'            => $this->username,
            'hak_akses'           => $this->hak_akses,
            'is_active'           => $this->is_active,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
            'details'             => PermissionUserDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
