<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Http\Resources\AlbumResource;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AlbumApiController extends Controller
{
    public function index()
    {
        try {
            // Tambahkan withCount('media') untuk menghitung jumlah item di dalam album
            $albums = Album::withCount('media') 
                ->orderBy('tanggal_kegiatan', 'desc')
                ->paginate(12);

            return AlbumResource::collection($albums)->additional([
                'success' => true,
                'message' => 'Daftar album berhasil diambil'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data album',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            // Untuk detail, kita juga hitung jumlahnya agar sinkron
            $album = Album::withCount('media')
                ->with(['media' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])
                ->findOrFail($id);

            return (new AlbumResource($album))->additional([
                'success' => true,
                'message' => 'Detail album ditemukan'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Album tidak ditemukan'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}