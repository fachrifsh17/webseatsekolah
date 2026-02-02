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
<<<<<<< HEAD
            'judul'     => ['sometimes', 'required', 'string', 'max:150'],
            'file_path' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
=======
            'hari'            => ['sometimes', 'required', 'in:Senin,Selasa,Rabu,Kamis,Jumat'],
            'jam_ke'          => ['sometimes', 'required', 'integer'],
            'waktu_mulai'     => ['sometimes', 'required', 'date_format:H:i'],
            'waktu_selesai'   => ['sometimes', 'required', 'date_format:H:i', 'after:waktu_mulai'],
            'jenis'           => ['sometimes', 'required', 'in:Pelajaran,Istirahat,Kegiatan'],
>>>>>>> master
        ];
    }

    public function messages(): array
    {
        return [
<<<<<<< HEAD
            'judul.required'  => 'Judul wajib diisi.',
            'judul.string'    => 'Judul harus berupa teks.',
            'judul.max'       => 'Judul maksimal 150 karakter.',
            'file_path.file'  => 'File jadwal harus berupa file.',
            'file_path.mimes' => 'Format file harus PDF, JPG, atau PNG.',
            'file_path.max'   => 'Ukuran file maksimal 5MB.',
=======
            'hari.required'            => 'Hari wajib diisi.',
            'hari.in'                  => 'Hari harus salah satu dari Senin sampai Jumat.',
            'jam_ke.required'          => 'Jam ke wajib diisi.',
            'jam_ke.integer'           => 'Jam ke harus berupa angka.',
            'waktu_mulai.required'     => 'Waktu mulai wajib diisi.',
            'waktu_mulai.date_format'  => 'Format waktu mulai harus HH:ii.',
            'waktu_selesai.required'   => 'Waktu selesai wajib diisi.',
            'waktu_selesai.date_format'=> 'Format waktu selesai harus HH:ii.',
            'waktu_selesai.after'      => 'Waktu selesai harus setelah waktu mulai.',
            'jenis.required'           => 'Jenis wajib diisi.',
            'jenis.in'                 => 'Jenis harus Pelajaran, Istirahat, atau Kegiatan.',
>>>>>>> master
        ];
    }

    public function attributes(): array
    {
        return [
<<<<<<< HEAD
            'judul'     => 'Judul',
            'file_path' => 'File jadwal',
=======
            'hari'            => 'Hari',
            'jam_ke'          => 'Jam Ke',
            'waktu_mulai'     => 'Waktu Mulai',
            'waktu_selesai'   => 'Waktu Selesai',
            'jenis'           => 'Jenis',
>>>>>>> master
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> master
