<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKurikulumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'               => ['required', 'string', 'max:255'],
            'penjelasan_kurikulum'=> ['nullable', 'string'],
            'file_jadwal_path'    => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul kurikulum wajib diisi.',
            'judul.string'   => 'Judul kurikulum harus berupa teks.',
            'judul.max'      => 'Judul kurikulum tidak boleh lebih dari 255 karakter.',

            'penjelasan_kurikulum.string' => 'Penjelasan kurikulum harus berupa teks.',

            'file_jadwal_path.file'  => 'File jadwal harus berupa file.',
            'file_jadwal_path.mimes' => 'Format file hanya boleh PDF, JPG, atau PNG.',
            'file_jadwal_path.max'   => 'Ukuran file maksimal 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'judul'               => 'Judul kurikulum',
            'penjelasan_kurikulum'=> 'Penjelasan kurikulum',
            'file_jadwal_path'    => 'File jadwal kurikulum',
        ];
    }
}