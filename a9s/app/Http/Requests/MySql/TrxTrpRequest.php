<?php

namespace App\Http\Requests\MySql;

use Illuminate\Foundation\Http\FormRequest;

class TrxTrpRequest extends FormRequest
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
            $rules['id'] = 'required|exists:App\Models\MySql\TrxTrp,id';
        }
        if (request()->isMethod('put')) {
            $rules['id'] = 'required|exists:App\Models\MySql\TrxTrp,id';
        }
        if (request()->isMethod('post') || request()->isMethod('put')) {
            $rules['tanggal'] = 'required|date_format:Y-m-d';
            $rules['xto'] = 'required|max:50';
            $rules['id_uj'] = 'required|exists:App\Models\MySql\Ujalan,id';
            // $rules['tipe'] = 'required|max:50';
            $rules['jenis'] = 'required|in:CPO,TBS,PK,TBSK';
            $rules['supir'] = 'required|max:255';
            $rules['kernet'] = 'nullable|max:255';
            $rules['no_pol'] = 'required|max:12|regex:/(\D)+\s{1}(\d)+\s{1}(\D)+/';
            $rules['online_status'] = 'required';
        }
        return $rules;
    }

    public function messages()
    {
        return [
            
            'tanggal.required' => 'U.Jalan Per tidak boleh kosong',
            'tanggal.date_format' => 'Format U.Jalan Per Tidak sesuai',

            'id.required' => 'ID tidak boleh kosong',
            'id.exists' => 'ID tidak terdaftar',

            'id_uj.required' => 'Tipe tidak boleh kosong',
            'id_uj.exists' => 'Tipe tidak terdaftar',

            'xto.required' => 'Tujuan tidak boleh kosong',
            'xto.max' => 'Tujuan tidak boleh melebihi 50 karakter',

            // 'tipe.required' => 'Tipe tidak boleh kosong',
            // 'tipe.max' => 'Tipe tidak boleh melebihi 50 karakter',

            'jenis.required' => 'Jenis tidak boleh kosong',
            'jenis.in' => 'Jenis harus dipilih',

            'supir.required' => 'Supir tidak boleh kosong',
            'supir.max' => 'Supir tidak boleh melebihi 255 karakter',

            'kernet.max' => 'kernet tidak boleh melebihi 255 karakter',

            'no_pol.required' => 'No Pol tidak boleh kosong',
            'no_pol.max' => 'No Pol tidak boleh melebihi 12 karakter',
            'no_pol.regex' => 'Format No Pol salah',

        ];
    }
}
