<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Support\Facades\Validator;

class BannerApiController extends Controller
{
    public function index()
    {
        $banners = Banner::whereNull('aktif_sampai')
            ->orWhere('aktif_sampai', '>=', now())
            ->orderBy('id','desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'judul' => 'required|string|max:255',
            'url_link' => 'nullable|url|max:255',
            'aktif_sampai' => 'nullable|date',
            'foto' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $banner = Banner::create($v->validated());

        return response()->json([
            'success' => true,
            'data' => $banner
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::find($id);

        if (! $banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner tidak ditemukan'
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'judul' => 'sometimes|required|string|max:255',
            'url_link' => 'nullable|url|max:255',
            'aktif_sampai' => 'nullable|date',
            'foto' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $banner->update($v->validated());

        return response()->json([
            'success' => true,
            'data' => $banner
        ]);
    }

    public function destroy($id)
    {
        $banner = Banner::find($id);

        if (! $banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner tidak ditemukan'
            ], 404);
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner dihapus'
        ]);
    }
}
