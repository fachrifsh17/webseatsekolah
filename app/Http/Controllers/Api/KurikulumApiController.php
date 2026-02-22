<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KurikulumResource;
use App\Models\Kurikulum;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KurikulumApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Kurikulum::query();

        // Jika bukan request dari admin, filter hanya yang aktif
        if (!$request->is('api/admin/*')) {
            $query->where('is_active', true);
        }

        $kurikulum = $query->latest()->get(); 
        
        return KurikulumResource::collection($kurikulum)->additional([
            'success' => true,
            'message' => 'Data kurikulum berhasil dimuat.'
        ]);
    }

    public function show(Request $request, $id)
    {
        $query = Kurikulum::query();

        // Proteksi agar publik tidak bisa melihat kurikulum non-aktif via ID
        if (!$request->is('api/admin/*')) {
            $query->where('is_active', true);
        }

        $kurikulum = $query->find($id);

        if (!$kurikulum) {
            return response()->json([
                'success' => false,
                'message' => 'Kurikulum tidak ditemukan atau sudah tidak aktif.'
            ], Response::HTTP_NOT_FOUND);
        }

        return (new KurikulumResource($kurikulum))->additional([
            'success' => true
        ]);
    }
}