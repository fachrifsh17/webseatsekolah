<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use App\Http\Resources\JamSekolahResource;
<<<<<<< HEAD
use App\Http\Requests\UpdateJamSekolahRequest;
=======
use App\Http\Requests\StoreJamSekolahRequest;
use App\Http\Requests\UpdateJamSekolahRequest;
use App\Exports\JamSekolahExport;
use App\Imports\JamSekolahImport;
use Maatwebsite\Excel\Facades\Excel;
>>>>>>> master
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
<<<<<<< HEAD
=======
use Illuminate\Http\Request;
>>>>>>> master
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JamSekolahController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
<<<<<<< HEAD
        $this->middleware('log.admin')->only(['update']);
    }

    public function update(UpdateJamSekolahRequest $request, ?JamSekolah $jamSekolah = null): JsonResponse
    {
        $validated = $request->validated();

=======
        $this->middleware('log.admin')->only(['update', 'store', 'import']);
    }

    public function index(): JsonResponse
    {
        $data = JamSekolah::with('tahunAjaran')->get();
        return response()->json([
            'success' => true,
            'data'    => JamSekolahResource::collection($data)
        ], Response::HTTP_OK);
    }

    public function export()
    {
        return Excel::download(new JamSekolahExport, 'data_jam_sekolah.xlsx');
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
                'message' => 'Data jam sekolah berhasil diimport.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal import: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreJamSekolahRequest $request): JsonResponse
    {
        $validated = $request->validated();
>>>>>>> master
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif) {
            return response()->json([
                'success' => false,
<<<<<<< HEAD
                'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif (is_active = true).'
=======
                'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif.'
>>>>>>> master
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated['tahun_ajaran_id'] = $tahunAktif->id;

<<<<<<< HEAD
=======
        $exists = JamSekolah::where('tahun_ajaran_id', $tahunAktif->id)
            ->where('hari', $validated['hari'])
            ->where('jam_ke', $validated['jam_ke'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "Gagal: Jadwal untuk hari {$validated['hari']} jam ke {$validated['jam_ke']} sudah ada."
            ], Response::HTTP_CONFLICT);
        }

>>>>>>> master
        $fileKey = $request->hasFile('file_path') ? 'file_path' : ($request->hasFile('file') ? 'file' : null);
        if ($fileKey) {
            $newPath = $request->file($fileKey)->store('jam_sekolah', 'public');
            if ($newPath) {
                $validated['file_path'] = $newPath;
            }
        }

        DB::beginTransaction();
        try {
<<<<<<< HEAD
            if ($jamSekolah === null) {
                $jamSekolah = JamSekolah::create($validated);
                $jamSekolah->refresh()->load('tahunAjaran');
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Jadwal jam sekolah berhasil dibuat otomatis untuk tahun aktif.',
                    'data'    => new JamSekolahResource($jamSekolah)
                ], Response::HTTP_CREATED);
            }

=======
            $jamSekolah = JamSekolah::create($validated);
            $jamSekolah->refresh()->load('tahunAjaran');
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal jam sekolah berhasil dibuat.',
                'data'    => new JamSekolahResource($jamSekolah)
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
                'message' => "Gagal: Jadwal untuk hari {$validated['hari']} jam ke {$validated['jam_ke']} sudah ada."
            ], Response::HTTP_CONFLICT);
        }

        $fileKey = $request->hasFile('file_path') ? 'file_path' : ($request->hasFile('file') ? 'file' : null);
        if ($fileKey) {
            $newPath = $request->file($fileKey)->store('jam_sekolah', 'public');
            if ($newPath) {
                $validated['file_path'] = $newPath;
            }
        }

        DB::beginTransaction();
        try {
>>>>>>> master
            $oldPath = $jamSekolah->file_path;
            $jamSekolah->update($validated);
            $jamSekolah->refresh()->load('tahunAjaran');
            DB::commit();

            if (!empty($validated['file_path']) && $oldPath && $validated['file_path'] !== $oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Jadwal jam sekolah berhasil diperbarui.',
                'data'    => new JamSekolahResource($jamSekolah)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
<<<<<<< HEAD

            if (!empty($validated['file_path']) && ($jamSekolah?->file_path ?? null) !== $validated['file_path']) {
                Storage::disk('public')->delete($validated['file_path']);
            }

            Log::error('JamSekolah upsert error', [
                'jam_sekolah_id' => $jamSekolah ? (string) $jamSekolah->id : null,
                'error'          => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses jadwal jam sekolah.',
=======
            if (!empty($validated['file_path']) && $jamSekolah->file_path !== $validated['file_path']) {
                Storage::disk('public')->delete($validated['file_path']);
            }
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
>>>>>>> master
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}