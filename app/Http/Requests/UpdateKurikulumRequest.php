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
            'judul'                => ['required', 'string', 'max:255'],
            'penjelasan_kurikulum' => ['nullable', 'string'],
            'file_jadwal'          => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'is_active'            => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required'    => 'Judul kurikulum wajib diisi.',
            'file_jadwal.mimes' => 'Format file hanya boleh PDF, JPG, JPEG, PNG, atau WEBP.',
            'file_jadwal.max'   => 'Ukuran file maksimal 5MB.',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}