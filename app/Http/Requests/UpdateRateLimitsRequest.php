<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRateLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $limitId = $this->route('rate_limit') ? $this->route('rate_limit')->id : null;

        return [
            'key_name'    => ['required', 'string', 'max:128', 'unique:rate_limits,key_name,' . $limitId],
            'attempts'    => ['required', 'integer', 'min:0'],
            'last_attempt'=> ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'key_name.required' => 'Key name wajib diisi.',
            'key_name.string'   => 'Key name harus berupa teks.',
            'key_name.max'      => 'Key name tidak boleh lebih dari 128 karakter.',
            'key_name.unique'   => 'Key name sudah digunakan, silakan pilih nama lain.',

            'attempts.required' => 'Jumlah attempts wajib diisi.',
            'attempts.integer'  => 'Jumlah attempts harus berupa angka.',
            'attempts.min'      => 'Jumlah attempts minimal bernilai 0.',

            'last_attempt.required' => 'Tanggal last attempt wajib diisi.',
            'last_attempt.date'     => 'Tanggal last attempt harus berupa format tanggal yang valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'key_name'    => 'Key name',
            'attempts'    => 'Jumlah attempts',
            'last_attempt'=> 'Tanggal last attempt',
        ];
    }
}