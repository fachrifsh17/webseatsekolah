<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use App\Models\ProfilSekolah;
use App\Models\DataKontak;
use App\Http\Resources\JamSekolahResource;
use App\Http\Requests\StoreJamSekolahRequest;
use App\Http\Requests\UpdateJamSekolahRequest;
use App\Exports\JamSekolahExport;
use App\Imports\JamSekolahImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JamSekolahController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Kurikulum');
        $this->middleware('log.admin')->only(['update', 'store', 'import', 'destroy']);
    }

    private function resolveTahunAjaranId(Request $request)
    {
        if ($request->has('tahun_ajaran_id') && !empty($request->tahun_ajaran_id)) {
            return $request->tahun_ajaran_id;
        }

        $aktif = TahunAjaran::where('is_active', true)->first();
        return $aktif ? $aktif->id : null;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $tahunAjaranId = $this->resolveTahunAjaranId($request);
            $query = JamSekolah::with('tahunAjaran');

            if ($tahunAjaranId) {
                $query->where('tahun_ajaran_id', $tahunAjaranId);
            }

            $data = $query->orderBy('hari')
                          ->orderBy('waktu_mulai')
                          ->get();

            return response()->json([
                'success' => true,
                'data'    => JamSekolahResource::collection($data),
                'meta'    => [
                    'filter_tahun_ajaran_id' => $tahunAjaranId,
                    'is_auto_selected' => !$request->has('tahun_ajaran_id')
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
            $tahunAjaranId = $this->resolveTahunAjaranId($request);
            
            if (!$tahunAjaranId) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tahun ajaran tidak ditentukan.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $ta = TahunAjaran::find($tahunAjaranId);
            $namaTA = $ta ? str_replace(['/', '\\', ' '], '-', $ta->nama) : date('Ymd_His');

            $profil = ProfilSekolah::first();
            $kontak = DataKontak::first();
            $fileName = 'jam_sekolah_' . $namaTA . '.xlsx';

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
        
        if (!isset($validated['tahun_ajaran_id'])) {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            if (!$tahunAktif) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tidak ada tahun ajaran aktif.'
                ], Response::HTTP_BAD_REQUEST);
            }
            $validated['tahun_ajaran_id'] = $tahunAktif->id;
        }

        $exists = JamSekolah::where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
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

        try {
            $jamSekolah = DB::transaction(fn() => JamSekolah::create($validated));
            return response()->json([
                'success' => true, 
                'data' => new JamSekolahResource($jamSekolah->load('tahunAjaran'))
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
            'data'    => new JamSekolahResource($jamSekolah->load('tahunAjaran'))
        ], Response::HTTP_OK);
    }

    public function update(UpdateJamSekolahRequest $request, JamSekolah $jamSekolah): JsonResponse
    {
        $validated = $request->validated();
        $tahunId = $validated['tahun_ajaran_id'] ?? $jamSekolah->tahun_ajaran_id;

        $exists = JamSekolah::where('tahun_ajaran_id', $tahunId)
            ->where('hari', $validated['hari'] ?? $jamSekolah->hari)
            ->where('jam_ke', $validated['jam_ke'] ?? $jamSekolah->jam_ke)
            ->whereNotNull('jam_ke')
            ->where('id', '!=', $jamSekolah->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false, 
                'message' => 'Konflik jadwal terdeteksi.'
            ], Response::HTTP_CONFLICT);
        }

        try {
            DB::transaction(fn() => $jamSekolah->update($validated));
            return response()->json([
                'success' => true, 
                'data' => new JamSekolahResource($jamSekolah->load('tahunAjaran'))
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