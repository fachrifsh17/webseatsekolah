<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{GuruMapel, JamSekolah, GuruStaf, ProfilSekolah, DataKontak};
use App\Http\Resources\GuruMapelResource;
use App\Exports\GuruMapelExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log, DB};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Throwable;

class GuruMapelController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'export']);
        $this->authorizeResource(GuruMapel::class, 'guru_mapel');
    }

    public function index(Request $request): JsonResponse
    {
        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);
        $query = $this->applyFilters($request, $query);

        $perPage = $request->query('per_page', 20);
        $data = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar penugasan guru jurusan berhasil dimuat.',
            'data'    => GuruMapelResource::collection($data)->response()->getData(true),
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'guru_id'         => 'required|exists:guru_staf,id',
            'mapel_id'        => 'required|exists:mapel,id',
            'kelas_id'        => 'required|exists:kelas,id',
            'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
            'hari'            => 'required|string',
            'jam_mulai'       => 'required',
            'jam_selesai'     => 'required',
        ]);

        try {
            DB::beginTransaction();

            $user = Auth::user();
            $guruStaf = GuruStaf::where('user_id', $user->id)->first();

            $mapel = \App\Models\Matapelajaran::findOrFail($request->mapel_id);
            if ($mapel->jurusan_id !== $guruStaf->jurusan_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda hanya bisa menambah penugasan untuk mata pelajaran di jurusan Anda.'
                ], Response::HTTP_FORBIDDEN);
            }

            $data = GuruMapel::create($request->all());

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru berhasil ditambahkan.',
                'data'    => new GuruMapelResource($data->load(['guru', 'mapel', 'kelas']))
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Store GuruMapel Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menambah data.'], 500);
        }
    }

    public function show(GuruMapel $guruMapel): JsonResponse
    {
        $this->checkJurusanAccess($guruMapel);

        $guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);
        return response()->json([
            'success' => true,
            'data'    => new GuruMapelResource($guruMapel)
        ], Response::HTTP_OK);
    }

    public function update(Request $request, GuruMapel $guruMapel): JsonResponse
    {
        $this->checkJurusanAccess($guruMapel);

        $request->validate([
            'guru_id'         => 'sometimes|exists:guru_staf,id',
            'mapel_id'        => 'sometimes|exists:mapel,id',
            'kelas_id'        => 'sometimes|exists:kelas,id',
            'tahun_ajaran_id' => 'sometimes|exists:tahun_ajaran,id',
        ]);

        try {
            $guruMapel->update($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru berhasil diperbarui.',
                'data'    => new GuruMapelResource($guruMapel->load(['guru', 'mapel', 'kelas']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update GuruMapel Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data.'], 500);
        }
    }

    public function destroy(GuruMapel $guruMapel): JsonResponse
    {
        $this->checkJurusanAccess($guruMapel);

        try {
            $guruMapel->delete();
            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete GuruMapel Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], 500);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', GuruMapel::class);

        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        
        if (!$guruStaf?->jurusan_id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);
        $query = $this->applyFilters($request, $query);

        $profil = ProfilSekolah::first();
        $kontak = DataKontak::first();

        $filename = 'Data_Penugasan_Guru_' . Str::slug($guruStaf->jurusan->nama_jurusan ?? 'Jurusan') . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new GuruMapelExport($query, $profil, $kontak), $filename);
    }

    public function getJamByHari(Request $request): JsonResponse
    {
        $hari = $request->query('hari');
        $jam = JamSekolah::where('hari', $hari)->orderBy('waktu_mulai')->get();

        return response()->json([
            'success' => true,
            'data'    => $jam
        ], Response::HTTP_OK);
    }

    private function applyFilters(Request $request, $query)
    {
        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        $jurusanId = $guruStaf?->jurusan_id;

        if ($jurusanId) {
            $query->whereHas('mapel', fn($q) => $q->where('jurusan_id', $jurusanId));
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('guru', fn($g) => $g->where('nama', 'LIKE', "%{$search}%")->orWhere('nip', 'LIKE', "%{$search}%"))
                  ->orWhereHas('mapel', fn($m) => $m->where('nama_mapel', 'LIKE', "%{$search}%"))
                  ->orWhereHas('kelas', fn($k) => $k->where('nama_kelas', 'LIKE', "%{$search}%"));
            });
        }

        return $query;
    }

    private function checkJurusanAccess(GuruMapel $guruMapel)
    {
        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        
        if (!$guruStaf || $guruMapel->mapel->jurusan_id !== $guruStaf->jurusan_id) {
            abort(Response::HTTP_FORBIDDEN, 'Anda tidak diizinkan memodifikasi data dari jurusan lain.');
        }
    }
}