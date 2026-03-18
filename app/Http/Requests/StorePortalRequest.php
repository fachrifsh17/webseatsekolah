<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_platform' => 'required|string|max:255',
            'url_link'      => 'required|url|max:255',
            'tipe'          => 'required|in:Sosial Media,Portal Khusus',
            // icon_class dihapus dari sini
        ];
    }

    public function messages(): array
    {
        return [
            'nama_platform.required' => 'Nama platform wajib diisi.',
            'url_link.required'      => 'URL link wajib diisi.',
            'url_link.url'           => 'Format URL tidak valid. Sertakan https://',
            'tipe.required'          => 'Tipe wajib dipilih.',
            'tipe.in'                => 'Tipe tidak valid.',
        ];
    }
}