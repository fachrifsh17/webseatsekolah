<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKontakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alamat_lengkap' => 'nullable|string',
            'telepon' => 'nullable|string|max:20',
            'email_resmi' => 'nullable|email|max:100',
            'peta_embed_code' => 'nullable|string',
        ];
    }
}