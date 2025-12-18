<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\LogAdmin;

class LogAktivitasAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful() && $request->user() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            
            $routeName = $request->route() ? $request->route()->getName() : $request->path();

            LogAdmin::create([
                'user_id' => $request->user()->id,
                'aksi' => "Admin [{$request->user()->username}] melakukan {$request->method()} pada modul: {$routeName}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
        }

        return $response;
    }
}