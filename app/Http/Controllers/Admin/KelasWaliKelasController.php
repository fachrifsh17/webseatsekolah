<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KelasWaliKelas;
use App\Http\Requests\StoreKelasWaliKelasRequest;
use App\Http\Requests\UpdateKelasWaliKelasRequest;
use App\Http\Resources\KelasWaliKelasResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelasWaliKelasController extends Controller
{
    public function index()
    {
        $riwayat = KelasWaliKelas::with(['kelas', 'guruStaf', 'tahunAjaran'])
                                 ->latest()
                                 ->get();
        return KelasWaliKelasResource::collection($riwayat);
    }

    public function store(StoreKelasWaliKelasRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            // LOGIKA: Jika wali kelas baru ini diset aktif,
            // nonaktifkan wali kelas lain untuk kelas, tahun ajaran, dan semester yang sama.
            if ($validated['is_active'] ?? false) {
                KelasWaliKelas::where('kelas_id', $validated['kelas_id'])
                    ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
                    // Tambahkan pengecekan semester jika diperlukan:
                    // ->where('semester', $validated['semester']) 
                    ->update(['is_active' => false]);
            }

            $riwayat = KelasWaliKelas::create($validated);

            return new KelasWaliKelasResource($riwayat->load(['kelas', 'guruStaf', 'tahunAjaran']));
        });
    }

    public function show(KelasWaliKelas $kelasWaliKelas)
    {
        return new KelasWaliKelasResource($kelasWaliKelas->load(['kelas', 'guruStaf', 'tahunAjaran']));
    }

    public function update(UpdateKelasWaliKelasRequest $request, KelasWaliKelas $kelasWaliKelas)
    {
        return DB::transaction(function () use ($request, $kelasWaliKelas) {
            $validated = $request->validated();

            // LOGIKA: Jika data yang diupdate menjadi aktif,
            // nonaktifkan yang lain untuk kelas, tahun ajaran, dan semester yang sama.
            if ($validated['is_active'] ?? false) {
                KelasWaliKelas::where('kelas_id', $validated['kelas_id'])
                    ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
                    ->where('id', '!=', $kelasWaliKelas->id)
                    // ->where('semester', $validated['semester'])
                    ->update(['is_active' => false]);
            }

            $kelasWaliKelas->update($validated);

            return new KelasWaliKelasResource($kelasWaliKelas->load(['kelas', 'guruStaf', 'tahunAjaran']));
        });
    }

    public function destroy(KelasWaliKelas $kelasWaliKelas)
    {
        $kelasWaliKelas->delete();
        return response()->json(['message' => 'Riwayat wali kelas berhasil dihapus'], 200);
    }
}