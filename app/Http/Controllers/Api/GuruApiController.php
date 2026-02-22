<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuruStaf;
use App\Http\Resources\GuruResource;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

class GuruApiController extends Controller
{
    public function index(Request $request)
    {
        // Publik HANYA butuh relasi jurusan, tidak butuh data 'user' (akun login)
        $query = GuruStaf::with(['jurusan'])
            ->where('is_active', true); // Mutlak hanya yang aktif

        // Filter Pencarian Nama
        if ($request->filled('search')) {
            $query->where('nama', 'like', '%' . $request->search . '%');
        }

        // Filter Berdasarkan Jurusan
        if ($request->filled('jurusan_id')) {
            $query->where('jurusan_id', $request->jurusan_id);
        }

        // Urutkan abjad agar rapi di tampilan list sekolah
        $guru = $query->orderBy('nama', 'asc')->paginate(12);

        return GuruResource::collection($guru)->additional([
            'success' => true,
            'message' => 'Daftar Guru dan Staf berhasil dimuat.'
        ]);
    }

    public function show($id)
    {
        // Pastikan guru yang dicari juga harus aktif
        $guru = GuruStaf::with(['jurusan'])
            ->where('is_active', true)
            ->find($id);

        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Profil guru tidak ditemukan atau sudah tidak aktif.'
            ], Response::HTTP_NOT_FOUND);
        }

        return (new GuruResource($guru))->additional([
            'success' => true
        ]);
    }
}