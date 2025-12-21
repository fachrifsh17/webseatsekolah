<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruMapel;
use App\Models\GuruStaf;
use App\Models\MataPelajaran;
use App\Http\Resources\GuruResource;
use App\Http\Resources\MapelResource;
use App\Http\Resources\GuruMapelResource;
use App\Http\Requests\StoreGuruMapelRequest;
use Illuminate\Http\JsonResponse;

class GuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $assignments = GuruMapel::with(['guru', 'mapel'])->get();
        return response()->json(GuruMapelResource::collection($assignments));
    }

    public function getLists(): JsonResponse
    {
        return response()->json([
            'guru_list' => GuruResource::collection(GuruStaf::all()),
            'mapel_list' => MapelResource::collection(MataPelajaran::all()),
        ]);
    }

    public function store(StoreGuruMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $assignment = GuruMapel::firstOrCreate(
            [
                'guru_staf_id' => $validated['guru_staf_id'],
                'mata_pelajaran_id' => $validated['mata_pelajaran_id'],
            ],
            []
        );

        if (! $assignment->wasRecentlyCreated) {
            return response()->json([
                'message' => 'Penugasan mata pelajaran ini sudah ada.'
            ], 409);
        }

        return response()->json(new GuruMapelResource($assignment->load(['guru', 'mapel'])), 201);
    }

    public function destroy(GuruMapel $guruMapel): JsonResponse
    {
        $guruMapel->delete();
        return response()->json(null, 204);
    }
}