<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use App\Http\Resources\FasilitasResource;
use App\Http\Requests\StoreFasilitasRequest;
use App\Http\Requests\UpdateFasilitasRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Throwable;

class FasilitasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = Fasilitas::paginate(12);

        return response()->json([
            'success' => true,
            'data'    => FasilitasResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ]);
    }
    
    public function show(Fasilitas $fasilitas): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new FasilitasResource($fasilitas),
        ]);
    }

    public function store(StoreFasilitasRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('foto')) {
                $validated['foto'] = $request->file('foto')->store('uploads/fasilitas', 'public');
            }

            $fasilitas = Fasilitas::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Fasilitas berhasil ditambahkan.',
                'data'    => new FasilitasResource($fasilitas),
            ], 201);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan fasilitas.',
            ], 500);
        }
    }

    public function update(UpdateFasilitasRequest $request, Fasilitas $fasilitas): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('foto')) {
                if ($fasilitas->foto) {
                    Storage::disk('public')->delete($fasilitas->foto);
                }
                $validated['foto'] = $request->file('foto')->store('uploads/fasilitas', 'public');
            }

            $fasilitas->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Fasilitas berhasil diperbarui.',
                'data'    => new FasilitasResource($fasilitas),
            ]);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui fasilitas.',
            ], 500);
        }
    }

    public function destroy(Fasilitas $fasilitas): JsonResponse
    {
        try {
            if ($fasilitas->foto) {
                Storage::disk('public')->delete($fasilitas->foto);
            }

            $fasilitas->delete();

            return response()->json([
                'success' => true,
                'message' => 'Fasilitas berhasil dihapus.'
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus fasilitas.',
            ], 500);
        }
    }
}
