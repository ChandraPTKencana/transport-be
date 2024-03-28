<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;

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
        return [
            'id'                => $this->id,
            'xto'               => $this->xto,
            'tipe'              => $this->tipe,
            // 'status'            => $this->status,
            'jenis'             => $this->jenis,
            'harga'             => $this->harga,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'created_user'      => $this->created_user,
            'created_user'      => $this->created_user,
            'details'           => UjalanDetailResource::collection($this->whenLoaded('details')),

            'val'               => $this->val,
            'val_user'          => $this->val_user ?? "",
            'val_by'             => new IsUserResource($this->whenLoaded('val_by')),
            'val_at'          => $this->val_at ?? "",
        ];
    }
}
