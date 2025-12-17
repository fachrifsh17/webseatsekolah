<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSekolahSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tagline' => 'nullable|string|max:255',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
            'pesan_selamat_datang' => 'nullable|string',
        ];
    }
}