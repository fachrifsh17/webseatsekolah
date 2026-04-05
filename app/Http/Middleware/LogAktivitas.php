<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\LogAktivitas as LogAktivitasModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LogAktivitas
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if ($response->isSuccessful()
            && Auth::check()
            && in_array($request->method(), $methods)) {

            $user = Auth::user();
            $roles = $user->roles()->pluck('role_name')->toArray();
            $roleLabel = !empty($roles) ? implode(', ', $roles) : 'User';
            
            // 1. Membersihkan Nama Module agar lebih manusiawi
            // Contoh: "admin.kurikulum.update" menjadi "Kurikulum"
            $routeName = $request->route()?->getName() ?? $request->path();
            $moduleName = $this->formatModuleName($routeName);

            // 2. Mapping Kata Kerja yang lebih rapi
            $verbMap = [
                'POST'   => 'menambahkan data baru pada',
                'PUT'    => 'memperbarui data pada',
                'PATCH'  => 'mengubah data pada',
                'DELETE' => 'menghapus data dari',
            ];

            $aksiManusiawi = $verbMap[$request->method()] ?? $request->method();

            try {
                LogAktivitasModel::create([
                    'user_id'    => (string) $user->id,
                    // Hasil: Admin [adminsekolah] memperbarui data pada modul Kurikulum
                    'aksi'       => "{$roleLabel} [{$user->username}] {$aksiManusiawi} modul {$moduleName}",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Exception $e) {
                Log::error("Log Error: " . $e->getMessage());
            }
        }

        return $response;
    }

    /**
     * Fungsi pembantu untuk merapikan nama route/module
     */
    private function formatModuleName($routeName)
    {
        // Pisahkan berdasarkan titik (misal: admin.kurikulum.update)
        $parts = explode('.', $routeName);
        
        if (count($parts) > 1) {
            // Ambil kata tengahnya (biasanya nama modulnya)
            // Misal: kurikulum, siswa, guru
            $name = $parts[count($parts) - 2]; 
        } else {
            $name = $routeName;
        }

        // Ubah jadi huruf kapital di awal (Siswa, Kurikulum, Orang Tua)
        return Str::title(str_replace(['-', '_'], ' ', $name));
    }
}