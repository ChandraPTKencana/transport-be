<?php

namespace App\Http\Resources\Stok;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;

class UnitResource extends JsonResource
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
            'name'                => $this->name,

            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
            'updator'             => new IsUserResource($this->whenLoaded('updator')),
            'creator'             => new IsUserResource($this->whenLoaded('creator')),
        ];
    }
}
