<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateJadwalProduktifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && (Auth::user()->role === 'admin' || Auth::user()->role === 'guru');
    }

    public function rules(): array
    {
        return [
            'jurusan_id'        => ['sometimes', 'required', 'exists:jurusan,id'],
            'guru_staf_id'      => ['sometimes', 'required', 'exists:guru_staf,id'],
            'judul'             => ['sometimes', 'required', 'string', 'max:255'],
            'penjelasan_jadwal' => ['nullable', 'string'],
            'file_jadwal'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'jurusan_id.required'   => 'Jurusan wajib dipilih.',
            'jurusan_id.exists'     => 'Jurusan tidak valid.',

            'guru_staf_id.required' => 'Guru wajib dipilih.',
            'guru_staf_id.exists'   => 'Guru tidak ditemukan.',

            'judul.required' => 'Judul jadwal wajib diisi.',
            'judul.string'   => 'Judul jadwal harus berupa teks.',
            'judul.max'      => 'Judul jadwal tidak boleh lebih dari 255 karakter.',

            'penjelasan_jadwal.string' => 'Penjelasan jadwal harus berupa teks.',

            'file_jadwal.file'  => 'File jadwal harus berupa file.',
            'file_jadwal.mimes' => 'Format file harus PDF, JPG, atau PNG.',
            'file_jadwal.max'   => 'Ukuran file maksimal 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'jurusan_id'        => 'Jurusan',
            'guru_staf_id'      => 'Guru',
            'judul'             => 'Judul jadwal',
            'penjelasan_jadwal' => 'Penjelasan jadwal',
            'file_jadwal'       => 'File jadwal',
        ];
    }
}