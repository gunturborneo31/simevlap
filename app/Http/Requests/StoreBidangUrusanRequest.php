<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBidangUrusanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
                'kode' => 'required|string|max:10|unique:bidang_urusans,kode,NULL,id,urusan_id,' . $this->urusan_id,
            'kode' => 'required|string|max:10|unique:bidang_urusans,kode,NULL,id,urusan_id,' . $this->urusan_id,
            'nama' => 'required|string|max:100',
        ];
    }

    public function messages()
    {
        return [
            'urusan_id.required' => 'Urusan wajib dipilih.',
            'urusan_id.exists' => 'Urusan tidak valid.',
            'kode.required' => 'Kode wajib diisi.',
            'kode.unique' => 'Kode sudah terpakai di urusan ini.',
            'nama.required' => 'Nama wajib diisi.',
        ];
    }
}
