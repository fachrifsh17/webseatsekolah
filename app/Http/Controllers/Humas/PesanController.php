<?php

namespace App\Http\Controllers\Humas;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Http\Resources\PesanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PesanController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['updateStatus', 'destroy']);

        // Mengotomatisasi pengecekan Policy untuk index, show, dan destroy
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
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Pesan $pesan): JsonResponse
    {
        try {
            // Otomatis tandai sebagai terbaca saat detail dibuka
            if (!$pesan->is_read) {
                $pesan->update(['is_read' => true]);
            }

            return response()->json([
                'success' => true,
                'data'    => new PesanResource($pesan),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch pesan detail', [
                'pesan_id' => (string) $pesan->id,
                'error'    => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail pesan',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update status pesan secara manual
     */
    public function updateStatus(Pesan $pesan): JsonResponse
    {
        // Karena updateStatus bukan method standar, panggil authorize manual
        $this->authorize('update', $pesan);

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
                'pesan_id' => (string) $pesan->id,
                'error'    => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status pesan',
                'errors'  => ['exception' => [$e->getMessage()]]
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
                'success'      => true,
                'message'      => 'Pesan berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete pesan', [
                'pesan_id' => (string) $pesan->id,
                'error'    => $e->getMessage()
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus pesan',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}