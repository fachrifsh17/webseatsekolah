<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use App\Http\Resources\StrukturJabatanResource;
use Illuminate\Http\Request;

class StrukturJabatanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin'); 
    }

    public function index()
    {
        // Mengambil data, menyertakan relasi guru, dan diurutkan
        $data = StrukturJabatan::with('guru')->orderBy('urutan_tampil')->get(); 
        
        return StrukturJabatanResource::collection($data);
    }
    
    public function show($id)
    {
        $item = StrukturJabatan::with('guru')->findOrFail($id);
        
        return new StrukturJabatanResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_jabatan_struktural' => 'required|string|max:255',
            'guru_staf_id' => 'required|exists:guru_staf,id',
            'periode_mulai' => 'nullable|date',
            'urutan_tampil' => 'required|integer|min:1',
        ]);
        
        $item = StrukturJabatan::create($validated); 
        
        return new StrukturJabatanResource($item->load('guru'));
    }

    public function update(Request $request, $id)
    {
        $item = StrukturJabatan::findOrFail($id); 
        
        $validated = $request->validate([
            'nama_jabatan_struktural' => 'required|string|max:255',
            'guru_staf_id' => 'required|exists:guru_staf,id',
            'periode_mulai' => 'nullable|date',
            'urutan_tampil' => 'required|integer|min:1',
        ]);
        
        $item->update($validated); 
        
        return new StrukturJabatanResource($item->load('guru'));
    }

    public function destroy($id)
    {
        StrukturJabatan::findOrFail($id)->delete(); 
        
        return response()->json(null, 204);
    }
}