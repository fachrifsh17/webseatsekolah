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
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Throwable;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['updateGeneral', 'updateKontak']);
    }

    public function index(): JsonResponse
    {
        $setting = SekolahSetting::find(1);
        $kontak = DataKontak::find(1);

        return response()->json([
            'general_settings' => $setting ? new SekolahSettingResource($setting) : null,
            'contact_data'     => $kontak ? new DataKontakResource($kontak) : null,
        ]);
    }

    public function updateGeneral(UpdateGeneralSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $newLogoPath = null;

        DB::beginTransaction();
        try {
            $setting = SekolahSetting::find(1);

            if ($request->hasFile('logo')) {
                $newLogoPath = $request->file('logo')->store('uploads/logo', 'public');
                if ($newLogoPath) {
                    $validated['logo'] = $newLogoPath;
                }
            }

            if ($setting) {
                $oldLogo = $setting->logo;
                $setting->fill($validated);
                $setting->save();

                if (!empty($newLogoPath) && $oldLogo && $oldLogo !== $newLogoPath) {
                    Storage::disk('public')->delete($oldLogo);
                }
            } else {
                $setting = new SekolahSetting($validated);
                $setting->id = 1;
                $setting->save();
            }

            DB::commit();
            return response()->json(new SekolahSettingResource($setting->fresh()));
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($newLogoPath)) {
                Storage::disk('public')->delete($newLogoPath);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pengaturan umum'
            ], 500);
        }
    }

    public function updateKontak(UpdateKontakRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $kontak = DataKontak::find(1);

            if ($kontak) {
                $kontak->fill($validated);
                $kontak->save();
            } else {
                $kontak = new DataKontak($validated);
                $kontak->id = 1;
                $kontak->save();
            }

            DB::commit();
            return response()->json(new DataKontakResource($kontak->fresh()));
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data kontak'
            ], 500);
        }
    }
}