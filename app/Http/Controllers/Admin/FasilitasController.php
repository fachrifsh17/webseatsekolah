<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use App\Http\Resources\FasilitasResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FasilitasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        // Otorisasi: Hanya Admin yang biasanya mengelola data master seperti Fasilitas
        $this->middleware('role:Admin');
    }

    public function index()
    {
        $data = Fasilitas::paginate(12);
        
        return FasilitasResource::collection($data);
    }
    
    public function show($id)
    {
        $item = Fasilitas::findOrFail($id);
        
        return new FasilitasResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_fasilitas' => 'required|string|max:255',
            'keterangan' => 'nullable|string',
            'foto' => 'nullable|image|max:2048',
        ]);
        
        $fotoPath = $request->hasFile('foto') 
            ? $request->file('foto')->store('uploads/fasilitas','public') 
            : null;

        $fasilitas = Fasilitas::create([
            'nama_fasilitas' => $validated['nama_fasilitas'],
            'foto' => $fotoPath,
            'keterangan' => $validated['keterangan'],
        ]);
        
        return new FasilitasResource($fasilitas);
    }

    public function update(Request $request, $id)
    {
        $item = Fasilitas::findOrFail($id);
        
        $validated = $request->validate([
            'nama_fasilitas' => 'required|string|max:255',
            'keterangan' => 'nullable|string',
            'foto' => 'nullable|image|max:2048',
        ]);
        
        if ($request->hasFile('foto')) {
            if ($item->foto) {
                Storage::disk('public')->delete($item->foto);
            }
            $item->foto = $request->file('foto')->store('uploads/fasilitas','public');
        }

        $item->nama_fasilitas = $validated['nama_fasilitas']; 
        $item->keterangan = $validated['keterangan']; 
        $item->save();
        
        return new FasilitasResource($item);
    }

    public function destroy($id)
    {
        $item = Fasilitas::findOrFail($id); 
        
        if ($item->foto) {
            Storage::disk('public')->delete($item->foto);
        }
        
        $item->delete(); 
        
        return response()->json(null, 204);
    }
}