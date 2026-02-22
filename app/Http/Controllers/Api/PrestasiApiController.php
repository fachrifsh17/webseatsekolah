<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use App\Http\Resources\PrestasiResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrestasiApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Prestasi::query();

        // Fitur Filter: ?kategori=Siswa
        if ($request->has('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        // Fitur Cari: ?search=voli
        if ($request->has('search')) {
            $query->where('judul', 'like', '%' . $request->search . '%');
        }

        $data = $query->orderBy('tahun', 'desc')
                      ->orderBy('created_at', 'desc')
                      ->paginate(10);

        return PrestasiResource::collection($data)->additional([
            'success' => true
        ]);
    }

    public function show($id)
    {
        $prestasi = Prestasi::findOrFail($id);
        
        return (new PrestasiResource($prestasi))->additional([
            'success' => true
        ]);
    }
}