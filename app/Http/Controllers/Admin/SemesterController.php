<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use App\Http\Resources\SemesterResource;
use App\Http\Requests\StoreSemesterRequest;
use App\Http\Requests\UpdateSemesterRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SemesterController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        // Parameter route harus bernama 'semester'
        $this->authorizeResource(Semester::class, 'semester');
    }

    public function index()
    {
        $semesters = Semester::with('tahunAjaran')->orderBy('created_at', 'desc')->get();
        return SemesterResource::collection($semesters);
    }

    public function store(StoreSemesterRequest $request)
    {
        $this->authorize('create', Semester::class);

        $activeTahunAjaran = TahunAjaran::where('is_active', true)->first();

        if (!$activeTahunAjaran) {
            throw ValidationException::withMessages([
                'tahun_ajaran_id' => ['Tidak ada tahun ajaran yang aktif.'],
            ]);
        }

        $validated = $request->validated();
        $validated['tahun_ajaran_id'] = $activeTahunAjaran->id;
        $validated['is_active'] = true; 

        return DB::transaction(function () use ($validated, $activeTahunAjaran) {
            
            $existingSemester = Semester::where('tahun_ajaran_id', $activeTahunAjaran->id)
                ->where('nama', $validated['nama'])
                ->first();

            if ($existingSemester) {
                throw ValidationException::withMessages([
                    'nama' => ['Nama semester sudah ada dalam tahun ajaran ini.'],
                ]);
            }

            $currentSemesterCount = Semester::where('tahun_ajaran_id', $activeTahunAjaran->id)->count();

            if ($currentSemesterCount >= 2) {
                throw ValidationException::withMessages([
                    'nama' => ['Tahun ajaran ini sudah memiliki 2 semester.'],
                ]);
            }

            Semester::query()->update(['is_active' => false]);

            $semester = Semester::create($validated);
            
            return new SemesterResource($semester->load('tahunAjaran'));
        });
    }

    // Perubahan: Menggunakan Semester $semester agar authorizeResource bekerja
    public function show(Semester $semester)
    {
        return new SemesterResource($semester->load('tahunAjaran'));
    }

    // Perubahan: Menggunakan Semester $semester agar authorizeResource bekerja
    public function update(UpdateSemesterRequest $request, Semester $semester)
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $semester) {
            
            $existingSemester = Semester::where('tahun_ajaran_id', $semester->tahun_ajaran_id)
                ->where('nama', $validated['nama'])
                ->where('id', '!=', $semester->id)
                ->first();

            if ($existingSemester) {
                throw ValidationException::withMessages([
                    'nama' => ['Nama semester sudah ada dalam tahun ajaran ini.'],
                ]);
            }

            if (isset($validated['is_active']) && $validated['is_active']) {
                Semester::where('id', '!=', $semester->id)->update(['is_active' => false]);
            }

            $semester->update($validated);
            
            return new SemesterResource($semester->load('tahunAjaran'));
        });
    }

    // Perubahan: Menggunakan Semester $semester agar authorizeResource bekerja
    public function destroy(Semester $semester)
    {
        if (
            $semester->jamSekolah()->exists() ||
            $semester->presensi()->exists()
        ) {
            return response()->json([
                'message' => 'Semester tidak dapat dihapus karena masih digunakan di data Jam Sekolah atau Presensi.'
            ], Response::HTTP_CONFLICT);
        }

        $semester->delete();

        return response()->json([
            'message' => 'Semester berhasil dihapus'
        ], Response::HTTP_OK);
    }
}