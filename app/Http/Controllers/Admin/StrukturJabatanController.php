<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use App\Http\Resources\StrukturJabatanResource;
use App\Http\Requests\StoreStrukturJabatanRequest;
use App\Http\Requests\UpdateStrukturJabatanRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\JsonResponse;

class StrukturJabatanController extends Controller implements HasMiddleware
{
       public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        $data = StrukturJabatan::with('guru')->orderBy('urutan_tampil')->get(); 
        
        return StrukturJabatanResource::collection($data);
    }
    
    public function show(StrukturJabatan $strukturJabatan)
    {
        return new StrukturJabatanResource($strukturJabatan->load('guru'));
    }

    public function store(StoreStrukturJabatanRequest $request)
    {
        $item = StrukturJabatan::create($request->validated()); 
        
        return new StrukturJabatanResource($item->load('guru'));
    }

    public function update(UpdateStrukturJabatanRequest $request, StrukturJabatan $strukturJabatan)
    {
        $strukturJabatan->update($request->validated()); 
        
        return new StrukturJabatanResource($strukturJabatan->load('guru'));
    }

    public function destroy(StrukturJabatan $strukturJabatan): JsonResponse
    {
        $strukturJabatan->delete(); 
        
        return response()->json(null, 204);
    }
}