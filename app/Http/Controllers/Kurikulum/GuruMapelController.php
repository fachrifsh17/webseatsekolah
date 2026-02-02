<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruMapel;
use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use App\Http\Resources\GuruMapelResource;
use App\Http\Requests\StoreGuruMapelRequest;
use App\Http\Requests\UpdateGuruMapelRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class GuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas']);

        if ($request->has('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->whereHas('guru', function ($g) use ($search) {
                    $g->where('nama', 'LIKE', "%{$search}%")
                      ->orWhere('nip', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('mapel', function ($m) use ($search) {
                    $m->where('nama_mapel', 'LIKE', "%{$search}%")
                      ->orWhere('tipe_mapel', 'LIKE', "%{$search}%")
                      ->orWhereHas('jurusan', function ($j) use ($search) {
                          $j->where('nama_jurusan', 'LIKE', "%{$search}%");
                      });
                })
                ->orWhereHas('kelas', function ($k) use ($search) {
                    $k->where('nama_kelas', 'LIKE', "%{$search}%");
                });
            });
        }

        $perPage = $request->query('per_page', 10);

        $assignments = $query->latest()->paginate($perPage);

        return GuruMapelResource::collection($assignments)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function show(GuruMapel $guruMapel): JsonResponse
    {
        return response()->json(
            new GuruMapelResource(
                $guruMapel->load(['guru', 'mapel.jurusan', 'kelas'])
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

        return response()->json(
            [
                'success' => true,
                'data'    => $jam
            ],
            Response::HTTP_OK
        );
    }

    public function store(StoreGuruMapelRequest $request): JsonResponse
    {
        $this->authorize('create', GuruMapel::class);

        $validated = $request->validated();

        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $validated['tahun_ajaran_id'] = $validated['tahun_ajaran_id'] ?? optional($tahunAktif)->id;

        $exists = GuruMapel::where('guru_staf_id', $validated['guru_staf_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->where('kelas_id', $validated['kelas_id'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->exists();

        if ($exists) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Guru sudah terdaftar pada mata pelajaran dan kelas ini.',
                    'errors'  => [
                        'conflict' => [
                            'Kombinasi Guru, Mata Pelajaran, Kelas, dan Tahun Ajaran sudah ada.'
                        ]
                    ]
                ],
                Response::HTTP_CONFLICT
            );
        }

        try {
            $assignment = DB::transaction(
                fn() => GuruMapel::create($validated)
            );

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Penugasan guru berhasil ditambahkan.',
                    'data'    => new GuruMapelResource(
                        $assignment->load(['guru', 'mapel.jurusan', 'kelas'])
                    )
                ],
                Response::HTTP_CREATED
            );
        } catch (Throwable $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal menambahkan penugasan guru',
                    'errors'  => [
                        'exception' => [$e->getMessage()]
                    ]
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function update(UpdateGuruMapelRequest $request, GuruMapel $guruMapel): JsonResponse
    {
        $this->authorize('update', $guruMapel);

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
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Kombinasi Guru, Mata Pelajaran, Kelas, dan Tahun Ajaran ini sudah digunakan.',
                    'errors'  => [
                        'conflict' => [
                            'Data penugasan serupa sudah ada di sistem.'
                        ]
                    ]
                ],
                Response::HTTP_CONFLICT
            );
        }

        try {
            DB::transaction(
                fn() => $guruMapel->update($validated)
            );

            $guruMapel->refresh();

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Penugasan guru berhasil diperbarui.',
                    'data'    => new GuruMapelResource(
                        $guruMapel->load(['guru', 'mapel.jurusan', 'kelas'])
                    )
                ],
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal memperbarui penugasan guru',
                    'errors'  => [
                        'exception' => [$e->getMessage()]
                    ]
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy(GuruMapel $guruMapel): JsonResponse
    {
        $this->authorize('delete', $guruMapel);

        try {
            DB::transaction(
                fn() => $guruMapel->delete()
            );

            return response()->json(
                [
                    'success'      => true,
                    'message'      => 'Penugasan guru berhasil dihapus',
                    'notification' => 'Berhasil dihapus'
                ],
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal menghapus penugasan guru',
                    'errors'  => [
                        'exception' => [$e->getMessage()]
                    ]
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
