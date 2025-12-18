<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Http\Resources\JurusanResource;
use App\Http\Requests\StoreJurusanRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class JurusanController extends Controller implements HasMiddleware
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
        $data = Jurusan::paginate(12);
        return JurusanResource::collection($data);
    }
    
    public function show(Jurusan $jurusan)
    {
        return new JurusanResource($jurusan);
    }

    public function store(StoreJurusanRequest $request)
    {
        $validated = $request->validated();
        
        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/jurusan', 'public');
        }

        $jurusan = Jurusan::create($validated);
        
        return new JurusanResource($jurusan);
    }

    public function update(StoreJurusanRequest $request, Jurusan $jurusan)
    {
        $validated = $request->validated();
        
        if ($request->hasFile('foto')) {
            if ($jurusan->foto) {
                Storage::disk('public')->delete($jurusan->foto);
            }
            $validated['foto'] = $request->file('foto')->store('uploads/jurusan', 'public');
        }

        $jurusan->update($validated);
        
        return new JurusanResource($jurusan);
    }

    public function destroy(Jurusan $jurusan)
    {
        if ($jurusan->foto) {
            Storage::disk('public')->delete($jurusan->foto);
        }
        
        $jurusan->delete(); 
        
        return response()->json(null, 204);
    }
}