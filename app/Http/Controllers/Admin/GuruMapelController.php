<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruMapel;
<<<<<<< HEAD
use App\Models\GuruStaf;
use App\Models\MataPelajaran;
use App\Models\Kelas;
use App\Http\Resources\GuruResource;
use App\Http\Resources\MapelResource;
use App\Http\Resources\KelasResource;
use App\Http\Resources\GuruMapelResource;
use App\Http\Requests\StoreGuruMapelRequest;
use App\Http\Requests\UpdateGuruMapelRequest;
=======
use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use App\Http\Resources\GuruMapelResource;
use App\Http\Requests\StoreGuruMapelRequest;
use App\Http\Requests\UpdateGuruMapelRequest;
use App\Exports\GuruMapelExport;
use App\Imports\GuruMapelImport;
use Maatwebsite\Excel\Facades\Excel;
>>>>>>> master
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
<<<<<<< HEAD
use Symfony\Component\HttpFoundation\Response; 
=======
use Symfony\Component\HttpFoundation\Response;
>>>>>>> master

class GuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
<<<<<<< HEAD
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
=======
        $this->middleware('log.admin')->only(['store', 'update', 'destroy', 'import']);
    }

    private function applyFilters(Request $request)
    {
        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('guru', fn($g) => $g->where('nama', 'LIKE', "%{$search}%")->orWhere('nip', 'LIKE', "%{$search}%"))
                  ->orWhereHas('mapel', fn($m) => $m->where('nama_mapel', 'LIKE', "%{$search}%"))
                  ->orWhereHas('kelas', fn($k) => $k->where('nama_kelas', 'LIKE', "%{$search}%"));
            });
        }

        $query->when($request->guru_staf_id, fn($q, $id) => $q->where('guru_staf_id', $id))
              ->when($request->mata_pelajaran_id, fn($q, $id) => $q->where('mata_pelajaran_id', $id))
              ->when($request->kelas_id, fn($q, $id) => $q->where('kelas_id', $id))
              ->when($request->tahun_ajaran_id, fn($q, $id) => $q->where('tahun_ajaran_id', $id))
              ->when($request->hari, fn($q, $hari) => $q->where('hari', $hari));

        return $query;
>>>>>>> master
    }

    public function index(Request $request): JsonResponse
    {
<<<<<<< HEAD
        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('guru', function($g) use ($search) {
                    $g->where('nama', 'LIKE', "%{$search}%")
                      ->orWhere('nip', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('mapel', function($m) use ($search) {
                    $m->where('nama_mapel', 'LIKE', "%{$search}%")
                      ->orWhere('tipe_mapel', 'LIKE', "%{$search}%")
                      ->orWhereHas('jurusan', function($j) use ($search) {
                          $j->where('nama_jurusan', 'LIKE', "%{$search}%");
                      });
                })
                ->orWhereHas('kelas', function($k) use ($search) {
                    $k->where('nama_kelas', 'LIKE', "%{$search}%");
                });
            });
        }

        $perPage = $request->query('per_page', 10);
        $assignments = $query->latest()->paginate($perPage);

        return GuruMapelResource::collection($assignments)->response()
            ->setStatusCode(Response::HTTP_OK); 
=======
        $query = $this->applyFilters($request);
        $perPage = $request->query('per_page', 10);
        $assignments = $query->latest()->paginate($perPage);

        return GuruMapelResource::collection($assignments)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $query = $this->applyFilters($request);
        return Excel::download(new GuruMapelExport($query), 'data_penugasan_guru.xlsx');
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
                'message' => 'Data penugasan guru berhasil diimport'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal import: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
>>>>>>> master
    }

    public function show(GuruMapel $guruMapel): JsonResponse
    {
        return response()->json(
<<<<<<< HEAD
            new GuruMapelResource($guruMapel->load(['guru', 'mapel.jurusan', 'kelas'])),
            Response::HTTP_OK 
        );
    }

    public function store(StoreGuruMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();
=======
            new GuruMapelResource(
                $guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran'])
            ),
            Response::HTTP_OK
        );
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
        $this->authorize('create', GuruMapel::class);
        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $validated['tahun_ajaran_id'] = $validated['tahun_ajaran_id'] ?? optional($tahunAktif)->id;
>>>>>>> master

        $exists = GuruMapel::where('guru_staf_id', $validated['guru_staf_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->where('kelas_id', $validated['kelas_id'])
<<<<<<< HEAD
=======
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
>>>>>>> master
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Guru sudah terdaftar pada mata pelajaran dan kelas ini.',
<<<<<<< HEAD
                'errors'  => [
                    'conflict' => ['Kombinasi Guru, Mata Pelajaran, dan Kelas sudah ada.']
                ]
            ], Response::HTTP_CONFLICT); 
=======
                'errors'  => ['conflict' => ['Kombinasi Guru, Mata Pelajaran, Kelas, dan Tahun Ajaran sudah ada.']]
            ], Response::HTTP_CONFLICT);
>>>>>>> master
        }

        try {
            $assignment = DB::transaction(fn() => GuruMapel::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru berhasil ditambahkan.',
<<<<<<< HEAD
                'data'    => new GuruMapelResource($assignment->load(['guru', 'mapel.jurusan', 'kelas']))
=======
                'data'    => new GuruMapelResource($assignment->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']))
>>>>>>> master
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan penugasan guru',
                'errors'  => ['exception' => [$e->getMessage()]]
<<<<<<< HEAD
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
=======
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
>>>>>>> master
        }
    }

    public function update(UpdateGuruMapelRequest $request, GuruMapel $guruMapel): JsonResponse
    {
<<<<<<< HEAD
        $validated = $request->validated();
=======
        $this->authorize('update', $guruMapel);
        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $validated['tahun_ajaran_id'] = $validated['tahun_ajaran_id'] ?? optional($tahunAktif)->id;
>>>>>>> master

        $exists = GuruMapel::where('guru_staf_id', $validated['guru_staf_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->where('kelas_id', $validated['kelas_id'])
<<<<<<< HEAD
=======
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
>>>>>>> master
            ->where('id', '<>', $guruMapel->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
<<<<<<< HEAD
                'message' => 'Kombinasi Guru, Mata Pelajaran, dan Kelas ini sudah digunakan.',
                'errors'  => [
                    'conflict' => ['Data penugasan serupa sudah ada di sistem.']
                ]
            ], Response::HTTP_CONFLICT); 
=======
                'message' => 'Kombinasi Guru, Mata Pelajaran, Kelas, dan Tahun Ajaran ini sudah digunakan.',
                'errors'  => ['conflict' => ['Data penugasan serupa sudah ada di sistem.']]
            ], Response::HTTP_CONFLICT);
>>>>>>> master
        }

        try {
            DB::transaction(fn() => $guruMapel->update($validated));
            $guruMapel->refresh();
<<<<<<< HEAD

            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru berhasil diperbarui.',
                'data'    => new GuruMapelResource($guruMapel->load(['guru', 'mapel.jurusan', 'kelas']))
            ], Response::HTTP_OK); 
=======
            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru berhasil diperbarui.',
                'data'    => new GuruMapelResource($guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']))
            ], Response::HTTP_OK);
>>>>>>> master
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui penugasan guru',
                'errors'  => ['exception' => [$e->getMessage()]]
<<<<<<< HEAD
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
=======
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
>>>>>>> master
        }
    }

    public function destroy(GuruMapel $guruMapel): JsonResponse
    {
<<<<<<< HEAD
        try {
            DB::transaction(fn() => $guruMapel->delete());

=======
        $this->authorize('delete', $guruMapel);
        try {
            DB::transaction(fn() => $guruMapel->delete());
>>>>>>> master
            return response()->json([
                'success'      => true,
                'message'      => 'Penugasan guru berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus penugasan guru',
                'errors'  => ['exception' => [$e->getMessage()]]
<<<<<<< HEAD
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
        }
    }
}
=======
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
>>>>>>> master
