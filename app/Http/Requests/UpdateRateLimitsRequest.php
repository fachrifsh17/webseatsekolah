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
            'key_name' => 'required|string|max:128|unique:rate_limits,key_name,' . $limitId,
            'attempts' => 'required|integer|min:0',
            'last_attempt' => 'required|date',
        ];
    }
}