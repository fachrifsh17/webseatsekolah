<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruMapel;
use App\Models\GuruStaf;
use App\Models\MataPelajaran;
use App\Http\Resources\GuruResource;
use App\Http\Resources\MapelResource;
use App\Http\Resources\GuruMapelResource;
use App\Http\Requests\StoreGuruMapelRequest;
use App\Http\Requests\UpdateGuruMapelRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class GuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $assignments = GuruMapel::with(['guru', 'mapel'])->get();
        return response()->json(GuruMapelResource::collection($assignments));
    }

    public function show(?GuruMapel $guruMapel): JsonResponse
    {
        if (!$guruMapel) {
            return response()->json([
                'success' => false,
                'message' => 'Data penugasan tidak ditemukan',
                'errors'  => ['id' => ['Penugasan dengan ID tersebut tidak ada']]
            ], 404);
        }

        return response()->json(new GuruMapelResource($guruMapel->load(['guru', 'mapel'])), 200);
    }

    public function getLists(): JsonResponse
    {
        return response()->json([
            'guru_list'  => GuruResource::collection(GuruStaf::all()),
            'mapel_list' => MapelResource::collection(MataPelajaran::all()),
        ]);
    }

    public function store(StoreGuruMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $exists = GuruMapel::where('guru_staf_id', $validated['guru_staf_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Guru sudah terdaftar pada mata pelajaran ini.',
                'errors'  => [
                    'guru_staf_id'      => ['Guru sudah terdaftar pada mata pelajaran ini.'],
                    'mata_pelajaran_id' => ['Guru sudah terdaftar pada mata pelajaran ini.']
                ]
            ], 409);
        }

        try {
            $assignment = DB::transaction(fn() => GuruMapel::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru ke mata pelajaran berhasil ditambahkan.',
                'data'    => new GuruMapelResource($assignment->load(['guru','mapel']))
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan penugasan guru ke mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function update(UpdateGuruMapelRequest $request, ?GuruMapel $guruMapel): JsonResponse
    {
        if (!$guruMapel) {
            return response()->json([
                'success' => false,
                'message' => 'Data penugasan tidak ditemukan',
                'errors'  => ['id' => ['Penugasan dengan ID tersebut tidak ada']]
            ], 404);
        }

        $validated = $request->validated();

        $exists = GuruMapel::where('guru_staf_id', $validated['guru_staf_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->where('id','<>',$guruMapel->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Guru sudah terdaftar pada mata pelajaran ini.',
                'errors'  => [
                    'guru_staf_id'      => ['Guru sudah terdaftar pada mata pelajaran ini.'],
                    'mata_pelajaran_id' => ['Guru sudah terdaftar pada mata pelajaran ini.']
                ]
            ], 409);
        }

        try {
            DB::transaction(fn() => $guruMapel->update($validated));
            $guruMapel->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru ke mata pelajaran berhasil diperbarui.',
                'data'    => new GuruMapelResource($guruMapel->load(['guru','mapel']))
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui penugasan guru ke mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function destroy(?GuruMapel $guruMapel): JsonResponse
    {
        if (!$guruMapel) {
            return response()->json([
                'success' => false,
                'message' => 'Data penugasan tidak ditemukan',
                'errors'  => ['id' => ['Penugasan dengan ID tersebut tidak ada']]
            ], 404);
        }

        try {
            DB::transaction(fn() => $guruMapel->delete());

            return response()->json([
                'success'      => true,
                'message'      => 'Penugasan guru ke mata pelajaran berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus penugasan guru ke mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }
}
