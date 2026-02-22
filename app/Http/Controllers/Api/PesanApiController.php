<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Http\Requests\StorePesanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PesanApiController extends Controller
{
    public function store(StorePesanRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Mapping data untuk disimpan ke Database
            $data = [
                'nama_lengkap'  => $validated['nama_lengkap'],
                'email'         => $validated['email'],
                'subjek'        => $validated['subjek'],
                'isi_pesan'     => $validated['pesan'] ?? $validated['isi_pesan'],
                'status'        => 'belum_dibaca', 
                'tanggal_kirim' => Carbon::now()->toDateString(),
            ];

            // Simpan data
            Pesan::create($data);

            // Response: Hanya sukses dan pesan saja (Data disembunyikan)
            return response()->json([
                'success' => true,
                'message' => 'Terima kasih, pesan Anda telah kami terima.',
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('Pesan Store Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim pesan. Silakan coba lagi nanti.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}