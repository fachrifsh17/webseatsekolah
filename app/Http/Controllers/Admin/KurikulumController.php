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
use Symfony\Component\HttpFoundation\Response;

class KurikulumController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        
        // Proteksi Policy
        $this->authorizeResource(Kurikulum::class, 'kurikulum');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $data = Kurikulum::orderBy('is_active', 'desc')
                             ->orderBy('created_at', 'desc')
                             ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => KurikulumResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kurikulum Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil daftar kurikulum'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreKurikulumRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (Kurikulum::where('judul', $validated['judul'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data. Judul "' . $validated['judul'] . '" sudah ada.',
            ], Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('file_jadwal')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal')->store('uploads/kurikulum', 'public');
        }

        DB::beginTransaction();
        try {
            // Logika meng-nonaktifkan kurikulum lain saat yang baru dibuat
            Kurikulum::query()->update(['is_active' => 0]);
            $validated['is_active'] = 1;

            $kurikulum = Kurikulum::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kurikulum baru berhasil ditambahkan dan otomatis diaktifkan.',
                'data'    => new KurikulumResource($kurikulum),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['file_jadwal_path'])) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            Log::error('Kurikulum Store Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan kurikulum'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Kurikulum $kurikulum): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new KurikulumResource($kurikulum),
        ], Response::HTTP_OK);
    }

    public function update(UpdateKurikulumRequest $request, Kurikulum $kurikulum): JsonResponse
    {
        $validated = $request->validated();
        $oldPath = $kurikulum->file_jadwal_path;

        if (isset($validated['judul']) && $validated['judul'] !== $kurikulum->judul) {
            if (Kurikulum::where('judul', $validated['judul'])->where('id', '!=', $kurikulum->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Konflik: Judul sudah digunakan kurikulum lain.',
                ], Response::HTTP_CONFLICT);
            }
        }

        if ($request->hasFile('file_jadwal')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal')->store('uploads/kurikulum', 'public');
        }

        DB::beginTransaction();
        try {
            if (isset($validated['is_active']) && $validated['is_active'] == 1) {
                Kurikulum::where('id', '!=', $kurikulum->id)->update(['is_active' => 0]);
            }

            $kurikulum->update($validated);
            DB::commit();

            if ($request->hasFile('file_jadwal') && $oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Kurikulum berhasil diperbarui.',
                'data'    => new KurikulumResource($kurikulum),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Kurikulum Update Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui kurikulum'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Kurikulum $kurikulum): JsonResponse
    {
        if ($kurikulum->tahunAjaran()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Kurikulum ini masih digunakan oleh data Tahun Ajaran.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $filePath = $kurikulum->file_jadwal_path;
        DB::beginTransaction();
        try {
            $kurikulum->delete();
            DB::commit();
            if ($filePath) Storage::disk('public')->delete($filePath);
            return response()->json(['success' => true, 'message' => 'Kurikulum berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Kurikulum Delete Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus kurikulum.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}