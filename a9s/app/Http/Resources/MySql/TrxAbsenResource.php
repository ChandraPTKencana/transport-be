<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

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

        // $img = "data:image/png;base64,";
        // if(mb_detect_encoding($this->gambar)===false){
        //     $img.=base64_encode($this->gambar);
        // }else{
        //     $img.=$this->gambar;        
        // }
        // // return parent::toArray($request);
        // return [
        //     'id'                => $this->id,
        //     'trx_trp_id'        => $this->trx_trp_id,
        //     // 'gambar'            => $this->gambar ? "data:image/png;base64,{$imageData}" : "",
        //     'gambar'            => $this->gambar ? $img : "",
        //     'created_at'        => $this->created_at,
        //     'updated_at'        => $this->updated_at,
        // ];


        // $img="";
        // // $imageData = base64_encode($this->gambar);
        // if($this->gambar_loc){
        //     $img = File::exists(files_path($this->gambar_loc)) ? "data:image/png;base64,".base64_encode(File::get(files_path($this->gambar_loc))) :"";
        // }else if($this->gambar){
        //     $img = "data:image/png;base64,";
        //     if(mb_detect_encoding($this->gambar)===false){
        //         $img.=base64_encode($this->gambar);
        //     }else{
        //         $img.=$this->gambar;        
        //     }
        // }
        // return parent::toArray($request);
        return [
            'id'                => $this->id,
            'trx_trp_id'        => $this->trx_trp_id,
            // 'gambar'            => $this->gambar ? "data:image/png;base64,{$imageData}" : "",
            // 'gambar'            => $this->gambar ? $img : "",
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'status'            => $this->status,
            'gambar'            => null,
            'gambar_preview'    => $this->gambar_preview,

            // 'gambar_loc'        => $this->gambar_loc,
            // 'gambar_preview'  => $this->gambar ? "data:".$this->gambar_type.";base64,".$this->gambar : "",
            // 'gambar_preview'    => $this->gambar_loc && Storage::disk('public')->exists($this->gambar_loc) ? "trx_trp/absen/att/".$this->trx_trp_id."/".$this->id : "",
            // 'gambar_type'       => $this->gambar_type,
        ];
    }
}
