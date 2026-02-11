<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Http\Resources\PesanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PesanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['updateStatus', 'destroy']);

        // Menambahkan proteksi Policy (Opsional tapi disarankan)
        $this->authorizeResource(Pesan::class, 'pesan');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 10), 100);
            $pesan   = Pesan::latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => PesanResource::collection($pesan),
                'meta'    => [
                    'current_page' => $pesan->currentPage(),
                    'last_page'    => $pesan->lastPage(),
                    'per_page'     => $pesan->perPage(),
                    'total'        => $pesan->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch pesan list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar pesan',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Pesan $pesan): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new PesanResource($pesan),
        ], Response::HTTP_OK);
    }

    public function updateStatus(Pesan $pesan): JsonResponse
    {
        DB::beginTransaction();
        try {
            $pesan->update(['is_read' => true]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status pesan berhasil diperbarui',
                'data'    => new PesanResource($pesan->fresh())
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update pesan status', [
                'pesan_id' => $pesan->id,
                'error'    => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status pesan',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Pesan $pesan): JsonResponse
    {
        DB::beginTransaction();
        try {
            $pesan->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pesan berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete pesan', [
                'pesan_id' => $pesan->id,
                'error'    => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pesan',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}