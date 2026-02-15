<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{Kelas, Siswa, Jurusan, TahunAjaran, ProfilSekolah, DataKontak};
use App\Http\Requests\{StoreKelasRequest, UpdateKelasRequest};
use App\Http\Resources\KelasResource;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Log, Auth};
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\KelasExport;
use Illuminate\Support\Str;

class KelasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only([
            'store', 
            'update', 
            'destroy', 
            'generateFromPreviousYear'
        ]);
        
        $this->authorizeResource(Kelas::class, 'kelas');
    }

    /**
     * Helper untuk mengambil Jurusan Ketua & Tahun Ajaran Aktif
     */
    private function getContext()
    {
        $user = Auth::user();
        $jurusanId = $user->guruStaf?->jurusan_id;
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        return [
            'jurusan_id' => $jurusanId,
            'tahun_aktif' => $tahunAktif,
            'nama_jurusan' => $user->guruStaf?->jurusan?->nama_jurusan
        ];
    }

    public function index(Request $request): JsonResponse
    {
        ['jurusan_id' => $jurusanId, 'tahun_aktif' => $tahunAktif] = $this->getContext();

        if (!$jurusanId || !$tahunAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Profil jurusan atau tahun ajaran aktif tidak ditemukan.'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $query = Kelas::with(['jurusan', 'tahunAjaran', 'waliKelas'])
                ->withCount('siswa')
                ->where('jurusan_id', $jurusanId) // Kunci Jurusan
                ->where('tahun_ajaran_id', $tahunAktif->id); // Kunci Tahun Aktif

            if ($request->filled('search')) {
                $query->where('nama_kelas', 'like', '%' . $request->search . '%');
            }

            $perPage = $request->get('per_page', 10);
            $kelas = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => "Daftar kelas aktif jurusan berhasil dimuat.",
                'data'    => KelasResource::collection($kelas),
                'meta'    => [
                    'current_page' => $kelas->currentPage(),
                    'last_page'    => $kelas->lastPage(),
                    'per_page'     => $kelas->perPage(),
                    'total'        => $kelas->total(),
                    'tahun_ajaran' => $tahunAktif->nama . " - " . $tahunAktif->semester
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Index Kelas Kajur Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar kelas.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Kelas::class);
        ['jurusan_id' => $jurusanId, 'tahun_aktif' => $tahunAktif, 'nama_jurusan' => $namaJurusan] = $this->getContext();

        if (!$jurusanId || !$tahunAktif) {
            abort(Response::HTTP_FORBIDDEN, 'Data tidak lengkap untuk ekspor.');
        }

        try {
            // Kunci filter hanya untuk jurusan si Ketua dan Tahun Aktif
            $filters = [
                'jurusan_id' => $jurusanId,
                'tahun_ajaran_id' => $tahunAktif->id,
                'is_active' => true,
                'search' => $request->search
            ];

            $profil = ProfilSekolah::first() ?? new ProfilSekolah(); 
            $kontak = DataKontak::first() ?? new DataKontak(); 

            $fileName = 'Data_Kelas_' . Str::slug($namaJurusan) . '_' . date('Ymd_His') . '.xlsx';

            return Excel::download(new KelasExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Kelas Kajur Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreKelasRequest $request): JsonResponse
    {
        ['jurusan_id' => $jurusanId, 'tahun_aktif' => $tahunAktif] = $this->getContext();

        if (!$tahunAktif) {
            return response()->json(['success' => false, 'message' => 'Tahun Ajaran aktif tidak ditemukan.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validated();
        $validated['jurusan_id'] = $jurusanId; // Override ke jurusan user
        $validated['tahun_ajaran_id'] = $tahunAktif->id; // Override ke tahun aktif
        $validated['is_active'] = true;

        // Cek duplikasi di lingkup jurusan dan tahun aktif
        if (Kelas::where('nama_kelas', $validated['nama_kelas'])->where('tahun_ajaran_id', $tahunAktif->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Nama kelas sudah terdaftar di periode ini.'], Response::HTTP_CONFLICT);
        }

        try {
            $kelas = DB::transaction(fn() => Kelas::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil ditambahkan.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])->loadCount('siswa')),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Kelas $kelas): JsonResponse
    {
        ['jurusan_id' => $jurusanId] = $this->getContext();

        // Kunci: Tidak bisa intip kelas jurusan lain
        if ($kelas->jurusan_id !== $jurusanId) {
            return response()->json(['success' => false, 'message' => 'Akses dilarang.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])->loadCount('siswa')),
        ], Response::HTTP_OK);
    }

    public function update(UpdateKelasRequest $request, Kelas $kelas): JsonResponse
    {
        ['jurusan_id' => $jurusanId] = $this->getContext();

        if ($kelas->jurusan_id !== $jurusanId || !$kelas->tahunAjaran->is_active) {
            return response()->json(['success' => false, 'message' => 'Hanya bisa mengubah kelas aktif milik jurusan sendiri.'], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::transaction(fn() => $kelas->update($request->validated()));
            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil diperbarui.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])->loadCount('siswa')),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        ['jurusan_id' => $jurusanId] = $this->getContext();

        if ($kelas->jurusan_id !== $jurusanId || !$kelas->tahunAjaran->is_active) {
            return response()->json(['success' => false, 'message' => 'Akses dilarang.'], Response::HTTP_FORBIDDEN);
        }

        if ($kelas->siswa()->exists()) {
            return response()->json(['success' => false, 'message' => 'Kelas tidak bisa dihapus karena masih berisi siswa.'], Response::HTTP_CONFLICT);
        }

        try {
            DB::transaction(fn() => $kelas->delete());
            return response()->json(['success' => true, 'message' => 'Data kelas berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}