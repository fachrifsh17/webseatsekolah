<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tingkatan;
use App\Http\Requests\StoreTingkatanRequest;
use App\Http\Requests\UpdateTingkatanRequest;
use App\Http\Resources\TingkatanResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TingkatanController extends Controller
{
    public function index()
    {
        $tingkatan = Tingkatan::all();
        return TingkatanResource::collection($tingkatan);
    }

    public function store(StoreTingkatanRequest $request)
    {
        $tingkatan = Tingkatan::create($request->validated());
        return (new TingkatanResource($tingkatan))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Tingkatan $tingkatan)
    {
        return new TingkatanResource($tingkatan);
    }

    public function update(UpdateTingkatanRequest $request, Tingkatan $tingkatan)
    {
        $tingkatan->update($request->validated());
        return new TingkatanResource($tingkatan);
    }

    public function destroy($id)
    {
        $tingkatan = Tingkatan::findOrFail($id);

        if ($tingkatan->kelas()->exists()) {
            return response()->json([
                'message' => 'Tingkatan tidak dapat dihapus karena masih digunakan oleh data kelas.'
            ], Response::HTTP_CONFLICT);
        }

        $tingkatan->delete();
        return response()->json(['message' => 'Tingkatan berhasil dihapus'], Response::HTTP_OK);
    }
}