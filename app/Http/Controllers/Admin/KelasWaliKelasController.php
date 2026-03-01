<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KelasWaliKelas;
use App\Models\TahunAjaran;
use App\Models\Kelas;
use App\Http\Requests\StoreKelasWaliKelasRequest;
use App\Http\Requests\UpdateKelasWaliKelasRequest;
use App\Http\Resources\KelasWaliKelasResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class KelasWaliKelasController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', KelasWaliKelas::class);
        $riwayat = KelasWaliKelas::with(['kelas', 'guruStaf', 'tahunAjaran'])
            ->latest()
            ->paginate(15);
        return KelasWaliKelasResource::collection($riwayat);
    }

    public function store(StoreKelasWaliKelasRequest $request)
    {
        $this->authorize('create', KelasWaliKelas::class);
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();
            if ($validated['is_active'] ?? false) {
                KelasWaliKelas::where('kelas_id', $validated['kelas_id'])
                    ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
                    ->update(['is_active' => false]);
            }
            $riwayat = KelasWaliKelas::create($validated);
            return new KelasWaliKelasResource($riwayat->load(['kelas', 'guruStaf', 'tahunAjaran']));
        });
    }

    public function show(KelasWaliKelas $kelasWaliKelas)
    {
        $this->authorize('view', $kelasWaliKelas);
        return new KelasWaliKelasResource($kelasWaliKelas->load(['kelas', 'guruStaf', 'tahunAjaran']));
    }

    public function update(UpdateKelasWaliKelasRequest $request, KelasWaliKelas $kelasWaliKelas)
    {
        $this->authorize('update', $kelasWaliKelas);
        return DB::transaction(function () use ($request, $kelasWaliKelas) {
            $validated = $request->validated();
            if ($validated['is_active'] ?? false) {
                KelasWaliKelas::where('kelas_id', $validated['kelas_id'])
                    ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
                    ->where('id', '!=', $kelasWaliKelas->id)
                    ->update(['is_active' => false]);
            }
            $kelasWaliKelas->update($validated);
            return new KelasWaliKelasResource($kelasWaliKelas->load(['kelas', 'guruStaf', 'tahunAjaran']));
        });
    }

    public function destroy(KelasWaliKelas $kelasWaliKelas)
    {
        $this->authorize('delete', $kelasWaliKelas);
        $kelasWaliKelas->delete();
        return response()->json(['message' => 'Riwayat wali kelas berhasil dihapus'], Response::HTTP_OK);
    }

    public function cloneToNewYear(Request $request)
    {
        $this->authorize('cloneToNewYear', KelasWaliKelas::class);
        
        $tahunAktif = TahunAjaran::where('is_active', true)->first();
        if (!$tahunAktif) {
            return response()->json(['message' => 'Tidak ada tahun ajaran aktif'], Response::HTTP_BAD_REQUEST);
        }

        $dataSudahAda = KelasWaliKelas::where('tahun_ajaran_id', $tahunAktif->id)->exists();
        if ($dataSudahAda) {
            return response()->json(['message' => 'Data untuk tahun ajaran aktif sudah ada'], Response::HTTP_CONFLICT);
        }

        $tahunLalu = TahunAjaran::where('id', '!=', $tahunAktif->id)
            ->orderBy('created_at', 'desc')
            ->first();
            
        if (!$tahunLalu) {
            return response()->json(['message' => 'Data tahun ajaran sebelumnya tidak ditemukan'], Response::HTTP_NOT_FOUND);
        }

        $oldData = KelasWaliKelas::where('tahun_ajaran_id', $tahunLalu->id)
            ->where('is_active', true)
            ->get();
            
        if ($oldData->isEmpty()) {
            return response()->json(['message' => 'Data wali kelas aktif tahun lalu tidak ditemukan'], Response::HTTP_NOT_FOUND);
        }

        DB::transaction(function () use ($oldData, $tahunAktif, $tahunLalu) {
            KelasWaliKelas::where('tahun_ajaran_id', $tahunLalu->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            foreach ($oldData as $data) {
                KelasWaliKelas::create([
                    'kelas_id'        => $data->kelas_id,
                    'guru_staf_id'    => $data->guru_staf_id,
                    'tahun_ajaran_id' => $tahunAktif->id,
                    'is_active'       => true, 
                ]);
            }
        });
        
        return response()->json(['message' => 'Data berhasil disalin, data tahun lalu dinonaktifkan'], Response::HTTP_CREATED);
    }

    public function bulkUpdateTingkat(Request $request)
    {
        $this->authorize('bulkUpdateTingkat', KelasWaliKelas::class);
        
        $request->validate([
            'mapping'  => 'required|array', 
        ]);
        
        $tahunAktif = TahunAjaran::where('is_active', true)->first();
        if (!$tahunAktif) {
            return response()->json(['message' => 'Tidak ada tahun ajaran aktif'], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            DB::transaction(function () use ($request, $tahunAktif) {
                foreach ($request->mapping as $idWali => $idKelasBaru) {
                    
                    // Filter: Cari berdasarkan ID DAN Tahun Ajaran Aktif
                    $waliKelas = KelasWaliKelas::where('id', $idWali)
                        ->where('tahun_ajaran_id', $tahunAktif->id)
                        ->first();
                        
                    if ($waliKelas) {
                        $waliKelas->update([
                            'kelas_id'        => $idKelasBaru,
                            'is_active'       => true 
                        ]);
                    } else {
                        // Jika data tidak ditemukan di tahun aktif, lemparkan eror
                        throw new \Exception("ID Wali Kelas {$idWali} tidak ditemukan atau bukan merupakan data tahun ajaran aktif.");
                    }
                }
            });
            
            return response()->json(['message' => 'Tingkat kelas berhasil diperbarui pada tahun ajaran aktif'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memperbarui tingkat kelas.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function prepareNewYear(Request $request)
    {
        $this->authorize('prepareNewYear', KelasWaliKelas::class);
        
        $tahunAktif = TahunAjaran::where('is_active', true)->first();
        if (!$tahunAktif) {
            return response()->json(['message' => 'Tidak ada tahun ajaran aktif'], Response::HTTP_BAD_REQUEST);
        }
        
        $allClasses = Kelas::where('is_active', true)->get();
        
        DB::transaction(function () use ($allClasses, $tahunAktif) {
            KelasWaliKelas::where('tahun_ajaran_id', '!=', $tahunAktif->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
                
            foreach ($allClasses as $kelas) {
                $exists = KelasWaliKelas::where('kelas_id', $kelas->id)
                    ->where('tahun_ajaran_id', $tahunAktif->id)
                    ->exists();
                    
                if (!$exists) {
                    KelasWaliKelas::create([
                        'kelas_id'        => $kelas->id,
                        'guru_staf_id'    => null, 
                        'tahun_ajaran_id' => $tahunAktif->id,
                        'is_active'       => true, 
                    ]);
                }
            }
        });
        
        return response()->json(['message' => 'Kelas berhasil disiapkan dan diaktifkan, data lama dinonaktifkan'], Response::HTTP_OK);
    }
}