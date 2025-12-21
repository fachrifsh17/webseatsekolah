<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Http\Resources\BannerResource;
use App\Http\Requests\StoreBannerRequest;
use App\Http\Requests\UpdateBannerRequest;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index()
    {
        $data = Banner::orderByDesc('aktif_sampai')->paginate(12);
        return BannerResource::collection($data);
    }

    public function show(Banner $banner)
    {
        return new BannerResource($banner);
    }

    public function store(StoreBannerRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/banner', 'public');
        }

        $banner = Banner::create($validated);

        return new BannerResource($banner);
    }

    public function update(UpdateBannerRequest $request, Banner $banner)
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            if ($banner->foto) {
                Storage::disk('public')->delete($banner->foto);
            }
            $validated['foto'] = $request->file('foto')->store('uploads/banner', 'public');
        }

        $banner->update($validated);

        return new BannerResource($banner);
    }

    public function destroy(Banner $banner)
    {
        if ($banner->foto) {
            Storage::disk('public')->delete($banner->foto);
        }

        $banner->delete();

        return response()->noContent();
    }
}