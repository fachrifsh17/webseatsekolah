<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PPDBLink;
use App\Models\LogAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PpdbLinkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth'); // atau middleware admin sesuai project
    }

    public function index()
    {
        $links = PPDBLink::orderBy('id')->get();
        return view('admin.ppdb.index', compact('links'));
    }

    public function create()
    {
        return view('admin.ppdb.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'url_link' => 'required|url|max:255',
            'status_ppdb' => 'nullable|in:Buka,Tutup,Segera',
        ]);

        $link = PPDBLink::create($validated);

        // optional: log admin action
        if (Auth::check()) {
            LogAdmin::create([
                'user_id' => Auth::id(),
                'aksi' => 'Menambah PPDB link id='.$link->id,
            ]);
        }

        return redirect()->route('admin.ppdb.index')->with('success', 'PPDB link berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $link = PPDBLink::findOrFail($id);
        return view('admin.ppdb.edit', compact('link'));
    }

    public function update(Request $request, $id)
    {
        $link = PPDBLink::findOrFail($id);

        $validated = $request->validate([
            'url_link' => 'required|url|max:255',
            'status_ppdb' => 'nullable|in:Buka,Tutup,Segera',
        ]);

        $link->update($validated);

        if (Auth::check()) {
            LogAdmin::create([
                'user_id' => Auth::id(),
                'aksi' => 'Mengubah PPDB link id='.$link->id,
            ]);
        }

        return redirect()->route('admin.ppdb.index')->with('success', 'PPDB link berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $link = PPDBLink::findOrFail($id);
        $link->delete();

        if (Auth::check()) {
            LogAdmin::create([
                'user_id' => Auth::id(),
                'aksi' => 'Menghapus PPDB link id='.$id,
            ]);
        }

        return redirect()->route('admin.ppdb.index')->with('success', 'PPDB link berhasil dihapus.');
    }
}
