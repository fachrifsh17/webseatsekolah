<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogAdmin;
use App\Http\Resources\LogAdminResource;
use Illuminate\Http\JsonResponse;

class LogAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
    }

    public function index(): JsonResponse
    {
        $data = LogAdmin::with('user')->orderByDesc('created_at')->paginate(20);
        return response()->json(LogAdminResource::collection($data));
    }
    
    public function show(LogAdmin $log): JsonResponse
    {
        $log->load('user');
        return response()->json(new LogAdminResource($log));
    }
}