<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use App\Http\Resources\KurikulumResource;
use App\Http\Requests\{StoreKurikulumRequest, UpdateKurikulumRequest};
use Illuminate\Support\Facades\{Storage, DB, Log};
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class KurikulumController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);

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
            Log::error('Failed to fetch kurikulum list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar kurikulum',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Kurikulum $kurikulum): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new KurikulumResource($kurikulum),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail kurikulum',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreKurikulumRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (Kurikulum::where('judul', $validated['judul'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data. Kurikulum dengan judul "' . $validated['judul'] . '" sudah ada.',
            ], Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('file_jadwal')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal')->store('uploads/kurikulum', 'public');
        }

        DB::beginTransaction();
        try {
            Kurikulum::query()->update(['is_active' => false]);
            $validated['is_active'] = true;

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
            Log::error('Failed to create kurikulum', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat kurikulum',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateKurikulumRequest $request, Kurikulum $kurikulum): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['judul']) && $validated['judul'] !== $kurikulum->judul) {
            if (Kurikulum::where('judul', $validated['judul'])->where('id', '!=', $kurikulum->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Konflik: Judul tersebut sudah digunakan oleh kurikulum lain.',
                ], Response::HTTP_CONFLICT);
            }
        }

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
            if (isset($validated['is_active']) && $validated['is_active'] == true) {
                Kurikulum::where('id', '!=', $kurikulum->id)->update(['is_active' => false]);
            }

            $kurikulum->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kurikulum berhasil diperbarui.',
                'data'    => new KurikulumResource($kurikulum),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['file_jadwal_path']) && ($validated['file_jadwal_path'] !== $kurikulum->file_jadwal_path)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui kurikulum',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Kurikulum $kurikulum): JsonResponse
    {
        if ($kurikulum->tahunAjaran()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus kurikulum karena masih memiliki data Tahun Ajaran yang terkait.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $filePath = $kurikulum->file_jadwal_path;
        DB::beginTransaction();
        try {
            $kurikulum->delete();
            DB::commit();

            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data kurikulum berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete kurikulum', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kurikulum',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}