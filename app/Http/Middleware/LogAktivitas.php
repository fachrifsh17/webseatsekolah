<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\LogAdmin;
use Illuminate\Support\Facades\Log;

class LogAktivitas
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful() && $request->user() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            
            $user = $request->user();

            if ($user->role_id == 1 || $user->role_id == 2) {
                
                $routeName = $request->route() ? $request->route()->getName() : $request->path();
                $roleLabel = ($user->role_id == 1) ? 'Admin' : 'Guru';

                try {
                    LogAdmin::create([
                        'user_id'    => $user->id,
                        'aksi'       => "{$roleLabel} [{$user->username}] melakukan {$request->method()} pada modul: {$routeName}",
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent()
                    ]);
                } catch (\Exception $e) {
                    Log::error("Log Error: " . $e->getMessage());
                }
            }
        }

        return $response;
    }
}