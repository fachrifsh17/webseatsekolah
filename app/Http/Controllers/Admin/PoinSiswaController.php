<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoinSiswa;
use App\Models\TahunAjaran;
<<<<<<< HEAD
use App\Http\Requests\StorePoinSiswaRequest;
use App\Http\Requests\UpdatePoinSiswaRequest;
use App\Http\Resources\PoinSiswaResource;
=======
use App\Models\Siswa;
use App\Http\Requests\StorePoinSiswaRequest;
use App\Http\Requests\UpdatePoinSiswaRequest;
use App\Http\Resources\PoinSiswaResource;
use App\Exports\PoinSiswaExport;
>>>>>>> master
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
<<<<<<< HEAD
=======
use Maatwebsite\Excel\Facades\Excel;
>>>>>>> master
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PoinSiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
<<<<<<< HEAD
        $this->authorizeResource(PoinSiswa::class, 'poin_siswa');
=======
>>>>>>> master
    }

    public function index(Request $request): JsonResponse
    {
<<<<<<< HEAD
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran']);
        $scope = $request->query('scope');

        $isAdmin = $user->hasAnyRole(['admin', 'Admin', 'ADMIN']);
        $isGuru  = $user->hasAnyRole(['guru', 'Guru']);
        $isSiswa = $user->hasAnyRole(['siswa', 'Siswa']);
        $isOrtu  = $user->hasAnyRole(['orangtua', 'Orangtua', 'Orang Tua']);

        if ($isAdmin && $scope !== 'guru') {
            // Full Access
        } elseif ($isSiswa) {
            $siswaId = $user->siswa?->id;
            if (!$siswaId) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Profil siswa tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }
            $query->where('siswa_id', (string) $siswaId);
        } elseif ($isGuru || ($isAdmin && $scope === 'guru')) {
            $query->where('guru_staf_id', (string) $user->guruStaf?->id);
        } elseif ($isOrtu) {
            $childrenIds = $user->orangtua?->anak()->pluck('id')->toArray() ?? [];
            $query->whereIn('siswa_id', $childrenIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        $poin = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => PoinSiswaResource::collection($poin),
            'meta'    => [
                'current_page' => $poin->currentPage(),
                'last_page'    => $poin->lastPage(),
                'per_page'     => $poin->perPage(),
                'total'        => $poin->total(),
=======
        $this->authorize('viewAny', PoinSiswa::class);

        $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
            ->whereHas('siswa', function ($q) {
                $q->where('is_active', true);
            });

        if ($request->filled('siswa_id')) {
            $query->where('siswa_id', $request->siswa_id);
        }

        if ($request->filled('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
        }

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal', date('m', strtotime($request->bulan)))
                  ->whereYear('tanggal', date('Y', strtotime($request->bulan)));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where(function($sq) use ($search) {
                    $sq->where('nama_lengkap', 'like', "%{$search}%")
                       ->orWhere('nisn', 'like', "%{$search}%");
                });
            });
        }

        $query->orderByDesc('tanggal')->orderByDesc('created_at');

        $summaryGlobal = null;
        if ($request->filled('siswa_id')) {
            $summaryGlobal = DB::table('poin_siswa')
                ->where('siswa_id', $request->siswa_id)
                ->select(
                    DB::raw('SUM(poin_positif) as total_plus'),
                    DB::raw('SUM(poin_negatif) as total_minus'),
                    DB::raw('SUM(poin_positif) - SUM(poin_negatif) as saldo_poin')
                )->first();
        }

        $perPage = $request->get('per_page', 20);
        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'summary_kumulatif' => $summaryGlobal,
            'data'    => PoinSiswaResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => (int) $data->perPage(),
                'total'        => $data->total(),
>>>>>>> master
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
<<<<<<< HEAD
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $scope = $request->query('scope');
        
        $tahunAjaran = TahunAjaran::where('is_active', 1)->first();
        if (!$tahunAjaran) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada tahun ajaran aktif.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validated();

        if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN']) && $scope !== 'guru') {
            $validated['guru_staf_id'] = $request->guru_staf_id ?? (string) $user->guruStaf?->id;
        } else {
            $validated['guru_staf_id'] = (string) $user->guruStaf?->id;
        }
            
        $validated['tahun_ajaran_id'] = (string) $tahunAjaran->id;

        try {
            $poin = DB::transaction(fn() => PoinSiswa::create($validated));

            return response()->json([
                'success' => true,
                'message' => 'Poin siswa berhasil dicatat.',
                'data'    => new PoinSiswaResource($poin->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat poin siswa.'
=======
        $this->authorize('create', PoinSiswa::class);

        $user = Auth::user();
        $tahunAjaran = TahunAjaran::where('is_active', 1)->first();

        if (!$tahunAjaran) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun ajaran aktif tidak ditemukan.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $siswa = Siswa::where('id', $request->siswa_id)
            ->where('is_active', true)
            ->first();

        if (!$siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan atau sudah tidak aktif.'
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $validated['guru_staf_id'] = $request->guru_staf_id ?? $user->guruStaf?->id;
        $validated['tahun_ajaran_id'] = $tahunAjaran->id;

        try {
            $poin = DB::transaction(fn () => PoinSiswa::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Poin berhasil dicatat.',
                'data'    => new PoinSiswaResource($poin->load(['siswa.kelas', 'guruStaf', 'tahunAjaran'])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Admin Store Poin Error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat poin.'
>>>>>>> master
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

<<<<<<< HEAD
    public function show(PoinSiswa $poin_siswa): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new PoinSiswaResource($poin_siswa->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
        ], Response::HTTP_OK);
    }

    public function update(UpdatePoinSiswaRequest $request, PoinSiswa $poin_siswa): JsonResponse
    {
        $validated = $request->validated();
        try {
            DB::transaction(fn() => $poin_siswa->update($validated));
            return response()->json([
                'success' => true,
                'message' => 'Catatan poin diperbarui.',
                'data'    => new PoinSiswaResource($poin_siswa->fresh(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui poin siswa.'
=======
    public function export(Request $request)
    {
        $this->authorize('viewAny', PoinSiswa::class);

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
            ->whereHas('siswa', function ($q) {
                $q->where('is_active', true);
            })
            ->orderBy('tanggal', 'asc');

        if ($request->filled('kelas_id')) {
            $query->whereHas('siswa', function ($q) use ($request) {
                $q->where('kelas_id', $request->kelas_id);
            });
        }

        if ($request->filled('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal', date('m', strtotime($request->bulan)))
                  ->whereYear('tanggal', date('Y', strtotime($request->bulan)));
            $labelWaktu = date('F Y', strtotime($request->bulan));
        } else {
            $labelWaktu = "Seluruh Periode (Kumulatif)";
        }

        $namaKelas = $request->nama_kelas ?? 'Seluruh Siswa';

        return Excel::download(
            new PoinSiswaExport($query, $namaKelas, $labelWaktu, $profil, $kontak),
            "Rekap_Poin_Siswa_" . now()->format('YmdHis') . ".xlsx"
        );
    }

    public function show($id): JsonResponse
    {
        $poin = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->findOrFail($id);
        $this->authorize('view', $poin);

        return response()->json([
            'success' => true,
            'data'    => new PoinSiswaResource($poin),
        ], Response::HTTP_OK);
    }

    public function update(UpdatePoinSiswaRequest $request, $id): JsonResponse
    {
        $poin = PoinSiswa::findOrFail($id);
        $this->authorize('update', $poin);

        try {
            DB::transaction(fn () => $poin->update($request->validated()));
            return response()->json([
                'success' => true,
                'message' => 'Data diperbarui.',
                'data'    => new PoinSiswaResource($poin->fresh(['siswa.kelas', 'guruStaf', 'tahunAjaran'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Admin Update Poin Error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data.'
>>>>>>> master
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

<<<<<<< HEAD
    public function destroy(PoinSiswa $poin_siswa): JsonResponse
    {
        try {
            DB::transaction(fn() => $poin_siswa->delete());
            return response()->json([
                'success' => true, 
                'message' => 'Data poin siswa dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menghapus poin siswa.'
=======
    public function destroy($id): JsonResponse
    {
        $poin = PoinSiswa::findOrFail($id);
        $this->authorize('delete', $poin);

        try {
            DB::transaction(fn () => $poin->delete());
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Admin Delete Poin Error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data.'
>>>>>>> master
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}