<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use App\Http\Resources\KurikulumResource;
use App\Http\Requests\StoreKurikulumRequest;
use App\Http\Requests\UpdateKurikulumRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;

class KurikulumController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = Kurikulum::paginate(12);
        return response()->json(KurikulumResource::collection($data));
    }
    
    public function show(Kurikulum $kurikulum): JsonResponse
    {
        return response()->json(new KurikulumResource($kurikulum));
    }

    public function store(StoreKurikulumRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file_jadwal')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal')->store('uploads/kurikulum', 'public');
        }

        DB::beginTransaction();
        try {
            $kurikulum = Kurikulum::create($validated);
            DB::commit();
            return response()->json(new KurikulumResource($kurikulum), 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Kurikulum store error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat kurikulum'
            ], 500);
        }
    }

    public function update(UpdateKurikulumRequest $request, Kurikulum $kurikulum): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file_jadwal')) {
            $newPath = $request->file('file_jadwal')->store('uploads/kurikulum', 'public');
            if ($newPath) {
                if ($kurikulum->file_jadwal_path) {
                    Storage::disk('public')->delete($kurikulum->file_jadwal_path);
                }
                $validated['file_jadwal_path'] = $newPath;
            }
        }

        DB::beginTransaction();
        try {
            $kurikulum->update($validated);
            DB::commit();
            return response()->json(new KurikulumResource($kurikulum));
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Kurikulum update error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if (!empty($validated['file_jadwal_path'] ?? null) && ($validated['file_jadwal_path'] !== $kurikulum->file_jadwal_path)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui kurikulum'
            ], 500);
        }
    }

    public function destroy(Kurikulum $kurikulum): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($kurikulum->file_jadwal_path) {
                Storage::disk('public')->delete($kurikulum->file_jadwal_path);
            }

            $kurikulum->delete();
            DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Data kurikulum berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Kurikulum destroy error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kurikulum'
            ], 500);
        }
    }
}
