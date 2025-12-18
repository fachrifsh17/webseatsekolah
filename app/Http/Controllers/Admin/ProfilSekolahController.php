<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use App\Http\Resources\ProfilSekolahResource;
use App\Http\Requests\UpdateProfilSekolahRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProfilSekolahController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['update', 'destroy']),
        ];
    }
    
    public function index()
    {
        $profil = ProfilSekolah::find(1); 
        
        if (!$profil) {
             return response()->json([
                'status' => 'success',
                'message' => 'Data profil sekolah belum diisi.',
                'data' => null
            ], 200);
        }

        return new ProfilSekolahResource($profil);
    }
    
    public function update(UpdateProfilSekolahRequest $request): ProfilSekolahResource
    {
        $profil = ProfilSekolah::firstOrNew(['id' => 1]);

        $profil->fill($request->validated());
        
        if (!$profil->exists) {
             $profil->id = 1;
        }
        
        $profil->save();

        return new ProfilSekolahResource($profil);
    }

    public function destroy(mixed $id): JsonResponse
    {
        if ((int)$id !== 1) {
            return response()->json(['message' => 'Hanya profil sekolah (ID 1) yang tersedia.'], 403);
        }
        
        $profil = ProfilSekolah::find(1);
        
        if ($profil) {
            $profil->delete();
        }
        
        return response()->json(null, 204);
    }
}