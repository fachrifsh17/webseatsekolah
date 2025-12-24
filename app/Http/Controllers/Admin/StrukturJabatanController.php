<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use App\Http\Resources\StrukturJabatanResource;
use App\Http\Requests\StoreStrukturJabatanRequest;
use App\Http\Requests\UpdateStrukturJabatanRequest;
use Illuminate\Http\JsonResponse;

class StrukturJabatanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = StrukturJabatan::with('guru')->orderBy('urutan_tampil')->get();
        return new JsonResponse(StrukturJabatanResource::collection($data));
    }
    
    public function show(StrukturJabatan $strukturJabatan): JsonResponse
    {
        return new JsonResponse(new StrukturJabatanResource($strukturJabatan->load('guru')));
    }

    public function store(StoreStrukturJabatanRequest $request): JsonResponse
    {
        $item = StrukturJabatan::create($request->validated());
        return new JsonResponse(new StrukturJabatanResource($item->load('guru')), 201);
    }

    public function update(UpdateStrukturJabatanRequest $request, StrukturJabatan $strukturJabatan): JsonResponse
    {
        $strukturJabatan->update($request->validated());
        return new JsonResponse(new StrukturJabatanResource($strukturJabatan->load('guru')));
    }

    public function destroy(StrukturJabatan $strukturJabatan): JsonResponse
    {
        $strukturJabatan->delete();
        return new JsonResponse(null, 204);
    }
}