<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jadwal Mapel Kelas</title>
    <style>
        @page { 
            margin: 0.5cm; 
            margin-bottom: 3.5cm; 
        } 
        body { font-family: sans-serif; line-height: 1.1; margin: 0; padding: 0; color: #000; }
        
        .header-table { width: 100%; border: none; border-bottom: 2px solid #000; margin-bottom: 8px; }
        .school-name { font-size: 14pt; font-weight: bold; text-transform: uppercase; }
        .info-table { width: 100%; margin-bottom: 5px; font-size: 8pt; }
        .info-table td { border: none !important; padding: 1px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f2f2f2; border: 1px solid #000; padding: 5px; font-size: 8pt; text-transform: uppercase; }
        td { border: 1px solid #000; padding: 4px; font-size: 7.5pt; vertical-align: middle; }
        
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
        .signature-table td { border: none !important; padding: 5px; text-align: center; vertical-align: top; }
        .spacer { height: 45px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td width="100%" class="text-center" style="border: none !important;">
                <div style="font-size: 10pt; font-weight: bold;">PEMERINTAH PROVINSI {{ strtoupper($kontak->provinsi ?? 'JAWA BARAT') }}</div>
                <div style="font-size: 10pt; font-weight: bold;">DINAS PENDIDIKAN</div>
                <div style="font-size: 9pt; font-weight: bold;">{{ strtoupper($profil->cabang_dinas ?? 'CABANG DINAS PENDIDIKAN WILAYAH XII') }}</div>
                <div class="school-name">{{ $profil->nama_sekolah }}</div>
                <div style="font-size: 8pt;">
                    @php
                        $alamatParts = array_filter([
                            $kontak->alamat_jalan ?? null,
                            isset($kontak->desa_kelurahan) ? 'Desa ' . $kontak->desa_kelurahan : null,
                            isset($kontak->kecamatan) ? 'Kec. ' . $kontak->kecamatan : null,
                            $kontak->kabupaten_kota ?? null,
                            $kontak->provinsi ?? null,
                            $kontak->kode_pos ?? null
                        ]);
                        echo implode(', ', $alamatParts);
                    @endphp
                    <br>
                    Telp: {{ $kontak->telepon ?? '-' }} | Email: {{ $kontak->email_resmi ?? '-' }} | NPSN: {{ $profil->npsn ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="text-center text-bold" style="font-size: 10pt; margin-top: 10px;">DAFTAR PENUGASAN GURU MATA PELAJARAN</div>
    <div class="text-center text-bold" style="font-size: 9pt; margin-bottom: 10px;">
        TAHUN PELAJARAN {{ $tahun->nama ?? '' }} {{ strtoupper($tahun->semester ?? '') }}
    </div>

    <table class="info-table">
        <tr>
            <td width="12%">Kelas</td><td width="2%">:</td><td width="36%" class="text-bold">{{ $kelas }}</td>
            <td width="12%">Hari</td><td width="2%">:</td><td width="36%" class="text-bold">{{ strtoupper($hari) }}</td>
        </tr>
        <tr>
            <td>Kategori</td><td>:</td><td class="text-bold">{{ strtoupper($kategori) }}</td>
            <td>Status Data</td><td>:</td><td class="text-bold">AKTIF</td>
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
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center text-bold {{ $lastDay !== $item->hari ? 'bg-gray' : '' }}">
                    {{ $lastDay !== $item->hari ? strtoupper($item->hari) : '' }}
                </td>
                <td>{{ strtoupper($item->guru->nama ?? '-') }}</td>
                <td class="text-center">{{ $item->guru->nip ?? '-' }}</td>
                <td>{{ strtoupper($item->mapel->nama_mapel ?? '-') }}</td>
                <td class="text-center">{{ strtoupper($item->mapel->kategori_mapel ?? '-') }}</td>
                <td class="text-center">
                    @if($item->jamMulai && $item->jamSelesai)
                        Jam Ke {{ $item->jamMulai->jam_ke }} - {{ $item->jamSelesai->jam_ke }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @php $lastDay = $item->hari; @endphp
            @empty
            <tr>
                <td colspan="7" class="text-center">Tidak ada data jadwal ditemukan.</td>
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
                    <div class="spacer"></div>
                    <span class="text-bold underline">{{ strtoupper($wakaKur) }}</span><br>
                    NIP. {{ $nipWakaKur }}
                </td>

                <td width="50%">
                    {{ strtoupper($kontak->kabupaten_kota ?? 'TASIKMALAYA') }}, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}<br>
                    Kepala Sekolah
                    <div class="spacer"></div>
                    <span class="text-bold underline">{{ strtoupper($kepsek) }}</span><br>
                    NIP. {{ $nipKepsek }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>