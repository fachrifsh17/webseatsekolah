<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StorePresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'siswa_id'   => ['required', 'string', 'exists:siswa,id'],
            
            // UBAH: dari 'required' menjadi 'sometimes' atau 'nullable'
            // Ini agar validator tidak protes jika tanggal tidak dikirim di Postman
            'tanggal'    => ['sometimes', 'date'], 
            
            'status'     => ['required', 'in:Hadir,Izin,Sakit,Alpa'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'siswa_id.required' => 'Siswa harus dipilih.',
            'siswa_id.string'   => 'ID siswa harus berupa ID string.',
            'siswa_id.exists'   => 'Data siswa tidak ditemukan.',

            // Pesan ini hanya akan muncul jika Admin mengisi tanggal tapi formatnya salah
            'tanggal.date'      => 'Format tanggal absensi tidak valid.',

            'status.required'   => 'Status kehadiran harus diisi.',
            'status.in'         => 'Status harus berupa Hadir, Izin, Sakit, atau Alpa.',

            'keterangan.string' => 'Keterangan harus berupa teks.',
            'keterangan.max'    => 'Keterangan tidak boleh lebih dari 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'siswa_id'   => 'Siswa',
            'tanggal'    => 'Tanggal absensi',
            'status'     => 'Status kehadiran',
            'keterangan' => 'Keterangan',
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