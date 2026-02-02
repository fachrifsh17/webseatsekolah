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
            $lastUser = User::where('id', 'like', 'U%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
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

            $lastOrtua = Orangtua::orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')->first();
            $lastOrtuaId = $lastOrtua ? (int) substr($lastOrtua->id, 1) : 0;
            $newOrtuaId = 'O' . str_pad($lastOrtuaId + 1, 3, '0', STR_PAD_LEFT);

            $orangtua = Orangtua::create([
                'id'           => $newOrtuaId,
                'user_id'      => $newUserId,
                'nama_lengkap' => $row['nama_lengkap'],
                'telepon'      => $row['telepon'],
                'is_active'    => 1,
            ]);

            if (!empty($row['nis_anak'])) {
                $nisList = explode(',', $row['nis_anak']);
                foreach ($nisList as $nis) {
                    $siswa = Siswa::where('nis', trim($nis))->first();
                    if ($siswa) {
                        $orangtua->anak()->attach($siswa->id, [
                            'hubungan'   => $row['hubungan'] ?? 'ayah',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            return $orangtua;
        });
    }
}