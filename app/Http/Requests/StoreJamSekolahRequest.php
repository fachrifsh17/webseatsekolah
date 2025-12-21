<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJamSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajaran,id'],
            'semester'        => ['required', 'in:Ganjil,Genap'],
            'file_path'       => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'keterangan'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'tahun_ajaran_id.required' => 'Tahun ajaran wajib dipilih.',
            'tahun_ajaran_id.integer'  => 'Tahun ajaran harus berupa angka.',
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak ditemukan.',
            'semester.required'        => 'Semester wajib ditentukan.',
            'semester.in'              => 'Pilihan semester harus Ganjil atau Genap.',
            'file_path.required'       => 'File jadwal jam sekolah wajib diunggah.',
            'file_path.file'           => 'File jadwal harus berupa file.',
            'file_path.mimes'          => 'Format file harus PDF, JPG, JPEG, PNG, atau WEBP.',
            'file_path.max'            => 'Ukuran file maksimal adalah 5MB.',
            'keterangan.string'        => 'Keterangan harus berupa teks.',
            'keterangan.max'           => 'Keterangan tidak boleh lebih dari 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tahun_ajaran_id' => 'Tahun ajaran',
            'semester'        => 'Semester',
            'file_path'       => 'File jadwal jam sekolah',
            'keterangan'      => 'Keterangan',
        ];
    }
}