<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RateLimitsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'key_name' => $this->key_name,
            'attempts' => (int) $this->attempts,
            'last_attempt' => $this->last_attempt,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}