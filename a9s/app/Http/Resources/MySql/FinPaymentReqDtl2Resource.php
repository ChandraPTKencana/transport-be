<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;
use App\Models\MySql\StandbyDtl;

class FinPaymentReqDtl2Resource extends JsonResource
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
        $array                          = [];
        $array['trx_trp_id']            = $this->trx_trp_id;
        $array['trx_trp']               = new TrxTrpResource($this->whenLoaded('trx_trp'));
        $array['potongan_trx_ids']      = $this->potongan_trx_ids;
        $array['extra_money_trx_id']    = $this->extra_money_trx_id;
        $array['extra_money_trx']       = new ExtraMoneyTrxResource($this->whenLoaded('extra_money_trx'));

        // $tt = $array['trx_trp'] ? $array['trx_trp']:$array['extra_money_trx']->prev_trx_trp;

        $array['employee_id']           = $this->employee_id;
        $array['employee_name']         = $this->employee_name;
        $array['employee_role']         = $this->employee_role;
        $array['employee_rek_no']       = $this->employee_rek_no;
        $array['employee_rek_name']     = $this->employee_rek_name;
        $array['employee_bank_code']    = $this->employee_bank_code;
        $array['nom_potongan_trxs']     = $this->nom_potongan_trxs;
        $array['nom_trx_trp']           = $this->nom_trx_trp;
        $array['nom_extra_money_trx']   = $this->nom_extra_money_trx;
        $array['nom_final']             = $this->nom_final;
        $array['status']                = $this->status;
        $array['failed_reason']         = $this->failed_reason;
        return $array;
    }
}
