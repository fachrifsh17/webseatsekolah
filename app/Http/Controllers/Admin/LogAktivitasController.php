<?php

namespace App\Http\Controllers\Admin;

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
        $this->middleware('role:Admin');
        $this->authorizeResource(LogAktivitas::class, 'log');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->query('per_page', 20), 100);
            $search = request()->query('search');
            $date = request()->query('date'); // Tangkap parameter tanggal khusus

            $query = LogAktivitas::with(['user.guruStaf'])->orderByDesc('log_aktivitas.created_at');

            // 1. Filter jika ada input tanggal khusus
            if ($date) {
                $query->whereDate('log_aktivitas.created_at', $date);
            }

            // 2. Filter pencarian umum
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('log_aktivitas.aksi', 'like', "%{$search}%")
                      ->orWhere('log_aktivitas.ip_address', 'like', "%{$search}%")
                      ->orWhere('log_aktivitas.user_agent', 'like', "%{$search}%")
                      // Tambahkan ini: Pencarian tanggal melalui kotak search
                      ->orWhereDate('log_aktivitas.created_at', $search) 
                      ->orWhereHas('user', function ($u) use ($search) {
                          $u->where('username', 'like', "%{$search}%")
                            ->orWhereHas('guruStaf', function ($g) use ($search) {
                                $g->where('nama', 'like', "%{$search}%");
                            });
                      });
                });
            }

            $data = $query->paginate($perPage);
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
                    'path'          => $paginationData['path'] ?? null,
                    'links'         => $paginationData['links'] ?? [],
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('LogAktivitas Index Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil log aktivitas.',
                'debug'   => $e->getMessage() 
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(LogAktivitas $log): JsonResponse
    {
        try {
            $log->load('user.guruStaf');

            return response()->json([
                'success' => true,
                'data'    => new LogAktivitasResource($log),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('LogAktivitas Show Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail log aktivitas.',
            ], Response::HTTP_NOT_FOUND);
        }
    }
}