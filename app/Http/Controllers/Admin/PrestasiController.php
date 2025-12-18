<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use App\Http\Resources\PrestasiResource;
use App\Http\Requests\StorePrestasiRequest;
use App\Http\Requests\UpdatePrestasiRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\JsonResponse;

class PrestasiController extends Controller implements HasMiddleware
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
        $items = Prestasi::orderBy('tahun', 'desc')->paginate(12);
        return PrestasiResource::collection($items);
    }
    
    public function show(Prestasi $prestasi)
    {
        return new PrestasiResource($prestasi);
    }

    public function store(StorePrestasiRequest $request)
    {
        $validated = $request->validated();
        
        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/prestasi', 'public');
        }
        
        $prestasi = Prestasi::create($validated);
        return new PrestasiResource($prestasi);
    }

    public function update(UpdatePrestasiRequest $request, Prestasi $prestasi)
    {
        $validated = $request->validated();
        
        if ($request->hasFile('foto')) {
            if ($prestasi->foto) {
                Storage::disk('public')->delete($prestasi->foto);
            }
            $validated['foto'] = $request->file('foto')->store('uploads/prestasi', 'public');
        }
        
        $prestasi->update($validated);
        return new PrestasiResource($prestasi);
    }

    public function destroy(Prestasi $prestasi): JsonResponse
    {
        if ($prestasi->foto) {
            Storage::disk('public')->delete($prestasi->foto);
        }
        
        $prestasi->delete();
        return response()->json(null, 204);
    }
}