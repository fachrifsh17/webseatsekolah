<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PortalSosmed;
use App\Http\Resources\PortalSosmedResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalApiController extends Controller
{
    /**
     * Menampilkan daftar portal untuk Landing Page / Footer.
     */
    public function index(Request $request)
    {
        $query = PortalSosmed::query();

        // Filter Publik: Hanya izinkan filter berdasarkan tipe yang valid (Enum)
        if ($request->has('tipe') && in_array($request->tipe, ['Sosial Media', 'Portal Khusus'])) {
            $query->where('tipe', $request->tipe);
        }

        // Urutkan berdasarkan nama agar enak dilihat di UI
        $data = $query->orderBy('nama_platform', 'asc')->get();

        return PortalSosmedResource::collection($data)->additional([
            'success' => true,
            'message' => 'Portal informasi publik berhasil dimuat.'
        ]);
    }

    /**
     * Jika publik klik salah satu link (jarang dipakai tapi tetap aman).
     */
    public function show($id)
    {
        $portal = PortalSosmed::find($id);

        if (!$portal) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.'
            ], Response::HTTP_NOT_FOUND);
        }

        return (new PortalSosmedResource($portal))->additional([
            'success' => true
        ]);
    }
}