<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Semua user diizinkan, bisa diganti dengan logika otorisasi jika perlu
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'        => ['sometimes', 'required', 'string', 'max:255'],
            'url_link'     => ['nullable', 'url', 'max:255'],
            'aktif_sampai' => ['nullable', 'date'],
            // 'foto' bersifat opsional ('nullable'), harus berupa file gambar, maks 5MB.
            'foto'         => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul banner wajib diisi.',
            'judul.string'   => 'Judul banner harus berupa teks.',
            'judul.max'      => 'Judul banner tidak boleh lebih dari 255 karakter.',

            'url_link.url' => 'URL link harus berupa tautan yang valid.',
            'url_link.max' => 'URL link tidak boleh lebih dari 255 karakter.',

            'aktif_sampai.date' => 'Format tanggal aktif sampai tidak valid.',

            'foto.file'  => 'Foto harus berupa file.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.max'   => 'Ukuran foto maksimal 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'judul'        => 'Judul banner',
            'url_link'     => 'URL link',
            'aktif_sampai' => 'Tanggal aktif sampai',
            'foto'         => 'Foto banner',
        ];
    }
}