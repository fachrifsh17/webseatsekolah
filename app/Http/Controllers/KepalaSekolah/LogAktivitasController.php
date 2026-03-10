<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\LogAktivitas; 
use App\Http\Resources\LogAktivitasResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class LogAktivitasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->authorizeResource(LogAktivitas::class, 'log');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->query('per_page', 20), 100);
            $data = LogAktivitas::with('user')->orderByDesc('created_at')->paginate($perPage);
            
            // Mengonversi data paginasi ke array untuk mengambil path dan links
            $paginationData = $data->toArray();

            return response()->json([
                'success' => true,
                'data'    => LogAktivitasResource::collection($data),
                'meta'    => [
                    'current_page'  => $data->currentPage(),
                    'last_page'     => $data->lastPage(),
                    'per_page'      => $data->perPage(),
                    'total'         => $data->total(),
                    'from'          => $data->firstItem(),
                    'to'            => $data->lastItem(),
                    'next_page_url' => $data->nextPageUrl(),
                    'prev_page_url' => $data->previousPageUrl(),
                    'path'          => $paginationData['path'],
                    'links'         => $paginationData['links'],
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch activity logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil log aktivitas.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(LogAktivitas $log): JsonResponse
    {
        try {
            $log->load('user');

            return response()->json([
                'success' => true,
                'data'    => new LogAktivitasResource($log),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch activity log detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail log aktivitas.',
            ], Response::HTTP_NOT_FOUND);
        }
    }
}