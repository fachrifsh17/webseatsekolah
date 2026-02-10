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
        $kalenderId = $this->route('kalender')->id;

        return [
            'kegiatan' => [
                'required', 
                'string', 
                'max:255',
             
                Rule::unique('kalender_akademik')->where(function ($query) {
                    return $query->where('tanggal_mulai', $this->tanggal_mulai);
                })->ignore($kalenderId)
            ],
            'tanggal_mulai'   => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'kategori'        => ['required', 'in:Ujian,Libur,Hari Efektif,Akademik'],
        ];
    }

    public function messages(): array
    {
        return [
            'kegiatan.required'        => 'Nama kegiatan wajib diisi.',
            'kegiatan.unique'          => 'Kegiatan dengan nama yang sama sudah ada di tanggal mulai tersebut.',
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
            'kegiatan'        => 'Nama kegiatan',
            'tanggal_mulai'   => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai',
            'kategori'        => 'Kategori kegiatan',
        ];
    }
}