<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;

class TrxCpoResource extends JsonResource
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
            'tanggal'           => $this->tanggal,
            'xto'               => $this->xto,
            'pv'                => $this->pv,
            'tiketa'            => $this->tiketa,
            'tiketb'            => $this->tiketb,
            'bruto'             => $this->bruto,
            'tara'              => $this->tara,
            'netto'             => $this->netto,
            'val'               => $this->val,
            'val_user'          => $this->val_user,
            'val_date'          => $this->val_date,
            'print'             => $this->print,
            'created_user'      => $this->created_user,
            'updated_user'      => $this->updated_user,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'status'            => $this->status,
        ];
    }
}
