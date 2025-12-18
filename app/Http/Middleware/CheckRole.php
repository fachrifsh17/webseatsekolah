<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->roles()->whereIn('nama_role', $roles)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses (Role) untuk tindakan ini.'
            ], 403);
        }

        return $next($request);
    }
}