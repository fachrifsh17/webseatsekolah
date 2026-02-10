<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreKalenderAkademikRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kegiatan' => [
                'required', 
                'string', 
                'max:255',
                Rule::unique('kalender_akademik')->where(function ($query) {
                    return $query->where('tanggal_mulai', $this->tanggal_mulai);
                }),
            ],
            'tanggal_mulai'   => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'kategori'        => ['required', 'in:Akademik,Libur,Ujian,Event'],
        ];
    }

    public function messages(): array
    {
        return [
            'kegiatan.required'              => 'Nama kegiatan akademik wajib diisi.',
            'kegiatan.string'                => 'Nama kegiatan harus berupa teks.',
            'kegiatan.max'                   => 'Nama kegiatan tidak boleh lebih dari 255 karakter.',
            'kegiatan.unique'                => 'Kegiatan dengan nama ini sudah terdaftar di tanggal tersebut.',
            'tanggal_mulai.required'         => 'Tanggal mulai harus ditentukan.',
            'tanggal_mulai.date'             => 'Format tanggal mulai tidak valid.',
            'tanggal_selesai.date'           => 'Tanggal selesai harus berupa format tanggal yang valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.',
            'kategori.required'              => 'Kategori kegiatan wajib dipilih.',
            'kategori.in'                    => 'Kategori harus salah satu dari: Akademik, Libur, Ujian, Event.',
        ];
    }

    public function attributes(): array
    {
        return [
            'kegiatan'        => 'Nama kegiatan akademik',
            'tanggal_mulai'   => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai',
            'kategori'        => 'Kategori kegiatan',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal atau kegiatan sudah ada.',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}