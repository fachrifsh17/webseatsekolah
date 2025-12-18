<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePpdbRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url_link' => 'required|url|max:255',
            'status_ppdb' => 'nullable|in:Buka,Tutup,Segera',
        ];
    }
}