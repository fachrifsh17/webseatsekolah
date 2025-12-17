<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource, 
            'token_type' => 'Bearer',
            'expires_at' => $this->when(isset($this->expires_at), function () {
                return $this->expires_at;
            }),
            'user' => $this->whenLoaded('user', function () {
                return new UserResource($this->user); 
            }),
            'created_at' => $this->created_at,
        ];
    }
}