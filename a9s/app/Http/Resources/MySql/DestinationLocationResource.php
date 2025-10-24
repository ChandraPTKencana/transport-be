<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;

class DestinationLocationResource extends JsonResource
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
            'id'                      => $this->id,
            'xto'                     => $this->xto,
            'minimal_trip'            => $this->minimal_trip,
            'bonus_trip_supir'        => $this->bonus_trip_supir,
            'bonus_next_trip_supir'   => $this->bonus_next_trip_supir,
            'bonus_trip_kernet'       => $this->bonus_trip_kernet,
            'bonus_next_trip_kernet'  => $this->bonus_next_trip_kernet,

            'created_user'            => $this->created_user,
            'updated_user'            => $this->updated_user,
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
        ];
    }
}
