<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use App\Http\Resources\StrukturJabatanResource;
use App\Http\Requests\StoreStrukturJabatanRequest;
use App\Http\Requests\UpdateStrukturJabatanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class StrukturJabatanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = StrukturJabatan::with('guru')->orderBy('urutan_tampil')->get();
        return response()->json(StrukturJabatanResource::collection($data));
    }
    
    public function show(StrukturJabatan $strukturJabatan): JsonResponse
    {
        return response()->json(new StrukturJabatanResource($strukturJabatan->load('guru')));
    }

    public function store(StoreStrukturJabatanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Prevent a guru from having more than one struktur jabatan (business rule)
        $exists = StrukturJabatan::where('guru_staf_id', $validated['guru_staf_id'])->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Guru/Staf sudah memiliki struktur jabatan.',
                'errors'  => ['guru_staf_id' => ['Guru/Staf sudah memiliki struktur jabatan.']]
            ], 409);
        }

        try {
            $item = DB::transaction(fn () => StrukturJabatan::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Data struktur jabatan berhasil ditambahkan.',
                'data'    => new StrukturJabatanResource($item->load('guru'))
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan struktur jabatan',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function update(UpdateStrukturJabatanRequest $request, StrukturJabatan $strukturJabatan): JsonResponse
    {
        $validated = $request->validated();

        // Prevent assigning a guru that already has another struktur jabatan
        if (!empty($validated['guru_staf_id'])) {
            $exists = StrukturJabatan::where('guru_staf_id', $validated['guru_staf_id'])
                ->where('id', '<>', $strukturJabatan->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guru/Staf sudah memiliki struktur jabatan.',
                    'errors'  => ['guru_staf_id' => ['Guru/Staf sudah memiliki struktur jabatan.']]
                ], 409);
            }
        }

        try {
            DB::transaction(fn () => $strukturJabatan->update($validated));
            $strukturJabatan->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Data struktur jabatan berhasil diperbarui.',
                'data'    => new StrukturJabatanResource($strukturJabatan->load('guru'))
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui struktur jabatan',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function destroy(StrukturJabatan $strukturJabatan): JsonResponse
    {
        try {
            DB::transaction(fn () => $strukturJabatan->delete());

            return response()->json([
                'success'      => true,
                'message'      => 'Data struktur jabatan berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus struktur jabatan',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }
}
