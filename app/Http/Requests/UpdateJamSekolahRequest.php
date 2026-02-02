<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdateJamSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hari'          => ['sometimes', 'required', 'in:Senin,Selasa,Rabu,Kamis,Jumat'],
            'jam_ke'        => ['sometimes', 'required', 'integer'],
            'waktu_mulai'   => ['sometimes', 'required', 'date_format:H:i'],
            'waktu_selesai' => ['sometimes', 'required', 'date_format:H:i', 'after:waktu_mulai'],
            'jenis'         => ['sometimes', 'required', 'in:Pelajaran,Istirahat,Kegiatan'],
        ];
    }

    public function messages(): array
    {
        return [
            'hari.required'             => 'Hari wajib diisi.',
            'hari.in'                   => 'Hari harus salah satu dari Senin sampai Jumat.',
            'jam_ke.required'           => 'Jam ke wajib diisi.',
            'jam_ke.integer'            => 'Jam ke harus berupa angka.',
            'waktu_mulai.required'      => 'Waktu mulai wajib diisi.',
            'waktu_mulai.date_format'   => 'Format waktu mulai harus HH:ii.',
            'waktu_selesai.required'    => 'Waktu selesai wajib diisi.',
            'waktu_selesai.date_format' => 'Format waktu selesai harus HH:ii.',
            'waktu_selesai.after'       => 'Waktu selesai harus setelah waktu mulai.',
            'jenis.required'            => 'Jenis wajib diisi.',
            'jenis.in'                  => 'Jenis harus Pelajaran, Istirahat, atau Kegiatan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'hari'          => 'Hari',
            'jam_ke'        => 'Jam Ke',
            'waktu_mulai'   => 'Waktu Mulai',
            'waktu_selesai' => 'Waktu Selesai',
            'jenis'         => 'Jenis',
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