<?php

namespace App\Http\Controllers\Humas;

use App\Http\Controllers\Controller;
use App\Models\PpdbLink;
use App\Http\Resources\PpdbLinkResource;
use App\Http\Requests\UpdatePpdbLinkRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PpdbLinkController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['update']);
    }

    public function index(): JsonResponse
    {
        // Otorisasi viewAny pada model PpdbLink
        $this->authorize('viewAny', PpdbLink::class);

        try {
            $link = PpdbLink::first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data PPDB belum tersedia'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data'    => new PpdbLinkResource($link),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch PPDB link', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data PPDB',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePpdbLinkRequest $request): JsonResponse
    {
        // Otorisasi update pada model PpdbLink
        $this->authorize('update', PpdbLink::class);

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $link = PpdbLink::updateOrCreate(
                ['id' => 1],
                $validated
            );
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data PPDB berhasil diperbarui',
                'data'    => new PpdbLinkResource($link),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update PPDB link', [
                'payload' => $validated,
                'error'   => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data PPDB',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}