<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Http\Resources\EkstrakurikulerResource;
use App\Http\Requests\StoreEkstrakurikulerRequest;
use App\Http\Requests\UpdateEkstrakurikulerRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Throwable;

class EkstrakurikulerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = Ekstrakurikuler::with('pembina')->paginate(12);
        return new JsonResponse(EkstrakurikulerResource::collection($data));
    }
    
    public function show(Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $ekstrakurikuler->load('pembina');
        return new JsonResponse(new EkstrakurikulerResource($ekstrakurikuler));
    }

    public function store(StoreEkstrakurikulerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('foto')) {
                $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
            }
            
            $ekskul = Ekstrakurikuler::create($validated);
            return new JsonResponse(new EkstrakurikulerResource($ekskul->load('pembina')), 201);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menambahkan ekstrakurikuler'
            ], 500);
        }
    }

    public function update(UpdateEkstrakurikulerRequest $request, Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('foto')) {
                if ($ekstrakurikuler->foto) {
                    Storage::disk('public')->delete($ekstrakurikuler->foto);
                }
                $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
            }

            $ekstrakurikuler->update($validated);
            return new JsonResponse(new EkstrakurikulerResource($ekstrakurikuler->load('pembina')));
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memperbarui ekstrakurikuler'
            ], 500);
        }
    }

    public function destroy(Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        try {
            if ($ekstrakurikuler->foto) {
                Storage::disk('public')->delete($ekstrakurikuler->foto);
            }
            
            $ekstrakurikuler->delete();
            return new JsonResponse(null, 204);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menghapus ekstrakurikuler'
            ], 500);
        }
    }
}