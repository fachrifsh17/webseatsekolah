<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreGuruMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
<<<<<<< HEAD
            'guru_staf_id'      => ['bail', 'required', 'integer', 'exists:guru_staf,id'],
            'mata_pelajaran_id' => ['bail', 'required', 'integer', 'exists:mata_pelajaran,id'],
            'kelas_id'          => ['bail', 'required', 'integer', 'exists:kelas,id'],
=======
            'guru_staf_id'      => ['bail', 'required', 'string', 'exists:guru_staf,id'],
            'mata_pelajaran_id' => ['bail', 'required', 'string', 'exists:mata_pelajaran,id'],
            'kelas_id'          => ['bail', 'required', 'string', 'exists:kelas,id'],
            'hari'              => ['nullable', 'in:Senin,Selasa,Rabu,Kamis,Jumat'],
            'jam_mulai_id'      => ['nullable', 'string', 'exists:jam_sekolah,id'],
            'jam_selesai_id'    => ['nullable', 'string', 'exists:jam_sekolah,id'],
>>>>>>> master
        ];
    }

    public function messages(): array
    {
        return [
            'guru_staf_id.required'      => 'Guru wajib dipilih.',
<<<<<<< HEAD
            'guru_staf_id.integer'       => 'Guru harus berupa angka.',
            'guru_staf_id.exists'        => 'Data guru tidak ditemukan.',
            
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.integer'  => 'Mata pelajaran harus berupa angka.',
            'mata_pelajaran_id.exists'   => 'Data mata pelajaran tidak ditemukan.',
            
            'kelas_id.required'          => 'Kelas wajib dipilih.',
            'kelas_id.integer'           => 'Kelas harus berupa angka.',
            'kelas_id.exists'            => 'Data kelas tidak ditemukan.',
=======
            'guru_staf_id.string'        => 'Guru harus berupa ID string.',
            'guru_staf_id.exists'        => 'Data guru tidak ditemukan.',

            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.string'   => 'Mata pelajaran harus berupa ID string.',
            'mata_pelajaran_id.exists'   => 'Data mata pelajaran tidak ditemukan.',

            'kelas_id.required'          => 'Kelas wajib dipilih.',
            'kelas_id.string'            => 'Kelas harus berupa ID string.',
            'kelas_id.exists'            => 'Data kelas tidak ditemukan.',

            'hari.in'                    => 'Hari harus salah satu dari Senin sampai Jumat.',

            'jam_mulai_id.string'        => 'Jam mulai harus berupa ID string.',
            'jam_mulai_id.exists'        => 'Jam mulai tidak ditemukan dalam sistem.',

            'jam_selesai_id.string'      => 'Jam selesai harus berupa ID string.',
            'jam_selesai_id.exists'      => 'Jam selesai tidak ditemukan dalam sistem.',
>>>>>>> master
        ];
    }

    public function attributes(): array
    {
        return [
            'guru_staf_id'      => 'Guru',
<<<<<<< HEAD
            'mata_pelajaran_id' => 'Mata pelajaran',
            'kelas_id'          => 'Kelas',
        ];
    }
=======
            'mata_pelajaran_id' => 'Mata Pelajaran',
            'kelas_id'          => 'Kelas',
            'hari'              => 'Hari',
            'jam_mulai_id'      => 'Jam Mulai',
            'jam_selesai_id'    => 'Jam Selesai',
        ];
    }

>>>>>>> master
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> master
