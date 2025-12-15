<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PortalSosmed;
use Illuminate\Http\Request;

class PortalApiController extends Controller
{
    public function index()
    {
        return response()->json(PortalSosmed::orderBy('nama_platform')->get());
    }

    public function show($id)
    {
        $portal = PortalSosmed::findOrFail($id);
        return response()->json($portal);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_platform' => 'required|string|max:100',
            'url_link' => 'nullable|url|max:255',
            'tipe' => 'nullable|in:Sosial Media,Portal Khusus',
        ]);

        $portal = PortalSosmed::create($validated);
        return response()->json($portal, 201);
    }

    public function update(Request $request, $id)
    {
        $portal = PortalSosmed::findOrFail($id);

        $validated = $request->validate([
            'nama_platform' => 'sometimes|required|string|max:100',
            'url_link' => 'nullable|url|max:255',
            'tipe' => 'nullable|in:Sosial Media,Portal Khusus',
        ]);

        $portal->update($validated);
        return response()->json($portal);
    }

    public function destroy($id)
    {
        $portal = PortalSosmed::findOrFail($id);
        $portal->delete();
        return response()->json(['message' => 'Portal/Media sosial dihapus']);
    }
}
