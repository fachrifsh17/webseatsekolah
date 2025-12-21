<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePesanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255'],
            'subjek'       => ['nullable', 'string', 'max:255'],
            'pesan'        => ['required', 'string', 'min:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nama_lengkap.string'   => 'Nama lengkap harus berupa teks.',
            'nama_lengkap.max'      => 'Nama lengkap tidak boleh lebih dari 255 karakter.',
            'email.required'        => 'Alamat email wajib diisi.',
            'email.email'           => 'Format email tidak valid.',
            'email.max'             => 'Email tidak boleh lebih dari 255 karakter.',
            'subjek.string'         => 'Subjek harus berupa teks.',
            'subjek.max'            => 'Subjek tidak boleh lebih dari 255 karakter.',
            'pesan.required'        => 'Isi pesan tidak boleh kosong.',
            'pesan.string'          => 'Isi pesan harus berupa teks.',
            'pesan.min'             => 'Pesan terlalu pendek, minimal 10 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_lengkap' => 'Nama lengkap',
            'email'        => 'Alamat email',
            'subjek'       => 'Subjek',
            'pesan'        => 'Isi pesan',
        ];
    }
}