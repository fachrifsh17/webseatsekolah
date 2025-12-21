<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateJamSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'tahun_ajaran_id' => ['sometimes', 'required', 'exists:tahun_ajaran,id'],
            'semester'        => ['sometimes', 'required', 'in:Ganjil,Genap'],
            'file_path'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'keterangan'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'tahun_ajaran_id.required' => 'Tahun ajaran wajib dipilih.',
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak valid.',

            'semester.required' => 'Semester wajib dipilih.',
            'semester.in'       => 'Pilihan semester hanya Ganjil atau Genap.',

            'file_path.file'  => 'File jadwal harus berupa file.',
            'file_path.mimes' => 'Format file harus PDF, JPG, atau PNG.',
            'file_path.max'   => 'Ukuran file maksimal 5MB.',

            'keterangan.string' => 'Keterangan harus berupa teks.',
            'keterangan.max'    => 'Keterangan tidak boleh lebih dari 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tahun_ajaran_id' => 'Tahun ajaran',
            'semester'        => 'Semester',
            'file_path'       => 'File jadwal sekolah',
            'keterangan'      => 'Keterangan',
        ];
    }
}