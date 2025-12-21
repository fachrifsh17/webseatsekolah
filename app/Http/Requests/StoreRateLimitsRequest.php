<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRateLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key_name'    => ['required', 'string', 'max:128', 'unique:rate_limits,key_name'],
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
            'attempts.integer'  => 'Attempts harus berupa angka.',
            'attempts.min'      => 'Attempts tidak boleh bernilai negatif.',
            'last_attempt.required' => 'Tanggal attempt terakhir wajib diisi.',
            'last_attempt.date'     => 'Format tanggal attempt terakhir tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'key_name'    => 'Key name',
            'attempts'    => 'Jumlah attempts',
            'last_attempt'=> 'Tanggal attempt terakhir',
        ];
    }
}