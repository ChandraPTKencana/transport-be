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
            $rules['xto'] = 'required|max:50';
            $rules['tipe'] = 'required|max:50';
            $rules['jenis'] = 'required|max:50';
        }
        return $rules;
    }

    public function messages()
    {
        return [

            'id.required' => 'ID tidak boleh kosong',
            'id.exists' => 'ID tidak terdaftar',

            'xto.required' => 'To tidak boleh kosong',
            'xto.max' => 'To Maksimal 50 Karakter',

            'tipe.required' => 'Tipe tidak boleh kosong',
            'tipe.max' => 'Tipe Maksimal 50 Karakter',

            'jenis.required' => 'Jenis tidak boleh kosong',
            'jenis.max' => 'Jenis Maksimal 50 Karakter',

        ];
    }
}
