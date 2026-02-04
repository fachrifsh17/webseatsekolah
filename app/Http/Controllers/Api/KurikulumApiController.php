<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KurikulumResource;
use App\Models\Kurikulum;

class KurikulumApiController extends Controller
{
    public function index()
    {
        $kurikulum = Kurikulum::latest()->get(); 
        return KurikulumResource::collection($kurikulum);
    }

    public function show(Kurikulum $kurikulum)
    {
        return new KurikulumResource($kurikulum);
    }
}
