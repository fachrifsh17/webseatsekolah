<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\LogAdmin;

class LogAdminController extends Controller {
  public function index(){ $data = LogAdmin::with('user')->orderByDesc('created_at')->paginate(20); return view('admin.log_admin.index',compact('data')); }
}
