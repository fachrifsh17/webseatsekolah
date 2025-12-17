<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SekolahSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SettingApiController extends Controller
{
    public function index()
    {
        $data = SekolahSetting::all();
        // Mengembalikan data, status default 200 OK
        return response()->json($data, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(SekolahSetting $setting)
    {
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json($setting, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tagline' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
            'pesan_selamat_datang' => 'nullable|string',
        ]);

        $s = SekolahSetting::create($validated);
        return response()->json($s, Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, SekolahSetting $setting)
    {
        $validated = $request->validate([
            'tagline' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
            'pesan_selamat_datang' => 'nullable|string',
        ]);

        $setting->update($validated);
        return response()->json($setting, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(SekolahSetting $setting)
    {
        $setting->delete();
        return response()->json(['message' => 'Setting sekolah berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}