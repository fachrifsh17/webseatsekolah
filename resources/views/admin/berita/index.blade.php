@extends('admin.layouts.app')

@section('content')
<h2>Daftar Berita</h2>
<a href="{{ url('/admin/berita/create') }}">Buat Berita Baru</a>
@if(session('success'))<p style="color:green">{{ session('success') }}</p>@endif
@if(isset($beritas) && count($beritas) > 0)
<table border="1" cellpadding="6" cellspacing="0">
<thead><tr><th>ID</th><th>Judul</th><th>Author</th><th>Aksi</th></tr></thead>
<tbody>
@foreach($beritas as $b)
<tr>
<td>{{ $b->id }}</td>
<td>{{ $b->title }}</td>
<td>{{ $b->author }}</td>
<td>
  <a href="{{ url('/admin/berita/'.$b->id.'/edit') }}">Edit</a> |
  <form method="POST" action="{{ url('/admin/berita/'.$b->id) }}" style="display:inline">
    @csrf
    @method('DELETE')
    <button type="submit" onclick="return confirm('Hapus berita?')">Hapus</button>
  </form>
</td>
</tr>
@endforeach
</tbody>
</table>
@else
<p>Belum ada berita.</p>
@endif
@endsection
