<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PPDBLink;
use App\Http\Resources\PPDBLinkResource;
use App\Http\Requests\StorePpdbRequest;
use App\Http\Requests\UpdatePpdbRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\JsonResponse;

class PpdbLinkController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        $links = PPDBLink::orderBy('id')->get();
        return PPDBLinkResource::collection($links);
    }
    
    public function show(PPDBLink $ppdb)
    {
        return new PPDBLinkResource($ppdb);
    }

    public function store(StorePpdbRequest $request)
    {
        $link = PPDBLink::create($request->validated());

        return new PPDBLinkResource($link);
    }

    public function update(UpdatePpdbRequest $request, PPDBLink $ppdb)
    {
        $ppdb->update($request->validated());

        return new PPDBLinkResource($ppdb);
    }

    public function destroy(PPDBLink $ppdb): JsonResponse
    {
        $ppdb->delete();

        return response()->json(null, 204);
    }
}