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
            'tahun_ajaran_id' => [
                'nullable', 
                'string', 
                'exists:tahun_ajaran,id'
            ],
            'kegiatan' => [
                'required', 
                'string', 
                'max:255',
                Rule::unique('kalender_akademik')->where(function ($query) {
                    return $query->where('tanggal_mulai', $this->tanggal_mulai)
                                 ->where('tahun_ajaran_id', $this->tahun_ajaran_id ?? $this->route('kalender')->tahun_ajaran_id);
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
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak valid.',
            'kegiatan.required'        => 'Nama kegiatan wajib diisi.',
            'kegiatan.unique'          => 'Kegiatan dengan nama yang sama sudah ada di tanggal dan tahun ajaran tersebut.',
            'kegiatan.max'             => 'Nama kegiatan maksimal 255 karakter.',
            'tanggal_mulai.required'   => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'kategori.required'        => 'Kategori kegiatan wajib dipilih.',
            'kategori.in'              => 'Kategori tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tahun_ajaran_id' => 'Tahun ajaran',
            'kegiatan'        => 'Nama kegiatan',
            'tanggal_mulai'   => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai',
            'kategori'        => 'Kategori kegiatan',
        ];
    }
}