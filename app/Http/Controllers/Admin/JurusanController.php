<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Http\Resources\JurusanResource;
use App\Http\Requests\StoreJurusanRequest;
use App\Http\Requests\UpdateJurusanRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Throwable;

class JurusanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = Jurusan::paginate(12);
        return response()->json(JurusanResource::collection($data));
    }
    
    public function show(Jurusan $jurusan): JsonResponse
    {
        return response()->json(new JurusanResource($jurusan));
    }

    public function store(StoreJurusanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/jurusan', 'public');
        }

        DB::beginTransaction();
        try {
            $jurusan = Jurusan::create($validated);
            DB::commit();
            return response()->json(new JurusanResource($jurusan), 201);
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jurusan'
            ], 500);
        }
    }

    public function update(UpdateJurusanRequest $request, Jurusan $jurusan): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $newPath = $request->file('foto')->store('uploads/jurusan', 'public');
            if ($newPath) {
                if ($jurusan->foto) {
                    Storage::disk('public')->delete($jurusan->foto);
                }
                $validated['foto'] = $newPath;
            }
        }

        DB::beginTransaction();
        try {
            $jurusan->update($validated);
            DB::commit();
            return response()->json(new JurusanResource($jurusan));
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['foto'] ?? null) && ($validated['foto'] !== $jurusan->foto)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jurusan'
            ], 500);
        }
    }

    public function destroy(Jurusan $jurusan): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($jurusan->foto) {
                Storage::disk('public')->delete($jurusan->foto);
            }

            $jurusan->delete();
            DB::commit();

            return response()->json(null, 204);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jurusan'
            ], 500);
        }
    }
}