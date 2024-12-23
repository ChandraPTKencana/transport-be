<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;
use Illuminate\Support\Facades\File;

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
        // $imageData = base64_encode($this->gambar);
        if($this->gambar_loc){
            $img = File::exists(files_path($this->gambar_loc)) ? "data:image/png;base64,".base64_encode(File::get(files_path($this->gambar_loc))) :"";
        }else{
            $img = "data:image/png;base64,";
            if(mb_detect_encoding($this->gambar)===false){
                $img.=base64_encode($this->gambar);
            }else{
                $img.=$this->gambar;        
            }
        }
        // return parent::toArray($request);
        return [
            'id'                => $this->id,
            'trx_trp_id'        => $this->trx_trp_id,
            // 'gambar'            => $this->gambar ? "data:image/png;base64,{$imageData}" : "",
            'gambar'            => $this->gambar ? $img : "",
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
