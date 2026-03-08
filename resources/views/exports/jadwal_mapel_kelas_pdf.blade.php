<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>JADWAL MAPEL KELAS - {{ $kelas }}</title>
    <style>
        @page { 
            margin: 0.7cm; 
            margin-bottom: 5.5cm; 
        } 
        body { font-family: sans-serif; line-height: 1.2; margin: 0; padding: 0; color: #000; }
        .header-table { width: 100%; border-bottom: 3px double #000; margin-bottom: 10px; }
        .school-name { font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 2px 0; }
        
        /* Layout Filter Info */
        .filter-info { width: 100%; margin-bottom: 10px; font-size: 8.5pt; border-collapse: collapse; }
        .filter-info td { border: none; padding: 1px 0; vertical-align: top; }
        .label-width { width: 80px; } 
        .separator-width { width: 10px; }
        
        table.main-table { width: 100%; border-collapse: collapse; }
        table.main-table th { background-color: #f2f2f2; border: 1px solid #000; padding: 6px; font-size: 8pt; }
        table.main-table td { border: 1px solid #000; padding: 5px; font-size: 8pt; vertical-align: middle; }
        
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .underline { text-decoration: underline; }
        
        /* Style untuk Status Aktif */
        .status-aktif { color: #28a745; font-weight: bold; } 
        .status-default { font-weight: bold; }

        /* Layout Tanda Tangan */
        .signature-wrapper { position: fixed; bottom: -0.5cm; left: 0; right: 0; width: 100%; }
        .signature-table { width: 100%; border: none !important; font-size: 9pt; }
        .signature-table td { border: none !important; text-align: center; vertical-align: top; }
        .spacer-ttd { height: 65px; position: relative; width: 100%; }
        .ttd-img { max-height: 60px; position: absolute; left: 50%; transform: translateX(-50%); bottom: 5px; }
        
        /* Posisi paling bawah halaman */
        .print-time { 
            font-size: 7pt; 
            text-align: left; 
            margin-left: 0.5cm; 
            margin-top: 10px; 
            font-style: italic; 
            color: #333;
            width: 100%;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td width="15%" style="border:none; text-align: center; vertical-align: middle;">
                @php
                    $pathLogoProv = null;
                    if($profil->logo_provinsi) {
                        $cleanLogo = str_replace(['uploads/profil/', 'public/', 'storage/'], '', $profil->logo_provinsi);
                        $pathLogoProv = public_path('uploads/profil/' . $cleanLogo);
                    }
                @endphp
                @if($pathLogoProv && file_exists($pathLogoProv))
                    <img src="{{ $pathLogoProv }}" style="width: 80px;">
                @endif
            </td>
            <td width="85%" class="text-center" style="border:none; padding-right: 80px;">
                @php
                    $provinsi = strtoupper($kontak->provinsi ?? 'PROVINSI');
                    $cabdis = strtoupper($profil->cadis ?? $profil->wilayah_cabang ?? 'CABANG DINAS PENDIDIKAN');
                    $alamatFull = ($kontak->alamat_jalan ?? '-') . ", Desa " . ($kontak->desa_kelurahan ?? '-') . " Kec. " . ($kontak->kecamatan ?? '-') . " " . ($kontak->kabupaten_kota ?? '-') . " - " . ($kontak->provinsi ?? '-');
                @endphp
                <div style="font-size: 12pt; font-weight: bold;">PEMERINTAH PROVINSI {{ $provinsi }}</div>
                <div style="font-size: 12pt; font-weight: bold;">DINAS PENDIDIKAN</div>
                <div style="font-size: 11pt; font-weight: bold;">{{ str_contains(strtolower($cabdis), 'cabang') ? $cabdis : 'CABANG DINAS PENDIDIKAN ' . $cabdis }}</div>
                <div class="school-name">{{ strtoupper($profil->nama_sekolah ?? 'NAMA SEKOLAH') }}</div>
                <div style="font-size: 8.5pt;">{{ $alamatFull }}</div>
                <div style="font-size: 8.5pt;">Telp: {{ $kontak->telepon ?? '-' }} | Email: {{ strtolower($kontak->email_resmi ?? '-') }} | NPSN: {{ $profil->npsn ?? '-' }}</div>
            </td>
        </tr>
    </table>

    <div class="text-center text-bold" style="font-size: 11pt; margin-top: 5px;">DAFTAR PENUGASAN GURU MATA PELAJARAN</div>
    <div class="text-center text-bold" style="font-size: 10pt; margin-bottom: 10px;">
        TAHUN PELAJARAN {{ strtoupper($tahun_ajaran) }}-SEMESTER {{ strtoupper($semester) }}
    </div>

    <table class="filter-info">
        <tr>
            <td class="label-width">KELAS</td>
            <td class="separator-width">:</td>
            <td class="text-bold">{{ strtoupper($kelas) }}</td>
        </tr>
        <tr>
            <td class="label-width">HARI</td>
            <td class="separator-width">:</td>
            <td class="text-bold">{{ strtoupper($hari) }}</td>
        </tr>
        <tr>
            <td class="label-width">KATEGORI</td>
            <td class="separator-width">:</td>
            <td class="text-bold">{{ strtoupper($kategori ?? 'SEMUA KATEGORI') }}</td>
        </tr>
        <tr>
            <td class="label-width">STATUS</td>
            <td class="separator-width">:</td>
            <td class="{{ strtoupper($status_aktif) == 'AKTIF' ? 'status-aktif' : 'status-default' }}">
                {{ strtoupper($status_aktif ?? 'TIDAK AKTIF') }}
            </td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th width="5%">NO</th>
                <th width="12%">HARI</th>
                <th width="33%">NAMA GURU</th>
                <th width="30%">MATA PELAJARAN</th>
                <th width="20%">URUTAN JAM</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ strtoupper($item->hari) }}</td>
                <td>{{ strtoupper($item->guru?->nama ?? '-') }}</td>
                <td>{{ strtoupper($item->mapel?->nama_mapel ?? '-') }}</td>
                <td class="text-center">JAM {{ $item->jamMulai?->jam_ke ?? '-' }} - {{ $item->jamSelesai?->jam_ke ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center" style="padding: 20px;">
                    TIDAK ADA DATA JADWAL YANG DITEMUKAN PADA KATEGORI INI
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature-wrapper">
        <table class="signature-table">
            <tr>
                <td width="50%">
                    <br>MENGETAHUI,<br>WAKA KURIKULUM
                    <div class="spacer-ttd">
                        @php
                            $pathWaka = null;
                            if(!empty($fileTtdWaka)) {
                                $cleanWaka = str_replace(['private/', 'storage/', 'public/', 'tanda_tangan/'], '', $fileTtdWaka);
                                $pathWaka = storage_path('app/private/tanda_tangan/' . $cleanWaka);
                                if(!file_exists($pathWaka)) { $pathWaka = storage_path('app/private/' . $cleanWaka); }
                            }
                        @endphp
                        @if($pathWaka && file_exists($pathWaka))
                            <img src="{{ $pathWaka }}" class="ttd-img">
                        @endif
                    </div>
                    <div class="text-bold underline">( {{ strtoupper($wakaKur) }} )</div>
                    <div>NIP. {{ $nipWakaKur }}</div>
                </td>
                <td width="50%">
                    {{ strtoupper($kontak->kabupaten_kota ?? 'KOTA') }}, {{ strtoupper(\Carbon\Carbon::now()->translatedFormat('d F Y')) }}<br>
                    MENYETUJUI,<br>KEPALA SEKOLAH
                    <div class="spacer-ttd">
                        @php
                            $pathKs = null;
                            if(!empty($fileTtdKepsek)) {
                                $cleanKs = str_replace(['private/', 'storage/', 'public/', 'tanda_tangan/'], '', $fileTtdKepsek);
                                $pathKs = storage_path('app/private/tanda_tangan/' . $cleanKs);
                                if(!file_exists($pathKs)) { $pathKs = storage_path('app/private/' . $cleanKs); }
                            }
                        @endphp
                        @if($pathKs && file_exists($pathKs))
                            <img src="{{ $pathKs }}" class="ttd-img">
                        @endif
                    </div>
                    <div class="text-bold underline">( {{ strtoupper($kepsek) }} )</div>
                    <div>NIP. {{ $nipKepsek }}</div>
                </td>
            </tr>
        </table>
        
        <div class="print-time">
            Waktu Cetak: {{ date('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>