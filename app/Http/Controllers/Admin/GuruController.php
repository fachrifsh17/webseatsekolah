<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruStaf;
use App\Http\Resources\GuruResource;
use App\Http\Requests\StoreGuruRequest;
use App\Http\Requests\UpdateGuruRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Throwable;

class GuruController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = GuruStaf::with(['jurusan', 'user'])->paginate(12);
        return new JsonResponse(GuruResource::collection($data));
    }

    public function show(GuruStaf $guru): JsonResponse
    {
        $guru->load(['jurusan', 'user']);
        return new JsonResponse(new GuruResource($guru));
    }

    public function store(StoreGuruRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/guru', 'public');
        }

        if (Auth::check() && empty($validated['user_id'] ?? null)) {
            $validated['user_id'] = Auth::id();
        }

        try {
            $guru = DB::transaction(function () use ($validated) {
                return GuruStaf::create($validated);
            });

            return new JsonResponse(new GuruResource($guru->load(['jurusan', 'user'])));
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal membuat data guru'
            ], 500);
        }
    }

    public function update(UpdateGuruRequest $request, GuruStaf $guru): JsonResponse
    {
        $validated = $request->validated();
        $oldFoto = $guru->foto;

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/guru', 'public');
        }

        try {
            DB::transaction(function () use ($guru, $validated) {
                $guru->update($validated);
            });

            if (!empty($validated['foto']) && $oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return new JsonResponse(new GuruResource($guru->load(['jurusan', 'user'])));
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memperbarui data guru'
            ], 500);
        }
    }

    public function destroy(GuruStaf $guru): JsonResponse
    {
        $oldFoto = $guru->foto;

        try {
            DB::transaction(function () use ($guru) {
                $guru->delete();
            });

            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return new JsonResponse(null, 204);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menghapus data guru'
            ], 500);
        }
    }
}