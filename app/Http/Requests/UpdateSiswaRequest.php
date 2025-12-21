<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('siswa')->id ?? null;

        return [
            'user_id'      => 'sometimes|required|exists:users,id|unique:siswas,user_id,' . $id,
            'nis'          => 'sometimes|required|string|max:20|unique:siswas,nis,' . $id,
            'nama_lengkap' => 'sometimes|required|string|max:150',
            'kelas_id'     => 'sometimes|required|exists:kelas,id',
            'jurusan_id'   => 'sometimes|required|exists:jurusan,id',
            'orangtua_id'  => 'nullable|exists:orangtua,id',
            'foto'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ];
    }
}