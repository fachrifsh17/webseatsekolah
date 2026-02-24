<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jadwal Mapel Kelas</title>
    <style>
        /* Setup Halaman */
        @page { 
            margin: 0.5cm; 
            margin-bottom: 3.5cm; /* Beri ruang kosong di bawah untuk TTD agar tidak tertabrak data */
        } 
        body { font-family: sans-serif; line-height: 1.0; margin: 0; padding: 0; color: #000; }
        
        /* Header & Info */
        .header-table { width: 100%; border: none; border-bottom: 1.5px solid #000; margin-bottom: 8px; }
        .school-name { font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .info-table { width: 100%; margin-bottom: 5px; font-size: 7.5pt; }
        .info-table td { border: none !important; padding: 0px; }
        
        /* Tabel Utama */
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f2f2f2; border: 1px solid #000; padding: 3px; font-size: 7pt; text-transform: uppercase; }
        td { border: 1px solid #000; padding: 2px 3px; font-size: 6.5pt; vertical-align: middle; }
        
        /* Helper Classes */
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .bg-gray { background-color: #f9f9f9; }
        .underline { text-decoration: underline; }

        /* Tanda Tangan Tetap di Bawah */
        .signature-wrapper {
            position: fixed;
            bottom: -0.5cm; /* Menempel ke batas margin bawah */
            left: 0;
            right: 0;
            width: 100%;
        }
        .signature-table { 
            width: 100%; 
            border: none !important; 
            font-size: 7.5pt; 
        }
        .signature-table td { border: none !important; padding: 2px; text-align: center; vertical-align: top; }
        .spacer { height: 30px; } /* Ruang tanda tangan */
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td width="100%" class="text-center" style="border: none !important;">
                <div style="font-size: 8pt;">PEMERINTAH PROVINSI JAWA BARAT</div>
                <div style="font-size: 8pt;">DINAS PENDIDIKAN</div>
                <div class="school-name">{{ $profil->nama_sekolah }}</div>
                <div style="font-size: 7pt;">
                    {{ $kontak->alamat_lengkap }} <br>
                    Telp: {{ $kontak->telepon }} | Email: {{ $kontak->email_resmi }} | NPSN: {{ $profil->npsn }}
                </div>
            </td>
        </tr>
    </table>

    <div class="text-center text-bold" style="font-size: 9pt; margin-bottom: 1px;">DAFTAR PENUGASAN GURU MATA PELAJARAN</div>
    <div class="text-center text-bold" style="font-size: 8pt; margin-bottom: 5px;">
        TAHUN PELAJARAN {{ $tahun->nama ?? '2025/2026' }} {{ strtoupper($tahun->semester ?? 'GENAP') }}
    </div>

    <table class="info-table">
        <tr>
            <td width="10%">Kelas</td><td width="2%">:</td><td width="38%" class="text-bold">{{ $kelas }}</td>
            <td width="10%">Hari</td><td width="2%">:</td><td width="38%" class="text-bold">{{ strtoupper($hari) }}</td>
        </tr>
        <tr>
            <td>Kategori</td><td>:</td><td class="text-bold">{{ strtoupper($kategori) }}</td>
            <td>Status</td><td>:</td><td class="text-bold">AKTIF</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th width="3%">NO</th>
                <th width="8%">HARI</th>
                <th width="25%">NAMA GURU</th>
                <th width="13%">NIP</th>
                <th width="22%">MATA PELAJARAN</th>
                <th width="11%">KATEGORI</th>
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