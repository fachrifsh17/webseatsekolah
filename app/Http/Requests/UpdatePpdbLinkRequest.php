<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePpdbLinkRequest extends FormRequest
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
            'url_link.required' => 'Link PPDB wajib diisi.',
            'url_link.url'      => 'Link PPDB harus berupa URL yang valid.',
            'url_link.max'      => 'Link PPDB tidak boleh lebih dari 255 karakter.',

            'status_ppdb.required' => 'Status PPDB wajib dipilih.',
            'status_ppdb.in'       => 'Status PPDB hanya boleh bernilai Buka, Tutup, atau Segera.',
        ];
    }

    public function attributes(): array
    {
        return [
            'url_link'    => 'Link PPDB',
            'status_ppdb' => 'Status PPDB',
        ];
    }
}