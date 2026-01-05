<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Http\Resources\EkstrakurikulerResource;
use App\Http\Requests\StoreEkstrakurikulerRequest;
use App\Http\Requests\UpdateEkstrakurikulerRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
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
        return response()->json(EkstrakurikulerResource::collection($data));
    }

    public function show(Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $ekstrakurikuler->load('pembina');
        return response()->json(new EkstrakurikulerResource($ekstrakurikuler));
    }

    public function store(StoreEkstrakurikulerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
        }

        try {
            $ekskul = DB::transaction(function () use ($validated) {
                return Ekstrakurikuler::create($validated);
            });

            return response()->json(new EkstrakurikulerResource($ekskul->load('pembina')), 201);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan ekstrakurikuler'
            ], 500);
        }
    }

    public function update(UpdateEkstrakurikulerRequest $request, Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $validated = $request->validated();
        $oldFoto = $ekstrakurikuler->foto;

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
        }

        if ($request->filled('foto') && $request->foto === 'null') {
            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }
            $validated['foto'] = null;
        }

        try {
            DB::transaction(function () use ($ekstrakurikuler, $validated) {
                $ekstrakurikuler->update($validated);
            });

            $ekstrakurikuler->refresh();

            if (!empty($validated['foto']) && $oldFoto && $validated['foto'] !== $oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json(new EkstrakurikulerResource($ekstrakurikuler->load('pembina')));
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null) && $validated['foto'] !== $oldFoto) {
                Storage::disk('public')->delete($validated['foto']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui ekstrakurikuler'
            ], 500);
        }
    }

    public function destroy(Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $oldFoto = $ekstrakurikuler->foto;

        try {
            DB::transaction(function () use ($ekstrakurikuler) {
                $ekstrakurikuler->delete();
            });

            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success'      => true,
                'message'      => 'Data ekstrakurikuler berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus ekstrakurikuler'
            ], 500);
        }
    }
}
