<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use App\Http\Resources\KurikulumResource;
use App\Http\Requests\StoreKurikulumRequest;
use App\Http\Requests\UpdateKurikulumRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\JsonResponse;

class KurikulumController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        $data = Kurikulum::paginate(12);
        return KurikulumResource::collection($data);
    }
    
    public function show(Kurikulum $kurikulum)
    {
        return new KurikulumResource($kurikulum);
    }

    public function store(StoreKurikulumRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('file_jadwal')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal')->store('uploads/kurikulum', 'public');
        }

        $kurikulum = Kurikulum::create($validated);
        
        return new KurikulumResource($kurikulum);
    }

    public function update(UpdateKurikulumRequest $request, Kurikulum $kurikulum)
    {
        $validated = $request->validated();

        if ($request->hasFile('file_jadwal')) {
            if ($kurikulum->file_jadwal_path) {
                Storage::disk('public')->delete($kurikulum->file_jadwal_path);
            }
            $validated['file_jadwal_path'] = $request->file('file_jadwal')->store('uploads/kurikulum', 'public');
        }

        $kurikulum->update($validated);
        
        return new KurikulumResource($kurikulum);
    }

    public function destroy(Kurikulum $kurikulum): JsonResponse
    {
        if ($kurikulum->file_jadwal_path) {
            Storage::disk('public')->delete($kurikulum->file_jadwal_path);
        }

        $kurikulum->delete();
        
        return response()->json(null, 204);
    }
}