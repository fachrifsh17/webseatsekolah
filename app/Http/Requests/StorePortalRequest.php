<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePortalRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        // Mengambil ID portal dari route jika sedang proses update
        $portalId = $this->route('portal'); 

        return [
            'nama_platform' => 'required|string|max:255|unique:portal_sosmed,nama_platform,' . $portalId,
            'url_link' => 'required|url|max:255',
            'tipe' => 'required|in:Sosial Media,Website,Portal Lain', 
        ];
    }
}