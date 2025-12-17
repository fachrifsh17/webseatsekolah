<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruMapel;
use App\Models\GuruStaf;
use App\Models\MataPelajaran;
use App\Http\Resources\GuruResource;
use App\Http\Resources\MapelResource;
use Illuminate\Http\Request;

class GuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin');
    }

    public function index(Request $request)
    {
        // Mengembalikan data Model GuruMapel mentah, dimuat dengan relasi guru dan mapel.
        $assignments = GuruMapel::with(['guru', 'mapel'])->get(); 
        
        return response()->json($assignments);
    }
    
    public function getLists()
    {
        $guruList = GuruStaf::all();
        $mapelList = MataPelajaran::all();

        return response()->json([
            'guru_list' => GuruResource::collection($guruList),
            'mapel_list' => MapelResource::collection($mapelList),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'guru_staf_id' => 'required|exists:guru_staf,id', 
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id', 
        ]);

        $isExist = GuruMapel::where('guru_staf_id', $request->guru_staf_id)
                           ->where('mata_pelajaran_id', $request->mata_pelajaran_id)
                           ->exists();

        if ($isExist) {
            return response()->json([
                'message' => 'Penugasan mata pelajaran ini sudah ada.'
            ], 409);
        }

        $assignment = GuruMapel::create([
            'guru_staf_id' => $request->guru_staf_id,
            'mata_pelajaran_id' => $request->mata_pelajaran_id
        ]);
        
        // Mengembalikan data assignment yang baru dibuat dengan relasi yang dimuat.
        return response()->json($assignment->load(['guru', 'mapel']), 201);
    }

    public function destroy($id)
    {
        $assignment = GuruMapel::findOrFail($id);
        $assignment->delete();
        
        return response()->json(null, 204);
    }
}