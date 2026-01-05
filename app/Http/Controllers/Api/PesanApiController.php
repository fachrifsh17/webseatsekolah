<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Http\Requests\StorePesanRequest;
use App\Http\Resources\PesanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class PesanApiController extends Controller
{
    public function store(StorePesanRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['pesan']) && ! isset($data['isi_pesan'])) {
            $data['isi_pesan'] = $data['pesan'];
            unset($data['pesan']);
        }

        $pesanModel = new Pesan();
        $allowed = array_flip($pesanModel->getFillable());
        $data = array_intersect_key($data, $allowed);

        $statusInput = $data['status'] ?? 'unread';
        $data['status'] = match ($statusInput) {
            'unread' => 'belum_dibaca',
            'read' => 'sudah_dibaca',
            'belum_dibaca', 'sudah_dibaca' => $statusInput,
            default => 'belum_dibaca',
        };

        $data['tanggal_kirim'] = $data['tanggal_kirim'] ?? Carbon::now()->toDateString();

        try {
            $pesan = Pesan::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Terima kasih, pesan Anda telah kami terima.',
                'data'    => new PesanResource($pesan),
            ], 201);
        } catch (QueryException $e) {
            Log::error('Pesan create QueryException', ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pesan. Silakan coba lagi.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Pesan create Exception', ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
            ], 500);
        }
    }
}
