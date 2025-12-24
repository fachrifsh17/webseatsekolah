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
        return new JsonResponse(FasilitasResource::collection($data));
    }
    
    public function show(Fasilitas $fasilitas): JsonResponse
    {
        return new JsonResponse(new FasilitasResource($fasilitas));
    }

    public function store(StoreFasilitasRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('foto')) {
                $validated['foto'] = $request->file('foto')->store('uploads/fasilitas', 'public');
            }

            $fasilitas = Fasilitas::create($validated);

            return new JsonResponse(new FasilitasResource($fasilitas), 201);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menambahkan fasilitas'
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

            return new JsonResponse(new FasilitasResource($fasilitas));
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memperbarui fasilitas'
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

            return new JsonResponse(null, 204);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menghapus fasilitas'
            ], 500);
        }
    }
}