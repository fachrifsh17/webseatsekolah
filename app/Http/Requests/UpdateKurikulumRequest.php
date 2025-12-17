<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKurikulumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul' => 'required|string|max:255',
            'penjelasan_kurikulum' => 'nullable|string',
            'file_jadwal_path' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
        ];
    }
}