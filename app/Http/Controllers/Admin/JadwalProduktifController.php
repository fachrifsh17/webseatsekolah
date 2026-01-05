<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Http\Requests\StoreJadwalProduktifRequest;
use App\Http\Requests\UpdateJadwalProduktifRequest;
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Throwable;

class JadwalProduktifController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store','update','destroy']);
    }

    public function index(): JsonResponse
    {
        $jadwal = JadwalProduktif::with(['jurusan','guruStaf'])->latest()->get();
        return response()->json(JadwalProduktifResource::collection($jadwal));
    }

    public function show(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        return response()->json(new JadwalProduktifResource($jadwalProduktif->load(['jurusan','guruStaf'])));
    }

    public function store(StoreJadwalProduktifRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $isAdmin = $user->roles()->where('role_name','admin')->exists();

        if (!$isAdmin) {
            $guruId = $user->guruStaf?->id ?? $user->guru_staf_id ?? $user->guru_id ?? null;
            if (!$guruId) {
                return response()->json(['success' => false, 'message' => 'Akun belum terhubung dengan data guru.'], 422);
            }
            $validated['guru_staf_id'] = $guruId;
        }

        $jurusanId = $validated['jurusan_id'] ?? null;
        if (empty($jurusanId)) {
            return response()->json(['success' => false, 'message' => 'Field jurusan_id wajib diisi.'], 422);
        }

        if (JadwalProduktif::where('jurusan_id', $jurusanId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Jurusan ini sudah memiliki jadwal produktif'], 409);
        }

        if ($request->hasFile('file_jadwal_path')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal_path')->store('jadwal_produktif','public');
        }

        try {
            $jadwal = JadwalProduktif::create($validated);
            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil ditambahkan.',
                'data'    => new JadwalProduktifResource($jadwal->load(['jurusan','guruStaf']))
            ], 201);
        } catch (QueryException $qe) {
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            if ($qe->getCode() === '23000' || str_contains($qe->getMessage(), 'UNIQUE')) {
                return response()->json(['success' => false, 'message' => 'Jurusan sudah memiliki jadwal produktif'], 409);
            }
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan jadwal produktif.'], 500);
        } catch (Throwable $e) {
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan jadwal produktif.'], 500);
        }
    }

    public function update(UpdateJadwalProduktifRequest $request, JadwalProduktif $jadwalProduktif): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $isAdmin = $user->roles()->where('role_name','admin')->exists();

        if (!$isAdmin) {
            $guruId = $user->guruStaf?->id ?? $user->guru_staf_id ?? $user->guru_id ?? null;
            if (!$guruId) {
                return response()->json(['success' => false, 'message' => 'Akun belum terhubung dengan data guru.'], 422);
            }
            $validated['guru_staf_id'] = $guruId;
        }

        if (isset($validated['jurusan_id']) && $validated['jurusan_id'] != $jadwalProduktif->jurusan_id) {
            $newJurusanId = $validated['jurusan_id'];
            if (JadwalProduktif::where('jurusan_id', $newJurusanId)->where('id', '!=', $jadwalProduktif->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Jurusan tujuan sudah memiliki jadwal produktif'], 409);
            }
        }

        $newFilePath = null;
        if ($request->hasFile('file_jadwal_path')) {
            $newFilePath = $request->file('file_jadwal_path')->store('jadwal_produktif','public');
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
                'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan','guruStaf']))
            ], 200);
        } catch (QueryException $qe) {
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            if ($qe->getCode() === '23000' || str_contains($qe->getMessage(), 'UNIQUE')) {
                return response()->json(['success' => false, 'message' => 'Jurusan sudah memiliki jadwal produktif'], 409);
            }
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui jadwal produktif.'], 500);
        } catch (Throwable $e) {
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui jadwal produktif.'], 500);
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
                'success' => true,
                'message' => 'Jadwal produktif berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jadwal produktif.',
                'notification' => 'Gagal dihapus'
            ], 500);
        }
    }
}
