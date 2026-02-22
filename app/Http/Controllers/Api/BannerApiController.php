<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Http\Resources\BannerResource; // Panggil Resource
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BannerApiController extends Controller
{
    public function index(): JsonResponse
    {
        // Logika filter banner yang masih aktif sudah sangat bagus!
        $banners = Banner::where(function ($query) {
                $query->whereNull('aktif_sampai')
                      ->orWhere('aktif_sampai', '>=', now());
            })
            ->orderByDesc('created_at')
            ->get();

        // Bungkus dengan BannerResource::collection
        return response()->json([
            'success' => true,
            'data'    => BannerResource::collection($banners),
        ], Response::HTTP_OK);
    }
}