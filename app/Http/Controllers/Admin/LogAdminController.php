<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogAdmin;
use App\Http\Resources\LogAdminResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class LogAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
    }

    public function index(): JsonResponse
    {
        try {
            $data = LogAdmin::with('user')->orderByDesc('created_at')->paginate(20);

            return response()->json([
                'success' => true,
                'data'    => LogAdminResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch admin logs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil log admin.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function show(LogAdmin $log): JsonResponse
    {
        try {
            $log->load('user');

            return response()->json([
                'success' => true,
                'data'    => new LogAdminResource($log),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch admin log', ['log_id' => (string)$log->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail log admin.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
