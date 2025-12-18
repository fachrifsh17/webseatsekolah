<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Http\Resources\EkstrakurikulerResource;
use App\Http\Requests\StoreEkstrakurikulerRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EkstrakurikulerController extends Controller implements HasMiddleware
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
        $data = Ekstrakurikuler::with('pembina')->paginate(12);
        
        return EkstrakurikulerResource::collection($data);
    }
    
    public function show(Ekstrakurikuler $ekstrakurikuler)
    {
        $ekstrakurikuler->load('pembina');
        
        return new EkstrakurikulerResource($ekstrakurikuler);
    }

    public function store(StoreEkstrakurikulerRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
        }
        
        $ekskul = Ekstrakurikuler::create($validated);
        
        return new EkstrakurikulerResource($ekskul->load('pembina'));
    }

    public function update(StoreEkstrakurikulerRequest $request, Ekstrakurikuler $ekstrakurikuler)
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            if ($ekstrakurikuler->foto) {
                Storage::disk('public')->delete($ekstrakurikuler->foto);
            }
            $validated['foto'] = $request->file('foto')->store('uploads/ekskul', 'public');
        }

        $ekstrakurikuler->update($validated);
        
        return new EkstrakurikulerResource($ekstrakurikuler->load('pembina'));
    }

    public function destroy(Ekstrakurikuler $ekstrakurikuler)
    {
        if ($ekstrakurikuler->foto) {
            Storage::disk('public')->delete($ekstrakurikuler->foto);
        }
        
        $ekstrakurikuler->delete();
        
        return response()->json(null, 204);
    }
}