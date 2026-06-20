<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUrusanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'kode' => 'required|string|max:10|unique:urusans,kode',
            'nama' => 'required|string|max:100',
        ];
    }

    public function messages()
    {
        return [
            'kode.required' => 'Kode wajib diisi.',
            'kode.unique' => 'Kode sudah terpakai.',
            'nama.required' => 'Nama wajib diisi.',
        ];
    }
}
