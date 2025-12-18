<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Http\Resources\MapelResource;
use App\Http\Requests\StoreMapelRequest;

class MapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin');
    }

    public function index()
    {
        $data = MataPelajaran::with('jurusan')->paginate(12);
        
        return MapelResource::collection($data);
    }
    
    public function show($id)
    {
        $item = MataPelajaran::with('jurusan')->findOrFail($id);
        
        return new MapelResource($item);
    }

    public function store(StoreMapelRequest $request)
    {
        $validated = $request->validated();
        
        $item = MataPelajaran::create($validated); 
        
        return new MapelResource($item->load('jurusan'));
    }

    public function update(StoreMapelRequest $request, $id)
    {
        $item = MataPelajaran::findOrFail($id); 
        $validated = $request->validated();
        
        $item->update($validated); 
        
        return new MapelResource($item->load('jurusan'));
    }

    public function destroy($id)
    {
        MataPelajaran::findOrFail($id)->delete(); 
        
        return response()->json(null, 204);
    }
}