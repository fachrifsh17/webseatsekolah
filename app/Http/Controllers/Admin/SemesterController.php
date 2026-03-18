<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use App\Http\Resources\SemesterResource;
use App\Http\Requests\StoreSemesterRequest;
use App\Http\Requests\UpdateSemesterRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

class SemesterController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        
        $this->authorizeResource(Semester::class, 'semester');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->query('per_page', 10), 100);
            $items = Semester::with('tahunAjaran')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
            
            $paginationData = $items->toArray();

            return response()->json([
                'success' => true,
                'data'    => SemesterResource::collection($items),
                'meta'    => [
                    'current_page'  => $paginationData['current_page'],
                    'last_page'     => $paginationData['last_page'],
                    'per_page'      => $paginationData['per_page'],
                    'total'         => $paginationData['total'],
                    'from'          => $paginationData['from'],
                    'to'            => $paginationData['to'],
                    'path'          => $paginationData['path'],
                    'next_page_url' => $paginationData['next_page_url'],
                    'prev_page_url' => $paginationData['prev_page_url'],
                    'links'         => array_map(function ($link) {
                        return [
                            'url'    => $link['url'],
                            'label'  => $link['label'],
                            'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
                            'active' => $link['active'],
                        ];
                    }, $paginationData['links']),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch semesters', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar semester',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreSemesterRequest $request): JsonResponse
    {
        $activeTahunAjaran = TahunAjaran::where('is_active', true)->first();

        if (!$activeTahunAjaran) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada tahun ajaran yang aktif.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $semester = DB::transaction(function () use ($request, $activeTahunAjaran) {
                $validated = $request->validated();
                
                if (empty($validated['tahun'])) {
                    $namaTA = $activeTahunAjaran->nama;
                    if (str_contains(strtolower($validated['nama']), 'ganjil')) {
                        $validated['tahun'] = (int) substr($namaTA, 0, 4);
                    } else {
                        $validated['tahun'] = (int) substr($namaTA, 5, 4);
                    }
                }

                $existingSemester = Semester::where('tahun_ajaran_id', $activeTahunAjaran->id)
                    ->where('nama', $validated['nama'])
                    ->first();

                if ($existingSemester) {
                    throw ValidationException::withMessages([
                        'nama' => ['Nama semester sudah ada dalam tahun ajaran ini.'],
                    ]);
                }

                $currentSemesterCount = Semester::where('tahun_ajaran_id', $activeTahunAjaran->id)->count();

                if ($currentSemesterCount >= 2) {
                    throw ValidationException::withMessages([
                        'nama' => ['Tahun ajaran ini sudah memiliki 2 semester.'],
                    ]);
                }

                Semester::query()->update(['is_active' => false]);

                $validated['tahun_ajaran_id'] = $activeTahunAjaran->id;
                $validated['is_active'] = true;

                return Semester::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil ditambahkan.',
                'data'    => new SemesterResource($semester->load('tahunAjaran'))
            ], Response::HTTP_CREATED);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::error('Failed to create semester', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan semester'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Semester $semester): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new SemesterResource($semester->load('tahunAjaran')),
        ], Response::HTTP_OK);
    }

    public function update(UpdateSemesterRequest $request, Semester $semester): JsonResponse
    {
        try {
            DB::transaction(function () use ($request, $semester) {
                $validated = $request->validated();
                
                if (empty($validated['tahun'])) {
                    $namaTA = $semester->tahunAjaran->nama; 
                    if (str_contains(strtolower($validated['nama']), 'ganjil')) {
                        $validated['tahun'] = (int) substr($namaTA, 0, 4);
                    } else {
                        $validated['tahun'] = (int) substr($namaTA, 5, 4);
                    }
                }

                $existingSemester = Semester::where('tahun_ajaran_id', $semester->tahun_ajaran_id)
                    ->where('nama', $validated['nama'])
                    ->where('id', '!=', $semester->id)
                    ->first();

                if ($existingSemester) {
                    throw ValidationException::withMessages([
                        'nama' => ['Nama semester sudah ada dalam tahun ajaran ini.'],
                    ]);
                }

                if (isset($validated['is_active']) && $validated['is_active']) {
                    Semester::where('id', '!=', $semester->id)->update(['is_active' => false]);
                }

                $semester->update($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil diperbarui.',
                'data'    => new SemesterResource($semester->fresh()->load('tahunAjaran'))
            ], Response::HTTP_OK);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::error('Failed to update semester', ['id' => $semester->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui semester'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Semester $semester): JsonResponse
    {
        try {
            $relations = [
                'presensiGuruMapel' => 'Presensi Guru Mapel',
                'waliKelas'         => 'Wali Kelas',
                'riwayatKelas'      => 'Riwayat Kelas',
                'presensi'          => 'Presensi',
                'poinSiswa'         => 'Poin Siswa',
                'jamSekolah'        => 'Jam Sekolah',
                'kalenderAkademik'  => 'Kalender Akademik',
                'guruMapel'         => 'Guru Mapel',
            ];

            foreach ($relations as $relation => $label) {
                if ($semester->$relation()->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Semester tidak dapat dihapus karena masih digunakan di data $label."
                    ], Response::HTTP_CONFLICT);
                }
            }

            $semester->delete();

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil dihapus'
            ], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            Log::error('Failed to delete semester', ['id' => $semester->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus semester'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}