<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSekolahSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tagline'              => ['nullable', 'string', 'max:255'],
            'pesan_selamat_datang' => ['nullable', 'string'],
            'buku_poin_path'       => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'no_wa_kesiswaan'      => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'tagline.string'   => 'Tagline harus berupa teks.',
            'tagline.max'      => 'Tagline tidak boleh lebih dari 255 karakter.',

            'pesan_selamat_datang.string' => 'Pesan selamat datang harus berupa teks.',

            'buku_poin_path.file'  => 'Buku poin harus berupa file.',
            'buku_poin_path.mimes' => 'Buku poin hanya boleh dalam format PDF.',
            'buku_poin_path.max'   => 'Ukuran buku poin tidak boleh lebih dari 5 MB.',

            'no_wa_kesiswaan.string' => 'Nomor WhatsApp harus berupa teks.',
            'no_wa_kesiswaan.max'    => 'Nomor WhatsApp tidak boleh lebih dari 20 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tagline'              => 'Tagline',
            'pesan_selamat_datang' => 'Pesan selamat datang',
            'buku_poin_path'       => 'File buku poin',
            'no_wa_kesiswaan'      => 'Nomor WhatsApp kesiswaan',
        ];
    }
}