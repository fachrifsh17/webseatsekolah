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
        return GuruResource::collection($data)->response();
    }

    public function show(?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'message' => 'Data guru tidak ditemukan',
                'errors'  => ['id' => ['Guru dengan ID tersebut tidak ada']]
            ], 404);
        }

        $guru->load(['jurusan', 'user']);
        return response()->json(new GuruResource($guru));
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

        foreach (['nip', 'nuptk', 'user_id'] as $field) {
            if (!empty($validated[$field]) && GuruStaf::where($field, $validated[$field])->exists()) {
                if (!empty($validated['foto'])) {
                    Storage::disk('public')->delete($validated['foto']);
                }
                return response()->json([
                    'message' => strtoupper($field) . ' sudah terdaftar.',
                    'errors'  => [$field => [strtoupper($field) . ' sudah terdaftar.']]
                ], 409);
            }
        }

        try {
            $guru = DB::transaction(fn() => GuruStaf::create($validated));
            return response()->json(new GuruResource($guru->load(['jurusan', 'user'])), 201);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'message' => 'Gagal membuat data guru',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function update(UpdateGuruRequest $request, ?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'message' => 'Data guru tidak ditemukan',
                'errors'  => ['id' => ['Guru dengan ID tersebut tidak ada']]
            ], 404);
        }

        $validated = $request->validated();
        $oldFoto = $guru->foto;

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/guru', 'public');
        }

        if ($request->filled('foto') && $request->foto === 'null') {
            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }
            $validated['foto'] = null;
        }

        foreach (['nip', 'nuptk', 'user_id'] as $field) {
            if (!empty($validated[$field]) &&
                GuruStaf::where($field, $validated[$field])
                    ->where('id', '<>', $guru->id)
                    ->exists()) {
                if (!empty($validated['foto']) && $validated['foto'] !== $oldFoto) {
                    Storage::disk('public')->delete($validated['foto']);
                }
                return response()->json([
                    'message' => strtoupper($field) . ' sudah terdaftar.',
                    'errors'  => [$field => [strtoupper($field) . ' sudah terdaftar.']]
                ], 409);
            }
        }

        try {
            DB::transaction(fn() => $guru->update($validated));
            $guru->refresh();

            if (!empty($validated['foto']) && $oldFoto && $validated['foto'] !== $oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json(new GuruResource($guru->load(['jurusan', 'user'])), 200);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null) && $validated['foto'] !== $oldFoto) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'message' => 'Gagal memperbarui data guru',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function destroy(?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'message' => 'Data guru tidak ditemukan',
                'errors'  => ['id' => ['Guru dengan ID tersebut tidak ada']]
            ], 404);
        }

        $oldFoto = $guru->foto;

        try {
            DB::transaction(fn() => $guru->delete());

            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success'      => true,
                'message'      => 'Data guru berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Gagal menghapus data guru',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }
}
