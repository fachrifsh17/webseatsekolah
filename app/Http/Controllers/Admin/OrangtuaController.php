<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Orangtua;
use App\Models\Kelas;
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

class OrangtuaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'import']);

        // Mengaktifkan Policy otomatis
        $this->authorizeResource(Orangtua::class, 'orangtua');
    }

    private function applyFilters(Request $request, $query)
    {
        $tahunAjaranAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
        $tahunAjaranId = $request->query('tahun_ajaran_id', $tahunAjaranAktif?->id);

        $query->whereHas('anak.kelas', function ($q) use ($tahunAjaranId) {
            if ($tahunAjaranId) {
                $q->where('tahun_ajaran_id', $tahunAjaranId);
            }
        });

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
        try {
            $tahunAjaranAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
            $tahunAjaranId = $request->query('tahun_ajaran_id', $tahunAjaranAktif?->id);

            $query = Orangtua::with(['user', 'anak' => function($q) use ($tahunAjaranId) {
                $q->whereHas('kelas', function($qk) use ($tahunAjaranId) {
                    $qk->where('tahun_ajaran_id', $tahunAjaranId);
                })->with('kelas.jurusan');
            }]);

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
        } catch (Throwable $e) {
            Log::error('Fetch Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data orang tua'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
            Log::error('Create Orangtua Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan orang tua',
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
            Log::error('Update Orangtua Error', ['id' => $orangtua->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui orang tua',
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
            Log::error('Delete Orangtua Error', ['id' => $orangtua->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus orang tua',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $this->authorize('viewAny', Orangtua::class);

            $query = Orangtua::query()->with(['anak.kelas.jurusan']);
            $query = $this->applyFilters($request, $query);

            $filterParts = [];
            // ... (logika penamaan file tetap sama) ...
            
            $filename = 'Data_Orangtua_' . now()->format('Ymd_His') . '.xlsx';
            
            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            $kelasData = $request->filled('kelas_id') ? Kelas::find($request->kelas_id) : null;

            return Excel::download(
                new OrangtuaExport($query, $profil, $kontak, $kelasData, $request->all()), 
                $filename
            );
        } catch (Throwable $e) {
            Log::error('Export Orangtua Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal export data'], 500);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', Orangtua::class);

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            $import = new OrangtuaImport;
            Excel::import($import, $request->file('file'));
            $conflicts = $import->getMessages();

            return response()->json([
                'success' => true, 
                'message' => count($conflicts) > 0 
                             ? 'Import selesai dengan beberapa catatan.' 
                             : 'Data orang tua berhasil diimport.',
                'conflicts' => $conflicts
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