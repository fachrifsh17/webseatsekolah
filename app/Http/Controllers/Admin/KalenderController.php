<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use App\Http\Resources\KalenderAkademikResource;
use App\Http\Requests\StoreKalenderAkademikRequest;
use App\Http\Requests\UpdateKalenderAkademikRequest;
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

    public function store(StoreKalenderAkademikRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $item = DB::transaction(fn () => KalenderAkademik::create($validated));

        return response()->json(new KalenderAkademikResource($item), 201);
    }

    public function update(UpdateKalenderAkademikRequest $request, KalenderAkademik $kalender): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(fn () => $kalender->update($validated));

        return response()->json(new KalenderAkademikResource($kalender));
    }

    public function destroy(KalenderAkademik $kalender): JsonResponse
    {
        DB::transaction(fn () => $kalender->delete());

        return response()->json([
            'success'      => true,
            'message'      => 'Data kalender akademik berhasil dihapus',
            'notification' => 'Berhasil dihapus'
        ], 200);
    }
}
