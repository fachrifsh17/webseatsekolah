<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateJadwalProduktifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if (!$user) {
            return;
        }

        $isAdmin = ($user->role ?? null) === 'admin';

        if (!$isAdmin) {
            $guruId = $user->guruStaf?->id ?? $user->guru_staf_id ?? $user->guru_id ?? null;
            if ($guruId) {
                $this->merge(['guru_staf_id' => $guruId]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'jurusan_id'        => ['bail','sometimes','required','integer','exists:jurusan,id'],
            'guru_staf_id'      => ['bail','sometimes','required','integer','exists:guru_staf,id'],
            'judul'             => ['bail','sometimes','required','string','max:255'],
            'penjelasan_jadwal' => ['nullable','string'],
            'file_jadwal_path'  => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'jurusan_id.required'       => 'Jurusan wajib dipilih.',
            'jurusan_id.integer'        => 'Jurusan harus berupa angka.',
            'jurusan_id.exists'         => 'Jurusan tidak valid.',
            'guru_staf_id.required'     => 'Guru pengampu wajib dipilih.',
            'guru_staf_id.integer'      => 'Guru pengampu harus berupa angka.',
            'guru_staf_id.exists'       => 'Guru pengampu tidak ditemukan.',
            'judul.required'            => 'Judul jadwal wajib diisi.',
            'judul.string'              => 'Judul jadwal harus berupa teks.',
            'judul.max'                 => 'Judul jadwal tidak boleh lebih dari 255 karakter.',
            'penjelasan_jadwal.string'  => 'Penjelasan jadwal harus berupa teks.',
            'file_jadwal_path.file'     => 'File jadwal harus berupa file.',
            'file_jadwal_path.mimes'    => 'Format file harus PDF, JPG, JPEG, PNG, atau WEBP.',
            'file_jadwal_path.max'      => 'Ukuran file maksimal 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'jurusan_id'        => 'Jurusan',
            'guru_staf_id'      => 'Guru pengampu',
            'judul'             => 'Judul jadwal',
            'penjelasan_jadwal' => 'Penjelasan jadwal',
            'file_jadwal_path'  => 'File jadwal',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], 422));
    }
}
