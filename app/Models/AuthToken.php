<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthToken extends Model
{
    use HasFactory;

    protected $table = 'auth_tokens';

    protected $fillable = [
        'user_id',
        'token_hash',
        'refresh_token',
        'expires_at',
        'refresh_expires_at',
        'revoked',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}