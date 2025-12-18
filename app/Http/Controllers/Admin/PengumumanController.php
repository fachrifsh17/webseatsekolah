<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Http\Resources\PengumumanResource;
use App\Http\Requests\StorePengumumanRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\JsonResponse;

class PengumumanController extends Controller implements HasMiddleware
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
        $data = Pengumuman::latest()->paginate(10);
        
        return PengumumanResource::collection($data);
    }
    
    public function show(Pengumuman $pengumuman)
    {
        return new PengumumanResource($pengumuman);
    }

    public function store(StorePengumumanRequest $request)
    {
        $item = Pengumuman::create($request->validated());

        return new PengumumanResource($item);
    }

    public function update(StorePengumumanRequest $request, Pengumuman $pengumuman)
    {
        $pengumuman->update($request->validated());

        return new PengumumanResource($pengumuman);
    }

    public function destroy(Pengumuman $pengumuman): JsonResponse
    {
        $pengumuman->delete();
        
        return response()->json(null, 204);
    }
}