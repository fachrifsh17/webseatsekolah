<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PresensiGuruMapel;
use App\Models\GuruMapel;
use App\Http\Requests\StorePresensiGuruMapelRequest;
use App\Http\Requests\UpdatePresensiGuruMapelRequest;
use App\Http\Resources\PresensiGuruMapelResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use BadMethodCallException;
use Throwable;

class PresensiGuruMapelController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:admin')->only(['destroy']);
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    protected function isAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin') || $user->hasRole('Admin');
        }

        return isset($user->role_id) && (int) $user->role_id === 2;
    }

    protected function applyGuruMapelOwnershipQuery($query, $user)
    {
        $applied = false;

        try {
            $query->where(function ($q) use ($user, &$applied) {
                try {
                    $q->whereHas('guruStaf', fn($r) => $r->where('user_id', $user->id));
                    $applied = true;
                } catch (BadMethodCallException $e) {
                    //
                }

                try {
                    $q->orWhereHas('guru', fn($r) => $r->where('user_id', $user->id));
                    $applied = true;
                } catch (BadMethodCallException $e) {
                    //
                }

                if (! $applied) {
                    $candidates = [
                        'guru_staf',
                        'guru_staf_id',
                        'id_guru_staff',
                        'guru_id',
                        'guru_staff_id',
                        'staf'
                    ];

                    foreach ($candidates as $col) {
                        if (Schema::hasColumn('guru_mapel', $col)) {
                            $q->orWhere($col, $user->guru?->id ?? $user->id);
                            $applied = true;
                            break;
                        }
                    }
                }
            });
        } catch (Throwable $e) {
            Log::warning('applyGuruMapelOwnershipQuery failed', ['error' => $e->getMessage()]);
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);

        $user = $request->user();

        $query = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa',
            'guruMapel',
            'mataPelajaran',
            'kelas'
        ]);

        if (! $this->isAdmin($user)) {
            $query->whereHas('guruMapel', fn($q) => $this->applyGuruMapelOwnershipQuery($q, $user));
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return PresensiGuruMapelResource::collection(
            $query->latest()->paginate($perPage)
        )->response();
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $this->authorize('create', PresensiGuruMapel::class);

        $user = $request->user();
        $guruMapelId = $request->input('guru_mapel_id');

        if ($this->isAdmin($user)) {
            if (empty($guruMapelId) && $request->filled('mata_pelajaran_id')) {
                $relasi = GuruMapel::where('mata_pelajaran_id', $request->input('mata_pelajaran_id'))->first();
                $guruMapelId = $relasi->id ?? null;
            }
        } else {
            if (! $request->filled('mata_pelajaran_id') && ! $request->filled('guru_mapel_id')) {
                return response()->json(['message' => 'Field mata_pelajaran_id atau guru_mapel_id diperlukan untuk guru.'], 422);
            }

            if ($request->filled('guru_mapel_id')) {
                $relasiQuery = GuruMapel::where('id', $request->input('guru_mapel_id'));
            } else {
                $relasiQuery = GuruMapel::where('mata_pelajaran_id', $request->input('mata_pelajaran_id'));
            }

            $this->applyGuruMapelOwnershipQuery($relasiQuery, $user);

            $relasi = $relasiQuery->first();

            if (! $relasi) {
                return response()->json(['message' => 'Akses ditolak. Anda bukan guru mapel untuk mata pelajaran/kelas ini.'], 403);
            }

            $guruMapelId = $relasi->id;
        }

        if (empty($guruMapelId)) {
            return response()->json(['message' => 'Field guru_mapel_id tidak boleh kosong.'], 422);
        }

        $presensiItems = $request->input('presensi', []);
        if (! is_array($presensiItems)) {
            return response()->json(['message' => 'Format presensi tidak valid.'], 422);
        }

        try {
            $presensi = DB::transaction(function () use ($request, $guruMapelId, $presensiItems) {
                $header = PresensiGuruMapel::create([
                    'guru_mapel_id'     => $guruMapelId,
                    'kelas_id'          => $request->input('kelas_id'),
                    'mata_pelajaran_id' => $request->input('mata_pelajaran_id'),
                    'tanggal'           => $request->input('tanggal'),
                    'jam_masuk'         => $request->input('jam_masuk'),
                    'jam_keluar'        => $request->input('jam_keluar'),
                    'materi'            => $request->input('materi'),
                ]);

                collect($presensiItems)->each(function ($item) use ($header) {
                    if (empty($item['siswa_id']) || ! isset($item['status'])) {
                        return;
                    }

                    $header->presensiSiswaDetail()->create([
                        'siswa_id' => $item['siswa_id'],
                        'status'   => $item['status'],
                        'catatan'  => $item['catatan'] ?? null,
                    ]);
                });

                return $header->load('presensiSiswaDetail.siswa', 'guruMapel', 'mataPelajaran', 'kelas');
            });

            return (new PresensiGuruMapelResource($presensi))
                ->response()
                ->setStatusCode(201);

        } catch (Throwable $e) {
            Log::error('Presensi Store Error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);
            return response()->json(['message' => 'Gagal menyimpan data.'], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa',
            'guruMapel',
            'mataPelajaran',
            'kelas'
        ])->findOrFail($id);

        $this->authorize('view', $presensi);

        return (new PresensiGuruMapelResource($presensi))->response();
    }

    public function update(UpdatePresensiGuruMapelRequest $request, int $id): JsonResponse
    {
        $header = PresensiGuruMapel::findOrFail($id);

        $this->authorize('update', $header);

        $user = $request->user();
        if (! $this->isAdmin($user)) {
            $ownerMatch = false;

            try {
                $gm = $header->guruMapel;
                if ($gm) {
                    if (isset($gm->guru_staf) && (string) $gm->guru_staf === (string) ($user->guru?->id ?? '')) {
                        $ownerMatch = true;
                    } elseif (isset($gm->guru_staf_id) && (string) $gm->guru_staf_id === (string) ($user->guru?->id ?? '')) {
                        $ownerMatch = true;
                    } elseif (isset($gm->id_guru_staff) && (string) $gm->id_guru_staff === (string) ($user->guru?->id ?? '')) {
                        $ownerMatch = true;
                    } elseif (isset($gm->guru_id) && (string) $gm->guru_id === (string) ($user->guru?->id ?? '')) {
                        $ownerMatch = true;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Owner check failed', ['error' => $e->getMessage()]);
            }

            if (! $ownerMatch) {
                $exists = GuruMapel::where('id', $header->guru_mapel_id);
                $this->applyGuruMapelOwnershipQuery($exists, $user);
                if (! $exists->exists()) {
                    return response()->json(['message' => 'Akses ditolak. Anda bukan guru mapel pemilik presensi ini.'], 403);
                }
            }
        }

        $presensiItems = $request->input('presensi', []);
        if (! is_array($presensiItems) && $request->filled('presensi')) {
            return response()->json(['message' => 'Format presensi tidak valid.'], 422);
        }

        try {
            DB::transaction(function () use ($request, $header, $presensiItems) {
                $header->update($request->only(['jam_masuk', 'jam_keluar', 'materi', 'tanggal']));

                if (! empty($presensiItems)) {
                    collect($presensiItems)->each(function ($item) use ($header) {
                        if (empty($item['siswa_id']) || ! isset($item['status'])) {
                            return;
                        }

                        $header->presensiSiswaDetail()->updateOrCreate(
                            ['siswa_id' => $item['siswa_id']],
                            [
                                'status'  => $item['status'],
                                'catatan' => $item['catatan'] ?? null
                            ]
                        );
                    });
                }
            });

            $header->load(['presensiSiswaDetail.siswa', 'guruMapel', 'mataPelajaran', 'kelas']);

            return (new PresensiGuruMapelResource($header))
                ->response()
                ->setStatusCode(200);

        } catch (Throwable $e) {
            Log::error('Presensi Update Error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
                'presensi_id' => $id,
            ]);
            return response()->json(['message' => 'Gagal memperbarui data.'], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);

        $this->authorize('delete', $presensi);

        $presensi->delete();

        return response()->json(['message' => 'Data dihapus.']);
    }
}
