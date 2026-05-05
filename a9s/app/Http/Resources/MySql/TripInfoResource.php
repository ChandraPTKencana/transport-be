<?php

namespace App\Http\Resources\MySql;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\IsUserResource;
use Illuminate\Support\Facades\Storage;

class TripInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        // $trip_infos = $this->trip_infos ?? collect();

        // $a_in_mobil_preview = null;
        // $a_in_mobil_permit_manual_input = false;
        // $a_in_mobil_source_browser = true;

        // $a_in_gasoli_preview = null;
        // $a_in_gasoli_permit_manual_input = false;
        // $a_in_gasoli_source_browser = true;

        // $a_in_cabin_preview = null;
        // $a_in_cabin_permit_manual_input = false;
        // $a_in_cabin_source_browser = true;

        // $a_out_mobil_preview = null;
        // $a_out_mobil_permit_manual_input = false;
        // $a_out_mobil_source_browser = true;

        // $a_out_gasoli_preview = null;
        // $a_out_gasoli_permit_manual_input = false;
        // $a_out_gasoli_source_browser = true;

        // $a_out_cabin_preview = null;
        // $a_out_cabin_permit_manual_input = false;
        // $a_out_cabin_source_browser = true;

        // $b_in_mobil_preview = null;
        // $b_in_mobil_permit_manual_input = false;
        // $b_in_mobil_source_browser = true;

        // $b_out_mobil_preview = null;
        // $b_out_mobil_permit_manual_input = false;
        // $b_out_mobil_source_browser = true;


        // foreach ($trip_infos as $v) {
        //     if ($v->trip_info_ordinal->dkey == "aksi.saat_timbang_a.masuk.mobil") {
        //         $a_in_mobil = null;
        //         $a_in_mobil_preview = $v->img_preview;
        //         $a_in_mobil_permit_manual_input = $v->trip_info_ordinal->permit_manual_input;
        //         $a_in_mobil_source_browser = $v->source_browser;
        //     }
        //     if ($v->trip_info_ordinal->dkey == "aksi.saat_timbang_a.masuk.gasoli") {
        //         $a_in_gasoli = null;
        //         $a_in_gasoli_preview = $v->img_preview;
        //         $a_in_gasoli_permit_manual_input = $v->trip_info_ordinal->permit_manual_input;
        //         $a_in_gasoli_source_browser = $v->source_browser;
        //     }
        //     if ($v->trip_info_ordinal->dkey == "aksi.saat_timbang_a.masuk.cabin") {
        //         $a_in_cabin = null;
        //         $a_in_cabin_preview = $v->img_preview;
        //         $a_in_cabin_permit_manual_input = $v->trip_info_ordinal->permit_manual_input;
        //         $a_in_cabin_source_browser = $v->source_browser;
        //     }
        //     if ($v->trip_info_ordinal->dkey == "aksi.saat_timbang_a.keluar.mobil") {
        //         $a_out_mobil = null;
        //         $a_out_mobil_preview = $v->img_preview;
        //         $a_out_mobil_permit_manual_input = $v->trip_info_ordinal->permit_manual_input;
        //         $a_out_mobil_source_browser = $v->source_browser;
        //     }
        //     if ($v->trip_info_ordinal->dkey == "aksi.saat_timbang_a.keluar.gasoli") {
        //         $a_out_gasoli = null;
        //         $a_out_gasoli_preview = $v->img_preview;
        //         $a_out_gasoli_permit_manual_input = $v->trip_info_ordinal->permit_manual_input;
        //         $a_out_gasoli_source_browser = $v->source_browser;
        //     }
        //     if ($v->trip_info_ordinal->dkey == "aksi.saat_timbang_a.keluar.cabin") {
        //         $a_out_cabin = null;
        //         $a_out_cabin_preview = $v->img_preview;
        //         $a_out_cabin_permit_manual_input = $v->trip_info_ordinal->permit_manual_input;
        //         $a_out_cabin_source_browser = $v->source_browser;
        //     }
        //     if ($v->trip_info_ordinal->dkey == "aksi.saat_timbang_b.masuk.mobil") {
        //         $b_in_mobil = null;
        //         $b_in_mobil_preview = $v->img_preview;
        //         $b_in_mobil_permit_manual_input = $v->trip_info_ordinal->permit_manual_input;
        //         $b_in_mobil_source_browser = $v->source_browser;
        //     }
        //     if ($v->trip_info_ordinal->dkey == "aksi.saat_timbang_b.keluar.mobil") {
        //         $b_out_mobil = null;
        //         $b_out_mobil_preview = $v->img_preview;
        //         $b_out_mobil_permit_manual_input = $v->trip_info_ordinal->permit_manual_input;
        //         $b_out_mobil_source_browser = $v->source_browser;
        //     }
        // }


        // return parent::toArray($request);
        return [
            'id'                => $this->id,
            'trip_info_ordinal' => new TripInfoOrdinalResource($this->whenLoaded('trip_info_ordinal')),

            'img'               => null,
            'img_preview'       => $this->img_preview,
            'img_exists'        => $this->img_preview ? true : false,
            'img_at'            => $this->img_at,

            'img_latitude'      => $this->img_latitude,
            'img_longitude'     => $this->img_longitude,
            'img_note'          => $this->img_note,
            'img_upload_at'     => $this->img_upload_at,
            'img_upload_user'   => $this->img_upload_user,
            'source_browser'    => $this->source_browser,
            
            'created_user'      => $this->created_user,
            'updated_user'      => $this->updated_user,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,

            
            // 'timbang_a_in_mobil'                        => null,
            // 'timbang_a_in_mobil_preview'                => $a_in_mobil_preview,
            // 'timbang_a_in_mobil_permit_manual_input'    => $a_in_mobil_permit_manual_input,
            // 'timbang_a_in_mobil_source_browser'         => $a_in_mobil_source_browser,
    
            // 'timbang_a_in_gasoli'                       => null,
            // 'timbang_a_in_gasoli_preview'               => $a_in_gasoli_preview,
            // 'timbang_a_in_gasoli_permit_manual_input'   => $a_in_gasoli_permit_manual_input,
            // 'timbang_a_in_gasoli_source_browser'        => $a_in_gasoli_source_browser,
    
            // 'timbang_a_in_cabin'                        => null,
            // 'timbang_a_in_cabin_preview'                => $a_in_cabin_preview,
            // 'timbang_a_in_cabin_permit_manual_input'    => $a_in_cabin_permit_manual_input,
            // 'timbang_a_in_cabin_source_browser'         => $a_in_cabin_source_browser,
    
            // 'timbang_a_out_mobil'                       => null,
            // 'timbang_a_out_mobil_preview'               => $a_out_mobil_preview,
            // 'timbang_a_out_mobil_permit_manual_input'   => $a_out_mobil_permit_manual_input,
            // 'timbang_a_out_mobil_source_browser'        => $a_out_mobil_source_browser,
    
            // 'timbang_a_out_gasoli'                      => null,
            // 'timbang_a_out_gasoli_preview'              => $a_out_gasoli_preview,
            // 'timbang_a_out_gasoli_permit_manual_input'  => $a_out_gasoli_permit_manual_input,
            // 'timbang_a_out_gasoli_source_browser'       => $a_out_gasoli_source_browser,
    
            // 'timbang_a_out_cabin'                       => null,
            // 'timbang_a_out_cabin_preview'               => $a_out_cabin_preview,
            // 'timbang_a_out_cabin_permit_manual_input'   => $a_out_cabin_permit_manual_input,
            // 'timbang_a_out_cabin_source_browser'        => $a_out_cabin_source_browser,
    
            // 'timbang_b_in_mobil'                        => null,
            // 'timbang_b_in_mobil_preview'                => $b_in_mobil_preview,
            // 'timbang_b_in_mobil_permit_manual_input'    => $b_in_mobil_permit_manual_input,
            // 'timbang_b_in_mobil_source_browser'         => $b_in_mobil_source_browser,
    
            // 'timbang_b_out_mobil'                       => null,
            // 'timbang_b_out_mobil_preview'               => $b_out_mobil_preview,
            // 'timbang_b_out_mobil_permit_manual_input'   => $b_out_mobil_permit_manual_input,
            // 'timbang_b_out_mobil_source_browser'        => $b_out_mobil_source_browser,

            

            
        ];
    }
}
