<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SekolahSetting;
use App\Models\DataKontak;
use App\Http\Resources\SekolahSettingResource;
use App\Http\Resources\DataKontakResource;
use App\Http\Requests\UpdateGeneralSettingRequest;
use App\Http\Requests\UpdateKontakRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['updateGeneral', 'updateKontak']),
        ];
    }

    public function index(): JsonResponse
    {
        $setting = SekolahSetting::find(1);
        $kontak = DataKontak::find(1);
        
        return response()->json([
            'general_settings' => $setting ? new SekolahSettingResource($setting) : null,
            'contact_data' => $kontak ? new DataKontakResource($kontak) : null,
        ]);
    }

    public function updateGeneral(UpdateGeneralSettingRequest $request): SekolahSettingResource
    {
        $validated = $request->validated();
        $setting = SekolahSetting::firstOrNew(['id' => 1]);

        if ($request->hasFile('logo')) {
            if ($setting->logo) {
                Storage::disk('public')->delete($setting->logo);
            }
            $validated['logo'] = $request->file('logo')->store('uploads/logo', 'public');
        }

        $setting->fill($validated);
        $setting->id = 1; 
        $setting->save();
        
        return new SekolahSettingResource($setting);
    }

    public function updateKontak(UpdateKontakRequest $request): DataKontakResource
    {
        $kontak = DataKontak::firstOrNew(['id' => 1]);
        
        $kontak->fill($request->validated());
        $kontak->id = 1; 
        $kontak->save();
        
        return new DataKontakResource($kontak);
    }
}