<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;
use Illuminate\Support\Facades\Storage;

class TripInfoOrdinalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                    => $this->id,
            'ordinal'               => $this->ordinal,
            'dkey'                  => $this->dkey,
            'title'                 => $this->title,
            'permit_manual_input'   => $this->permit_manual_input,
            
            
            'created_user'          => $this->created_user,
            'updated_user'          => $this->updated_user,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }
}
