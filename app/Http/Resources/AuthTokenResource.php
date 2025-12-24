<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->access_token,
            'refresh_token' => $this->refresh_token, 
            'token_type' => 'Bearer',
            'expires_at' => $this->expires_at,
            'refresh_expires_at' => $this->refresh_expires_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at,
        ];
    }
}