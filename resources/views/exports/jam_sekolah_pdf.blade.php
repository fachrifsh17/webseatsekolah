<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { 
            size: a4 landscape; 
            margin: 0.5cm; 
        }
        
        body { 
            font-family: 'Helvetica', Arial, sans-serif; 
            font-size: 7pt; 
            margin: 0; 
            padding: 0; 
        }

        .text-center { text-align: center; }
        
        /* Kop Surat (Sesuai Row 1-5 di Excel) */
        .kop { 
            border-bottom: 3px double #000; /* Sesuai BORDER_THICK di Excel */
            margin-bottom: 10px; 
            line-height: 1.2; 
        }
        .kop h2, .kop h3 { margin: 0; padding: 0; }
        .kop h3 { font-size: 10pt; }
        .kop h2 { font-size: 12pt; }
        .kop p { margin: 2px 0; font-size: 8pt; }
        
        /* Judul (Sesuai Row 7-8 di Excel) */
        .judul { margin-bottom: 10px; font-weight: bold; font-size: 9pt; }

        /* Tabel (Sesuai A11 ke bawah di Excel) */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            table-layout: fixed; 
        }

        th, td { 
            border: 1px solid #000; /* Border Thin */
            padding: 4px 2px; 
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
        }

        /* Header (Sesuai Row 11 di Excel) */
        th { 
            background-color: #f2f2f2; 
            font-weight: bold; 
            text-transform: uppercase;
        }
        
        /* Warna (Sesuai ARGB di Excel) */
        .istirahat { background-color: #FFFF00 !important; } /* Kuning */
        .kegiatan { background-color: #C6E0B4 !important; } /* Hijau Muda */

        .col-pukul { width: 7.5%; }
        .col-hari { width: 12.5%; }

    </style>
</head>
<body>
    <div class="kop text-center">
        <h3>PEMERINTAH PROVINSI JAWA BARAT</h3>
        <h3>DINAS PENDIDIKAN</h3>
        <h2>{{ strtoupper($profil->nama_sekolah ?? 'NAMA SEKOLAH') }}</h2>
        <p>{{ $kontak->alamat_lengkap ?? '' }} | Telp: {{ $kontak->telepon ?? '' }}</p>
        <p>Email: {{ $kontak->email_resmi ?? '' }} | NPSN: {{ $profil->npsn ?? '-' }}</p>
    </div>

    <div class="judul text-center">
        <div>PENYESUAIAN JAM PELAJARAN</div>
        <div>TAHUN PELAJARAN {{ strtoupper($ta->nama ?? '') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($hariList as $hari)
                    <th class="col-pukul">PUKUL</th>
                    <th class="col-hari">{{ strtoupper($hari == 'Jumat' ? "JUM'AT" : $hari) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php
                // Logika mencari baris maksimal agar tabel rata (seperti rowStart 12 di Excel)
                $maxRows = 0;
                foreach($hariList as $h) {
                    $count = isset($dataPerHari[$h]) ? count($dataPerHari[$h]) : 0;
                    if($count > $maxRows) $maxRows = $count;
                }
            @endphp

            @for($i = 0; $i < $maxRows; $i++)
            <tr>
                @foreach($hariList as $hari)
                    @php 
                        $jam = $dataPerHari[$hari][$i] ?? null; 
                        $class = '';
                        $label = '';
                        
                        if ($jam) {
                            $jenisTrim = ucfirst(strtolower(trim($jam->jenis)));
                            
                            // Penentuan Label (Sama dengan Logika Excel)
                            if ($jenisTrim === 'Pelajaran') {
                                $label = $jam->jam_ke;
                            } elseif ($jenisTrim === 'Istirahat') {
                                $label = 'ISTIRAHAT';
                                $class = 'istirahat';
                            } else {
                                $label = strtoupper($jam->keterangan ?? 'KEGIATAN');
                                $class = 'kegiatan';
                            }
                        }
                    @endphp

                    @if($jam)
                        <td class="{{ $class }} col-pukul">
                            {{ date('H.i', strtotime($jam->waktu_mulai)) }} - {{ date('H.i', strtotime($jam->waktu_selesai)) }}
                        </td>
                        <td class="{{ $class }} col-hari">
                            {{ $label }}
                        </td>
                    @else
                        {{-- Sel Kosong Jika Data Tidak Ada (Menjaga Struktur Tabel) --}}
                        <td></td>
                        <td></td>
                    @endif
                @endforeach
            </tr>
            @endfor
        </tbody>
    </table>

    <div style="margin-top: 10px; font-size: 6pt; font-style: italic;">
        Dicetak pada: {{ date('d/m/Y H:i:s') }}
    </div>
</body>
</html>