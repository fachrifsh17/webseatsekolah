<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PresensiGuruMapel;
use App\Http\Requests\StorePresensiGuruMapelRequest;
use App\Http\Requests\UpdatePresensiGuruMapelRequest;
use App\Http\Resources\PresensiGuruMapelResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PresensiGuruMapelController extends Controller
{
    public function index()
    {
        // Gunakan query builder agar bisa difilter
        $query = PresensiGuruMapel::with(['rincianSiswa.siswa', 'mapel', 'kelas', 'guru'])
                 ->latest();

        // LOGIKA RIWAYAT: Jika yang login adalah Guru, hanya tampilkan data miliknya sendiri
        if (Auth::user()->role === 'Guru') {
            $query->where('guru_id', Auth::id());
        }

        // Jika Admin, dia bisa melihat semua (tanpa filter where di atas)
        return PresensiGuruMapelResource::collection($query->paginate(20));
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $header = PresensiGuruMapel::create([
                'guru_id'           => Auth::id(), // Otomatis ambil ID guru yang login
                'kelas_id'          => $request->kelas_id,
                'mata_pelajaran_id' => $request->mata_pelajaran_id,
                'tanggal'           => $request->tanggal,
                'jam_masuk'         => $request->jam_masuk,
                'jam_keluar'        => $request->jam_keluar,
                'materi'            => $request->materi,
            ]);

            foreach ($request->presensi as $item) {
                $header->rincianSiswa()->create([
                    'siswa_id' => $item['siswa_id'],
                    'status'   => $item['status'],
                    'catatan'  => $item['catatan'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil disimpan',
                'data'    => new PresensiGuruMapelResource($header->load('rincianSiswa'))
            ], 201);
        });
    }

    public function show($id): JsonResponse
    {
        $data = PresensiGuruMapel::with(['rincianSiswa.siswa', 'mapel', 'kelas', 'guru'])->findOrFail($id);
        
        // Proteksi: Guru tidak boleh melihat detail riwayat guru lain via URL
        if (Auth::user()->role === 'Guru' && $data->guru_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(new PresensiGuruMapelResource($data));
    }

    public function update(UpdatePresensiGuruMapelRequest $request, $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $header = PresensiGuruMapel::findOrFail($id);
            
            $header->update($request->only([
                'kelas_id', 
                'mata_pelajaran_id', 
                'tanggal', 
                'jam_masuk', 
                'jam_keluar', 
                'materi'
            ]));

            if ($request->has('presensi')) {
                foreach ($request->presensi as $item) {
                    $header->rincianSiswa()->updateOrCreate(
                        ['siswa_id' => $item['siswa_id']],
                        [
                            'status'  => $item['status'], 
                            'catatan' => $item['catatan'] ?? null
                        ]
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil diperbarui',
                'data'    => new PresensiGuruMapelResource($header->load('rincianSiswa'))
            ]);
        });
    }

    public function destroy($id): JsonResponse
    {
        $data = PresensiGuruMapel::findOrFail($id);
        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus'
        ], 200);
    }
}