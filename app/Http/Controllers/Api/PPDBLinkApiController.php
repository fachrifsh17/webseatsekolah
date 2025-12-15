<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PPDBLink;
use Illuminate\Http\Request;

class PpdbLinkApiController extends Controller
{
    public function index()
    {
        return response()->json(PPDBLink::all());
    }

    public function show($id)
    {
        $link = PPDBLink::findOrFail($id);
        return response()->json($link);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'url_link' => 'required|url|max:255',
            'status_ppdb' => 'nullable|in:Buka,Tutup,Segera',
        ]);

        $link = PPDBLink::create($validated);
        return response()->json($link, 201);
    }

    public function update(Request $request, $id)
    {
        $link = PPDBLink::findOrFail($id);

        $validated = $request->validate([
            'url_link' => 'nullable|url|max:255',
            'status_ppdb' => 'nullable|in:Buka,Tutup,Segera',
        ]);

        $link->update($validated);
        return response()->json($link);
    }

    public function destroy($id)
    {
        $link = PPDBLink::findOrFail($id);
        $link->delete();
        return response()->json(['message' => 'PPDB link dihapus']);
    }
}
