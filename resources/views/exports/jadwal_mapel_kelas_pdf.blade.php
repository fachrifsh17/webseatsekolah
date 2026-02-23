<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jadwal Mapel Kelas</title>
    <style>
        /* Setup Halaman */
        @page { margin: 1cm; }
        body { font-family: sans-serif; line-height: 1.1; margin: 0; padding: 0; color: #000; }
        
        /* Header & Info */
        .header-table { width: 100%; border: none; border-bottom: 2px solid #000; margin-bottom: 15px; }
        .school-name { font-size: 16pt; font-weight: bold; text-transform: uppercase; }
        .info-table { width: 100%; margin-bottom: 10px; font-size: 9pt; }
        .info-table td { border: none !important; padding: 1px; }
        
        /* Tabel Utama */
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f2f2f2; border: 1px solid #000; padding: 6px; font-size: 8pt; text-transform: uppercase; }
        td { border: 1px solid #000; padding: 5px; font-size: 8pt; vertical-align: middle; }
        
        /* Helper Classes */
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .bg-gray { background-color: #f9f9f9; }
        .underline { text-decoration: underline; }

        /* Tanda Tangan di Paling Bawah */
        .signature-wrapper {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
        }
        .signature-table { 
            width: 100%; 
            border: none !important; 
            font-size: 9pt; 
        }
        .signature-table td { border: none !important; padding: 5px; text-align: center; vertical-align: top; }
        .spacer { height: 55px; } /* Ruang tanda tangan */
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td width="100%" class="text-center" style="border: none !important;">
                <div style="font-size: 10pt;">PEMERINTAH PROVINSI JAWA BARAT</div>
                <div style="font-size: 10pt;">DINAS PENDIDIKAN</div>
                <div class="school-name">{{ $profil->nama_sekolah }}</div>
                <div style="font-size: 8.5pt;">
                    {{ $kontak->alamat_lengkap }} <br>
                    Telp: {{ $kontak->telepon }} | Email: {{ $kontak->email_resmi }} | NPSN: {{ $profil->npsn }}
                </div>
            </td>
        </tr>
    </table>

    <div class="text-center text-bold" style="font-size: 11pt; margin-bottom: 2px;">DAFTAR PENUGASAN GURU MATA PELAJARAN</div>
    <div class="text-center text-bold" style="font-size: 10pt; margin-bottom: 10px;">
        TAHUN PELAJARAN {{ $tahun->nama ?? '2025/2026' }} {{ strtoupper($tahun->semester ?? 'GENAP') }}
    </div>

    <table class="info-table">
        <tr>
            <td width="12%">Kelas</td><td width="2%">:</td><td width="36%" class="text-bold">{{ $kelas }}</td>
            <td width="12%">Hari</td><td width="2%">:</td><td width="36%" class="text-bold">{{ strtoupper($hari) }}</td>
        </tr>
        <tr>
            <td>Kategori</td><td>:</td><td class="text-bold">{{ strtoupper($kategori) }}</td>
            <td>Status</td><td>:</td><td class="text-bold">AKTIF</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th width="4%">NO</th>
                <th width="10%">HARI</th>
                <th width="22%">NAMA GURU</th>
                <th width="14%">NIP</th>
                <th width="20%">MATA PELAJARAN</th>
                <th width="12%">KATEGORI</th>
                <th width="18%">URUTAN JAM</th>
            </tr>
        </thead>
        <tbody>
            @php $lastDay = null; @endphp
            @forelse($data as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center text-bold {{ $lastDay !== $item->hari ? 'bg-gray' : '' }}">
                    {{ $lastDay !== $item->hari ? strtoupper($item->hari) : '' }}
                </td>
                <td>{{ $item->guru->nama ?? '-' }}</td>
                <td class="text-center">{{ $item->guru->nip ?? '-' }}</td>
                <td>{{ $item->mapel->nama_mapel ?? '-' }}</td>
                <td class="text-center">{{ strtoupper($item->mapel->kategori_mapel ?? '-') }}</td>
                <td class="text-center">
                    @if($item->jamMulai && $item->jamSelesai)
                        Ke {{ $item->jamMulai->jam_ke }} - {{ $item->jamSelesai->jam_ke }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @php $lastDay = $item->hari; @endphp
            @empty
            <tr>
                <td colspan="7" class="text-center">Tidak ada data jadwal untuk filter ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature-wrapper">
        <table class="signature-table">
            <tr>
                <td width="33%">
                    Mengetahui,<br>
                    Wali Kelas
                    <div class="spacer"></div>
                    <span class="text-bold underline">{{ $wali ?? '...........................' }}</span><br>
                    NIP. {{ $nipWali ?? '...........................' }}
                </td>

                <td width="33%">
                    <br>
                    Wakasek Kurikulum
                    <div class="spacer"></div>
                    <span class="text-bold underline">{{ $wakaKur ?? '...........................' }}</span><br>
                    NIP. {{ $nipWakaKur ?? '...........................' }}
                </td>

                <td width="33%">
                    Tasikmalaya, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}<br>
                    Kepala Sekolah
                    <div class="spacer"></div>
                    <span class="text-bold underline">{{ $kepsek ?? '...........................' }}</span><br>
                    NIP. {{ $nipKepsek ?? '...........................' }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>