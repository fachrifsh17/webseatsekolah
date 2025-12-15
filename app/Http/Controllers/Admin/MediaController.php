<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct() { $this->middleware('auth:admin'); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'album_id'=>'required|exists:album,id',
            'media_path'=>'required|file|mimes:jpg,jpeg,png,mp4',
            'jenis_media'=>'required|in:Foto,Video',
            'keterangan'=>'nullable|string|max:255'
        ]);
        $data['media_path'] = $request->file('media_path')->store('media','public');
        Media::create($data);
        return back()->with('success','Media ditambahkan');
    }

    public function destroy(Media $media)
    {
        $media->delete();
        return back()->with('success','Media dihapus');
    }
}
