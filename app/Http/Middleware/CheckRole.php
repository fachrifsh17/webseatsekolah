<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan login terlebih dahulu.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $raw = implode(',', $roles);
        $requireAll = false;

        if (str_starts_with($raw, 'all:')) {
            $requireAll = true;
            $raw = substr($raw, 4);
        }

        $allowedRoles = $this->normalizeRoles([$raw]);

        if (empty($allowedRoles)) {
            return $this->ensureResponse($next($request));
        }

        try {
            $rolesCollection = $user->relationLoaded('roles') ? $user->roles : ($user->roles()->get() ?? collect());
        } catch (\Throwable $e) {
            $rolesCollection = collect();
        }

        $rolesCollection = collect($rolesCollection);

        if ($rolesCollection->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak memiliki role apa pun.'
            ], Response::HTTP_FORBIDDEN);
        }

        $userRoleIdentifiers = $rolesCollection->map(function ($r) {
            if (is_string($r)) {
                return strtolower(trim($r));
            }

            $value = null;
            if (!empty($r->slug)) $value = $r->slug;
            elseif (!empty($r->name)) $value = $r->name;
            elseif (!empty($r->role_name)) $value = $r->role_name;
            elseif (!empty($r->nama)) $value = $r->nama;
            elseif (isset($r->id)) $value = (string) $r->id;

            return $value !== null ? strtolower((string) $value) : null;
        })->filter()->unique()->values()->all();

        if ($requireAll) {
            $missing = array_diff($allowedRoles, $userRoleIdentifiers);
            if (!empty($missing)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda harus memiliki semua role yang diperlukan.'
                ], Response::HTTP_FORBIDDEN);
            }
        } else {
            if (empty(array_intersect($userRoleIdentifiers, $allowedRoles))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak akses untuk halaman ini.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        try {
            return $this->ensureResponse($next($request));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    protected function normalizeRoles(array $roles): array
    {
        $normalized = [];

        foreach ($roles as $role) {
            if (!is_string($role)) {
                continue;
            }

            foreach (explode(',', $role) as $part) {
                $trimmed = trim($part);
                if ($trimmed === '') {
                    continue;
                }

                $normalized[] = strtolower($trimmed);
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function ensureResponse($response): Response
    {
        if ($response instanceof Response) {
            return $response;
        }

        if ($response instanceof JsonResponse || $response instanceof RedirectResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        if (is_array($response) || is_object($response)) {
            return response()->json($response);
        }

        return response((string) $response);
    }
}
