@extends('admin.layouts.app')

@section('content')
<h2>Edit Berita</h2>
<form method="post" action="{{ url('/admin/berita/'.$berita->id) }}">
  @csrf
  @method('PUT')
  <label>Judul: <input name="title" value="{{ $berita->title }}"></label><br>
  <label>Author: <input name="author" value="{{ $berita->author }}"></label><br>
  <label>Isi: <textarea name="content">{{ $berita->content }}</textarea></label><br>
  <button type="submit">Update</button>
</form>
@endsection
