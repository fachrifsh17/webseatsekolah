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
            'kegiatan'        => ['required', 'string', 'max:255'],
            'tanggal_mulai'   => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'kategori'        => ['required', 'in:Ujian,Libur,Hari Efektif,Akademik'],
        ];
    }

    public function messages(): array
    {
        return [
            'kegiatan.required'        => 'Nama kegiatan wajib diisi.',
            'kegiatan.string'          => 'Nama kegiatan harus berupa teks.',
            'kegiatan.max'             => 'Nama kegiatan tidak boleh lebih dari 255 karakter.',

            'tanggal_mulai.required'   => 'Tanggal mulai wajib diisi.',
            'tanggal_mulai.date'       => 'Tanggal mulai harus berupa tanggal yang valid.',

            'tanggal_selesai.date'     => 'Tanggal selesai harus berupa tanggal yang valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',

            'kategori.required'        => 'Kategori kegiatan wajib dipilih.',
            'kategori.in'              => 'Kategori harus salah satu dari: Ujian, Libur, Hari Efektif, Akademik.',
        ];
    }

    public function attributes(): array
    {
        return [
            'kegiatan'        => 'Nama kegiatan',
            'tanggal_mulai'   => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai',
            'kategori'        => 'Kategori kegiatan',
        ];
    }
}
