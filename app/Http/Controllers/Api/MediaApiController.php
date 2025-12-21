<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class MediaApiController extends Controller
{
    public function index()
    {
        $media = Media::with('album')->orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $media], Response::HTTP_OK);
    }

    public function show($id)
    {
        $media = Media::with('album')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $media], Response::HTTP_OK);
    }
}