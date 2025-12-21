<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKalenderAkademikRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul_kegiatan'  => ['required', 'string', 'max:255'],
            'tanggal_mulai'   => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'tahun_akademik'  => ['required', 'string', 'max:15'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul_kegiatan.required' => 'Judul kegiatan wajib diisi.',
            'judul_kegiatan.string'   => 'Judul kegiatan harus berupa teks.',
            'judul_kegiatan.max'      => 'Judul kegiatan tidak boleh lebih dari 255 karakter.',

            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_mulai.date'     => 'Tanggal mulai harus berupa tanggal yang valid.',

            'tanggal_selesai.date'            => 'Tanggal selesai harus berupa tanggal yang valid.',
            'tanggal_selesai.after_or_equal'  => 'Tanggal selesai harus sama atau setelah tanggal mulai.',

            'tahun_akademik.required' => 'Tahun akademik wajib diisi.',
            'tahun_akademik.string'   => 'Tahun akademik harus berupa teks.',
            'tahun_akademik.max'      => 'Tahun akademik tidak boleh lebih dari 15 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'judul_kegiatan'  => 'Judul kegiatan',
            'tanggal_mulai'   => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai',
            'tahun_akademik'  => 'Tahun akademik',
        ];
    }
}