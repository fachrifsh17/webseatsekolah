<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PpdbLink;
use App\Http\Resources\PpdbLinkResource;
use App\Http\Requests\UpdatePpdbLinkRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PpdbLinkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['update']);
    }

    public function index(): JsonResponse
    {
        try {
            $link = PpdbLink::first();

            return response()->json([
                'success' => true,
                'data'    => $link ? new PpdbLinkResource($link) : null,
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch PPDB link', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data PPDB',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePpdbLinkRequest $request): JsonResponse
    {
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
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}