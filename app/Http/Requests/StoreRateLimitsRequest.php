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
            'key_name' => 'required|string|max:128|unique:rate_limits,key_name',
            'attempts' => 'required|integer|min:0',
            'last_attempt' => 'required|date',
        ];
    }
}