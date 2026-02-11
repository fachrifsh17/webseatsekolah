<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Http\Resources\EkstrakurikulerResource;
use App\Http\Requests\{StoreEkstrakurikulerRequest, UpdateEkstrakurikulerRequest};
use Illuminate\Support\Facades\{Storage, DB, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class EkstrakurikulerController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(Ekstrakurikuler::class, 'ekstrakurikuler');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $data = Ekstrakurikuler::with('pembina')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => EkstrakurikulerResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kesiswaan Ekskul Index Error: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil daftar ekstrakurikuler');
        }
    }

    public function show(Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new EkstrakurikulerResource($ekstrakurikuler->load('pembina'))
        ], Response::HTTP_OK);
    }

    public function store(StoreEkstrakurikulerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($this->isNameTaken($validated['nama_ekskul'])) {
            return $this->errorResponse('Nama ekstrakurikuler sudah terdaftar.', Response::HTTP_CONFLICT);
        }

        if ($this->isScheduleConflict($validated)) {
            return $this->errorResponse('Jadwal pembina bentrok.', Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
        }

        try {
            $ekskul = DB::transaction(fn() => Ekstrakurikuler::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Ekstrakurikuler berhasil ditambahkan',
                'data'    => new EkstrakurikulerResource($ekskul->load('pembina'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            if (!empty($validated['foto'])) Storage::disk('public')->delete($validated['foto']);
            Log::error('Store Error: ' . $e->getMessage());
            return $this->errorResponse('Gagal menambahkan data.');
        }
    }

    public function update(UpdateEkstrakurikulerRequest $request, Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $validated = $request->validated();
        $oldFoto   = $ekstrakurikuler->foto;

        if (isset($validated['nama_ekskul']) && $this->isNameTaken($validated['nama_ekskul'], $ekstrakurikuler->id)) {
            return $this->errorResponse('Nama sudah digunakan.', Response::HTTP_CONFLICT);
        }

        if ($this->isScheduleConflict($validated, $ekstrakurikuler)) {
            return $this->errorResponse('Jadwal pembina bentrok.', Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
        }

        if ($request->filled('foto') && $request->foto === 'null') {
            if ($oldFoto) Storage::disk('public')->delete($oldFoto);
            $validated['foto'] = null;
        }

        try {
            DB::transaction(fn() => $ekstrakurikuler->update($validated));
            if (isset($validated['foto']) && $oldFoto && $validated['foto'] !== $oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Berhasil diperbarui',
                'data'    => new EkstrakurikulerResource($ekstrakurikuler->fresh('pembina'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Error: ' . $e->getMessage());
            return $this->errorResponse('Gagal memperbarui data.');
        }
    }

    public function destroy(Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $oldFoto = $ekstrakurikuler->foto;
        try {
            DB::transaction(fn() => $ekstrakurikuler->delete());
            if ($oldFoto) Storage::disk('public')->delete($oldFoto);
            return response()->json(['success' => true, 'message' => 'Berhasil dihapus']);
        } catch (Throwable $e) {
            Log::error('Delete Error: ' . $e->getMessage());
            return $this->errorResponse('Gagal menghapus data.');
        }
    }

    private function isNameTaken($name, $ignoreId = null) 
    {
        return Ekstrakurikuler::where('nama_ekskul', $name)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function isScheduleConflict($data, $ekskul = null) 
    {
        $pembinaId = $data['pembina_id'] ?? ($ekskul ? $ekskul->pembina_id : null);
        $hari      = $data['hari']        ?? ($ekskul ? $ekskul->hari : null);
        $start     = $data['jam_mulai']   ?? ($ekskul ? $ekskul->jam_mulai : null);
        $end       = $data['jam_selesai'] ?? ($ekskul ? $ekskul->jam_selesai : null);

        if (!$pembinaId || !$hari || !$start || !$end) return false;

        return Ekstrakurikuler::where('pembina_id', $pembinaId)
            ->where('hari', $hari)
            ->when($ekskul, fn($q) => $q->where('id', '!=', $ekskul->id))
            ->where(fn($q) => 
                $q->whereBetween('jam_mulai', [$start, $end])
                  ->orWhereBetween('jam_selesai', [$start, $end])
                  ->orWhere(fn($sq) => $sq->where('jam_mulai', '<=', $start)->where('jam_selesai', '>=', $end))
            )->exists();
    }

    private function errorResponse($message, $code = 500) 
    {
        return response()->json(['success' => false, 'message' => $message], $code);
    }
}