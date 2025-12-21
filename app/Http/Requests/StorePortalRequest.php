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
        $portalId = $this->route('portal');  

        return [
            'nama_platform' => 'required|string|max:255|unique:portal_sosmed,nama_platform,' . $portalId,
            'url_link'      => 'required|url|max:255',
            'tipe'          => 'required|in:Sosial Media,Website,Portal Lain',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_platform.required' => 'Nama platform wajib diisi.',
            'nama_platform.string'   => 'Nama platform harus berupa teks.',
            'nama_platform.max'      => 'Nama platform tidak boleh lebih dari 255 karakter.',
            'nama_platform.unique'   => 'Nama platform sudah terdaftar, silakan gunakan nama lain.',
            'url_link.required'      => 'URL link wajib diisi.',
            'url_link.url'           => 'Format URL tidak valid.',
            'url_link.max'           => 'URL link tidak boleh lebih dari 255 karakter.',
            'tipe.required'          => 'Tipe platform wajib dipilih.',
            'tipe.in'                => 'Tipe platform harus salah satu dari: Sosial Media, Website, atau Portal Lain.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_platform' => 'Nama platform',
            'url_link'      => 'URL link',
            'tipe'          => 'Tipe platform',
        ];
    }
}