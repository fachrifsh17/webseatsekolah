<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jadwal Mapel Kelas - {{ $kelas }}</title>
    <style>
        @page { 
            margin: 0.7cm; 
            margin-bottom: 5.5cm; 
        } 
        body { font-family: sans-serif; line-height: 1.2; margin: 0; padding: 0; color: #000; }
        
        .header-table { width: 100%; border-bottom: 2px solid #000; margin-bottom: 10px; }
        .school-name { font-size: 14pt; font-weight: bold; text-transform: uppercase; }
        
        .info-table { width: 100%; margin-bottom: 10px; font-size: 9pt; }
        .info-table td { border: none !important; padding: 2px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f2f2f2; border: 1px solid #000; padding: 6px; font-size: 8pt; }
        td { border: 1px solid #000; padding: 5px; font-size: 8pt; vertical-align: middle; }
        
        tr { page-break-inside: avoid; }

        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .bg-gray { background-color: #f9f9f9; }
        .underline { text-decoration: underline; }

        .signature-wrapper {
            position: fixed;
            bottom: -0.5cm;
            left: 0;
            right: 0;
            width: 100%;
        }
        .signature-table { 
            width: 100%; 
            border: none !important; 
            font-size: 9pt; 
        }
        .signature-table td { border: none !important; text-align: center; vertical-align: top; }
        
        .ttd-container { height: 70px; margin: 5px 0; }
        .ttd-image { height: 70px; width: auto; max-width: 180px; }
        .spacer { height: 70px; }
        
        /* Logo Styling */
        .logo-container { width: 70px; text-align: center; vertical-align: middle; }
        .logo-prov { width: 70px; height: auto; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="logo-container" style="border: none !important;">
                @php
                    $cleanLogoProv = str_replace(['uploads/profil/', 'public/', 'storage/'], '', $profil->logo_provinsi);
                    $pathLogoProv = public_path('uploads/profil/' . $cleanLogoProv);
                @endphp
                @if($profil->logo_provinsi && file_exists($pathLogoProv))
                    <img src="{{ $pathLogoProv }}" class="logo-prov">
                @endif
            </td>
            <td width="100%" class="text-center" style="border: none !important; padding-right: 70px;">
                <div style="font-size: 11pt; font-weight: bold;">PEMERINTAH PROVINSI {{ strtoupper($kontak->provinsi ?? 'JAWA BARAT') }}</div>
                <div style="font-size: 11pt; font-weight: bold;">DINAS PENDIDIKAN</div>
                <div style="font-size: 10pt; font-weight: bold;">{{ strtoupper($profil->cadis ?? 'CABANG DINAS PENDIDIKAN') }}</div>
                <div class="school-name">{{ $profil->nama_sekolah ?? 'NAMA SEKOLAH' }}</div>
                <div style="font-size: 8.5pt;">
                    {{ $kontak->alamat_jalan ?? '' }} 
                    Des. {{ $kontak->desa_kelurahan ?? '' }} 
                    Kec. {{ $kontak->kecamatan ?? '' }} 
                    {{ $kontak->kabupaten_kota ?? '' }} <br>
                    Telp: {{ $kontak->telepon ?? '-' }} | Email: {{ $kontak->email_resmi ?? '-' }} | NPSN: {{ $profil->npsn ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="text-center text-bold" style="font-size: 11pt; margin-top: 10px;">DAFTAR PENUGASAN GURU MATA PELAJARAN</div>
    <div class="text-center text-bold" style="font-size: 10pt; margin-bottom: 15px;">
        TAHUN PELAJARAN {{ strtoupper($semester_nama ?? '-') }}
    </div>

    <table class="info-table">
        <tr>
            <td width="12%">Kelas</td><td width="2%">:</td><td width="36%" class="text-bold">{{ $kelas }}</td>
            <td width="12%">Hari</td><td width="2%">:</td><td width="36%" class="text-bold">{{ strtoupper($hari) }}</td>
        </tr>
        <tr>
            <td>Kategori</td><td>:</td><td class="text-bold">{{ strtoupper($kategori) }}</td>
            <td>Status Data</td><td>:</td><td class="text-bold" style="color: green;">AKTIF (SISTEM)</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th width="4%">NO</th>
                <th width="10%">HARI</th>
                <th width="24%">NAMA GURU</th>
                <th width="14%">NIP</th>
                <th width="20%">MATA PELAJARAN</th>
                <th width="12%">KATEGORI</th>
                <th width="16%">URUTAN JAM</th>
            </tr>
        </thead>
        <tbody>
            @php $lastDay = null; @endphp
            @forelse($data as $index => $item)
            @php
                $guru = $item->guru instanceof \Illuminate\Support\Collection ? $item->guru->first() : $item->guru;
                $mapel = $item->mapel instanceof \Illuminate\Support\Collection ? $item->mapel->first() : $item->mapel;
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center text-bold {{ $lastDay !== $item->hari ? 'bg-gray' : '' }}">
                    {{ $lastDay !== $item->hari ? strtoupper($item->hari) : '' }}
                </td>
                <td>{{ strtoupper($guru->nama ?? '-') }}</td>
                <td class="text-center">{{ $guru->nip ?? '-' }}</td>
                <td>{{ strtoupper($mapel->nama_mapel ?? '-') }}</td>
                <td class="text-center">{{ strtoupper($mapel->kategori_mapel ?? '-') }}</td>
                <td class="text-center">
                    @if(isset($item->jamMulai->jam_ke) && isset($item->jamSelesai->jam_ke))
                        Jam Ke {{ $item->jamMulai->jam_ke }} - {{ $item->jamSelesai->jam_ke }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @php $lastDay = $item->hari; @endphp
            @empty
            <tr>
                <td colspan="7" class="text-center">Tidak ada data jadwal ditemukan untuk kriteria ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature-wrapper">
        <table class="signature-table">
            <tr>
                <td width="50%">
                    Mengetahui,<br>
                    Wakasek Kurikulum
                    <div class="ttd-container">
                        @php
                            $cleanWaka = str_replace(['storage/', 'public/'], '', $fileTtdWaka);
                            $pathWaka = $fileTtdWaka ? storage_path('app/' . $cleanWaka) : null;
                        @endphp
                        @if($pathWaka && file_exists($pathWaka))
                            <img src="{{ $pathWaka }}" class="ttd-image">
                        @else
                            <div class="spacer"></div>
                        @endif
                    </div>
                    <span class="text-bold underline">{{ strtoupper($wakaKur) }}</span><br>
                    NIP. {{ $nipWakaKur }}
                </td>

                <td width="50%">
                    {{ strtoupper($kontak->kabupaten_kota ?? 'JAWA BARAT') }}, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}<br>
                    Kepala Sekolah
                    <div class="ttd-container">
                        @php
                            $cleanKepsek = str_replace(['storage/', 'public/'], '', $fileTtdKepsek);
                            $pathKepsek = $fileTtdKepsek ? storage_path('app/' . $cleanKepsek) : null;
                        @endphp
                        @if($pathKepsek && file_exists($pathKepsek))
                            <img src="{{ $pathKepsek }}" class="ttd-image">
                        @else
                            <div class="spacer"></div>
                        @endif
                    </div>
                    <span class="text-bold underline">{{ strtoupper($kepsek) }}</span><br>
                    NIP. {{ $nipKepsek }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>