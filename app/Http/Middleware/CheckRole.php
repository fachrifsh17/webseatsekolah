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

        // 1. Pastikan user sudah login
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan login terlebih dahulu.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 2. Ambil daftar role yang diizinkan dari middleware (misal: 'Admin,Guru')
        $raw = implode(',', $roles);
        $allowedRoles = $this->normalizeRoles([$raw]);

        if (empty($allowedRoles)) {
            return $this->ensureResponse($next($request));
        }

        // 3. AMBIL HANYA CURRENT ROLE (Strict Mode)
        // Kita tidak lagi mengecek $user->roles() agar fitur switch-role berguna
        $userActiveRole = strtolower(trim($user->current_role ?? ''));

        if (!$userActiveRole) {
            return response()->json([
                'success' => false,
                'message' => 'Role aktif tidak ditemukan. Silakan pilih role terlebih dahulu.'
            ], Response::HTTP_FORBIDDEN);
        }

        // 4. PENGECEKAN KETAT
        // User hanya lolos jika current_role-nya ada di dalam list $allowedRoles
        if (!in_array($userActiveRole, $allowedRoles)) {
            return response()->json([
                'success' => false,
                'message' => "Akses ditolak. Role aktif Anda ($userActiveRole) tidak diizinkan mengakses halaman ini."
            ], Response::HTTP_FORBIDDEN);
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
            if (!is_string($role)) continue;
            foreach (explode(',', $role) as $part) {
                $trimmed = trim($part);
                if ($trimmed === '') continue;
                $normalized[] = strtolower($trimmed);
            }
        }
        return array_values(array_unique($normalized));
    }

    protected function ensureResponse($response): Response
    {
        if ($response instanceof Response) return $response;
        if ($response instanceof JsonResponse || $response instanceof RedirectResponse || $response instanceof StreamedResponse) return $response;
        if (is_array($response) || is_object($response)) return response()->json($response);
        return response((string) $response);
    }
}