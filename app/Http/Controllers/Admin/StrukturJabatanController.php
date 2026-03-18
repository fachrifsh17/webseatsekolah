<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use App\Http\Resources\StrukturJabatanResource;
use App\Http\Requests\StoreStrukturJabatanRequest;
use App\Http\Requests\UpdateStrukturJabatanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class StrukturJabatanController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token')->except(['showTtd']);
        $this->middleware('role:Admin')->except(['showTtd']);
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);

        $this->authorizeResource(StrukturJabatan::class, 'struktur_jabatan', [
            'except' => ['showTtd']
        ]);
    }

    public function showTtd($id)
    {
        $item = StrukturJabatan::findOrFail($id);
        
        if (!$item->file_ttd) {
            abort(404);
        }

        $pathDefault = $item->file_ttd;
        $pathPrivate = 'private/' . $item->file_ttd;

        if (Storage::disk('local')->exists($pathPrivate)) {
            return Storage::disk('local')->response($pathPrivate);
        }

        if (Storage::disk('local')->exists($pathDefault)) {
            return Storage::disk('local')->response($pathDefault);
        }

        abort(404);
    }

    public function index(): JsonResponse
    {
        try {
            $data = StrukturJabatan::with(['guruStaf', 'jabatan'])
                ->orderBy('urutan_tampil', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => StrukturJabatanResource::collection($data),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Struktur Jabatan Index Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar struktur jabatan.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreStrukturJabatanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            if ($request->hasFile('file_ttd')) {
                $file = $request->file('file_ttd');
                $path = $file->store('tanda_tangan', 'local');
                $validated['file_ttd'] = $path;
            }

            $item = StrukturJabatan::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Struktur jabatan berhasil ditambahkan.',
                'data'    => new StrukturJabatanResource($item->load(['guruStaf', 'jabatan'])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Store Struktur Jabatan Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateStrukturJabatanRequest $request, StrukturJabatan $strukturJabatan): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            if ($request->hasFile('file_ttd')) {
                if ($strukturJabatan->file_ttd) {
                    if (Storage::disk('local')->exists($strukturJabatan->file_ttd)) {
                        Storage::disk('local')->delete($strukturJabatan->file_ttd);
                    }
                    if (Storage::disk('local')->exists('private/' . $strukturJabatan->file_ttd)) {
                        Storage::disk('local')->delete('private/' . $strukturJabatan->file_ttd);
                    }
                }
                
                $file = $request->file('file_ttd');
                $path = $file->store('tanda_tangan', 'local');
                $validated['file_ttd'] = $path;
            }

            $strukturJabatan->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Struktur jabatan berhasil diperbarui.',
                'data'    => new StrukturJabatanResource($strukturJabatan->load(['guruStaf', 'jabatan'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(StrukturJabatan $strukturJabatan): JsonResponse
    {
        try {
            if ($strukturJabatan->file_ttd) {
                if (Storage::disk('local')->exists($strukturJabatan->file_ttd)) {
                    Storage::disk('local')->delete($strukturJabatan->file_ttd);
                }
                if (Storage::disk('local')->exists('private/' . $strukturJabatan->file_ttd)) {
                    Storage::disk('local')->delete('private/' . $strukturJabatan->file_ttd);
                }
            }

            $strukturJabatan->delete();
            return response()->json([
                'success' => true,
                'message' => 'Data struktur jabatan berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}