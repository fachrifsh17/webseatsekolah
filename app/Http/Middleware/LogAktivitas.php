<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\LogAktivitas as LogAktivitasModel; // SESUAIKAN DISINI
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class LogAktivitas
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful()
            && Auth::check()
            && in_array($request->method(), ['POST','PUT','PATCH','DELETE'])) {

            $user = Auth::user();
            $roles = $user->roles()->pluck('role_name')->toArray();
            $roleLabel = !empty($roles) ? implode(', ', $roles) : 'User';
            $routeName = $request->route()?->getName() ?? $request->path();

            try {
                // Panggil model LogAktivitas
                LogAktivitasModel::create([
                    'user_id'    => (string) $user->id,
                    'aksi'       => "{$roleLabel} [{$user->username}] melakukan {$request->method()} pada modul: {$routeName}",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Exception $e) {
                Log::error("Log Error: " . $e->getMessage());
            }
        }

        return $response;
    }
}