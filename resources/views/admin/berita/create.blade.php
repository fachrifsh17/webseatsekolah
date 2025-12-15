@extends('admin.layouts.app')

@section('content')
<h2>Buat Berita</h2>
<form method="post" action="/admin/berita/store" enctype="multipart/form-data">
  <!-- CSRF token in real Laravel -->
  <label>Judul: <input name="title"></label><br>
  <label>Isi: <textarea name="content"></textarea></label><br>
  <button type="submit">Simpan</button>
</form>
@endsection
