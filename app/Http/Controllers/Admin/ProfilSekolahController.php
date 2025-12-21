<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use App\Http\Resources\ProfilSekolahResource;
use App\Http\Requests\UpdateProfilSekolahRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

class ProfilSekolahController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['update', 'destroy']);
    }
    
    public function index(): JsonResponse
    {
        $profil = ProfilSekolah::find(1); 
        
        if (!$profil) {
            return response()->json([
                'status' => 'success',
                'message' => 'Data profil sekolah belum diisi.',
                'data' => null
            ], 200);
        }

        return response()->json(new ProfilSekolahResource($profil));
    }
    
    public function update(UpdateProfilSekolahRequest $request): JsonResponse
    {
        $profil = ProfilSekolah::firstOrNew(['id' => 1]);

        $profil->fill($request->validated());
        
        if (!$profil->exists) {
            $profil->id = 1;
        }
        
        $profil->save();

        return response()->json(new ProfilSekolahResource($profil));
    }

    public function destroy(int $id): JsonResponse
    {
        if ($id !== 1) {
            return response()->json(['message' => 'Hanya profil sekolah (ID 1) yang tersedia.'], 403);
        }
        
        $profil = ProfilSekolah::find(1);
        
        if ($profil) {
            $profil->delete();
        }
        
        return response()->json(null, 204);
    }
}