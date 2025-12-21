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

    public function index()
    {
        $data = GuruStaf::with(['jurusan', 'user'])->paginate(12);
        return GuruResource::collection($data);
    }

    public function show(GuruStaf $guru)
    {
        $guru->load(['jurusan', 'user']);
        return new GuruResource($guru);
    }

    public function store(StoreGuruRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/guru', 'public');
        }

        if (Auth::check() && empty($validated['user_id'] ?? null)) {
            $validated['user_id'] = Auth::id();
        }

        DB::beginTransaction();
        try {
            $guru = GuruStaf::create($validated);
            DB::commit();
            return new GuruResource($guru->load(['jurusan', 'user']));
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat data guru'
            ], 500);
        }
    }

    public function update(UpdateGuruRequest $request, GuruStaf $guru)
    {
        $validated = $request->validated();

        $newFotoPath = null;
        if ($request->hasFile('foto')) {
            $newFotoPath = $request->file('foto')->store('uploads/guru', 'public');
            if ($newFotoPath) {
                $validated['foto'] = $newFotoPath;
            }
        }

        DB::beginTransaction();
        try {
            $oldFoto = $guru->foto;
            $guru->update($validated);
            DB::commit();

            if ($newFotoPath && $oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return new GuruResource($guru->load(['jurusan', 'user']));
        } catch (Throwable $e) {
            DB::rollBack();
            if ($newFotoPath) {
                Storage::disk('public')->delete($newFotoPath);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data guru'
            ], 500);
        }
    }

    public function destroy(GuruStaf $guru)
    {
        DB::beginTransaction();
        try {
            $oldFoto = $guru->foto;
            $guru->delete();
            DB::commit();

            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->noContent();
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data guru'
            ], 500);
        }
    }
}