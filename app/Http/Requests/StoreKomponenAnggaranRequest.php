<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKomponenAnggaranRequest extends FormRequest
{
    public function rules()
    {
        return [
            'parent_id'     => 'nullable|exists:komponen_anggaran,id',
            'opd_id'        => 'nullable|exists:opds,id',
            'kode'          => 'required|string',
            'kode_program'  => 'nullable|string',
            'jenis'         => 'required|in:program,kegiatan,sub_kegiatan',
            'sub_unit'      => 'nullable|string',
            'urusan'        => 'nullable|string',
            'bidang_urusan' => 'nullable|string',
            'nama_komponen' => 'required|string',
            'tahun'         => 'nullable|integer',
            'document_type' => 'nullable|in:dpa,renja',
            'indikator'     => 'nullable|array',
            'indikator.*.nama_indikator' => 'nullable|string',
            'indikator.*.tolok_ukur'     => 'nullable|string',
            'indikator.*.sifat_indikator'=> 'nullable|in:positif,negatif,akumulatif',
            'indikator.*.target_indikator'=> 'nullable|string|max:100',
            'indikator.*.satuan'         => 'nullable|string|max:50',
        ];
    }
    public function authorize() { return true; }
}
