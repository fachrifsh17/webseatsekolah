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

        if ($response->isOk()
            && $request->user()
            && in_array($request->method(), ['POST','PUT','PATCH','DELETE'])) {

            $user = $request->user();

            $roles = $user->roles()->pluck('role_name')->toArray();
            $roleLabel = !empty($roles) ? implode(', ', $roles) : 'User';

            $routeName = $request->route()?->getName() ?? $request->path();

            try {
                LogAdmin::create([
                    'user_id'    => (string) $user->id,
                    'aksi'       => "{$roleLabel} [{$user->username}] melakukan {$request->method()} pada modul: {$routeName}",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Exception $e) {
                Log::error("Log Error: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $response;
    }
}
