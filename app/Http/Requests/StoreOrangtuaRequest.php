<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrangtuaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'   => ['required', 'integer', 'exists:users,id', 'unique:orangtua,user_id'],
            'nama_ayah' => ['required', 'string', 'max:150'],
            'nama_ibu'  => ['required', 'string', 'max:150'],
            'no_hp'     => ['required', 'string', 'max:15'],
            'alamat'    => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'   => 'Akun pengguna harus dihubungkan.',
            'user_id.integer'    => 'User harus berupa angka.',
            'user_id.exists'     => 'User tidak ditemukan.',
            'user_id.unique'     => 'Akun ini sudah terdaftar sebagai orang tua.',
            'nama_ayah.required' => 'Nama ayah wajib diisi.',
            'nama_ayah.string'   => 'Nama ayah harus berupa teks.',
            'nama_ayah.max'      => 'Nama ayah tidak boleh lebih dari 150 karakter.',
            'nama_ibu.required'  => 'Nama ibu wajib diisi.',
            'nama_ibu.string'    => 'Nama ibu harus berupa teks.',
            'nama_ibu.max'       => 'Nama ibu tidak boleh lebih dari 150 karakter.',
            'no_hp.required'     => 'Nomor HP aktif wajib diisi.',
            'no_hp.string'       => 'Nomor HP harus berupa teks.',
            'no_hp.max'          => 'Nomor HP maksimal 15 karakter.',
            'alamat.string'      => 'Alamat harus berupa teks.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'   => 'Akun pengguna',
            'nama_ayah' => 'Nama ayah',
            'nama_ibu'  => 'Nama ibu',
            'no_hp'     => 'Nomor HP',
            'alamat'    => 'Alamat',
        ];
    }
}