<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SekolahSetting;
use App\Models\DataKontak;
use App\Http\Resources\SekolahSettingResource;
use App\Http\Resources\DataKontakResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin'); 
    }

    /**
     * Mengambil semua data setting dan kontak dalam satu respons.
     */
    public function index()
    {
        // Ambil setting dan kontak pertama (asumsi single-row, id=1)
        $setting = SekolahSetting::firstOrNew(['id' => 1]);
        $kontak = DataKontak::firstOrNew(['id' => 1]);
        
        // Cek apakah ada data yang sudah tersimpan sebelum membuat Resource
        $settingResource = $setting->exists ? new SekolahSettingResource($setting) : null;
        $kontakResource = $kontak->exists ? new DataKontakResource($kontak) : null;
        
        return response()->json([
            'general_settings' => $settingResource,
            'contact_data' => $kontakResource,
        ]);
    }

    /**
     * Memperbarui setting umum (SekolahSetting).
     */
    public function updateGeneral(Request $request)
    {
        $validated = $request->validate([
            'tagline' => 'nullable|string|max:255',
            'pesan_selamat_datang' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
        ]);
        
        $setting = SekolahSetting::firstOrNew(['id' => 1]);

        if ($request->hasFile('logo')) {
            if ($setting->logo) {
                Storage::disk('public')->delete($setting->logo);
            }
            $validated['logo'] = $request->file('logo')->store('uploads/logo','public');
        } else {
            // Hilangkan 'logo' dari validated jika tidak di-upload agar tidak menimpa path lama
            unset($validated['logo']);
        }

        $setting->fill($validated);
        $setting->id = 1; 
        $setting->save();
        
        return new SekolahSettingResource($setting);
    }

    /**
     * Memperbarui data kontak (DataKontak).
     */
    public function updateKontak(Request $request)
    {
        $validated = $request->validate([
            'alamat_lengkap' => 'nullable|string',
            'telepon' => 'nullable|string|max:20',
            'email_resmi' => 'nullable|email|max:100',
            'peta_embed_code' => 'nullable|string',
        ]);
        
        $kontak = DataKontak::firstOrNew(['id' => 1]);
        
        $kontak->fill($validated);
        $kontak->id = 1; 
        $kontak->save();
        
        return new DataKontakResource($kontak);
    }
}