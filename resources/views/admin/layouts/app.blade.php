<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin - WEBSEKOLAH</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <header>
    <h1>Admin Panel - WEBSEKOLAH</h1>
    <nav>
      <a href="{{ url('/admin') }}">Dashboard</a> |
      <a href="{{ url('/admin/berita') }}">Berita</a> |
      <a href="{{ url('/admin/pengumuman') }}">Pengumuman</a>
    </nav>
  </header>
  <main style="padding:10px">
    @yield('content')
  </main>
  <footer style="margin-top:20px">WEBSEKOLAH &copy; 2025</footer>
</body>
</html>
