<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;

class TrxAbsenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $imageData = base64_encode($this->gambar);
        // return parent::toArray($request);
        return [
            'id'                => $this->id,
            'trx_trp_id'        => $this->trx_trp_id,
            'gambar'            => $this->gambar ? "data:image/png;base64,{$imageData}" : "",
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
