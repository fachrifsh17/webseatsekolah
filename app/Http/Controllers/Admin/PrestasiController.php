<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use App\Http\Resources\PrestasiResource;
use App\Http\Requests\StorePrestasiRequest;
use App\Http\Requests\UpdatePrestasiRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Throwable;

class PrestasiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $items = Prestasi::orderBy('tahun', 'desc')->paginate(12);
        return response()->json(PrestasiResource::collection($items));
    }
    
    public function show(Prestasi $prestasi): JsonResponse
    {
        return response()->json(new PrestasiResource($prestasi));
    }

    public function store(StorePrestasiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/prestasi', 'public');
        }

        DB::beginTransaction();
        try {
            $prestasi = Prestasi::create($validated);
            DB::commit();
            return response()->json(new PrestasiResource($prestasi), 201);
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat prestasi'
            ], 500);
        }
    }

    public function update(UpdatePrestasiRequest $request, Prestasi $prestasi): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $newPath = $request->file('foto')->store('uploads/prestasi', 'public');
            if ($newPath) {
                if ($prestasi->foto) {
                    Storage::disk('public')->delete($prestasi->foto);
                }
                $validated['foto'] = $newPath;
            }
        }

        DB::beginTransaction();
        try {
            $prestasi->update($validated);
            DB::commit();
            return response()->json(new PrestasiResource($prestasi));
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['foto'] ?? null) && ($validated['foto'] !== $prestasi->foto)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui prestasi'
            ], 500);
        }
    }

    public function destroy(Prestasi $prestasi): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($prestasi->foto) {
                Storage::disk('public')->delete($prestasi->foto);
            }

            $prestasi->delete();
            DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Prestasi berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus prestasi'
            ], 500);
        }
    }
}
