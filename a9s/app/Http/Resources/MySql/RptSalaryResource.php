<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;
use App\Models\MySql\SalaryPaid;

class RptSalaryResource extends JsonResource
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
            'period_end'        => $this->period_end,
            
            'val1'              => $this->val1,
            'val1_user'         => $this->val1_user ?? "",
            'val1_by'           => new IsUserResource($this->whenLoaded('val1_by')),
            'val1_at'           => $this->val1_at ?? "",


            'created_user'      => $this->created_user,
            'updated_user'      => $this->updated_user,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'details'           => RptSalaryDtlResource::collection($this->whenLoaded('details')),
        ];
    }
}
