<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruStaf;
use App\Http\Resources\GuruResource;
use App\Http\Requests\StoreGuruRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class GuruController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['store', 'update', 'destroy']),
        ];
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
        
        $guru = GuruStaf::create($validated);
        
        return new GuruResource($guru->load(['jurusan', 'user']));
    }

    public function update(StoreGuruRequest $request, GuruStaf $guru)
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            if ($guru->foto) {
                Storage::disk('public')->delete($guru->foto);
            }
            $validated['foto'] = $request->file('foto')->store('uploads/guru', 'public');
        }

        $guru->update($validated);
        
        return new GuruResource($guru->load(['jurusan', 'user']));
    }

    public function destroy(GuruStaf $guru)
    {
        if ($guru->foto) {
            Storage::disk('public')->delete($guru->foto);
        }

        $guru->delete();
        
        return response()->json(null, 204);
    }
}