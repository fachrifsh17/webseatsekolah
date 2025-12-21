<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateSekolahSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'tagline'              => ['nullable', 'string', 'max:255'],
            'logo'                 => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'pesan_selamat_datang' => ['nullable', 'string'],
            'buku_poin_path'       => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'no_wa_kesiswaan'      => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'tagline.string' => 'Tagline harus berupa teks.',
            'tagline.max'    => 'Tagline tidak boleh lebih dari 255 karakter.',

            'logo.file'  => 'Logo harus berupa file.',
            'logo.mimes' => 'Logo harus berupa format PNG, JPG, atau JPEG.',
            'logo.max'   => 'Ukuran logo maksimal 2MB.',

            'pesan_selamat_datang.string' => 'Pesan selamat datang harus berupa teks.',

            'buku_poin_path.file'  => 'Buku poin harus berupa file.',
            'buku_poin_path.mimes' => 'Buku poin harus berupa format PDF.',
            'buku_poin_path.max'   => 'Ukuran buku poin maksimal 5MB.',

            'no_wa_kesiswaan.string' => 'Nomor WhatsApp harus berupa teks.',
            'no_wa_kesiswaan.max'    => 'Nomor WhatsApp maksimal 20 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tagline'              => 'Tagline sekolah',
            'logo'                 => 'Logo sekolah',
            'pesan_selamat_datang' => 'Pesan selamat datang',
            'buku_poin_path'       => 'Buku poin',
            'no_wa_kesiswaan'      => 'Nomor WhatsApp kesiswaan',
        ];
    }
}