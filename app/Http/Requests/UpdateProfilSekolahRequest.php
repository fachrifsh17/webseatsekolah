<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdateProfilSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_sekolah'    => ['nullable', 'string', 'max:150'],
            'sejarah'         => ['nullable', 'string'],
            'visi'            => ['nullable', 'string'],
            'misi'            => ['nullable', 'string'],
            'npsn'            => ['bail', 'nullable', 'string', 'max:20'],
            'akreditasi'      => ['bail', 'nullable', 'string', 'max:10'],
            'guru_staf_id'    => ['bail', 'nullable', 'string', 'exists:guru_staf,id'],
            'sambutan_kepsek' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_sekolah.string'     => 'Nama sekolah harus berupa teks.',
            'nama_sekolah.max'        => 'Nama sekolah maksimal 150 karakter.',
            'sejarah.string'          => 'Sejarah harus berupa teks.',
            'visi.string'             => 'Visi harus berupa teks.',
            'misi.string'             => 'Misi harus berupa teks.',
            'npsn.string'             => 'NPSN harus berupa teks.',
            'npsn.max'                => 'NPSN maksimal 20 karakter.',
            'akreditasi.string'       => 'Akreditasi harus berupa teks.',
            'akreditasi.max'          => 'Akreditasi maksimal 10 karakter.',
            'guru_staf_id.string'     => 'Guru/Staf ID harus berupa ID string.',
            'guru_staf_id.exists'     => 'Data guru/staf tidak ditemukan.',
            'sambutan_kepsek.string'  => 'Sambutan kepala sekolah harus berupa teks.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_sekolah'    => 'Nama sekolah',
            'sejarah'         => 'Sejarah sekolah',
            'visi'            => 'Visi sekolah',
            'misi'            => 'Misi sekolah',
            'npsn'            => 'NPSN',
            'akreditasi'      => 'Akreditasi',
            'guru_staf_id'    => 'Guru/Staf',
            'sambutan_kepsek' => 'Sambutan kepala sekolah',
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
