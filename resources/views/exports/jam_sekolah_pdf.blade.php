<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { 
            size: a4 landscape; 
            margin: 0.5cm 0.8cm; 
        } 
        
        body { 
            font-family: 'Helvetica', Arial, sans-serif; 
            font-size: 7.5pt; 
            margin: 0; 
            padding: 0; 
            color: #000;
        }

        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .underline { text-decoration: underline; }
        
        .kop { 
            border-bottom: 3px double #000; 
            margin-bottom: 12px; 
            padding-bottom: 5px;
            width: 100%;
        }

        /* Container Kop dengan Logo */
        .kop-table { width: 100%; border: none !important; }
        .kop-table td { border: none !important; padding: 0; vertical-align: middle; }
        .logo-prov { width: 65px; height: auto; }

        .kop .instansi { font-size: 10pt; font-weight: bold; margin: 0; line-height: 1.2; }
        .kop .sekolah { font-size: 14pt; font-weight: bold; margin: 2px 0; line-height: 1.2; }
        .kop .alamat { font-size: 8pt; margin: 1px 0; font-weight: normal; }
        
        .judul { margin-bottom: 12px; font-weight: bold; font-size: 10pt; }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            table-layout: fixed; 
        }

        th, td { 
            border: 1px solid #000;
            padding: 5px 2px; 
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
        }

        th { 
            background-color: transparent; 
            font-weight: bold;
        }
        
        .istirahat { background-color: #FFFF00 !important; font-weight: bold; }
        .kegiatan { background-color: #C6E0B4 !important; }

        .col-pukul { width: 8%; }
        .col-hari { width: 12%; }

        .ttd-container {
            margin-top: 25px;
            width: 100%;
        }
        .ttd-table {
            border: none !important;
            width: 100%;
        }
        .ttd-table td {
            border: none !important;
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0;
            font-size: 9pt;
            line-height: 1.4;
        }
        .spacer-ttd { height: 60px; position: relative; }
    </style>
</head>
<body>
    <div class="kop">
        <table class="kop-table">
            <tr>
                <td width="70">
                    @php
                        $cleanLogoProv = str_replace(['uploads/profil/', 'public/', 'storage/'], '', $profil->logo_provinsi);
                        $pathLogoProv = public_path('uploads/profil/' . $cleanLogoProv);
                    @endphp
                    @if($profil->logo_provinsi && file_exists($pathLogoProv))
                        <img src="{{ $pathLogoProv }}" class="logo-prov">
                    @else
                        <div style="width: 65px;"></div>
                    @endif
                </td>
                <td class="text-center" style="padding-right: 70px;">
                    <div class="instansi">PEMERINTAH PROVINSI {{ strtoupper($kontak->provinsi ?? 'JAWA BARAT') }}</div>
                    <div class="instansi">DINAS PENDIDIKAN</div>
                    <div class="instansi">{{ strtoupper($profil->cadis ?? 'CABANG DINAS PENDIDIKAN') }}</div>
                    <div class="sekolah">{{ strtoupper($profil->nama_sekolah ?? 'NAMA SEKOLAH') }}</div>
                    <div class="alamat">{{ $alamat_lengkap }}</div>
                    <div class="alamat">
                        Telp: {{ $kontak->telepon ?? '-' }} | Email: {{ $kontak->email_resmi ?? '-' }} | NPSN: {{ $profil->npsn ?? '-' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="judul text-center">
        <div class="uppercase">PENYESUAIAN JAM PELAJARAN</div>
        <div class="uppercase">
            TAHUN PELAJARAN {{ $ta->nama ?? '' }} SEMESTER {{ $semester->nama ?? '' }}
        </div>
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
                            $jenisTrim = strtolower(trim($jam->jenis));
                            if ($jenisTrim === 'pelajaran') {
                                $label = $jam->jam_ke;
                            } elseif ($jenisTrim === 'istirahat') {
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
                        <td></td>
                        <td></td>
                    @endif
                @endforeach
            </tr>
            @endfor
        </tbody>
    </table>

    <div class="ttd-container">
        <table class="ttd-table">
            <tr>
                <td>
                    <br>
                    Mengetahui,<br>
                    Waka Kurikulum
                    <div class="spacer-ttd">
                        @php
                            $pathWaka = null;
                            if($waka && isset($waka->file_ttd) && $waka->file_ttd) {
                                $cleanWaka = str_replace(['storage/', 'public/'], '', $waka->file_ttd);
                                $pathWaka = storage_path('app/' . $cleanWaka);
                            }
                        @endphp
                        @if($pathWaka && file_exists($pathWaka))
                            <img src="{{ $pathWaka }}" style="max-height: 70px; max-width: 100%; position: absolute; left: 50%; transform: translateX(-50%); top: -5px;">
                        @endif
                    </div>
                    <div class="text-bold underline">( {{ strtoupper($waka->nama ?? '____________________') }} )</div>
                    <div>NIP. {{ $waka->nip ?? '...........................' }}</div>
                </td>
                <td>
                    {{ strtoupper($kontak->kabupaten_kota ?? 'TASIKMALAYA') }}, {{ $tanggal_cetak }}<br>
                    Menyetujui,<br>
                    Kepala Sekolah
                    <div class="spacer-ttd">
                        @php
                            $pathKs = null;
                            if($ks && isset($ks->file_ttd) && $ks->file_ttd) {
                                $cleanKs = str_replace(['storage/', 'public/'], '', $ks->file_ttd);
                                $pathKs = storage_path('app/' . $cleanKs);
                            }
                        @endphp
                        @if($pathKs && file_exists($pathKs))
                            <img src="{{ $pathKs }}" style="max-height: 70px; max-width: 100%; position: absolute; left: 50%; transform: translateX(-50%); top: -5px;">
                        @endif
                    </div>
                    <div class="text-bold underline">( {{ strtoupper($ks->nama ?? '____________________') }} )</div>
                    <div>NIP. {{ $ks->nip ?? '...........................' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 15px; font-size: 6pt; font-style: italic; color: #555;">
        Dicetak pada: {{ date('d/m/Y H:i:s') }}
    </div>
</body>
</html>