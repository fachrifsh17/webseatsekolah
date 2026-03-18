<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Penyesuaian tanpa mengubah kode lain:
        // Di Laravel 11/12, HandleCors sudah otomatis masuk di global stack.
        
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\CheckAuthToken::class,
            'role'       => \App\Http\Middleware\CheckRole::class,
            'log.aktivitas'  => \App\Http\Middleware\LogAktivitas::class,
            'jabatan'    => \App\Http\Middleware\CheckJabatan::class, 
        ]);

        // Tambahkan ini jika nanti error saat upload gambar yang agak besar
        // $middleware->validatePostSize(whitelist: ['api/berita']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk melakukan tindakan ini.',
            ], Response::HTTP_FORBIDDEN);
        });

    })->create();