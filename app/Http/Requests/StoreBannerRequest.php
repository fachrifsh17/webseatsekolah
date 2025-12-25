<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'        => ['required', 'string', 'max:255'],
            'url_link'     => ['nullable', 'url', 'max:255'],
            'aktif_sampai' => ['nullable', 'date'],
            'foto'         => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required'    => 'Judul banner wajib diisi.',
            'judul.string'      => 'Judul banner harus berupa teks.',
            'judul.max'         => 'Judul banner tidak boleh lebih dari 255 karakter.',
            'url_link.url'      => 'Format link URL tidak valid.',
            'url_link.max'      => 'Link URL tidak boleh lebih dari 255 karakter.',
            'aktif_sampai.date' => 'Tanggal aktif sampai harus berupa format tanggal yang valid.',
            'foto.required'     => 'File gambar banner wajib diunggah.',
            'foto.image'        => 'File harus berupa gambar.',
            'foto.mimes'        => 'Format gambar yang didukung: JPG, JPEG, PNG, dan WEBP.',
            'foto.max'          => 'Ukuran gambar maksimal adalah 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'judul'        => 'Judul banner',
            'url_link'     => 'Link URL',
            'aktif_sampai' => 'Tanggal aktif sampai',
            'foto'         => 'Foto banner',
        ];
    }
}