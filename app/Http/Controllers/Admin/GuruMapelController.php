<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruMapel;
use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use App\Http\Resources\GuruMapelResource;
use App\Http\Requests\StoreGuruMapelRequest;
use App\Http\Requests\UpdateGuruMapelRequest;
use App\Exports\GuruMapelExport;
use App\Imports\GuruMapelImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class GuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy', 'import']);
    }

    private function applyFilters(Request $request)
    {
        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);

        // Filter berdasarkan Tahun Ajaran Aktif secara default
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $tahunAjaranId = $request->get('tahun_ajaran_id', optional($tahunAktif)->id);

        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->whereHas('guru', fn($g) => $g->where('nama', 'LIKE', "%{$search}%")->orWhere('nip', 'LIKE', "%{$search}%"))
                  ->orWhereHas('mapel', fn($m) => $m->where('nama_mapel', 'LIKE', "%{$search}%"))
                  ->orWhereHas('kelas', fn($k) => $k->where('nama_kelas', 'LIKE', "%{$search}%"));
            });
        }

        $query->when($tahunAjaranId, fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
              ->when($request->guru_staf_id, fn($q, $id) => $q->where('guru_staf_id', $id))
              ->when($request->mata_pelajaran_id, fn($q, $id) => $q->where('mata_pelajaran_id', $id))
              ->when($request->kelas_id, fn($q, $id) => $q->where('kelas_id', $id))
              ->when($request->hari, fn($q, $hari) => $q->where('hari', $hari));

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->applyFilters($request);
        $perPage = $request->get('per_page', 10);
        $assignments = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => GuruMapelResource::collection($assignments),
            'meta'    => [
                'current_page' => $assignments->currentPage(),
                'last_page'    => $assignments->lastPage(),
                'per_page'     => $assignments->perPage(),
                'total'        => $assignments->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        try {
            $query = $this->applyFilters($request);
            
            // Ambil data profil sekolah untuk kop surat di excel (jika dibutuhkan oleh GuruMapelExport)
            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();

            $fileName = 'Data_Penugasan_Guru_' . now()->format('Ymd_His') . '.xlsx';
            
            return Excel::download(new GuruMapelExport($query, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Gagal mengekspor data penugasan.'], 500);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            DB::transaction(function () use ($request) {
                Excel::import(new GuruMapelImport, $request->file('file'));
            });

            return response()->json([
                'success' => true,
                'message' => 'Data penugasan guru berhasil diimport secara massal.',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal import: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(GuruMapel $guruMapel): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new GuruMapelResource($guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']))
        ], Response::HTTP_OK);
    }

    public function getJamByHari(Request $request): JsonResponse
    {
        $hari = $request->query('hari');
        $jam = JamSekolah::where('hari', $hari)
            ->orderBy('waktu_mulai')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $jam
        ], Response::HTTP_OK);
    }

    public function store(StoreGuruMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $validated['tahun_ajaran_id'] = $validated['tahun_ajaran_id'] ?? optional($tahunAktif)->id;

        $exists = GuruMapel::where('guru_staf_id', $validated['guru_staf_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->where('kelas_id', $validated['kelas_id'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Guru sudah terdaftar pada mata pelajaran dan kelas ini.',
                'errors'  => ['conflict' => ['Kombinasi Guru, Mapel, Kelas, dan Tahun Ajaran sudah ada.']]
            ], Response::HTTP_CONFLICT);
        }

        try {
            $assignment = DB::transaction(fn() => GuruMapel::create($validated));
            
            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru berhasil ditambahkan.',
                'data'    => new GuruMapelResource($assignment->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Store Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan penugasan guru.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateGuruMapelRequest $request, GuruMapel $guruMapel): JsonResponse
    {
        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $validated['tahun_ajaran_id'] = $validated['tahun_ajaran_id'] ?? optional($tahunAktif)->id;

        $exists = GuruMapel::where('guru_staf_id', $validated['guru_staf_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->where('kelas_id', $validated['kelas_id'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->where('id', '<>', $guruMapel->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Kombinasi penugasan ini sudah digunakan pada data lain.',
                'errors'  => ['conflict' => ['Data penugasan serupa sudah ada di sistem.']]
            ], Response::HTTP_CONFLICT);
        }

        try {
            DB::transaction(fn() => $guruMapel->update($validated));
            
            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru berhasil diperbarui.',
                'data'    => new GuruMapelResource($guruMapel->refresh()->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui penugasan guru.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(GuruMapel $guruMapel): JsonResponse
    {
        try {
            DB::transaction(fn() => $guruMapel->delete());
            
            return response()->json([
                'success'      => true,
                'message'      => 'Penugasan guru berhasil dihapus.',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus penugasan guru.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}