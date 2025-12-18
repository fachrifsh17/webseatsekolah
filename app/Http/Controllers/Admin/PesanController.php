<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Http\Resources\PesanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PesanController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['updateStatus', 'destroy']),
        ];
    }

    public function index()
    {
        $pesan = Pesan::latest()->paginate(10); 
        return PesanResource::collection($pesan);
    }

    public function show(Pesan $pesan)
    {
        return new PesanResource($pesan);
    }

    public function updateStatus(Pesan $pesan): JsonResponse
    {
        $pesan->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Status pesan berhasil diperbarui',
            'data'    => new PesanResource($pesan)
        ]);
    }

    public function destroy(Pesan $pesan): JsonResponse
    {
        $pesan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dihapus'
        ]);
    }
}