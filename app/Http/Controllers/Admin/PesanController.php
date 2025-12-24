<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Http\Resources\PesanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        return new JsonResponse(PesanResource::collection($pesan));
    }

    public function show(Pesan $pesan): JsonResponse
    {
        return new JsonResponse(new PesanResource($pesan));
    }

    public function updateStatus(Pesan $pesan): JsonResponse
    {
        DB::transaction(function () use ($pesan) {
            $pesan->update(['is_read' => true]);
        });

        return new JsonResponse([
            'success' => true,
            'message' => 'Status pesan berhasil diperbarui',
            'data'    => new PesanResource($pesan->fresh())
        ], 200);
    }

    public function destroy(Pesan $pesan): JsonResponse
    {
        DB::transaction(function () use ($pesan) {
            $pesan->delete();
        });

        return new JsonResponse(null, 204);
    }
}