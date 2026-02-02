<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Orangtua;
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
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('q');

        $orangtua = Orangtua::with(['user', 'anak.kelas.jurusan'])
            ->when($search, function ($query, $search) {
                $query->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('telepon', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($request->query('per_page', 20));

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

                if ($anak) {
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

                if ($anak) {
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
            Log::error('Failed to update orangtua', ['orangtua_id' => (string) $orangtua->id, 'error' => $e->getMessage()]);
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

            if (method_exists($orangtua, 'anak') && $orangtua->anak()->exists()) {
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
                if (method_exists($orangtua, 'anak')) {
                    $orangtua->anak()->detach();
                }
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
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $filters = $request->only(['q']);
        return Excel::download(new OrangtuaExport($filters), 'data_orangtua.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            Excel::import(new OrangtuaImport, $request->file('file'));
            return response()->json(['success' => true, 'message' => 'Data berhasil diimport.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}