<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PPDBLink;
use App\Http\Resources\PPDBLinkResource;
use App\Http\Requests\StorePpdbLinkRequest;
use App\Http\Requests\UpdatePpdbLinkRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

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
        return new JsonResponse(PPDBLinkResource::collection($links));
    }
    
    public function show(PPDBLink $ppdb): JsonResponse
    {
        return new JsonResponse(new PPDBLinkResource($ppdb));
    }

    public function store(StorePpdbLinkRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $link = DB::transaction(function () use ($validated) {
            return PPDBLink::create($validated);
        });

        return new JsonResponse(new PPDBLinkResource($link), 201);
    }

    public function update(UpdatePpdbLinkRequest $request, PPDBLink $ppdb): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($ppdb, $validated) {
            $ppdb->update($validated);
        });

        return new JsonResponse(new PPDBLinkResource($ppdb));
    }

    public function destroy(PPDBLink $ppdb): JsonResponse
    {
        DB::transaction(function () use ($ppdb) {
            $ppdb->delete();
        });

        return new JsonResponse(null, 204);
    }
}