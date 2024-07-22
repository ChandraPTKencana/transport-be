<?php

namespace App\Http\Requests\MySql;

use Illuminate\Foundation\Http\FormRequest;

class PotonganMstRequest extends FormRequest
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
            $rules['id'] = 'required|exists:App\Models\MySql\PotonganMst,id';
        }
        if (request()->isMethod('put')) {
            $rules['id'] = 'required|exists:App\Models\MySql\PotonganMst,id';
        }
        if (request()->isMethod('post') || request()->isMethod('put')) {
            $rules['employee_id'] = 'required|exists:App\Models\MySql\Employee,id';
            $rules['kejadian'] = 'required';
            $rules['no_pol'] = 'required|max:12';
            $rules['nominal'] = 'required|numeric';
            $rules['nominal_cut'] = 'required|numeric';
            $rules['status'] = 'required|in:Open,Close Clear,Close UnClear';
        }
        return $rules;
    }

    public function messages()
    {
        return [

            'id.required' => 'ID tidak boleh kosong',
            'id.exists' => 'ID tidak terdaftar',

            'employee_id.required' => 'Pekerja tidak boleh kosong',
            'employee_id.exists' => 'Pekerja tidak terdaftar',

            'kejadian.required' => 'Kejadian tidak boleh kosong',

            'no_pol.required' => 'No Pol tidak boleh kosong',
            'no_pol.max' => 'No Pol Maksimal 12 Karakter',

            'nominal.required' => 'Nominal tidak boleh kosong',
            'nominal.numeric' => 'Nominal harus berupa angka',

            'nominal_cut.required' => 'Nominal Potong tidak boleh kosong',
            'nominal_cut.numeric' => 'Nominal Potong harus berupa angka',

            'status.required' => 'Status tidak boleh kosong',
            'status.in' => 'Status harus dipilih',

        ];
    }

    protected function prepareForValidation()
    {
        // $this->merge([
        //     'name' => strtoupper($this->name),
        // ]);
    }
}
