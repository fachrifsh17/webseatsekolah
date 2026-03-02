<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KelasWaliKelas;
use App\Models\Semester; 
use App\Models\Kelas;
use App\Http\Requests\StoreKelasWaliKelasRequest;
use App\Http\Requests\UpdateKelasWaliKelasRequest;
use App\Http\Resources\KelasWaliKelasResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class KelasWaliKelasController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', KelasWaliKelas::class);
        
        $riwayat = KelasWaliKelas::with(['kelas', 'guruStaf', 'semester'])
            ->when($request->search, function ($query) use ($request) {
                $query->whereHas('kelas', function ($q) use ($request) {
                    $q->where('nama_kelas', 'like', '%' . $request->search . '%');
                })->orWhereHas('guruStaf', function ($q) use ($request) {
                    $q->where('nama', 'like', '%' . $request->search . '%')
                      ->orWhere('nip', 'like', '%' . $request->search . '%');
                });
            })
            ->when($request->semester_id, function ($query) use ($request) {
                $query->where('semester_id', $request->semester_id);
            })
            ->latest()
            ->paginate(15);
            
        return KelasWaliKelasResource::collection($riwayat);
    }

    public function store(StoreKelasWaliKelasRequest $request)
    {
        $this->authorize('create', KelasWaliKelas::class);
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();
            
            $semesterAktif = Semester::where('is_active', true)->first();
            if (!$semesterAktif) {
                return response()->json(['message' => 'Tidak ada semester yang aktif'], 400);
            }
            $validated['semester_id'] = $semesterAktif->id;

            if ($validated['is_active'] ?? false) {
                KelasWaliKelas::where('kelas_id', $validated['kelas_id'])
                    ->where('semester_id', $validated['semester_id'])
                    ->update(['is_active' => false]);
            }
            
            $riwayat = KelasWaliKelas::create($validated);
            
            $riwayat->load(['kelas', 'guruStaf', 'semester']);
            
            return new KelasWaliKelasResource($riwayat);
        });
    }

    public function show(KelasWaliKelas $kelasWaliKelas)
    {
        $this->authorize('view', $kelasWaliKelas);
        return new KelasWaliKelasResource($kelasWaliKelas->load(['kelas', 'guruStaf', 'semester']));
    }

    public function update(UpdateKelasWaliKelasRequest $request, $id)
    {
        $kelasWaliKelas = KelasWaliKelas::findOrFail($id);
        $this->authorize('update', $kelasWaliKelas);
        
        return DB::transaction(function () use ($request, $kelasWaliKelas) {
            $validated = $request->validated();
            
            $semesterAktif = Semester::where('is_active', true)->first();
            if (!$semesterAktif) {
                return response()->json(['message' => 'Tidak ada semester yang aktif'], 400);
            }
            $validated['semester_id'] = $semesterAktif->id;

            if ($validated['is_active'] ?? false) {
                KelasWaliKelas::where('kelas_id', $validated['kelas_id'])
                    ->where('semester_id', $validated['semester_id'])
                    ->where('id', '!=', $kelasWaliKelas->id)
                    ->update(['is_active' => false]);
            }
            
            $kelasWaliKelas->update($validated);
            
            $kelasWaliKelas->load(['kelas', 'guruStaf', 'semester']);
            
            return new KelasWaliKelasResource($kelasWaliKelas);
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
        
        $semesterAktif = Semester::where('is_active', true)->first();
        if (!$semesterAktif) {
            return response()->json(['message' => 'Tidak ada semester yang aktif'], Response::HTTP_BAD_REQUEST);
        }

        $dataSudahAda = KelasWaliKelas::where('semester_id', $semesterAktif->id)->exists();
        if ($dataSudahAda) {
            return response()->json(['message' => 'Data untuk semester aktif sudah ada'], Response::HTTP_CONFLICT);
        }

        $semesterLalu = Semester::where('id', '<', $semesterAktif->id)
            ->orderBy('id', 'desc')
            ->first();
            
        if (!$semesterLalu) {
            return response()->json(['message' => 'Data semester sebelumnya tidak ditemukan'], Response::HTTP_NOT_FOUND);
        }

        $oldData = KelasWaliKelas::where('semester_id', $semesterLalu->id)
            ->where('is_active', true)
            ->get();
            
        if ($oldData->isEmpty()) {
            return response()->json(['message' => 'Data wali kelas aktif semester lalu tidak ditemukan'], Response::HTTP_NOT_FOUND);
        }

        DB::transaction(function () use ($oldData, $semesterAktif, $semesterLalu) {
            foreach ($oldData as $data) {
                KelasWaliKelas::create([
                    'kelas_id'     => $data->kelas_id,
                    'guru_staf_id' => $data->guru_staf_id,
                    'semester_id'  => $semesterAktif->id,
                    'is_active'    => true, 
                ]);
            }
        });
        
        return response()->json(['message' => 'Data berhasil disalin, data semester lalu tetap aktif'], Response::HTTP_CREATED);
    }

    public function bulkUpdateTingkat(Request $request)
    {
        $this->authorize('bulkUpdateTingkat', KelasWaliKelas::class);
        
        $request->validate([
            'mapping'  => 'required|array', 
        ]);
        
        $semesterAktif = Semester::where('is_active', true)->first();
        if (!$semesterAktif) {
            return response()->json(['message' => 'Tidak ada semester yang aktif'], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            DB::transaction(function () use ($request, $semesterAktif) {
                foreach ($request->mapping as $idWaliLama => $idKelasBaru) {
                    
                    $waliKelasLama = KelasWaliKelas::where('id', $idWaliLama)
                        ->where('semester_id', $semesterAktif->id)
                        ->first();
                        
                    if (!$waliKelasLama) {
                        throw new \Exception("ID Wali Kelas {$idWaliLama} tidak ditemukan atau bukan data semester aktif.");
                    }
                    
                    $dataKelasTujuan = KelasWaliKelas::where('kelas_id', $idKelasBaru)
                        ->where('semester_id', $semesterAktif->id)
                        ->whereNull('guru_staf_id')
                        ->first();
                        
                    if (!$dataKelasTujuan) {
                        throw new \Exception("Kelas tujuan {$idKelasBaru} tidak ditemukan atau sudah ada walinya.");
                    }
                    
                    $dataKelasTujuan->update([
                        'guru_staf_id' => $waliKelasLama->guru_staf_id,
                        'is_active'    => true
                    ]);
                    
                    $waliKelasLama->update([
                        'guru_staf_id' => null,
                        'is_active'    => true
                    ]);
                }
            });
            
            return response()->json(['message' => 'Guru berhasil dipindahkan dan kelas lama dikosongkan'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memindahkan guru.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function prepareNewYear(Request $request)
    {
        $this->authorize('prepareNewYear', KelasWaliKelas::class);
        
        $semesterAktif = Semester::where('is_active', true)->first();
        if (!$semesterAktif) {
            return response()->json(['message' => 'Tidak ada semester yang aktif'], Response::HTTP_BAD_REQUEST);
        }
        
        $allClasses = Kelas::where('is_active', true)->get();
        
        DB::transaction(function () use ($allClasses, $semesterAktif) {
            foreach ($allClasses as $kelas) {
                $exists = KelasWaliKelas::where('kelas_id', $kelas->id)
                    ->where('semester_id', $semesterAktif->id)
                    ->exists();
                    
                if (!$exists) {
                    KelasWaliKelas::create([
                        'kelas_id'     => $kelas->id,
                        'guru_staf_id' => null, 
                        'semester_id'  => $semesterAktif->id, 
                        'is_active'    => true, 
                    ]);
                }
            }
        });
        
        return response()->json(['message' => 'Kelas berhasil disiapkan untuk semester aktif'], Response::HTTP_OK);
    }
}