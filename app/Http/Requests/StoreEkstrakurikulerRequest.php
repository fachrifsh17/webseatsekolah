<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEkstrakurikulerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_ekskul' => 'required|string|max:100',
            'deskripsi'   => 'nullable|string',
            'hari'        => 'nullable|string|max:50',
            'jam_mulai'   => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'pembina_id'  => 'nullable|integer|exists:guru_staf,id',
            'foto'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'keterangan'  => 'nullable|string|max:255',
        ];
    }
}