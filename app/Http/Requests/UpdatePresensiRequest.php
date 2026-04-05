<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdatePresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['sometimes', 'date'],
            'data_presensi' => ['required', 'array', 'min:1'],
            'data_presensi.*.id' => ['nullable'],
            'data_presensi.*.siswa_id' => ['required', 'string'],
            'data_presensi.*.status' => ['required', 'string', 'in:Hadir,Izin,Sakit,Alpa,H,I,S,A'],
            'data_presensi.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'data_presensi.required' => 'Data presensi wajib dikirim.',
            'data_presensi.min' => 'Minimal satu data siswa harus disertakan.',
            'data_presensi.*.siswa_id.required' => 'ID Siswa wajib diisi.',
            'data_presensi.*.status.required' => 'Status presensi wajib diisi.',
            'data_presensi.*.status.in' => 'Status harus Hadir, Izin, Sakit, atau Alpa.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}