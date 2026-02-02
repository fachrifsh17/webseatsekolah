<?php

namespace App\Imports;

use App\Models\GuruStaf;
use App\Models\User;
use App\Models\Jurusan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class GuruImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (empty($row['nip']) || empty($row['nama'])) {
            return null;
        }

        $existingGuru = GuruStaf::where('nip', $row['nip'])
            ->when(!empty($row['nuptk']), function ($q) use ($row) {
                return $q->orWhere('nuptk', $row['nuptk']);
            })
            ->first();

        if ($existingGuru) {
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
                'username'  => $row['nip'], 
                'password'  => Hash::make($row['nip']),
                'is_active' => 1,
            ]);

            DB::table('user_roles')->insert([
                'user_id'    => $newUserId,
                'role_id'    => 'R002', 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $jurusan = Jurusan::where('nama_jurusan', $row['jurusan'])->first();

            return new GuruStaf([
                'user_id'            => $newUserId,
                'nip'                => $row['nip'],
                'nuptk'              => $row['nuptk'] ?? null,
                'nama'               => $row['nama'],
                'jabatan_fungsional' => $row['jabatan_fungsional'] ?? null,
                'status_kepegawaian' => $row['status_kepegawaian'] ?? null,
                'jurusan_id'         => $jurusan ? $jurusan->id : null,
                'is_active'          => 1,
                'foto'               => null,
            ]);
        });
    }
}