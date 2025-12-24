<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Http\Resources\MapelResource;
use App\Http\Requests\StoreMapelRequest;
use App\Http\Requests\UpdateMapelRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): AnonymousResourceCollection
    {
        $data = MataPelajaran::with('jurusan')->paginate(12);
        return MapelResource::collection($data);
    }
    
    public function show(MataPelajaran $mapel): JsonResponse
    {
        return new JsonResponse(new MapelResource($mapel->load('jurusan')));
    }

    public function store(StoreMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $item = DB::transaction(function () use ($validated) {
            return MataPelajaran::create($validated);
        });

        return new JsonResponse(new MapelResource($item->load('jurusan')), 201);
    }

    public function update(UpdateMapelRequest $request, MataPelajaran $mapel): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($mapel, $validated) {
            $mapel->update($validated);
        });

        return new JsonResponse(new MapelResource($mapel->load('jurusan')));
    }

    public function destroy(MataPelajaran $mapel): JsonResponse
    {
        DB::transaction(function () use ($mapel) {
            $mapel->delete();
        });

        return new JsonResponse(null, 204);
    }
}