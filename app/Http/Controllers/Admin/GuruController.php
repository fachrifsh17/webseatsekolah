<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruStaf;
use App\Http\Resources\GuruResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GuruController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        // Otorisasi: Hanya Admin yang mengelola data Guru/Staf
        $this->middleware('role:Admin');
    }

    public function index()
    {
        // Memuat relasi jurusan dan user (jika user_id ada)
        $data = GuruStaf::with(['jurusan', 'user'])->paginate(12);
        
        return GuruResource::collection($data);
    }
    
    public function show($id)
    {
        $item = GuruStaf::with(['jurusan', 'user'])->findOrFail($id);
        
        return new GuruResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nip' => 'nullable|string|max:18|unique:guru_staf,nip',
            'nuptk' => 'nullable|string|max:16|unique:guru_staf,nuptk',
            'nama' => 'required|string|max:255',
            'jabatan_fungsional' => 'nullable|string',
            'status_kepegawaian' => 'nullable|string',
            'jurusan_id' => 'nullable|exists:jurusan,id',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/guru','public');
        }
        
        $guru = GuruStaf::create($validated);
        
        return new GuruResource($guru->load(['jurusan', 'user']));
    }

    public function update(Request $request, $id)
    {
        $item = GuruStaf::findOrFail($id);
        
        $validated = $request->validate([
            'nip' => 'nullable|string|max:18|unique:guru_staf,nip,' . $id,
            'nuptk' => 'nullable|string|max:16|unique:guru_staf,nuptk,' . $id,
            'nama' => 'required|string|max:255',
            'jabatan_fungsional' => 'nullable|string',
            'status_kepegawaian' => 'nullable|string',
            'jurusan_id' => 'nullable|exists:jurusan,id',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            if ($item->foto) {
                Storage::disk('public')->delete($item->foto);
            }
            $validated['foto'] = $request->file('foto')->store('uploads/guru','public');
        }

        $item->update($validated);
        
        return new GuruResource($item->load(['jurusan', 'user']));
    }

    public function destroy($id)
    {
        $item = GuruStaf::findOrFail($id);
        
        if ($item->foto) {
            Storage::disk('public')->delete($item->foto);
        }
        
        // Catatan: Jika GuruStaf memiliki relasi user (auth), Anda mungkin perlu menghapus user-nya juga di sini.
        // $item->user()->delete(); 

        $item->delete();
        
        return response()->json(null, 204);
    }
}