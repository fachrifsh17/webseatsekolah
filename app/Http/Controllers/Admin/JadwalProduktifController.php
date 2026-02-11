<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Http\Requests\StoreJadwalProduktifRequest;
use App\Http\Requests\UpdateJadwalProduktifRequest;
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JadwalProduktifController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(JadwalProduktif::class, 'jadwalProduktif');
    }

    public function index(): JsonResponse
    {
        $perPage = min((int) request()->get('per_page', 20), 100);

        $jadwal = JadwalProduktif::with(['jurusan', 'guruStaf'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => JadwalProduktifResource::collection($jadwal),
            'meta'    => [
                'current_page' => $jadwal->currentPage(),
                'last_page'    => $jadwal->lastPage(),
                'per_page'     => $jadwal->perPage(),
                'total'        => $jadwal->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan', 'guruStaf']))
        ], Response::HTTP_OK);
    }

    public function store(StoreJadwalProduktifRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user      = $request->user();

        $isAdmin = $user->roles()->where('role_name', 'admin')->exists();

        if (!$isAdmin) {
            $guruId = $user->guruStaf?->id ?? $user->guru_staf_id ?? $user->guru_id ?? null;
            if (!$guruId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun belum terhubung dengan data guru.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $validated['guru_staf_id'] = (string) $guruId;
        }

        $jurusanId = $validated['jurusan_id'] ?? null;
        if (empty($jurusanId)) {
            return response()->json([
                'success' => false,
                'message' => 'Field jurusan_id wajib diisi.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (JadwalProduktif::where('jurusan_id', (string) $jurusanId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Jurusan ini sudah memiliki jadwal produktif'
            ], Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('file_jadwal_path')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal_path')->store('jadwal_produktif', 'public');
        }

        try {
            $jadwal = JadwalProduktif::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil ditambahkan.',
                'data'    => new JadwalProduktifResource($jadwal->load(['jurusan', 'guruStaf']))
            ], Response::HTTP_CREATED);
        } catch (QueryException $qe) {
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            if ($qe->getCode() === '23000' || str_contains($qe->getMessage(), 'UNIQUE')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jurusan sudah memiliki jadwal produktif'
                ], Response::HTTP_CONFLICT);
            }
            Log::error('Failed to create jadwal produktif', ['payload' => $validated, 'error' => $qe->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jadwal produktif',
                'errors'  => ['exception' => [$qe->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (Throwable $e) {
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            Log::error('Failed to create jadwal produktif', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jadwal produktif',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateJadwalProduktifRequest $request, JadwalProduktif $jadwalProduktif): JsonResponse
    {
        $validated = $request->validated();
        $user      = $request->user();

        $isAdmin = $user->roles()->where('role_name', 'admin')->exists();

        if (!$isAdmin) {
            $guruId = $user->guruStaf?->id ?? $user->guru_staf_id ?? $user->guru_id ?? null;
            if (!$guruId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun belum terhubung dengan data guru.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $validated['guru_staf_id'] = (string) $guruId;
        }

        if (isset($validated['jurusan_id']) && $validated['jurusan_id'] != $jadwalProduktif->jurusan_id) {
            $newJurusanId = $validated['jurusan_id'];
            if (JadwalProduktif::where('jurusan_id', (string) $newJurusanId)
                ->where('id', '!=', (string) $jadwalProduktif->id)
                ->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jurusan tujuan sudah memiliki jadwal produktif'
                ], Response::HTTP_CONFLICT);
            }
        }

        if ($request->hasFile('file_jadwal_path')) {
            $newFilePath = $request->file('file_jadwal_path')->store('jadwal_produktif', 'public');
            if ($newFilePath) {
                if (!empty($jadwalProduktif->file_jadwal_path)) {
                    Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
                }
                $validated['file_jadwal_path'] = $newFilePath;
            }
        }

        try {
            $jadwalProduktif->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil diperbarui.',
                'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan', 'guruStaf']))
            ], Response::HTTP_OK);
        } catch (QueryException $qe) {
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            if ($qe->getCode() === '23000' || str_contains($qe->getMessage(), 'UNIQUE')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jurusan sudah memiliki jadwal produktif'
                ], Response::HTTP_CONFLICT);
            }
            Log::error('Failed to update jadwal produktif', [
                'jadwal_id' => (string) $jadwalProduktif->id,
                'payload'   => $validated,
                'error'     => $qe->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jadwal produktif',
                'errors'  => ['exception' => [$qe->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (Throwable $e) {
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            Log::error('Unexpected error updating jadwal produktif', [
                'jadwal_id' => (string) $jadwalProduktif->id,
                'payload'   => $validated,
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jadwal produktif',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        try {
            if (!empty($jadwalProduktif->file_jadwal_path)) {
                Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
            }

            $jadwalProduktif->delete();

            return response()->json([
                'success'      => true,
                'message'      => 'Jadwal produktif berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete jadwal produktif', [
                'jadwal_id' => (string) $jadwalProduktif->id,
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus jadwal produktif',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}