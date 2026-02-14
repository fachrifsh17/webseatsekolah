<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataKontak;
use App\Http\Requests\UpdateDataKontakRequest;
use App\Http\Resources\DataKontakResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class DataKontakController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['update']);
    }

    public function index(): JsonResponse
    {
        try {
            $dataKontak = DataKontak::firstOrCreate(
                ['id' => 1],
                [
                    'alamat_lengkap' => '-',
                    'telepon'        => '-',
                    'email_resmi'    => '-',
                    'peta_embed_code'=> null,
                ]
            );

            return response()->json([
                'success' => true,
                'data'    => new DataKontakResource($dataKontak),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch data kontak', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kontak',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateDataKontakRequest $request): JsonResponse
    {
        $this->authorize('update', DataKontak::class);

        try {
            $dataKontak = DataKontak::firstOrCreate(
                ['id' => 1],
                [
                    'alamat_lengkap' => '-',
                    'telepon'        => '-',
                    'email_resmi'    => '-',
                    'peta_embed_code'=> null,
                ]
            );

            $dataKontak->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Data kontak berhasil diperbarui',
                'data'    => new DataKontakResource($dataKontak->fresh()),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update data kontak', [
                'payload' => $request->validated(),
                'error'   => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data kontak',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
