<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use App\Http\Resources\FasilitasResource;
use App\Http\Requests\StoreFasilitasRequest;
use App\Http\Requests\UpdateFasilitasRequest;
use Illuminate\Support\Facades\Storage;

class FasilitasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
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

    public function update(UpdateFasilitasRequest $request, Fasilitas $fasilitas)
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
        
        return response()->noContent();
    }
}