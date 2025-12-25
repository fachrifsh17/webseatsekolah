<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePengumumanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'             => ['required', 'string', 'max:255'],
            'isi_pengumuman'    => ['required', 'string'],
            'tanggal_publikasi' => ['nullable', 'date'],
            'penting'           => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required'          => 'Judul pengumuman wajib diisi.',
            'judul.string'            => 'Judul pengumuman harus berupa teks.',
            'judul.max'               => 'Judul maksimal 255 karakter.',
            'isi_pengumuman.required' => 'Isi pengumuman tidak boleh kosong.',
            'isi_pengumuman.string'   => 'Isi pengumuman harus berupa teks.',
            'tanggal_publikasi.date'  => 'Format tanggal publikasi tidak valid.',
            'penting.boolean'         => 'Status penting harus berupa pilihan benar atau salah.',
        ];
    }

    public function attributes(): array
    {
        return [
            'judul'             => 'Judul pengumuman',
            'isi_pengumuman'    => 'Isi pengumuman',
            'tanggal_publikasi' => 'Tanggal publikasi',
            'penting'           => 'Status penting',
        ];
    }
}