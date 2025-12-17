<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Http\Resources\MapelResource;
use Illuminate\Http\Request;

class MapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin');
    }

    public function index()
    {
        // Memuat relasi jurusan
        $data = MataPelajaran::with('jurusan')->paginate(12);
        
        return MapelResource::collection($data);
    }
    
    public function show($id)
    {
        $item = MataPelajaran::with('jurusan')->findOrFail($id);
        
        return new MapelResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_mapel' => 'required|string|max:255|unique:mata_pelajaran,nama_mapel',
            'jurusan_id' => 'nullable|exists:jurusan,id',
            'tipe_mapel' => 'nullable|string|max:50',
            'kategori_mapel' => 'nullable|string|max:50',
        ]);
        
        $item = MataPelajaran::create($validated); 
        
        return new MapelResource($item->load('jurusan'));
    }

    public function update(Request $request, $id)
    {
        $item = MataPelajaran::findOrFail($id); 
        
        $validated = $request->validate([
            'nama_mapel' => 'required|string|max:255|unique:mata_pelajaran,nama_mapel,' . $id,
            'jurusan_id' => 'nullable|exists:jurusan,id',
            'tipe_mapel' => 'nullable|string|max:50',
            'kategori_mapel' => 'nullable|string|max:50',
        ]);
        
        $item->update($validated); 
        
        return new MapelResource($item->load('jurusan'));
    }

    public function destroy($id)
    {
        MataPelajaran::findOrFail($id)->delete(); 
        
        return response()->json(null, 204);
    }
}