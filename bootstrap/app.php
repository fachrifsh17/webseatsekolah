<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Response; // Tambahkan ini untuk konstanta HTTP

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\CheckAuthToken::class,
            'role'       => \App\Http\Middleware\CheckRole::class,
            'log.aktivitas'  => \App\Http\Middleware\LogAktivitas::class,
            'jabatan'    => \App\Http\Middleware\CheckJabatan::class, 
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        
        // 1. Pastikan semua error di jalur API dikirim sebagai JSON
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });

        // 2. Menggunakan Response::HTTP_FORBIDDEN (ganti angka 403)
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk melakukan tindakan ini.',
            ], Response::HTTP_FORBIDDEN); // Ini lebih rapi daripada nulis 403
        });

    })->create();