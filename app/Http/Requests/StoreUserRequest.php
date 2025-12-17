<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|max:50|unique:users_admin,username',
            'password' => 'required|string|min:6',
            'nama_lengkap' => 'nullable|string|max:100',
            'role_id' => 'required|integer|exists:roles,id',
        ];
    }
}