<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;

class UpdateGuruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $guru = $this->route('guru');
        $guruId = is_object($guru) ? $guru->id : $guru;

        return [
            'nip' => [
                'nullable',
                'string',
                'size:18',
                Rule::unique('guru_staf', 'nip')->ignore($guruId),
            ],
            'nuptk' => [
                'nullable',
                'string',
                'size:16',
                Rule::unique('guru_staf', 'nuptk')->ignore($guruId),
            ],
            'nama' => ['sometimes', 'required', 'string', 'max:100'],
            
            // --- VALIDASI KOLOM BARU ---
            'no_hp' => ['nullable', 'string', 'max:20'],
            'email' => [
                'nullable',
                'email',
                'max:100',
                Rule::unique('guru_staf', 'email')->ignore($guruId),
            ],
            'alamat_lengkap' => ['nullable', 'string'],
            'jenis_kelamin' => ['nullable', 'string', 'in:Laki-laki,Perempuan'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:20'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:50'],
            
            'jabatan_fungsional' => ['nullable', 'string', 'max:100'],
            'status_kepegawaian' => ['nullable', 'string', 'max:50'],
            'foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'jurusan_id' => ['nullable', 'string', 'exists:jurusan,id'],
            'is_active' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'nip.size' => 'NIP harus tepat 18 karakter.',
            'nip.unique' => 'NIP sudah terdaftar dalam sistem.',
            'nuptk.size' => 'NUPTK harus tepat 16 karakter.',
            'nuptk.unique' => 'NUPTK sudah terdaftar dalam sistem.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh guru lain.',
            'jenis_kelamin.in' => 'Jenis kelamin harus Laki-laki atau Perempuan.',
            'tanggal_lahir.date' => 'Format tanggal lahir tidak valid.',
            'foto.max' => 'Ukuran foto maksimal 5MB.',
            'foto.mimes' => 'Format foto harus jpg, jpeg, atau png.',
            'nama.required' => 'Nama lengkap wajib diisi.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}