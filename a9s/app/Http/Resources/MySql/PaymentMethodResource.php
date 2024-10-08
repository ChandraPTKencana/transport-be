<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;

class PaymentMethodResource extends JsonResource
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
            'id'            => $this->id,
            'name'          => $this->name,
            'account_code'  => $this->account_code,
        ];
    }
}
