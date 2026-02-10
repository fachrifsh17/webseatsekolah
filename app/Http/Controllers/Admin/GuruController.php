<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruStaf;
use App\Models\Jurusan;
use App\Http\Resources\GuruResource;
use App\Http\Requests\StoreGuruRequest;
use App\Http\Requests\UpdateGuruRequest;
use App\Exports\GuruExport;
use App\Imports\GuruImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class GuruController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy', 'import']);
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->get('q');
        $jabatan = $request->get('jabatan_fungsional');
        $status = $request->get('status_kepegawaian');
        $jurusan = $request->get('jurusan_id');
        $active = $request->has('is_active') ? $request->get('is_active') : 1;

        $data = GuruStaf::with(['jurusan', 'user'])
            ->when($search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('nip', 'like', "%{$search}%")
                      ->orWhere('nuptk', 'like', "%{$search}%");
                });
            })
            ->when($jabatan, fn($q) => $q->where('jabatan_fungsional', $jabatan))
            ->when($status, fn($q) => $q->where('status_kepegawaian', $status))
            ->when($jurusan, fn($q) => $q->where('jurusan_id', $jurusan))
            ->where('is_active', $active)
            ->latest()
            ->paginate($request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'data'    => GuruResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        try {
            $filters = $request->only(['q', 'jabatan_fungsional', 'status_kepegawaian', 'jurusan_id', 'is_active']);
            
            if (!isset($filters['is_active'])) {
                $filters['is_active'] = 1;
            }

            $labels = [];

            if (!empty($filters['q'])) {
                $labels[] = 'Cari_' . str_replace(' ', '_', $filters['q']);
            }

            if (!empty($filters['jabatan_fungsional'])) {
                $labels[] = str_replace(' ', '_', $filters['jabatan_fungsional']);
            }

            if (!empty($filters['status_kepegawaian'])) {
                $labels[] = str_replace(' ', '_', $filters['status_kepegawaian']);
            }

            if (!empty($filters['jurusan_id'])) {
                $jurusan = Jurusan::find($filters['jurusan_id']);
                if ($jurusan) {
                    $cleanJurusan = str_replace(' ', '_', preg_replace('/[^A-Za-z0-9 ]/', '', $jurusan->nama_jurusan));
                    $labels[] = $cleanJurusan;
                }
            }

            $suffix = !empty($labels) ? '_' . implode('_', $labels) : '';
            
            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();

            $fileName = 'Data_Guru_Staf' . $suffix . '_' . now()->format('Ymd_His') . '.xlsx';
            
            return Excel::download(new GuruExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Guru Error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Gagal mengekspor data guru.'], 500);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            $import = new GuruImport();
            Excel::import($import, $request->file('file'));
            $conflicts = $import->getMessages();

            return response()->json([
                'success' => true,
                'message' => count($conflicts) > 0 
                            ? 'Import selesai dengan beberapa catatan' 
                            : 'Data guru berhasil diimport secara massal.',
                'conflicts' => $conflicts
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Guru Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal import',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q');

        $gurus = GuruStaf::query()
            ->where('is_active', 1)
            ->when($search, function ($query, $search) {
                $query->where('nama', 'like', "%{$search}%")
                      ->orWhere('nip', 'like', "%{$search}%");
            })
            ->select('id', 'nama', 'nip')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $gurus,
        ], Response::HTTP_OK);
    }

    public function show(?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
                'errors'  => ['id' => ['Guru dengan ID tersebut tidak ada']],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data'    => new GuruResource($guru->load(['jurusan', 'user'])),
        ], Response::HTTP_OK);
    }

    public function store(StoreGuruRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/guru', 'public');
        }

        if (empty($validated['user_id'])) {
            $validated['user_id'] = (string) Auth::id();
        }

        try {
            $guru = DB::transaction(function () use ($validated) {
                return GuruStaf::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil ditambahkan.',
                'data'    => new GuruResource($guru->load(['jurusan', 'user'])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create guru', ['error' => $e->getMessage()]);

            if (!empty($validated['foto'])) {
                Storage::disk('public')->delete($validated['foto']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat data guru.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateGuruRequest $request, ?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $oldFoto = $guru->foto;

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/guru', 'public');
        }

        if ($request->filled('foto') && $request->foto === 'null') {
            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }
            $validated['foto'] = null;
        }

        try {
            DB::transaction(function () use ($guru, $validated) {
                $guru->update($validated);
            });

            if ($request->hasFile('foto') && $oldFoto && $oldFoto !== $validated['foto']) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil diperbarui.',
                'data'    => new GuruResource($guru->refresh()->load(['jurusan', 'user'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update guru', ['error' => $e->getMessage()]);

            if ($request->hasFile('foto') && isset($validated['foto'])) {
                Storage::disk('public')->delete($validated['foto']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data guru.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
            ], Response::HTTP_NOT_FOUND);
        }

        $oldFoto = $guru->foto;

        try {
            $blockers = [];

            $relations = [
                'user' => 'Terdapat akun user yang terhubung',
                'guruMapel' => 'Terhubung dengan data guru_mapel',
                'presensiGuruMapel' => 'Memiliki data presensi per mapel',
                'presensi' => 'Memiliki data presensi',
                'jadwalMengajar' => 'Memiliki jadwal mengajar',
                'strukturJabatan' => 'Memiliki struktur jabatan',
                'jadwalProduktif' => 'Memiliki jadwal produktif',
                'poinGuru' => 'Memiliki data poin'
            ];

            foreach ($relations as $method => $message) {
                if (method_exists($guru, $method) && $guru->$method()->exists()) {
                    $blockers[] = $message;
                }
            }

            if (!empty($blockers)) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'conflict_relations',
                    'message'    => 'Gagal menghapus: data masih terhubung dengan resource lain.',
                    'details'    => array_values(array_unique($blockers)),
                ], Response::HTTP_CONFLICT);
            }

            DB::transaction(function () use ($guru) {
                if (method_exists($guru, 'mapel')) {
                    $guru->mapel()->detach();
                }
                $guru->delete();
            });

            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success'      => true,
                'message'      => 'Data guru berhasil dihapus',
                'notification' => 'Berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete guru', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data guru',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}