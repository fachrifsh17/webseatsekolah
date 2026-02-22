<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Album; // Import Model Album
use App\Http\Resources\MediaResource;
use App\Http\Resources\AlbumResource; // Import Resource Album
use Symfony\Component\HttpFoundation\Response;

class MediaApiController extends Controller
{
    public function index()
    {
        try {
            // Index umum tetap seperti biasa agar tetap ada konteks album di setiap media
            $media = Media::with('album')
                ->orderBy('created_at', 'desc')
                ->paginate(20); 

            return MediaResource::collection($media)->additional([
                'success' => true,
                'message' => 'Daftar semua media berhasil diambil'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function show($album_id)
    {
        try {
            // 1. Ambil data Albumnya saja dulu (hanya 1 kali query)
            $album = Album::withCount('media')->find($album_id);

            if (!$album) {
                return response()->json([
                    'success' => false,
                    'message' => 'Album tidak ditemukan',
                ], Response::HTTP_NOT_FOUND);
            }

            // 2. Ambil list medianya (Tanpa Load Relasi Album lagi agar JSON ringan)
            $media = Media::where('album_id', $album_id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            // 3. Gabungkan: Album di 'additional', Media di 'data'
            return MediaResource::collection($media)->additional([
                'success' => true,
                'message' => 'Daftar media berdasarkan album berhasil diambil',
                'album'   => new AlbumResource($album) // Data album ditaruh di sini (paling atas/luar)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}