<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\User;
use App\Http\Requests\UpdateSiswaRequest;
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class SiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['update']);
    }

    private function applyFilters(Request $request, $query)
    {
        $this->applyAdditionalFilters($request, $query);
        return $query;
    }

    private function applyAdditionalFilters(Request $request, $query)
    {
        if ($request->filled('jurusan_id')) {
            $query->whereHas('kelas', fn($q) => $q->where('jurusan_id', $request->jurusan_id));
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhereHas('kelas', function ($qK) use ($search) {
                      $qK->where('nama_kelas', 'like', "%{$search}%");
                  });
            });
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = Siswa::with(['user', 'kelas.jurusan', 'orangtua']);
        $query = $this->applyFilters($request, $query);

        $perPage = $request->filled('search') ? 10 : 20;
        $data = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => SiswaResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $query = Siswa::query()->with(['kelas.jurusan', 'orangtua']);
        $query = $this->applyFilters($request, $query);

        $kelasData = null;
        if ($request->filled('kelas_id')) {
            $kelasData = Kelas::find($request->kelas_id);
        }

        $filename = 'data_siswa';
        if ($kelasData) {
            $filename .= '_' . Str::slug($kelasData->nama_kelas);
        }
        $filename .= '_' . now()->format('Ymd_His') . '.xlsx';

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        return Excel::download(new SiswaExport($query, $profil, $kontak, $kelasData), $filename);
    }

    public function show(Siswa $siswa): JsonResponse
    {
        $siswa->load(['user', 'kelas.jurusan', 'orangtua']);
        return response()->json([
            'success' => true,
            'data'    => new SiswaResource($siswa)
        ], Response::HTTP_OK);
    }

    public function update(UpdateSiswaRequest $request, Siswa $siswa): JsonResponse
    {
        $validated = $request->validated();
        $data = Arr::only($validated, (new Siswa())->getFillable());
        $oldFoto = $siswa->foto;

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        try {
            DB::transaction(fn() => $siswa->update($data));

            if ($request->hasFile('foto') && $oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diperbarui',
                'data'    => new SiswaResource($siswa->fresh(['user', 'kelas.jurusan', 'orangtua']))
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            if (isset($data['foto'])) Storage::disk('public')->delete($data['foto']);
            Log::error("Update Siswa ID {$siswa->id} Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui siswa'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}