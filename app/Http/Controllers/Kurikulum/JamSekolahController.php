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
use Illuminate\Support\Facades\Storage;
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
        // Middleware log tetap dipasang untuk mencatat aktivitas perubahan data
        $this->middleware('log.admin')->only(['store', 'update', 'import', 'destroy']);
    }

    public function index(): JsonResponse
    {
        try {
            $data = JamSekolah::with('tahunAjaran')->get();
            return response()->json([
                'success' => true,
                'data'    => JamSekolahResource::collection($data)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jam sekolah.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export()
    {
        try {
            $profil = ProfilSekolah::first();
            $kontak = DataKontak::first();
            $fileName = 'data_jam_sekolah_' . date('Ymd_His') . '.xlsx';

            // Mengirim profil dan kontak ke Class Export untuk Kop Surat
            return Excel::download(new JamSekolahExport($profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Jam Sekolah Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data jam sekolah.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        DB::beginTransaction();
        try {
            Excel::import(new JamSekolahImport, $request->file('file'));
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data jam sekolah berhasil diimpor.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('JamSekolah import error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreJamSekolahRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated['tahun_ajaran_id'] = $tahunAktif->id;

        $exists = JamSekolah::where('tahun_ajaran_id', $tahunAktif->id)
            ->where('hari', $validated['hari'])
            ->where('jam_ke', $validated['jam_ke'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "Gagal: Jadwal hari {$validated['hari']} jam ke {$validated['jam_ke']} sudah ada."
            ], Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('file_path')) {
            $validated['file_path'] = $request->file('file_path')->store('jam_sekolah', 'public');
        } elseif ($request->hasFile('file')) {
            $validated['file_path'] = $request->file('file')->store('jam_sekolah', 'public');
        }

        DB::beginTransaction();
        try {
            $jamSekolah = JamSekolah::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal jam sekolah berhasil dibuat.',
                'data'    => new JamSekolahResource($jamSekolah->load('tahunAjaran'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['file_path'])) {
                Storage::disk('public')->delete($validated['file_path']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jadwal jam sekolah.',
                'errors'  => ['exception' => [$e->getMessage()]]
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
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated['tahun_ajaran_id'] = $tahunAktif->id;

        $exists = JamSekolah::where('tahun_ajaran_id', $tahunAktif->id)
            ->where('hari', $validated['hari'])
            ->where('jam_ke', $validated['jam_ke'])
            ->where('id', '!=', $jamSekolah->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "Gagal: Jadwal hari {$validated['hari']} jam ke {$validated['jam_ke']} sudah ada."
            ], Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('file_path')) {
            $validated['file_path'] = $request->file('file_path')->store('jam_sekolah', 'public');
        } elseif ($request->hasFile('file')) {
            $validated['file_path'] = $request->file('file')->store('jam_sekolah', 'public');
        }

        DB::beginTransaction();
        try {
            $oldPath = $jamSekolah->file_path;
            $jamSekolah->update($validated);
            DB::commit();

            if (!empty($validated['file_path']) && $oldPath && $validated['file_path'] !== $oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Jadwal jam sekolah berhasil diperbarui.',
                'data'    => new JamSekolahResource($jamSekolah->load('tahunAjaran'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['file_path'])) {
                Storage::disk('public')->delete($validated['file_path']);
            }
            Log::error('JamSekolah update error', ['id' => $jamSekolah->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jadwal jam sekolah.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(JamSekolah $jamSekolah): JsonResponse
    {
        DB::beginTransaction();
        try {
            $oldPath = $jamSekolah->file_path;
            $jamSekolah->delete();
            DB::commit();

            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Jadwal jam sekolah berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jadwal jam sekolah.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}