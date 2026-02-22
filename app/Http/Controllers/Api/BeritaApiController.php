<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Http\Resources\BeritaResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BeritaApiController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Ambil berita yang tanggal publikasinya sudah lewat atau hari ini
            $query = Berita::where('tanggal_publikasi', '<=', now());

            // Fitur pencarian judul (Opsional, sangat berguna untuk publik)
            if ($request->has('search')) {
                $query->where('judul', 'like', '%' . $request->search . '%');
            }

            $berita = $query->orderBy('tanggal_publikasi', 'desc')->paginate(10);

            // Menggunakan Resource agar format JSON lebih rapi (terutama untuk URL gambar)
            return BeritaResource::collection($berita)
                ->additional(['success' => true]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data berita'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            // Cari berita yang sudah terbit
            $berita = Berita::where('tanggal_publikasi', '<=', now())->findOrFail($id);

            return (new BeritaResource($berita))
                ->additional(['success' => true]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], Response::HTTP_NOT_FOUND);
        }
    }
}