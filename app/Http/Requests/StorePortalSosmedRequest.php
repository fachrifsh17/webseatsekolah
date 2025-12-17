<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePortalSosmedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_platform' => 'required|string|max:100',
            'url_link'      => 'required|url|max:255',
            'tipe'          => 'required|in:Sosial Media,Portal Khusus',
        ];
    }
}