<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoinSiswa;
use App\Models\TahunAjaran;
use App\Http\Requests\StorePoinSiswaRequest;
use App\Http\Requests\UpdatePoinSiswaRequest;
use App\Http\Resources\PoinSiswaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PoinSiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(PoinSiswa::class, 'poin_siswa');
    }

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran']);
        $scope = $request->query('scope');

        $isAdmin = $user->hasAnyRole(['admin', 'Admin', 'ADMIN']);
        $isGuru  = $user->hasAnyRole(['guru', 'Guru']);
        $isSiswa = $user->hasAnyRole(['siswa', 'Siswa']);
        $isOrtu  = $user->hasAnyRole(['orangtua', 'Orangtua', 'Orang Tua']);

        if ($isAdmin && $scope !== 'guru') {
            // Full Access
        } elseif ($isSiswa) {
            $siswaId = $user->siswa?->id;
            if (!$siswaId) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Profil siswa tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }
            $query->where('siswa_id', (string) $siswaId);
        } elseif ($isGuru || ($isAdmin && $scope === 'guru')) {
            $query->where('guru_staf_id', (string) $user->guruStaf?->id);
        } elseif ($isOrtu) {
            $childrenIds = $user->orangtua?->anak()->pluck('id')->toArray() ?? [];
            $query->whereIn('siswa_id', $childrenIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        $poin = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => PoinSiswaResource::collection($poin),
            'meta'    => [
                'current_page' => $poin->currentPage(),
                'last_page'    => $poin->lastPage(),
                'per_page'     => $poin->perPage(),
                'total'        => $poin->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $scope = $request->query('scope');
        
        $tahunAjaran = TahunAjaran::where('is_active', 1)->first();
        if (!$tahunAjaran) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada tahun ajaran aktif.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validated();

        if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN']) && $scope !== 'guru') {
            $validated['guru_staf_id'] = $request->guru_staf_id ?? (string) $user->guruStaf?->id;
        } else {
            $validated['guru_staf_id'] = (string) $user->guruStaf?->id;
        }
            
        $validated['tahun_ajaran_id'] = (string) $tahunAjaran->id;

        try {
            $poin = DB::transaction(fn() => PoinSiswa::create($validated));

            return response()->json([
                'success' => true,
                'message' => 'Poin siswa berhasil dicatat.',
                'data'    => new PoinSiswaResource($poin->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat poin siswa.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(PoinSiswa $poin_siswa): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new PoinSiswaResource($poin_siswa->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
        ], Response::HTTP_OK);
    }

    public function update(UpdatePoinSiswaRequest $request, PoinSiswa $poin_siswa): JsonResponse
    {
        $validated = $request->validated();
        try {
            DB::transaction(fn() => $poin_siswa->update($validated));
            return response()->json([
                'success' => true,
                'message' => 'Catatan poin diperbarui.',
                'data'    => new PoinSiswaResource($poin_siswa->fresh(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui poin siswa.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(PoinSiswa $poin_siswa): JsonResponse
    {
        try {
            DB::transaction(fn() => $poin_siswa->delete());
            return response()->json([
                'success' => true, 
                'message' => 'Data poin siswa dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menghapus poin siswa.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}