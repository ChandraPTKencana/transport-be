<?php

namespace App\Http\Requests\MySql;

use Illuminate\Foundation\Http\FormRequest;

class DestinationLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $rules = [];
        if (request()->isMethod('get')) {
            $rules['id'] = 'required|exists:App\Models\MySql\DestinationLocation,id';
        }
        if (request()->isMethod('put')) {
            $rules['id'] = 'required|exists:App\Models\MySql\DestinationLocation,id';
        }
        if (request()->isMethod('post') || request()->isMethod('put')) {
            $rules['xto']                       = 'required|min:1|max:50';
            $rules['minimal_trip']              = 'required|numeric';
            $rules['bonus_trip_supir']          = 'required|numeric';
            $rules['bonus_next_trip_supir']     = 'required|numeric';
            $rules['bonus_trip_kernet']         = 'required|numeric';
            $rules['bonus_next_trip_kernet']    = 'required|numeric';
        }
        return $rules;
    }

    public function messages()
    {
        return [
            
            'id.required'                      => 'ID tidak boleh kosong',
            'id.exists'                        => 'ID tidak terdaftar',

            'xto.required'                     => 'Tipe tidak boleh kosong',
            'xto.in'                           => 'Tipe harus dipilih',

            'minimal_trip.required'            => 'Min Trip tidak boleh kosong',
            'minimal_trip.numeric'             => 'Format Min Trip harus angka',

            'bonus_trip_supir.required'        => 'Bonus Trip Supir tidak boleh kosong',
            'bonus_trip_supir.numeric'         => 'Format Bonus Trip Supir harus angka',

            'bonus_next_trip_supir.required'   => 'Bonus Next Trip Supir tidak boleh kosong',
            'bonus_next_trip_supir.numeric'    => 'Format Bonus Next Trip Supir harus angka',

            'bonus_trip_kernet.required'       => 'Bonus Trip Kernet tidak boleh kosong',
            'bonus_trip_kernet.numeric'        => 'Format Bonus Trip Kernet harus angka',

            'bonus_next_trip_kernet.required'  => 'Bonus Next Trip Kernet tidak boleh kosong',
            'bonus_next_trip_kernet.numeric'   => 'Format Bonus Next Trip Kernet harus angka',

        ];
    }
}
