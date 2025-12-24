<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Http\Resources\PengumumanResource;
use App\Http\Requests\StorePengumumanRequest;
use App\Http\Requests\UpdatePengumumanRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class PengumumanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = Pengumuman::latest()->paginate(10);
        return new JsonResponse(PengumumanResource::collection($data));
    }
    
    public function show(Pengumuman $pengumuman): JsonResponse
    {
        return new JsonResponse(new PengumumanResource($pengumuman));
    }

    public function store(StorePengumumanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $item = DB::transaction(function () use ($validated) {
            return Pengumuman::create($validated);
        });

        return new JsonResponse(new PengumumanResource($item), 201);
    }

    public function update(UpdatePengumumanRequest $request, Pengumuman $pengumuman): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($pengumuman, $validated) {
            $pengumuman->update($validated);
        });

        return new JsonResponse(new PengumumanResource($pengumuman));
    }

    public function destroy(Pengumuman $pengumuman): JsonResponse
    {
        DB::transaction(function () use ($pengumuman) {
            $pengumuman->delete();
        });

        return new JsonResponse(null, 204);
    }
}