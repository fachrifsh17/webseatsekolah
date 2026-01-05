<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PpdbLink;
use App\Http\Resources\PpdbLinkResource;
use App\Http\Requests\UpdatePpdbLinkRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class PpdbLinkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:admin');
        $this->middleware('log.admin')->only(['update']);
    }

    public function index(): JsonResponse
    {
        $link = PPDBLink::first();

        if (!$link) {
            return new JsonResponse([
                'message' => 'Data PPDB belum tersedia'
            ], 404);
        }

        return new JsonResponse(new PpdbLinkResource($link));
    }

    public function update(UpdatePpdbLinkRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $link = DB::transaction(function () use ($validated) {
            return PPDBLink::updateOrCreate(
                ['id' => 1],
                $validated
            );
        });

        return new JsonResponse(new PpdbLinkResource($link));
    }
}
