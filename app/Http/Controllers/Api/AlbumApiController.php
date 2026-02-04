<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AlbumApiController extends Controller
{
    public function index()
    {
        try {
            $albums = Album::orderBy('tanggal_kegiatan', 'desc')->paginate(12);

            return response()->json([
                'success' => true,
                'message' => 'Daftar album berhasil diambil',
                'data'    => $albums
            ], Response::HTTP_OK);

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
            $album = Album::with(['media' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail album ditemukan',
                'data'    => $album
            ], Response::HTTP_OK);

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