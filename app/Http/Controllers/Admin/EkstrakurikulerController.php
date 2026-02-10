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
            Log::error('Failed to fetch ekstrakurikuler detail', ['error' => $e->getMessage()]);
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

        $nameConflict = Ekstrakurikuler::where('nama_ekskul', $validated['nama_ekskul'])->exists();

        if ($nameConflict) {
            return response()->json([
                'success' => false,
                'message' => 'Nama ekstrakurikuler sudah terdaftar.',
                'errors'  => ['nama_ekskul' => ['Gunakan nama lain untuk menghindari duplikasi.']]
            ], Response::HTTP_CONFLICT);
        }

        $isConflict = Ekstrakurikuler::where('pembina_id', $validated['pembina_id'])
            ->where('hari', $validated['hari'])
            ->where(function ($query) use ($validated) {
                $query->whereBetween('jam_mulai', [$validated['jam_mulai'], $validated['jam_selesai']])
                    ->orWhereBetween('jam_selesai', [$validated['jam_mulai'], $validated['jam_selesai']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('jam_mulai', '<=', $validated['jam_mulai'])
                          ->where('jam_selesai', '>=', $validated['jam_selesai']);
                    });
            })->exists();

        if ($isConflict) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal pembina bentrok dengan ekstrakurikuler lain.',
                'errors'  => ['conflict' => ['Pembina sudah memiliki jadwal di jam tersebut.']]
            ], Response::HTTP_CONFLICT);
        }

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
            if (!empty($validated['foto'])) Storage::disk('public')->delete($validated['foto']);
            Log::error('Failed to create ekstrakurikuler', ['error' => $e->getMessage()]);
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
        $oldFoto = $ekstrakurikuler->foto;

        if (isset($validated['nama_ekskul'])) {
            $nameConflict = Ekstrakurikuler::where('nama_ekskul', $validated['nama_ekskul'])
                ->where('id', '!=', $ekstrakurikuler->id)
                ->exists();

            if ($nameConflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama ekstrakurikuler sudah digunakan oleh data lain.',
                    'errors'  => ['nama_ekskul' => ['Nama ini sudah ada di database.']]
                ], Response::HTTP_CONFLICT);
            }
        }

        $pembinaId = $validated['pembina_id'] ?? $ekstrakurikuler->pembina_id;
        $hari = $validated['hari'] ?? $ekstrakurikuler->hari;
        $jamMulai = $validated['jam_mulai'] ?? $ekstrakurikuler->jam_mulai;
        $jamSelesai = $validated['jam_selesai'] ?? $ekstrakurikuler->jam_selesai;

        $isConflict = Ekstrakurikuler::where('id', '!=', $ekstrakurikuler->id)
            ->where('pembina_id', $pembinaId)
            ->where('hari', $hari)
            ->where(function ($query) use ($jamMulai, $jamSelesai) {
                $query->whereBetween('jam_mulai', [$jamMulai, $jamSelesai])
                    ->orWhereBetween('jam_selesai', [$jamMulai, $jamSelesai])
                    ->orWhere(function ($q) use ($jamMulai, $jamSelesai) {
                        $q->where('jam_mulai', '<=', $jamMulai)
                          ->where('jam_selesai', '>=', $jamSelesai);
                    });
            })->exists();

        if ($isConflict) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal pembina bentrok dengan ekstrakurikuler lain.',
                'errors'  => ['conflict' => ['Pembina sudah memiliki jadwal di jam tersebut.']]
            ], Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
        }

        if ($request->filled('foto') && $request->foto === 'null') {
            if ($oldFoto) Storage::disk('public')->delete($oldFoto);
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
            if (!empty($validated['foto']) && $validated['foto'] !== $oldFoto) {
                Storage::disk('public')->delete($validated['foto']);
            }
            Log::error('Failed to update ekstrakurikuler', ['error' => $e->getMessage()]);
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
            if ($oldFoto) Storage::disk('public')->delete($oldFoto);

            return response()->json([
                'success' => true,
                'message' => 'Ekstrakurikuler berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete ekstrakurikuler', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus ekstrakurikuler',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}