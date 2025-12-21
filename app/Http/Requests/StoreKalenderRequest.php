<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKalenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kegiatan'        => ['required', 'string', 'max:255'],
            'tanggal_mulai'   => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'kategori'        => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'kegiatan.required'              => 'Nama kegiatan wajib diisi.',
            'kegiatan.string'                => 'Nama kegiatan harus berupa teks.',
            'kegiatan.max'                   => 'Nama kegiatan tidak boleh lebih dari 255 karakter.',
            'tanggal_mulai.required'         => 'Tanggal mulai kegiatan harus diisi.',
            'tanggal_mulai.date'             => 'Format tanggal mulai tidak valid.',
            'tanggal_selesai.date'           => 'Tanggal selesai harus berupa format tanggal yang valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
            'kategori.string'                => 'Kategori harus berupa teks.',
            'kategori.max'                   => 'Kategori maksimal 100 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'kegiatan'        => 'Nama kegiatan',
            'tanggal_mulai'   => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai',
            'kategori'        => 'Kategori kegiatan',
        ];
    }
}