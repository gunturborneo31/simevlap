<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKomponenAnggaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'parent_id'     => 'nullable|exists:komponen_anggaran,id',
            'opd_id'        => 'nullable|exists:opds,id',
            'kode'          => 'required|string|max:32',
            'kode_program'  => 'nullable|string|max:32',
            'jenis'         => 'required|in:program,kegiatan,sub_kegiatan',
            'sub_unit'      => 'nullable|string|max:255',
            'urusan'        => 'nullable|string|max:100',
            'bidang_urusan' => 'nullable|string|max:100',
            'nama_komponen' => 'required|string|max:255',
            'tahun'         => 'nullable|integer',
            'indikator'     => 'nullable|array',
            'indikator.*.nama_indikator' => 'nullable|string',
            'indikator.*.satuan'         => 'nullable|string|max:50',
        ];
    }
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode wajib diisi.',
            'jenis.required' => 'Jenis wajib diisi.',
            'sub_unit.required' => 'Sub Unit wajib diisi.',
            'urusan.required' => 'Urusan wajib diisi.',
            'bidang_urusan.required' => 'Bidang Urusan wajib diisi.',
            'nama_komponen.required' => 'Nama Komponen wajib diisi.',
            'indikator.required' => 'Minimal 1 indikator.',
            'indikator.*.nama_indikator.required' => 'Nama indikator wajib diisi.',
            'indikator.*.satuan.required' => 'Satuan wajib diisi.'
        ];
    }
}