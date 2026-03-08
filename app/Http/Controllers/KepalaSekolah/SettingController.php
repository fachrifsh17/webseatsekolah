<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\SekolahSetting;
use App\Models\DataKontak;
use App\Http\Resources\SekolahSettingResource;
use App\Http\Resources\DataKontakResource;
use App\Http\Requests\UpdateSekolahSettingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class SettingController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['updateGeneral']);
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', SekolahSetting::class);

        try {
            $setting = SekolahSetting::firstOrCreate(
                ['id' => 1],
                [
                    'tagline'              => '-',
                    'pesan_selamat_datang' => '-',
                    'buku_poin_path'       => null,
                    'no_wa_kesiswaan'      => '-',
                ]
            );

            $kontak = DataKontak::firstOrCreate(
                ['id' => 1],
                [
                    'alamat_lengkap'  => '-',
                    'telepon'         => '-',
                    'email_resmi'     => '-',
                    'peta_embed_code' => null,
                ]
            );

            return response()->json([
                'success'          => true,
                'general_settings' => new SekolahSettingResource($setting),
                'contact_data'     => new DataKontakResource($kontak),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pengaturan',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateGeneral(UpdateSekolahSettingRequest $request): JsonResponse
    {
        $this->authorize('update', SekolahSetting::class);

        $validated    = $request->validated();
        $newPdfName   = null;
        
        $pathPdf      = public_path('uploads/buku_poin');

        DB::beginTransaction();
        try {
            $setting = SekolahSetting::firstOrCreate(
                ['id' => 1],
                [
                    'tagline'              => '-',
                    'pesan_selamat_datang' => '-',
                    'buku_poin_path'       => null,
                    'no_wa_kesiswaan'      => '-',
                ]
            );

            $oldPdf = $setting->buku_poin_path;

            if ($request->hasFile('buku_poin_path')) {
                $file = $request->file('buku_poin_path');
                $newPdfName = time() . '_' . $file->getClientOriginalName();
                $file->move($pathPdf, $newPdfName);
                $setting->buku_poin_path = $newPdfName;
            }

            $setting->fill([
                'tagline'              => $validated['tagline'] ?? $setting->tagline,
                'pesan_selamat_datang' => $validated['pesan_selamat_datang'] ?? $setting->pesan_selamat_datang,
                'no_wa_kesiswaan'      => $validated['no_wa_kesiswaan'] ?? $setting->no_wa_kesiswaan,
            ]);

            $setting->save();
            
            if ($newPdfName && $oldPdf) {
                $oldPdfPath = $pathPdf . '/' . str_replace('uploads/buku_poin/', '', $oldPdf);
                if (file_exists($oldPdfPath)) unlink($oldPdfPath);
            }

            DB::commit();

            $fresh = $setting->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Pengaturan umum berhasil diperbarui',
                'data'    => [
                    'id'                   => $fresh->id,
                    'tagline'              => $fresh->tagline,
                    'pesan_selamat_datang' => $fresh->pesan_selamat_datang,
                    'buku_poin_url'        => $fresh->buku_poin_path ? asset('uploads/buku_poin/' . $fresh->buku_poin_path) : null,
                    'no_wa_kesiswaan'      => $fresh->no_wa_kesiswaan,
                    'updated_at'           => $fresh->updated_at ? $fresh->updated_at->format('d-m-Y H:i') : null,
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            if ($newPdfName && file_exists($pathPdf . '/' . $newPdfName)) unlink($pathPdf . '/' . $newPdfName);

            Log::error('Failed to update settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pengaturan umum',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}