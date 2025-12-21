<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Orangtua;
use App\Http\Requests\StoreOrangtuaRequest;
use App\Http\Requests\UpdateOrangtuaRequest;
use App\Http\Resources\OrangtuaResource;
use Illuminate\Http\JsonResponse;

class OrangtuaController extends Controller
{
    public function index(): JsonResponse
    {
        $orangtua = Orangtua::with(['user', 'anak'])->latest()->get();
        return response()->json(OrangtuaResource::collection($orangtua));
    }

    public function store(StoreOrangtuaRequest $request): JsonResponse
    {
        $orangtua = Orangtua::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data orang tua berhasil ditambahkan.',
            'data'    => new OrangtuaResource($orangtua->load('user'))
        ], 201);
    }

    public function show(Orangtua $orangtua): JsonResponse
    {
        return response()->json(new OrangtuaResource($orangtua->load(['user', 'anak'])));
    }

    public function update(UpdateOrangtuaRequest $request, Orangtua $orangtua): JsonResponse
    {
        $validated = $request->validated();

        $orangtua->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data orang tua berhasil diperbarui.',
            'data'    => new OrangtuaResource($orangtua->load('user'))
        ]);
    }

    public function destroy(Orangtua $orangtua): JsonResponse
    {
        $orangtua->delete();

        return response()->json(null, 204);
    }
}