<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\SekolahSetting;
use App\Models\DataKontak;
use App\Http\Resources\SekolahSettingResource;
use App\Http\Resources\DataKontakResource;
use App\Http\Requests\UpdateSekolahSettingRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('Log.admin')->only(('updateGeneral'));
    }

    public function index(): JsonResponse
    {
        try {
            $setting = SekolahSetting::firstOrCreate(
                ['id' => 1],
                [
                    'tagline'              => '-',
                    'logo'                 => null,
                    'pesan_selamat_datang' => '-',
                    'buku_poin_path'       => null,
                    'no_wa_kesiswaan'      => '-',
                ]
            );

            $kontak = DataKontak::firstOrCreate(
                ['id' => 1],
                [
                    'alamat_lengkap' => '-',
                    'telepon'        => '-',
                    'email_resmi'    => '-',
                    'peta_embed_code'=> null,
                ]
            );

            return response()->json([
                'success'          => true,
                'general_settings' => new SekolahSettingResource($setting),
                'contact_data'     => new DataKontakResource($kontak),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kepsek Settings Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pengaturan',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateGeneral(UpdateSekolahSettingRequest $request): JsonResponse
    {
        $validated    = $request->validated();
        $newLogoPath  = null;
        $newPdfPath   = null;

        DB::beginTransaction();
        try {
            $setting = SekolahSetting::firstOrCreate(['id' => 1]);

            if ($request->hasFile('logo')) {
                $newLogoPath = $request->file('logo')->store('uploads/logo', 'public');
                if ($setting->logo) {
                    Storage::disk('public')->delete($setting->logo);
                }
                $setting->logo = $newLogoPath;
            }

            if ($request->hasFile('buku_poin_path')) {
                $newPdfPath = $request->file('buku_poin_path')->store('uploads/buku_poin', 'public');
                if ($setting->buku_poin_path) {
                    Storage::disk('public')->delete($setting->buku_poin_path);
                }
                $setting->buku_poin_path = $newPdfPath;
            }

            $setting->fill([
                'tagline'              => $validated['tagline'] ?? $setting->tagline,
                'pesan_selamat_datang' => $validated['pesan_selamat_datang'] ?? $setting->pesan_selamat_datang,
                'no_wa_kesiswaan'      => $validated['no_wa_kesiswaan'] ?? $setting->no_wa_kesiswaan,
            ]);

            $setting->save();

            DB::commit();

            $fresh = $setting->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Pengaturan sekolah berhasil diperbarui oleh Kepala Sekolah',
                'data'    => [
                    'id'                   => $fresh->id,
                    'tagline'              => $fresh->tagline,
                    'logo_url'             => $fresh->logo ? Storage::url($fresh->logo) : null,
                    'pesan_selamat_datang' => $fresh->pesan_selamat_datang,
                    'buku_poin_url'        => $fresh->buku_poin_path ? Storage::url($fresh->buku_poin_path) : null,
                    'no_wa_kesiswaan'      => $fresh->no_wa_kesiswaan,
                    'updated_at'           => $fresh->updated_at ? $fresh->updated_at->format('d-m-Y H:i') : null,
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            if ($newLogoPath) Storage::disk('public')->delete($newLogoPath);
            if ($newPdfPath) Storage::disk('public')->delete($newPdfPath);

            Log::error('Kepsek Update Settings Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pengaturan sekolah',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}