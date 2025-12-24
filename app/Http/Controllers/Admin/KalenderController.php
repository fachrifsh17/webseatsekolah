<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use App\Http\Resources\KalenderAkademikResource;
use App\Http\Requests\StoreKalenderRequest;
use App\Http\Requests\UpdateKalenderRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class KalenderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = KalenderAkademik::orderBy('tanggal_mulai')->paginate(12);
        return response()->json(KalenderAkademikResource::collection($data));
    }
    
    public function show(KalenderAkademik $kalender): JsonResponse
    {
        return response()->json(new KalenderAkademikResource($kalender));
    }

    public function store(StoreKalenderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        $item = KalenderAkademik::create($validated);
        DB::commit();

        return response()->json(new KalenderAkademikResource($item), 201);
    }

    public function update(UpdateKalenderRequest $request, KalenderAkademik $kalender): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        $kalender->update($validated);
        DB::commit();

        return response()->json(new KalenderAkademikResource($kalender));
    }

    public function destroy(KalenderAkademik $kalender): JsonResponse
    {
        DB::beginTransaction();
        $kalender->delete();
        DB::commit();

        return response()->json(null, 204);
    }
}