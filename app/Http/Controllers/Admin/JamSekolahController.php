<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{JamSekolah, Semester, ProfilSekolah, DataKontak};
use App\Http\Resources\JamSekolahResource;
use App\Http\Requests\{StoreJamSekolahRequest, UpdateJamSekolahRequest};
use App\Exports\JamSekolahExport;
use App\Imports\JamSekolahImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JamSekolahController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['update', 'store', 'import', 'destroy']);

        $this->authorizeResource(JamSekolah::class, 'jam_sekolah');
    }

    private function resolveSemesterId(Request $request)
    {
        if ($request->has('semester_id') && !empty($request->semester_id)) {
            return $request->semester_id;
        }

        $aktif = Semester::where('is_active', true)->first();
        return $aktif ? $aktif->id : null;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $semesterId = $this->resolveSemesterId($request);
            $query = JamSekolah::with('semester.tahunAjaran');

            if ($semesterId) {
                $query->where('semester_id', $semesterId);
            }

            $data = $query->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')")
                          ->orderBy('waktu_mulai')
                          ->get();

            return response()->json([
                'success' => true,
                'data'    => JamSekolahResource::collection($data),
                'meta'    => [
                    'filter_semester_id' => $semesterId,
                    'is_auto_selected'   => !$request->has('semester_id')
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jam sekolah.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $this->authorize('viewAny', JamSekolah::class);

            $semesterId = $this->resolveSemesterId($request);
            
            if (!$semesterId) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Semester tidak ditentukan.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $sm = Semester::with('tahunAjaran')->find($semesterId);
            
            if ($sm) {
                $namaTA = str_replace(['/', '\\', ' '], '-', $sm->tahunAjaran->nama);
                $namaSem = str_replace(['/', '\\', ' '], '-', $sm->nama);
                // --- PERUBAHAN: Penambahan nama semester dan kapitalisasi ---
                $labelFile = strtoupper($namaTA . '-' . $namaSem);
                $tahunAjaranId = $sm->tahun_ajaran_id;
            } else {
                $labelFile = date('Ymd_His');
                $tahunAjaranId = null;
            }

            $profil = ProfilSekolah::first();
            $kontak = DataKontak::first();
            
            $fileName = 'JAM_SEKOLAH_' . $labelFile . '.xlsx';

            return Excel::download(new JamSekolahExport($profil, $kontak, $tahunAjaranId), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal ekspor.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', JamSekolah::class);

        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            $import = new JamSekolahImport();
            DB::transaction(fn() => Excel::import($import, $request->file('file')));

            return response()->json([
                'success'   => true,
                'message'   => 'Impor selesai.',
                'conflicts' => $import->getMessages()
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal impor: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreJamSekolahRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        if (!isset($validated['semester_id'])) {
            $semesterAktif = Semester::where('is_active', true)->first();
            if (!$semesterAktif) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tidak ada semester aktif.'
                ], Response::HTTP_BAD_REQUEST);
            }
            $validated['semester_id'] = $semesterAktif->id;
        }

        if ($validated['waktu_selesai'] <= $validated['waktu_mulai']) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu selesai harus lebih besar dari waktu mulai.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $exists = JamSekolah::where('semester_id', $validated['semester_id'])
            ->where('hari', $validated['hari'])
            ->where('jam_ke', $validated['jam_ke'])
            ->whereNotNull('jam_ke')
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false, 
                'message' => 'Jadwal jam tersebut sudah ada.'
            ], Response::HTTP_CONFLICT);
        }

        $overlap = JamSekolah::where('semester_id', $validated['semester_id'])
            ->where('hari', $validated['hari'])
            ->where(function ($query) use ($validated) {
                $query->where('waktu_mulai', '<', $validated['waktu_selesai'])
                      ->where('waktu_selesai', '>', $validated['waktu_mulai']);
            })->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu yang diinput bertabrakan dengan jam lain di hari yang sama.'
            ], Response::HTTP_CONFLICT);
        }

        try {
            $jamSekolah = DB::transaction(fn() => JamSekolah::create($validated));
            return response()->json([
                'success' => true, 
                'data' => new JamSekolahResource($jamSekolah->load('semester.tahunAjaran'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal simpan.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(JamSekolah $jamSekolah): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new JamSekolahResource($jamSekolah->load('semester.tahunAjaran'))
        ], Response::HTTP_OK);
    }

    public function update(UpdateJamSekolahRequest $request, JamSekolah $jamSekolah): JsonResponse
    {
        $validated = $request->validated();
        $semesterId = $validated['semester_id'] ?? $jamSekolah->semester_id;
        $hari = $validated['hari'] ?? $jamSekolah->hari;
        $mulai = $validated['waktu_mulai'] ?? $jamSekolah->waktu_mulai;
        $selesai = $validated['waktu_selesai'] ?? $jamSekolah->waktu_selesai;
        $jamKe = $validated['jam_ke'] ?? $jamSekolah->jam_ke;

        if ($selesai <= $mulai) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu selesai harus lebih besar dari waktu mulai.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $exists = JamSekolah::where('semester_id', $semesterId)
            ->where('hari', $hari)
            ->where('jam_ke', $jamKe)
            ->whereNotNull('jam_ke')
            ->where('id', '!=', $jamSekolah->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false, 
                'message' => 'Konflik nomor jam terdeteksi.'
            ], Response::HTTP_CONFLICT);
        }

        $overlap = JamSekolah::where('semester_id', $semesterId)
            ->where('hari', $hari)
            ->where('id', '!=', $jamSekolah->id)
            ->where(function ($query) use ($mulai, $selesai) {
                $query->where('waktu_mulai', '<', $selesai)
                      ->where('waktu_selesai', '>', $mulai);
            })->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu bertabrakan dengan jadwal lain di hari yang sama.'
            ], Response::HTTP_CONFLICT);
        }

        try {
            DB::transaction(fn() => $jamSekolah->update($validated));
            return response()->json([
                'success' => true, 
                'data' => new JamSekolahResource($jamSekolah->load('semester.tahunAjaran'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal update.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(JamSekolah $jamSekolah): JsonResponse
    {
        try {
            DB::transaction(fn() => $jamSekolah->delete());
            return response()->json([
                'success' => true, 
                'message' => 'Berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal hapus.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}