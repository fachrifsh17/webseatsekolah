<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Http\Resources\EkstrakurikulerResource;
use App\Http\Requests\StoreEkstrakurikulerRequest;
use App\Http\Requests\UpdateEkstrakurikulerRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class EkstrakurikulerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);

            $data = Ekstrakurikuler::with('pembina')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => EkstrakurikulerResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch ekstrakurikuler', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar ekstrakurikuler',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        try {
            $ekstrakurikuler->load('pembina');

            return response()->json([
                'success' => true,
                'data'    => new EkstrakurikulerResource($ekstrakurikuler)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch ekstrakurikuler detail', [
                'ekskul_id' => $ekstrakurikuler->id, // sudah integer
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail ekstrakurikuler',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreEkstrakurikulerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
        }

        try {
            $ekskul = DB::transaction(fn() => Ekstrakurikuler::create($validated));

            return response()->json([
                'success' => true,
                'message' => 'Ekstrakurikuler berhasil ditambahkan.',
                'data'    => new EkstrakurikulerResource($ekskul->load('pembina'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            Log::error('Failed to create ekstrakurikuler', [
                'payload' => $validated,
                'error'   => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan ekstrakurikuler',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateEkstrakurikulerRequest $request, Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $validated = $request->validated();
        $oldFoto   = $ekstrakurikuler->foto;

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
        }

        if ($request->filled('foto') && $request->foto === 'null') {
            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }
            $validated['foto'] = null;
        }

        try {
            DB::transaction(fn() => $ekstrakurikuler->update($validated));

            $ekstrakurikuler->refresh();

            if (!empty($validated['foto']) && $oldFoto && $validated['foto'] !== $oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ekstrakurikuler berhasil diperbarui.',
                'data'    => new EkstrakurikulerResource($ekstrakurikuler->load('pembina'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null) && $validated['foto'] !== $oldFoto) {
                Storage::disk('public')->delete($validated['foto']);
            }
            Log::error('Failed to update ekstrakurikuler', [
                'ekskul_id' => $ekstrakurikuler->id, // integer
                'payload'   => $validated,
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui ekstrakurikuler',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $oldFoto = $ekstrakurikuler->foto;

        try {
            DB::transaction(fn() => $ekstrakurikuler->delete());

            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success'      => true,
                'message'      => 'Ekstrakurikuler berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete ekstrakurikuler', [
                'ekskul_id' => $ekstrakurikuler->id, // integer
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus ekstrakurikuler',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
