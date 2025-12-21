<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Orangtua;
use App\Http\Resources\OrangtuaResource;
use App\Http\Requests\StoreOrangtuaRequest;
use Illuminate\Http\Request;

class OrangtuaApiController extends Controller
{
    public function index()
    {
        $orangtua = Orangtua::with('user')->latest()->get();
        return OrangtuaResource::collection($orangtua);
    }

    public function store(StoreOrangtuaRequest $request)
    {
        $orangtua = Orangtua::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data orang tua berhasil ditambahkan',
            'data' => new OrangtuaResource($orangtua->load('user'))
        ], 201);
    }

    public function show(Orangtua $orangtua)
    {
        return new OrangtuaResource($orangtua->load(['user', 'siswa']));
    }

    public function update(Request $request, Orangtua $orangtua)
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|required|exists:users,id|unique:orangtua,user_id,' . $orangtua->id,
            'nama_ayah' => 'sometimes|required|string|max:150',
            'nama_ibu' => 'sometimes|required|string|max:150',
            'no_hp' => 'sometimes|required|string|max:15',
            'alamat' => 'nullable|string'
        ]);

        $orangtua->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data orang tua berhasil diperbarui',
            'data' => new OrangtuaResource($orangtua->load('user'))
        ]);
    }

    public function destroy(Orangtua $orangtua)
    {
        $orangtua->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data orang tua berhasil dihapus'
        ]);
    }
}