<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDataKontakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Kolom Alamat yang sudah dipecah
            'alamat_jalan'    => ['required', 'string'],
            'desa_kelurahan'  => ['required', 'string', 'max:100'],
            'kecamatan'       => ['required', 'string', 'max:100'],
            'kabupaten_kota'  => ['required', 'string', 'max:100'],
            'provinsi'        => ['required', 'string', 'max:100'],
            
            // Kontak Lainnya
            'telepon'         => ['required', 'string', 'max:20'],
            'email_resmi'     => ['required', 'email', 'max:100'],
            'peta_embed_code' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'alamat_jalan.required'   => 'Alamat jalan wajib diisi.',
            'desa_kelurahan.required' => 'Desa/Kelurahan wajib diisi.',
            'kecamatan.required'      => 'Kecamatan wajib diisi.',
            'kabupaten_kota.required' => 'Kabupaten/Kota wajib diisi.',
            'provinsi.required'       => 'Provinsi wajib diisi.',

            'telepon.required'        => 'Nomor telepon wajib diisi.',
            'telepon.max'             => 'Nomor telepon tidak boleh lebih dari 20 karakter.',

            'email_resmi.required'    => 'Email resmi wajib diisi.',
            'email_resmi.email'       => 'Format email resmi tidak valid.',
            'email_resmi.max'         => 'Email resmi tidak boleh lebih dari 100 karakter.',

            'peta_embed_code.string'  => 'Kode embed peta harus berupa teks.',
        ];
    }

    public function attributes(): array
    {
        return [
            'alamat_jalan'    => 'Alamat jalan',
            'desa_kelurahan'  => 'Desa/Kelurahan',
            'kecamatan'       => 'Kecamatan',
            'kabupaten_kota'  => 'Kabupaten/Kota',
            'provinsi'        => 'Provinsi',
            'telepon'         => 'Nomor telepon',
            'email_resmi'     => 'Email resmi',
            'peta_embed_code' => 'Kode embed peta',
        ];
    }
}