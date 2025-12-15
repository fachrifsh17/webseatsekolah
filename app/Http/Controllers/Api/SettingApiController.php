<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SekolahSetting;
use Illuminate\Http\Request;

class SettingApiController extends Controller
{
    public function index()
    {
        return response()->json(SekolahSetting::all());
    }

    public function show($id)
    {
        $s = SekolahSetting::findOrFail($id);
        return response()->json($s);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tagline' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
            'pesan_selamat_datang' => 'nullable|string',
        ]);

        $s = SekolahSetting::create($validated);
        return response()->json($s, 201);
    }

    public function update(Request $request, $id)
    {
        $s = SekolahSetting::findOrFail($id);

        $validated = $request->validate([
            'tagline' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
            'pesan_selamat_datang' => 'nullable|string',
        ]);

        $s->update($validated);
        return response()->json($s);
    }

    public function destroy($id)
    {
        $s = SekolahSetting::findOrFail($id);
        $s->delete();
        return response()->json(['message' => 'Setting sekolah dihapus']);
    }
}
