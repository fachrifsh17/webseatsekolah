<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Http\Resources\PengumumanResource;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin');
    }

    public function index()
    {
        $data = Pengumuman::latest()->paginate(10);
        
        return PengumumanResource::collection($data);
    }
    
    public function show($id)
    {
        $item = Pengumuman::findOrFail($id);
        
        return new PengumumanResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'isi_pengumuman' => 'required|string',
            'tanggal_publikasi' => 'nullable|date',
            // 'boolean' di API biasanya dikirim sebagai 0 atau 1 atau true/false
            'penting' => 'nullable|boolean', 
        ]);

        $item = Pengumuman::create($validated);

        return new PengumumanResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = Pengumuman::findOrFail($id);

        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'isi_pengumuman' => 'required|string',
            'tanggal_publikasi' => 'nullable|date',
            'penting' => 'nullable|boolean',
        ]);

        $item->update($validated);

        return new PengumumanResource($item);
    }

    public function destroy($id)
    {
        Pengumuman::findOrFail($id)->delete();
        
        return response()->json(null, 204);
    }
}