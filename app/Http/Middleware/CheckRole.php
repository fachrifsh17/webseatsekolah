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

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan login terlebih dahulu.'
            ], 401);
        }

        $userRole = ($user->role && isset($user->role->role_name)) 
            ? strtolower($user->role->role_name) 
            : null;

        if (!$userRole || !in_array($userRole, array_map('strtolower', $roles))) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk tindakan ini.'
            ], 403);
        }

        return $next($request);
    }
}