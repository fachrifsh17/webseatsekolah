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
            'cadis'           => ['nullable', 'string', 'max:100'],
            'logo'            => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'sejarah'         => ['nullable', 'string'],
            'visi'            => ['nullable', 'string'],
            'misi'            => ['nullable', 'string'],
            'npsn'            => ['bail', 'nullable', 'string', 'max:20'],
            'akreditasi'      => ['bail', 'nullable', 'string', 'max:10'],
            'sambutan_kepsek' => ['nullable', 'string'],
            // guru_staf_id dihapus karena otomatis dari struktur jabatan
        ];
    }

    public function messages(): array
    {
        return [
            'nama_sekolah.string'     => 'Nama sekolah harus berupa teks.',
            'nama_sekolah.max'        => 'Nama sekolah maksimal 150 karakter.',
            'cadis.string'            => 'Cabang dinas harus berupa teks.',
            'cadis.max'               => 'Cabang dinas maksimal 100 karakter.',
            'logo.image'              => 'File harus berupa gambar.',
            'logo.mimes'              => 'Format logo harus jpeg, png, atau jpg.',
            'logo.max'                => 'Ukuran logo maksimal 2MB.',
            'sejarah.string'          => 'Sejarah harus berupa teks.',
            'visi.string'             => 'Visi harus berupa teks.',
            'misi.string'             => 'Misi harus berupa teks.',
            'npsn.string'             => 'NPSN harus berupa teks.',
            'npsn.max'                => 'NPSN maksimal 20 karakter.',
            'akreditasi.string'       => 'Akreditasi harus berupa teks.',
            'akreditasi.max'          => 'Akreditasi maksimal 10 karakter.',
            'sambutan_kepsek.string'  => 'Sambutan kepala sekolah harus berupa teks.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_sekolah'    => 'Nama sekolah',
            'cadis'           => 'Cabang dinas',
            'logo'            => 'Logo sekolah',
            'sejarah'         => 'Sejarah sekolah',
            'visi'            => 'Visi sekolah',
            'misi'            => 'Misi sekolah',
            'npsn'            => 'NPSN',
            'akreditasi'      => 'Akreditasi',
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