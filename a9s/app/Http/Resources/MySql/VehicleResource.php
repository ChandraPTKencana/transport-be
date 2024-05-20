<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
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
            'id'           => $this->id,
            'no_pol'       => $this->no_pol,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'created_user' => $this->created_user,
            'updated_user' => $this->updated_user,
        ];
    }
}
