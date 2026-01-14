<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreStrukturJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_staf_id'  => ['required', 'string', 'exists:guru_staf,id'],
            'jabatan_id'    => ['required', 'exists:jabatans,id'],
            'periode_mulai' => ['nullable', 'date'],
            'urutan_tampil' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_staf_id.required' => 'Guru/Staf wajib dipilih.',
            'guru_staf_id.string'   => 'Guru/Staf ID harus berupa ID string.',
            'guru_staf_id.exists'   => 'Guru/Staf tidak ditemukan dalam sistem.',

            'jabatan_id.required'   => 'Jabatan wajib dipilih.',
            'jabatan_id.exists'     => 'Jabatan tidak ditemukan dalam master data.',

            'periode_mulai.date'    => 'Format periode mulai tidak valid.',
            'urutan_tampil.integer' => 'Urutan tampil harus berupa angka.',
        ];
    }

    public function attributes(): array
    {
        return [
            'guru_staf_id'  => 'Guru/Staf',
            'jabatan_id'    => 'Jabatan',
            'periode_mulai' => 'Periode mulai',
            'urutan_tampil' => 'Urutan tampil',
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