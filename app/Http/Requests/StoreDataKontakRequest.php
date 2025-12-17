<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDataKontakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alamat_lengkap' => 'required|string',
            'telepon'        => 'required|string|max:20',
            'email_resmi'    => 'required|email|max:100',
            'peta_embed_code'=> 'nullable|string',
        ];
    }
}