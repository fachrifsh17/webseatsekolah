<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKurikulumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'                => ['required', 'string', 'max:255'],
            'penjelasan_kurikulum' => ['nullable', 'string'],
            'file_jadwal'          => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required'            => 'Judul kurikulum wajib diisi.',
            'judul.string'              => 'Judul kurikulum harus berupa teks.',
            'judul.max'                 => 'Judul maksimal 255 karakter.',
            'penjelasan_kurikulum.string' => 'Penjelasan kurikulum harus berupa teks.',
            'file_jadwal.file'          => 'File jadwal harus berupa file.',
            'file_jadwal.mimes'         => 'Format file harus berupa PDF, JPG, JPEG, PNG, atau WEBP.',
            'file_jadwal.max'           => 'Ukuran file maksimal adalah 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'judul'                => 'Judul kurikulum',
            'penjelasan_kurikulum' => 'Penjelasan kurikulum',
            'file_jadwal'          => 'File jadwal kurikulum',
        ];
    }
}
