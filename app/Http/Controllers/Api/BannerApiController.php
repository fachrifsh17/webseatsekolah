<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Symfony\Component\HttpFoundation\Response;

class BannerApiController extends Controller
{
    public function index()
    {
        $banners = Banner::whereNull('aktif_sampai')
            ->orWhere('aktif_sampai', '>=', now())
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $banners], Response::HTTP_OK);
    }
}