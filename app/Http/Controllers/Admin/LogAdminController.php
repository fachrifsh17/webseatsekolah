<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogAdmin;
use App\Http\Resources\LogAdminResource;
use Illuminate\Http\Request;

class LogAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        // Log harus diakses oleh Admin
        $this->middleware('role:Admin');
    }

    public function index()
    {
        $data = LogAdmin::with('user')->orderByDesc('created_at')->paginate(20); 
        
        return LogAdminResource::collection($data);
    }
    
    public function show($id)
    {
        $item = LogAdmin::with('user')->findOrFail($id);
        
        return new LogAdminResource($item);
    }
}