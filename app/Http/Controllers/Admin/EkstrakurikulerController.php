<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Http\Resources\EkstrakurikulerResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EkstrakurikulerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        // Otorisasi: Hanya Admin yang biasanya mengelola data master seperti Ekskul
        $this->middleware('role:Admin');
    }

    public function index()
    {
        // Memuat relasi pembina (asumsi relasi di model bernama 'pembina' ke tabel 'guru')
        $data = Ekstrakurikuler::with('pembina')->paginate(12);
        
        return EkstrakurikulerResource::collection($data);
    }
    
    public function show($id)
    {
        $item = Ekstrakurikuler::with('pembina')->findOrFail($id);
        
        return new EkstrakurikulerResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_ekskul' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'hari' => 'nullable|string',
            'jam_mulai' => 'nullable',
            'jam_selesai' => 'nullable',
            'pembina_id' => 'required|exists:guru,id',
            'keterangan' => 'nullable|string',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul','public');
        }
        
        $ekskul = Ekstrakurikuler::create($validated);
        
        return new EkstrakurikulerResource($ekskul->load('pembina'));
    }

    public function update(Request $request, $id)
    {
        $item = Ekstrakurikuler::with('pembina')->findOrFail($id);
        
        $validated = $request->validate([
            'nama_ekskul' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'hari' => 'nullable|string',
            'jam_mulai' => 'nullable',
            'jam_selesai' => 'nullable',
            'pembina_id' => 'required|exists:guru,id',
            'keterangan' => 'nullable|string',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            if ($item->foto) {
                Storage::disk('public')->delete($item->foto);
            }
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul','public');
        }

        $item->update($validated);
        
        return new EkstrakurikulerResource($item);
    }

    public function destroy($id)
    {
        $item = Ekstrakurikuler::findOrFail($id);
        
        if ($item->foto) {
            Storage::disk('public')->delete($item->foto);
        }
        
        $item->delete();
        
        return response()->json(null, 204);
    }
}