<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LogAdminResource;
use App\Models\LogAdmin;
use Illuminate\Http\Request;

class LogAdminApiController extends Controller
{
    public function index()
    {
        // Tampilkan log terbaru dengan paginasi, sertakan relasi user untuk Resource
        $logs = LogAdmin::with('user') 
                        ->latest()
                        ->paginate(20); 

        return LogAdminResource::collection($logs);
    }
    public function show(LogAdmin $logAdmin)
    {
        // Tampilkan detail log, sertakan relasi user
        return new LogAdminResource($logAdmin->load('user'));
    }
}