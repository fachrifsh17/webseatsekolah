<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingRequest extends FormRequest
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
            'logo'                 => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'tagline.string' => 'Tagline harus berupa teks.',
            'tagline.max'    => 'Tagline tidak boleh lebih dari 255 karakter.',

            'pesan_selamat_datang.string' => 'Pesan selamat datang harus berupa teks.',

            'logo.image' => 'Logo harus berupa gambar.',
            'logo.mimes' => 'Format logo hanya boleh JPEG, PNG, JPG, GIF, atau SVG.',
            'logo.max'   => 'Ukuran logo maksimal 2MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tagline'              => 'Tagline',
            'pesan_selamat_datang' => 'Pesan selamat datang',
            'logo'                 => 'Logo',
        ];
    }
}