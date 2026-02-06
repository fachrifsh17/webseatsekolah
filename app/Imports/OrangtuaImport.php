<?php

namespace App\Imports;

use App\Models\Orangtua;
use App\Models\User;
use App\Models\Siswa;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class OrangtuaImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (empty($row['nama_lengkap']) || empty($row['telepon'])) {
            return null;
        }

        $existingOrangtua = Orangtua::where('telepon', $row['telepon'])->first();

        if ($existingOrangtua) {
            return null;
        }

        return DB::transaction(function () use ($row) {
            // 1. Generate ID User (Uxxx)
            $lastUser = User::where('id', 'like', 'U%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();
                
            $lastId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
            $newUserId = 'U' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

            User::create([
                'id'        => $newUserId,
                'username'  => $row['telepon'], 
                'password'  => Hash::make($row['telepon']),
                'is_active' => 1,
            ]);

            DB::table('user_roles')->insert([
                'user_id'    => $newUserId,
                'role_id'    => 'R004', 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Generate ID Orangtua (Oxxx)
            $lastOrtua = Orangtua::where('id', 'like', 'O%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();

            $lastOrtuaId = $lastOrtua ? (int) substr($lastOrtua->id, 2) : 0; // Mengambil angka setelah 'O'
            $newOrtuaId = 'O' . str_pad($lastOrtuaId + 1, 3, '0', STR_PAD_LEFT);

            $orangtua = Orangtua::create([
                'id'           => $newOrtuaId,
                'user_id'      => $newUserId,
                'nama_lengkap' => $row['nama_lengkap'],
                'telepon'      => $row['telepon'],
                'is_active'    => 1,
            ]);

            // 3. Hubungkan ke Anak (Tabel Pivot orangtua_siswa)
            if (!empty($row['nis_anak'])) {
                // Mendukung input NIS lebih dari satu (dipisah koma)
                $nisList = explode(',', $row['nis_anak']);
                foreach ($nisList as $nis) {
                    $siswa = Siswa::where('nis', trim($nis))->first();
                    if ($siswa) {
                        // Cek gambar: tabel pivot Anda menggunakan kolom 'orangtua_id' dan 'siswa_id'
                        DB::table('orangtua_siswa')->insert([
                            'orangtua_id' => $newOrtuaId,
                            'siswa_id'    => $siswa->id, // Asumsi ID Siswa adalah Sxxx
                            'hubungan'    => strtolower($row['hubungan'] ?? 'ayah'),
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                    }
                }
            }

            return $orangtua;
        });
    }
}