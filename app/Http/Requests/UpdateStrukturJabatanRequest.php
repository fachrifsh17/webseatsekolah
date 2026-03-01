<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdateStrukturJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_staf_id'  => ['sometimes', 'required', 'string', 'exists:guru_staf,id'],
            'jabatan_id'    => ['sometimes', 'required', 'exists:jabatans,id'],
            'periode_mulai' => ['sometimes', 'nullable', 'date'],
            'urutan_tampil' => ['sometimes', 'nullable', 'integer'],
            // --- TAMBAHAN VALIDASI TTD ---
            'file_ttd'      => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'], // Maks 2MB
            // ----------------------------
        ];
    }

    public function messages(): array
    {
        return [
            'guru_staf_id.required' => 'Guru/Staf wajib dipilih.',
            'guru_staf_id.string'   => 'Guru/Staf ID harus berupa ID string.',
            'guru_staf_id.exists'   => 'Data guru/staf tidak ditemukan.',

            'jabatan_id.required'   => 'Jabatan wajib dipilih.',
            'jabatan_id.exists'     => 'Data jabatan tidak ditemukan di master jabatan.',

            'periode_mulai.date'    => 'Periode mulai harus berupa tanggal yang valid.',

            'urutan_tampil.integer' => 'Urutan tampil harus berupa angka.',

            // --- TAMBAHAN PESAN VALIDASI TTD ---
            'file_ttd.image'        => 'Tanda tangan harus berupa gambar.',
            'file_ttd.mimes'        => 'Format tanda tangan harus jpeg, png, atau jpg.',
            'file_ttd.max'          => 'Ukuran tanda tangan maksimal 2MB.',
            // ------------------------------------
        ];
    }

    public function attributes(): array
    {
        return [
            'guru_staf_id'  => 'Guru/Staf',
            'jabatan_id'    => 'Jabatan',
            'periode_mulai' => 'Periode mulai',
            'urutan_tampil' => 'Urutan tampil',
            'file_ttd'      => 'Tanda Tangan', // --- TAMBAHAN ATTRIBUTE ---
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}