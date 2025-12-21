<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PPDBLink;
use App\Http\Resources\PPDBLinkResource;
use App\Http\Requests\StorePpdbLinkRequest;
use App\Http\Requests\UpdatePpdbLinkRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Throwable;

class PpdbLinkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $links = PPDBLink::orderBy('id')->get();
        return response()->json(PPDBLinkResource::collection($links));
    }
    
    public function show(PPDBLink $ppdb): JsonResponse
    {
        return response()->json(new PPDBLinkResource($ppdb));
    }

    public function store(StorePpdbLinkRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $link = PPDBLink::create($validated);
            DB::commit();
            return response()->json(new PPDBLinkResource($link), 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat link PPDB'
            ], 500);
        }
    }

    public function update(UpdatePpdbLinkRequest $request, PPDBLink $ppdb): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $ppdb->update($validated);
            DB::commit();
            return response()->json(new PPDBLinkResource($ppdb));
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui link PPDB'
            ], 500);
        }
    }

    public function destroy(PPDBLink $ppdb): JsonResponse
    {
        DB::beginTransaction();
        try {
            $ppdb->delete();
            DB::commit();
            return response()->json(null, 204);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus link PPDB'
            ], 500);
        }
    }
}