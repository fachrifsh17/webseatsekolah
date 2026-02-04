<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BannerApiController extends Controller
{
    public function index(): JsonResponse
    {
        $banners = Banner::where(function ($query) {
                $query->whereNull('aktif_sampai')
                      ->orWhere('aktif_sampai', '>=', now());
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $banners,
        ], Response::HTTP_OK);
    }
}
