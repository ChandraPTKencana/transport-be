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

            'id_uj'             => $this->id_uj,
            'xto'               => $this->xto,
            'tipe'              => $this->tipe,
            'amount'            => $this->amount,

            'pv_id'             => $this->pv_id,
            'pv_no'             => $this->pv_no,
            'pv_total'          => $this->pv_total,

            'ticket_id'           => $this->ticket_id,
            'ticket_no'           => $this->ticket_no,
            'ticket_bruto'        => $this->ticket_bruto,
            'ticket_tara'         => $this->ticket_tara,
            'ticket_netto'        => $this->ticket_netto,
            'ticket_supir'        => $this->ticket_supir,
            'ticket_no_pol'        => $this->ticket_no_pol,

            'supir'             => $this->supir,
            'no_pol'            => $this->no_pol,
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

            'deleted'           => $this->deleted,
            'deleted_user'      => $this->deleted_user,
            'deleted_at'        => $this->deleted_at,
        ];
    }
}
