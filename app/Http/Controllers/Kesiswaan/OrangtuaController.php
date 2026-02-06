<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\Orangtua;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Http\Requests\UpdateOrangtuaRequest;
use App\Http\Resources\OrangtuaResource;
use App\Exports\OrangtuaExport;
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
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['update']);
    }

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
            Log::error('Kesiswaan - Update Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data orang tua',
                'errors'  => ['exception' => [$e->getMessage()]]
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
}