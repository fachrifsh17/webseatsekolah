<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use App\Http\Resources\KalenderAkademikResource;
use Illuminate\Http\Request;

class KalenderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin');
    }

    public function index()
    {
        $data = KalenderAkademik::orderBy('tanggal_mulai')->paginate(12); 
        
        return KalenderAkademikResource::collection($data);
    }
    
    public function show($id)
    {
        $item = KalenderAkademik::findOrFail($id);
        
        return new KalenderAkademikResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kegiatan' => 'required|string|max:255',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'kategori' => 'nullable|string|max:100',
        ]);
        
        $item = KalenderAkademik::create($validated);
        
        return new KalenderAkademikResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = KalenderAkademik::findOrFail($id); 
        
        $validated = $request->validate([
            'kegiatan' => 'required|string|max:255',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'kategori' => 'nullable|string|max:100',
        ]);
        
        $item->update($validated); 
        
        return new KalenderAkademikResource($item);
    }

    public function destroy($id)
    {
        KalenderAkademik::findOrFail($id)->delete(); 
        
        return response()->json(null, 204);
    }
}