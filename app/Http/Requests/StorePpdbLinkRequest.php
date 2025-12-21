<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePpdbLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url_link'    => ['required', 'url', 'max:255'],
            'status_ppdb' => ['required', 'in:Buka,Tutup,Segera'],
        ];
    }

    public function messages(): array
    {
        return [
            'url_link.required'    => 'URL pendaftaran wajib diisi.',
            'url_link.url'         => 'Format link tidak valid (gunakan http:// atau https://).',
            'url_link.max'         => 'URL pendaftaran tidak boleh lebih dari 255 karakter.',
            'status_ppdb.required' => 'Status PPDB wajib ditentukan.',
            'status_ppdb.in'       => 'Status harus berupa: Buka, Tutup, atau Segera.',
        ];
    }

    public function attributes(): array
    {
        return [
            'url_link'    => 'URL pendaftaran',
            'status_ppdb' => 'Status PPDB',
        ];
    }
}