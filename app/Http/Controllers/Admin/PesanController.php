<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Http\Resources\PesanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class PesanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['updateStatus', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $pesan = Pesan::latest()->paginate(10);
        return response()->json(PesanResource::collection($pesan));
    }

    public function show(Pesan $pesan): JsonResponse
    {
        return response()->json(new PesanResource($pesan));
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
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status pesan'
            ], 500);
        }
    }

    public function destroy(Pesan $pesan): JsonResponse
    {
        DB::beginTransaction();
        try {
            $pesan->delete();
            DB::commit();

            return response()->json(null, 204);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pesan'
            ], 500);
        }
    }
}