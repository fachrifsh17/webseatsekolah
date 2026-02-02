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
            'alamat_lengkap'  => ['required', 'string'],
            'telepon'         => ['required', 'string', 'max:20'],
            'email_resmi'     => ['required', 'email', 'max:100'],
            'peta_embed_code' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'alamat_lengkap.required' => 'Alamat lengkap wajib diisi.',
            'alamat_lengkap.string'   => 'Alamat lengkap harus berupa teks.',

            'telepon.required' => 'Nomor telepon wajib diisi.',
            'telepon.string'   => 'Nomor telepon harus berupa teks.',
            'telepon.max'      => 'Nomor telepon tidak boleh lebih dari 20 karakter.',

            'email_resmi.required' => 'Email resmi wajib diisi.',
            'email_resmi.email'    => 'Email resmi harus berupa alamat email yang valid.',
            'email_resmi.max'      => 'Email resmi tidak boleh lebih dari 100 karakter.',

            'peta_embed_code.string' => 'Kode embed peta harus berupa teks.',
        ];
    }

    public function attributes(): array
    {
        return [
            'alamat_lengkap'  => 'Alamat lengkap',
            'telepon'         => 'Nomor telepon',
            'email_resmi'     => 'Email resmi',
            'peta_embed_code' => 'Kode embed peta',
        ];
    }
}