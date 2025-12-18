<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use App\Http\Resources\FasilitasResource;
use App\Http\Requests\StoreFasilitasRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FasilitasController extends Controller implements HasMiddleware
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
        $data = Fasilitas::paginate(12);
        return FasilitasResource::collection($data);
    }
    
    public function show(Fasilitas $fasilitas)
    {
        return new FasilitasResource($fasilitas);
    }

    public function store(StoreFasilitasRequest $request)
    {
        $validated = $request->validated();
        
        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/fasilitas', 'public');
        }

        $fasilitas = Fasilitas::create($validated);
        
        return new FasilitasResource($fasilitas);
    }

    public function update(StoreFasilitasRequest $request, Fasilitas $fasilitas)
    {
        $validated = $request->validated();
        
        if ($request->hasFile('foto')) {
            if ($fasilitas->foto) {
                Storage::disk('public')->delete($fasilitas->foto);
            }
            $validated['foto'] = $request->file('foto')->store('uploads/fasilitas', 'public');
        }

        $fasilitas->update($validated);
        
        return new FasilitasResource($fasilitas);
    }

    public function destroy(Fasilitas $fasilitas)
    {
        if ($fasilitas->foto) {
            Storage::disk('public')->delete($fasilitas->foto);
        }
        
        $fasilitas->delete(); 
        
        return response()->json(null, 204);
    }
}