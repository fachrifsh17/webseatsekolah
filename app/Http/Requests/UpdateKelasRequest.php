<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'nama_kelas'      => ['sometimes', 'required', 'string', 'max:50'],
            'jurusan_id'      => ['sometimes', 'required', 'exists:jurusan,id'],
            'tahun_ajaran_id' => ['sometimes', 'required', 'exists:tahun_ajaran,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kelas.required' => 'Nama kelas wajib diisi.',
            'nama_kelas.string'   => 'Nama kelas harus berupa teks.',
            'nama_kelas.max'      => 'Nama kelas tidak boleh lebih dari 50 karakter.',

            'jurusan_id.required' => 'Jurusan wajib dipilih.',
            'jurusan_id.exists'   => 'Jurusan yang dipilih tidak valid.',

            'tahun_ajaran_id.required' => 'Tahun ajaran wajib dipilih.',
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_kelas'      => 'Nama kelas',
            'jurusan_id'      => 'Jurusan',
            'tahun_ajaran_id' => 'Tahun ajaran',
        ];
    }
}