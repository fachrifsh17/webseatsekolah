<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKalenderAkademikRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kalender = $this->route('kalender');
        $kalenderId = is_object($kalender) ? $kalender->id : $kalender;

        return [
            // PERUBAHAN: Ganti tahun_ajaran_id ke semester_id
            'semester_id' => [
                'nullable', 
                'integer', // Biasanya ID berupa integer
                'exists:semesters,id' // Pastikan nama tabelnya 'semesters'
            ],
            'kegiatan' => [
                'required', 
                'string', 
                'max:255',
                // PERUBAHAN: Ganti pengecekan unik ke semester_id
                Rule::unique('kalender_akademik')->where(function ($query) use ($kalender) {
                    return $query->where('tanggal_mulai', $this->tanggal_mulai)
                                 ->where('semester_id', $this->semester_id ?? $kalender->semester_id);
                })->ignore($kalenderId)
            ],
            'tanggal_mulai'   => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'kategori'        => ['required', 'in:Ujian,Libur,Hari Efektif,Akademik,Event'],
        ];
    }

    public function messages(): array
    {
        return [
            // PERUBAHAN: Update pesan kesalahan
            'semester_id.exists'   => 'Semester tidak valid.',
            'kegiatan.required'    => 'Nama kegiatan wajib diisi.',
            'kegiatan.unique'      => 'Kegiatan dengan nama yang sama sudah ada di tanggal dan semester tersebut.',
            'kegiatan.max'         => 'Nama kegiatan maksimal 255 karakter.',
            'tanggal_mulai.required'   => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'kategori.required'    => 'Kategori kegiatan wajib dipilih.',
            'kegiatan.in'          => 'Kategori tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            // PERUBAHAN: Update atribut
            'semester_id'   => 'Semester',
            'kegiatan'      => 'Nama kegiatan',
            'tanggal_mulai'   => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai',
            'kategori'      => 'Kategori kegiatan',
        ];
    }
}