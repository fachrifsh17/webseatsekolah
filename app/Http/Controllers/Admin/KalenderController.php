<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use App\Http\Resources\KalenderAkademikResource;
use App\Http\Requests\StoreKalenderRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\JsonResponse;

class KalenderController extends Controller implements HasMiddleware
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
        $data = KalenderAkademik::orderBy('tanggal_mulai')->paginate(12); 
        
        return KalenderAkademikResource::collection($data);
    }
    
    public function show(KalenderAkademik $kalender)
    {
        return new KalenderAkademikResource($kalender);
    }

    public function store(StoreKalenderRequest $request)
    {
        $item = KalenderAkademik::create($request->validated());
        
        return new KalenderAkademikResource($item);
    }

    public function update(StoreKalenderRequest $request, KalenderAkademik $kalender)
    {
        $kalender->update($request->validated()); 
        
        return new KalenderAkademikResource($kalender);
    }

    public function destroy(KalenderAkademik $kalender): JsonResponse
    {
        $kalender->delete(); 
        
        return response()->json(null, 204);
    }
}