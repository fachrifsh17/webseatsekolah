<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Http\Resources\PesanResource;
use Illuminate\Http\Request;
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
        
        $this->middleware('log.aktivitas')->only(['updateStatus', 'destroy', 'markAllAsRead']);

        $this->authorizeResource(Pesan::class, 'pesan');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 10), 100);
            $pesan   = Pesan::latest()->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data'    => PesanResource::collection($pesan),
                'meta'    => [
                    'current_page'  => $pesan->currentPage(),
                    'last_page'     => $pesan->lastPage(),
                    'per_page'      => $pesan->perPage(),
                    'total'         => $pesan->total(),
                    // Menghitung jumlah pesan yang statusnya masih 'belum_dibaca'
                    'unread_count'  => Pesan::where('status', 'belum_dibaca')->count(), 
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
        // LOGIKA OTOMATIS: Tandai 'sudah_dibaca' saat pesan dibuka
        if ($pesan->status === 'belum_dibaca') {
            try {
                $pesan->update(['status' => 'sudah_dibaca']);
            } catch (Throwable $e) {
                Log::warning('Gagal update status secara otomatis', ['id' => $pesan->id]);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => new PesanResource($pesan),
        ], Response::HTTP_OK);
    }

    public function markAllAsRead(): JsonResponse
    {
        $this->authorize('markAllAsRead', Pesan::class);

        DB::beginTransaction();
        try {
            // Update semua yang 'belum_dibaca' menjadi 'sudah_dibaca'
            Pesan::where('status', 'belum_dibaca')->update(['status' => 'sudah_dibaca']);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Semua pesan telah ditandai sebagai terbaca'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal mark all as read', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status semua pesan',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateStatus(Pesan $pesan): JsonResponse
    {
        $this->authorize('update', $pesan);

        DB::beginTransaction();
        try {
            $pesan->update(['status' => 'sudah_dibaca']);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status pesan berhasil diperbarui',
                // fresh() memastikan data yang dikembalikan adalah data terbaru dari DB
                'data'    => new PesanResource($pesan->fresh())
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
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
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pesan',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}