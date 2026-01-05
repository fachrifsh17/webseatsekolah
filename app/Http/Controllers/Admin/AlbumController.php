<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Http\Resources\AlbumResource;
use App\Http\Requests\StoreAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Throwable;

class AlbumController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
    }

    public function index(): JsonResponse
    {
        $data = Album::orderByDesc('tanggal_kegiatan')->paginate(12);

        return response()->json([
            'success' => true,
            'data'    => AlbumResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ]);
    }

    public function show(Album $album): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new AlbumResource($album),
        ]);
    }

    public function store(StoreAlbumRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tanggal_kegiatan'] = $validated['tanggal_kegiatan'] ?? null;

        DB::beginTransaction();
        try {
            if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                $validated['cover_path'] = $request->file('cover')->store('uploads/album', 'public');
            }

            $album = Album::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Album berhasil ditambahkan.',
                'data'    => new AlbumResource($album->fresh()),
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();

            if (!empty($validated['cover_path']) && Storage::disk('public')->exists($validated['cover_path'])) {
                Storage::disk('public')->delete($validated['cover_path']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan album.',
            ], 500);
        }
    }

    public function update(UpdateAlbumRequest $request, Album $album): JsonResponse
    {
        $validated = $request->validated();

        if ($request->has('tanggal_kegiatan')) {
            $validated['tanggal_kegiatan'] = $request->input('tanggal_kegiatan') === '' ? null : $request->input('tanggal_kegiatan');
        }

        if (empty($validated)) {
            $validated = $request->only(['nama_album', 'tanggal_kegiatan']);
        }

        DB::beginTransaction();
        $newCoverPath = null;
        $originalCover = $album->getOriginal('cover_path');

        try {
            if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                $newCoverPath = $request->file('cover')->store('uploads/album', 'public');
                $validated['cover_path'] = $newCoverPath;
            }

            $album->fill($validated);
            $album->save();

            if ($newCoverPath && $originalCover && Storage::disk('public')->exists($originalCover)) {
                Storage::disk('public')->delete($originalCover);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Album berhasil diperbarui.',
                'data'    => new AlbumResource($album->fresh()),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            if ($newCoverPath && Storage::disk('public')->exists($newCoverPath)) {
                Storage::disk('public')->delete($newCoverPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui album.',
            ], 500);
        }
    }

    public function destroy(Album $album): JsonResponse
    {
        DB::beginTransaction();

        try {
            if (!empty($album->cover_path) && Storage::disk('public')->exists($album->cover_path)) {
                Storage::disk('public')->delete($album->cover_path);
            }

            $album->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Album berhasil dihapus.'
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus album.',
            ], 500);
        }
    }
}
