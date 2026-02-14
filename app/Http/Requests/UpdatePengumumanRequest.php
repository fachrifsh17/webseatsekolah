<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePengumumanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Mengambil ID dari parameter route pengumuman
        $pengumumanId = $this->route('pengumuman')->id;

        return [
            'judul' => [
                'sometimes', 
                'required', 
                'string', 
                'max:255',
                // Rule unique: cek tabel pengumuman, kolom judul, abaikan ID ini
                Rule::unique('pengumuman', 'judul')->ignore($pengumumanId),
            ],
            'isi_pengumuman'    => ['sometimes', 'required', 'string'],
            'tanggal_publikasi' => ['nullable', 'date'],
            'penting'           => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul pengumuman wajib diisi.',
            'judul.string'   => 'Judul pengumuman harus berupa teks.',
            'judul.max'      => 'Judul pengumuman tidak boleh lebih dari 255 karakter.',
            'judul.unique'   => 'Judul pengumuman ini sudah digunakan, silakan gunakan judul lain.',

            'isi_pengumuman.required' => 'Isi pengumuman wajib diisi.',
            'isi_pengumuman.string'   => 'Isi pengumuman harus berupa teks.',

            'tanggal_publikasi.date' => 'Tanggal publikasi harus berupa tanggal yang valid.',

            'penting.boolean' => 'Status penting harus berupa nilai boolean (true/false).',
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