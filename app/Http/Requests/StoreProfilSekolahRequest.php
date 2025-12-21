<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProfilSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya admin yang boleh mengubah profil sekolah
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'sejarah'         => ['nullable', 'string'],
            'visi'            => ['nullable', 'string'],
            'misi'            => ['nullable', 'string'],
            'npsn'            => ['nullable', 'string', 'max:20'],
            'akreditasi'      => ['nullable', 'string', 'max:10'],
            'guru_staf_id'    => ['nullable', 'integer', 'exists:guru_staf,id'],
            'sambutan_kepsek' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'sejarah.string'       => 'Sejarah sekolah harus berupa teks.',
            'visi.string'          => 'Visi sekolah harus berupa teks.',
            'misi.string'          => 'Misi sekolah harus berupa teks.',
            'npsn.string'          => 'NPSN harus berupa teks.',
            'npsn.max'             => 'NPSN maksimal 20 karakter.',
            'akreditasi.string'    => 'Akreditasi harus berupa teks.',
            'akreditasi.max'       => 'Akreditasi maksimal 10 karakter.',
            'guru_staf_id.integer' => 'ID guru harus berupa angka.',
            'guru_staf_id.exists'  => 'Data guru tidak ditemukan.',
            'sambutan_kepsek.string' => 'Sambutan kepala sekolah harus berupa teks.',
        ];
    }

    public function attributes(): array
    {
        return [
            'sejarah'         => 'Sejarah sekolah',
            'visi'            => 'Visi sekolah',
            'misi'            => 'Misi sekolah',
            'npsn'            => 'NPSN',
            'akreditasi'      => 'Akreditasi',
            'guru_staf_id'    => 'Guru staf',
            'sambutan_kepsek' => 'Sambutan kepala sekolah',
        ];
    }
}