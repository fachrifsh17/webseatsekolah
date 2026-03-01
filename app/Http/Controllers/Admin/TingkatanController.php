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
        $this->authorize('viewAny', Tingkatan::class);
        $tingkatan = Tingkatan::all();
        return TingkatanResource::collection($tingkatan);
    }

    public function store(StoreTingkatanRequest $request)
    {
        $this->authorize('create', Tingkatan::class);
        // Data 'id' otomatis diisi oleh database (auto-increment)
        $tingkatan = Tingkatan::create($request->validated());
        return (new TingkatanResource($tingkatan))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Tingkatan $tingkatan)
    {
        $this->authorize('view', $tingkatan);
        return new TingkatanResource($tingkatan);
    }

    public function update(UpdateTingkatanRequest $request, Tingkatan $tingkatan)
    {
        $this->authorize('update', $tingkatan);
        $tingkatan->update($request->validated());
        return new TingkatanResource($tingkatan);
    }

    // --- PENYESUAIAN DI SINI ---
    // Laravel otomatis mengubah $id (string dari request) menjadi integer 
    // karena tipe data kolom id di model Tingkatan sudah diset ke 'int'
    public function destroy($id)
    {
        $tingkatan = Tingkatan::findOrFail($id);
        $this->authorize('delete', $tingkatan);

        // Pengecekan relasi
        if ($tingkatan->kelas()->exists()) {
            return response()->json([
                'message' => 'Tingkatan tidak dapat dihapus karena masih digunakan oleh data kelas.'
            ], Response::HTTP_CONFLICT);
        }

        $tingkatan->delete();
        return response()->json(['message' => 'Tingkatan berhasil dihapus'], Response::HTTP_OK);
    }
}