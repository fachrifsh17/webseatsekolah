<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortalSosmed;
use App\Http\Resources\PortalSosmedResource;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin');
    }

    public function index()
    {
        $data = PortalSosmed::paginate(12);
        
        return PortalSosmedResource::collection($data);
    }
    
    public function show($id)
    {
        $item = PortalSosmed::findOrFail($id);
        
        return new PortalSosmedResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_platform' => 'required|string|max:255|unique:portal_sosmed,nama_platform',
            'url_link' => 'required|url|max:255',
            'tipe' => 'required|in:Sosial Media,Website,Portal Lain', 
        ]);
        
        $item = PortalSosmed::create($validated); 
        
        return new PortalSosmedResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = PortalSosmed::findOrFail($id); 
        
        $validated = $request->validate([
            'nama_platform' => 'required|string|max:255|unique:portal_sosmed,nama_platform,' . $id,
            'url_link' => 'required|url|max:255',
            'tipe' => 'required|in:Sosial Media,Website,Portal Lain', 
        ]);
        
        $item->update($validated); 
        
        return new PortalSosmedResource($item);
    }

    public function destroy($id)
    {
        PortalSosmed::findOrFail($id)->delete(); 
        
        return response()->json(null, 204);
    }
}