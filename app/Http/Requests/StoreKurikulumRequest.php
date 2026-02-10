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
            'is_active'            => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'is_active' => true,
        ]);
    }

    public function messages(): array
    {
        return [
            'judul.required'    => 'Judul kurikulum wajib diisi.',
            'file_jadwal.mimes' => 'Format file harus PDF atau Gambar.',
            'file_jadwal.max'   => 'Ukuran file maksimal 5MB.',
        ];
    }
}