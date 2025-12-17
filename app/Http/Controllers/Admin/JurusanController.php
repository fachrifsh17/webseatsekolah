<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Http\Resources\JurusanResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JurusanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin');
    }

    public function index()
    {
        $data = Jurusan::paginate(12);
        
        return JurusanResource::collection($data);
    }
    
    public function show($id)
    {
        $item = Jurusan::findOrFail($id);
        
        return new JurusanResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_jurusan' => 'required|string|max:255|unique:jurusan,nama_jurusan',
            'deskripsi' => 'nullable|string',
            'foto' => 'nullable|image|max:2048',
        ]);
        
        $fotoPath = $request->hasFile('foto') 
            ? $request->file('foto')->store('uploads/jurusan','public') 
            : null;

        $jurusan = Jurusan::create([
            'nama_jurusan' => $validated['nama_jurusan'],
            'deskripsi' => $validated['deskripsi'],
            'foto' => $fotoPath,
        ]);
        
        return new JurusanResource($jurusan);
    }

    public function update(Request $request, $id)
    {
        $item = Jurusan::findOrFail($id);
        
        $validated = $request->validate([
            'nama_jurusan' => 'required|string|max:255|unique:jurusan,nama_jurusan,' . $id,
            'deskripsi' => 'nullable|string',
            'foto' => 'nullable|image|max:2048',
        ]);
        
        if ($request->hasFile('foto')) {
            if ($item->foto) {
                Storage::disk('public')->delete($item->foto);
            }
            $item->foto = $request->file('foto')->store('uploads/jurusan','public');
        } else {
            // Jika foto tidak diupload, pastikan field foto tidak masuk ke $validated
            unset($validated['foto']);
        }

        $item->update($validated);
        
        return new JurusanResource($item);
    }

    public function destroy($id)
    {
        $item = Jurusan::findOrFail($id); 
        
        if ($item->foto) {
            Storage::disk('public')->delete($item->foto);
        }
        
        $item->delete(); 
        
        return response()->json(null, 204);
    }
}