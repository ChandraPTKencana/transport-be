<?php

namespace App\Http\Requests\MySql;

use Illuminate\Foundation\Http\FormRequest;

class TrxCpoRequest extends FormRequest
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
            $rules['id'] = 'required|exists:App\Models\MySql\TrxCpo,id';
        }
        if (request()->isMethod('put')) {
            $rules['id'] = 'required|exists:App\Models\MySql\TrxCpo,id';
        }
        if (request()->isMethod('post') || request()->isMethod('put')) {
            $rules['tanggal'] = 'required|date_format:Y-m-d';
            $rules['xto'] = 'required|max:50';
            $rules['tipe'] = 'required|max:50';
            $rules['supir'] = 'required|max:255';
            $rules['no_pol'] = 'required|max:12';
        }
        return $rules;
    }

    public function messages()
    {
        return [

            'id.required' => 'ID tidak boleh kosong',
            'id.exists' => 'ID tidak terdaftar',

            'xto.required' => 'To tidak boleh kosong',
            'xto.max' => 'To tidak boleh melebihi 50 karakter',

            'tipe.required' => 'Tipe tidak boleh kosong',
            'tipe.max' => 'Tipe tidak boleh melebihi 50 karakter',

            'supir.required' => 'Supir tidak boleh kosong',
            'supir.max' => 'Supir tidak boleh melebihi 50 karakter',

            'no_pol.required' => 'No Pol tidak boleh kosong',
            'no_pol.max' => 'No Pol tidak boleh melebihi 50 karakter',

        ];
    }
}
