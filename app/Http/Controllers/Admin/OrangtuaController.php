<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Orangtua;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Http\Requests\StoreOrangtuaRequest;
use App\Http\Requests\UpdateOrangtuaRequest;
use App\Http\Resources\OrangtuaResource;
use App\Exports\OrangtuaExport;
use App\Imports\OrangtuaImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class OrangtuaController extends Controller
{
    private function applyFilters(Request $request, $query)
    {
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('telepon', 'like', "%{$search}%");
            });
        }

        if ($request->filled('jurusan_id')) {
            $query->whereHas('anak.kelas', function ($q) use ($request) {
                $q->where('jurusan_id', $request->jurusan_id);
            });
        }

        if ($request->filled('kelas_id')) {
            $query->whereHas('anak', function ($q) use ($request) {
                $q->where('kelas_id', $request->kelas_id);
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Orangtua::with(['user', 'anak.kelas.jurusan']);
        $query = $this->applyFilters($request, $query);

        $orangtua = $query->latest()->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => OrangtuaResource::collection($orangtua),
            'meta'    => [
                'current_page' => $orangtua->currentPage(),
                'last_page'    => $orangtua->lastPage(),
                'per_page'     => $orangtua->perPage(),
                'total'        => $orangtua->total(),
            ]
        ], Response::HTTP_OK);
    }

    public function store(StoreOrangtuaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $orangtua = DB::transaction(function () use ($validated) {
                $anak = $validated['anak'] ?? [];
                unset($validated['anak']);

                $orangtua = Orangtua::create($validated);

                if (!empty($anak)) {
                    $syncData = [];
                    foreach ($anak as $item) {
                        $syncData[(string) $item['siswa_id']] = [
                            'hubungan' => $item['hubungan'] ?? null
                        ];
                    }
                    $orangtua->anak()->sync($syncData);
                }

                return $orangtua;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data orang tua berhasil ditambahkan',
                'data'    => new OrangtuaResource($orangtua->load(['user','anak.kelas.jurusan']))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create orangtua', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan orang tua',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Orangtua $orangtua): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new OrangtuaResource($orangtua->load(['user','anak.kelas.jurusan']))
        ], Response::HTTP_OK);
    }

    public function update(UpdateOrangtuaRequest $request, Orangtua $orangtua): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $orangtua) {
                $anak = $validated['anak'] ?? [];
                unset($validated['anak']);

                $orangtua->update($validated);

                if (isset($anak)) {
                     $syncData = [];
                     foreach ($anak as $item) {
                         $syncData[(string) $item['siswa_id']] = [
                             'hubungan' => $item['hubungan'] ?? null
                         ];
                     }
                     $orangtua->anak()->sync($syncData);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Data orang tua berhasil diperbarui',
                'data'    => new OrangtuaResource($orangtua->load(['user','anak.kelas.jurusan']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update orangtua', ['orangtua_id' => (string) $orangtua->id, 'payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui orang tua',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Orangtua $orangtua): JsonResponse
    {
        try {
            $blockers = [];
            if ($orangtua->user()->exists()) {
                $blockers[] = 'Terdapat akun user yang terhubung';
            }
            if ($orangtua->anak()->exists()) {
                $blockers[] = 'Terhubung dengan data anak';
            }

            $force = request()->boolean('force', false);

            if (!empty($blockers) && !$force) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'conflict_relations',
                    'message'    => 'Penghapusan diblokir karena terdapat data terkait.',
                    'reasons'    => $blockers,
                    'hint'       => 'Gunakan parameter ?force=1 untuk memaksa penghapusan.'
                ], Response::HTTP_CONFLICT);
            }

            DB::transaction(function () use ($orangtua) {
                $orangtua->anak()->detach();
                $orangtua->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Data orang tua berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete orangtua', ['orangtua_id' => (string) $orangtua->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus orang tua',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $kelasId = $request->kelas_id;
        
        $query = Orangtua::query()->with(['anak' => function($q) use ($kelasId) {
            if ($kelasId) {
                $q->where('kelas_id', $kelasId);
            }
            $q->with('kelas');
        }]);
        
        $query = $this->applyFilters($request, $query);

        $filename = 'Data_Orangtua';
        $kelasData = null;
        
        if ($request->filled('kelas_id')) {
            $kelasData = Kelas::find($request->kelas_id);
            if ($kelasData) {
                $filename .= '_' . Str::slug($kelasData->nama_kelas);
            }
        } elseif ($request->filled('jurusan_id')) {
            $jurusan = Jurusan::find($request->jurusan_id);
            if ($jurusan) {
                $filename .= '_' . Str::slug($jurusan->nama_jurusan);
            }
        }

        $filename .= '_' . now()->format('Ymd_His') . '.xlsx';

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        return Excel::download(
            new OrangtuaExport($query, $profil, $kontak, $kelasData, $request->all()), 
            $filename
        );
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            Excel::import(new OrangtuaImport, $request->file('file'));
            return response()->json([
                'success' => true, 
                'message' => 'Data orang tua berhasil diimport.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengimport data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}