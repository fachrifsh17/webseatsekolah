<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use App\Http\Resources\ProfilSekolahResource;
use App\Http\Requests\UpdateProfilSekolahRequest;
use Illuminate\Http\Request;

class ProfilSekolahController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin');
    }
    
    // Metode untuk mengambil data (READ)
    public function index()
    {
        // Cari atau buat record ID 1 (Single Row Logic)
        $profil = ProfilSekolah::firstOrNew(['id' => 1]); 
        
        // Jika data belum ada (baru dibuat), kembalikan respons kosong atau default
        if (!$profil->exists) {
             return response()->json([
                'message' => 'Data profil sekolah belum diinisialisasi.'
            ], 200);
        }

        return new ProfilSekolahResource($profil);
    }
    
    public function update(UpdateProfilSekolahRequest $request)
    {
        $profil = ProfilSekolah::firstOrNew(['id' => 1]);

        $profil->fill($request->validated());
        
        if (!$profil->exists) {
             $profil->id = 1;
        }
        
        $profil->save();

        return new ProfilSekolahResource($profil);
    }

    public function destroy($id)
    {
        if ((int)$id !== 1) {
            return response()->json(['message' => 'Hanya profil ID 1 yang dapat dihapus, jika diperlukan.'], 403);
        }
        
        $profil = ProfilSekolah::findOrFail(1);
        $profil->delete();
        
        return response()->json(null, 204);
    }
}