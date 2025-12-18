<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogAdmin;
use App\Http\Resources\LogAdminResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LogAdminController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
        ];
    }

    public function index()
    {
        $data = LogAdmin::with('user')->orderByDesc('created_at')->paginate(20); 
        
        return LogAdminResource::collection($data);
    }
    
    public function show(LogAdmin $log)
    {
        $log->load('user');
        
        return new LogAdminResource($log);
    }
}