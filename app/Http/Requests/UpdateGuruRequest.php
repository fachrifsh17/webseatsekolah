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
            'user_id' => [
                'sometimes',
                'required',
                'string',
                'exists:users,id',
                Rule::unique('guru_staf', 'user_id')->ignore($guruId),
            ],
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
            'user_id.unique' => 'Akun user ini sudah dipakai guru lain.',
            'foto.max' => 'Ukuran foto maksimal 5MB.',
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