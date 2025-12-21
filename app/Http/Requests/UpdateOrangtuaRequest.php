<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateOrangtuaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && (Auth::user()->role === 'admin' || Auth::user()->role === 'guru');
    }

    public function rules(): array
    {
        $id = $this->route('orangtua');

        return [
            'user_id'   => ['sometimes', 'required', 'exists:users,id', 'unique:orangtua,user_id,' . $id],
            'nama_ayah' => ['sometimes', 'required', 'string', 'max:150'],
            'nama_ibu'  => ['sometimes', 'required', 'string', 'max:150'],
            'no_hp'     => ['sometimes', 'required', 'string', 'max:15'],
            'alamat'    => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID wajib diisi.',
            'user_id.exists'   => 'User ID tidak ditemukan.',
            'user_id.unique'   => 'Akun ini sudah digunakan oleh data orang tua lain.',

            'nama_ayah.required' => 'Nama ayah wajib diisi.',
            'nama_ayah.string'   => 'Nama ayah harus berupa teks.',
            'nama_ayah.max'      => 'Nama ayah tidak boleh lebih dari 150 karakter.',

            'nama_ibu.required' => 'Nama ibu wajib diisi.',
            'nama_ibu.string'   => 'Nama ibu harus berupa teks.',
            'nama_ibu.max'      => 'Nama ibu tidak boleh lebih dari 150 karakter.',

            'no_hp.required' => 'Nomor HP wajib diisi.',
            'no_hp.string'   => 'Nomor HP harus berupa teks.',
            'no_hp.max'      => 'Nomor HP maksimal 15 karakter.',

            'alamat.string' => 'Alamat harus berupa teks.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'   => 'User ID',
            'nama_ayah' => 'Nama ayah',
            'nama_ibu'  => 'Nama ibu',
            'no_hp'     => 'Nomor HP',
            'alamat'    => 'Alamat',
        ];
    }
}