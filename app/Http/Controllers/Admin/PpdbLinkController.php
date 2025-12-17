<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PPDBLink;
use App\Models\LogAdmin;
use App\Http\Resources\PPDBLinkResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PpdbLinkController extends Controller
{
    public function __construct()
    {
        // Menggunakan auth:sanctum dan role:Admin untuk konsistensi API
        $this->middleware('auth:sanctum'); 
        $this->middleware('role:Admin');
    }

    public function index()
    {
        $links = PPDBLink::orderBy('id')->get();
        
        return PPDBLinkResource::collection($links);
    }
    
    public function show($id)
    {
        $link = PPDBLink::findOrFail($id);
        
        return new PPDBLinkResource($link);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'url_link' => 'required|url|max:255',
            'status_ppdb' => 'nullable|in:Buka,Tutup,Segera',
        ]);

        $link = PPDBLink::create($validated);

        // Logging aksi untuk API Sanctum
        if (Auth::check()) {
            LogAdmin::create([
                'user_id' => Auth::id(),
                'aksi' => 'Menambah PPDB link id=' . $link->id,
            ]);
        }

        return new PPDBLinkResource($link);
    }

    public function update(Request $request, $id)
    {
        $link = PPDBLink::findOrFail($id);

        $validated = $request->validate([
            'url_link' => 'required|url|max:255',
            'status_ppdb' => 'nullable|in:Buka,Tutup,Segera',
        ]);

        $link->update($validated);

        // Logging aksi
        if (Auth::check()) {
            LogAdmin::create([
                'user_id' => Auth::id(),
                'aksi' => 'Mengubah PPDB link id=' . $link->id,
            ]);
        }

        return new PPDBLinkResource($link);
    }

    public function destroy($id)
    {
        $link = PPDBLink::findOrFail($id);
        $link->delete();

        // Logging aksi
        if (Auth::check()) {
            LogAdmin::create([
                'user_id' => Auth::id(),
                'aksi' => 'Menghapus PPDB link id=' . $id,
            ]);
        }

        return response()->json(null, 204);
    }
}