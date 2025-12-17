<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfilSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sejarah'           => 'nullable|string',
            'visi'              => 'nullable|string',
            'misi'              => 'nullable|string',
            'npsn'              => 'nullable|string|max:20',
            'akreditasi'        => 'nullable|string|max:10',
            'sambutan_kepsek'   => 'nullable|string',
        ];
    }
}