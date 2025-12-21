<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_platform' => ['required', 'string', 'max:100'],
            'url_link'      => ['required', 'url', 'max:255'],
            'tipe'          => ['required', 'in:Sosial Media,Portal Khusus'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_platform.required' => 'Nama platform wajib diisi.',
            'nama_platform.string'   => 'Nama platform harus berupa teks.',
            'nama_platform.max'      => 'Nama platform tidak boleh lebih dari 100 karakter.',

            'url_link.required' => 'URL link wajib diisi.',
            'url_link.url'      => 'URL link harus berupa tautan yang valid.',
            'url_link.max'      => 'URL link tidak boleh lebih dari 255 karakter.',

            'tipe.required' => 'Tipe wajib dipilih.',
            'tipe.in'       => 'Tipe hanya boleh bernilai Sosial Media atau Portal Khusus.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_platform' => 'Nama platform',
            'url_link'      => 'URL link',
            'tipe'          => 'Tipe',
        ];
    }
}