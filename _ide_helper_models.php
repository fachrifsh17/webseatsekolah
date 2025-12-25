<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string|null $nama_album
 * @property \Illuminate\Support\Carbon|null $tanggal_kegiatan
 * @property string|null $cover_path Foto sampul album
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Media> $media
 * @property-read int|null $media_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereCoverPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereNamaAlbum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereTanggalKegiatan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereUpdatedAt($value)
 */
	class Album extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $token_hash
 * @property string|null $refresh_token
 * @property int $user_id
 * @property string $expires_at
 * @property string|null $refresh_expires_at
 * @property int $revoked
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken whereRefreshExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken whereRevoked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken whereTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthToken whereUserId($value)
 */
	class AuthToken extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $judul
 * @property string|null $url_link
 * @property \Illuminate\Support\Carbon|null $aktif_sampai
 * @property string|null $foto Banner/Slider foto
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $foto_url
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner aktif()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner whereAktifSampai($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner whereFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner whereJudul($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner whereUrlLink($value)
 */
	class Banner extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $judul
 * @property string|null $isi_berita
 * @property \Illuminate\Support\Carbon|null $tanggal_publikasi
 * @property string|null $foto Foto utama berita
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Berita newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Berita newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Berita query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Berita whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Berita whereFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Berita whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Berita whereIsiBerita($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Berita whereJudul($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Berita whereTanggalPublikasi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Berita whereUpdatedAt($value)
 */
	class Berita extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $alamat_lengkap
 * @property string|null $telepon
 * @property string|null $email_resmi
 * @property string|null $peta_embed_code
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataKontak newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataKontak newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataKontak query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataKontak whereAlamatLengkap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataKontak whereEmailResmi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataKontak whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataKontak wherePetaEmbedCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataKontak whereTelepon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataKontak whereUpdatedAt($value)
 */
	class DataKontak extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $nama_ekskul
 * @property string|null $deskripsi
 * @property string|null $hari
 * @property string|null $jam_mulai
 * @property string|null $jam_selesai
 * @property int|null $pembina_id
 * @property string|null $foto Foto kegiatan ekskul
 * @property string|null $keterangan
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GuruStaf|null $pembina
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler whereDeskripsi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler whereFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler whereHari($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler whereJamMulai($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler whereJamSelesai($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler whereKeterangan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler whereNamaEkskul($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler wherePembinaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ekstrakurikuler whereUpdatedAt($value)
 */
	class Ekstrakurikuler extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $nama_fasilitas
 * @property string|null $foto Nama file foto fasilitas
 * @property string|null $keterangan
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fasilitas newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fasilitas newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fasilitas query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fasilitas whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fasilitas whereFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fasilitas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fasilitas whereKeterangan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fasilitas whereNamaFasilitas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fasilitas whereUpdatedAt($value)
 */
	class Fasilitas extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $guru_staf_id
 * @property int|null $mata_pelajaran_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GuruStaf|null $guru
 * @property-read \App\Models\MataPelajaran|null $mapel
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruMapel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruMapel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruMapel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruMapel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruMapel whereGuruStafId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruMapel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruMapel whereMataPelajaranId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruMapel whereUpdatedAt($value)
 */
	class GuruMapel extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $nip NIP PNS/PPPK (Bisa NULL)
 * @property string|null $nuptk NUPTK Pendidik/Tendik (Bisa NULL)
 * @property string|null $nama
 * @property string|null $jabatan_fungsional Contoh: Guru, Laboran, Staf TU
 * @property string|null $status_kepegawaian PNS, Honorer, Yayasan
 * @property string|null $foto Path/Nama file foto Guru/Staf
 * @property int|null $jurusan_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GuruMapel> $guruMapel
 * @property-read int|null $guru_mapel_count
 * @property-read \App\Models\Jurusan|null $jurusan
 * @property-read \App\Models\Kelas|null $kelas
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PoinSiswa> $poinSiswa
 * @property-read int|null $poin_siswa_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Presensi> $presensi
 * @property-read int|null $presensi_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StrukturJabatan> $strukturJabatan
 * @property-read int|null $struktur_jabatan_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereJabatanFungsional($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereJurusanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereNama($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereNip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereNuptk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereStatusKepegawaian($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuruStaf whereUserId($value)
 */
	class GuruStaf extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $jurusan_id Relasi ke jurusan, misalnya RPL
 * @property int $guru_staf_id Penginput, biasanya Ketua Jurusan
 * @property string $judul
 * @property string|null $penjelasan_jadwal
 * @property string $file_jadwal_path Path/Nama file PDF/Gambar Jadwal Produktif
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GuruStaf $guruStaf
 * @property-read \App\Models\Jurusan $jurusan
 * @method static \Database\Factories\JadwalProduktifFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif whereFileJadwalPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif whereGuruStafId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif whereJudul($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif whereJurusanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif wherePenjelasanJadwal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JadwalProduktif whereUpdatedAt($value)
 */
	class JadwalProduktif extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $judul
 * @property string $tahun_ajaran
 * @property string $semester
 * @property string $file_path Path/Nama file PDF/Gambar Jadwal Jam Pelajaran Sekolah
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TahunAjaran|null $tahunAjaran
 * @method static \Database\Factories\JamSekolahFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JamSekolah newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JamSekolah newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JamSekolah query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JamSekolah whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JamSekolah whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JamSekolah whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JamSekolah whereJudul($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JamSekolah whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JamSekolah whereTahunAjaran($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JamSekolah whereUpdatedAt($value)
 */
	class JamSekolah extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $nama_jurusan
 * @property string|null $deskripsi
 * @property string|null $foto
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GuruStaf> $guruStaf
 * @property-read int|null $guru_staf_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Siswa> $siswa
 * @property-read int|null $siswa_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jurusan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jurusan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jurusan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jurusan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jurusan whereDeskripsi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jurusan whereFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jurusan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jurusan whereNamaJurusan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jurusan whereUpdatedAt($value)
 */
	class Jurusan extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $kegiatan
 * @property \Illuminate\Support\Carbon|null $tanggal_mulai
 * @property \Illuminate\Support\Carbon|null $tanggal_selesai
 * @property string|null $kategori
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KalenderAkademik newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KalenderAkademik newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KalenderAkademik query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KalenderAkademik whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KalenderAkademik whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KalenderAkademik whereKategori($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KalenderAkademik whereKegiatan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KalenderAkademik whereTanggalMulai($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KalenderAkademik whereTanggalSelesai($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KalenderAkademik whereUpdatedAt($value)
 */
	class KalenderAkademik extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $nama_kelas
 * @property int|null $jurusan_id
 * @property int|null $wali_kelas_id
 * @property int|null $tahun_ajaran_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Jurusan|null $jurusan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Presensi> $presensi
 * @property-read int|null $presensi_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Siswa> $siswa
 * @property-read int|null $siswa_count
 * @property-read \App\Models\TahunAjaran|null $tahunAjaran
 * @property-read \App\Models\GuruStaf|null $waliKelas
 * @method static \Database\Factories\KelasFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kelas newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kelas newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kelas query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kelas whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kelas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kelas whereJurusanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kelas whereNamaKelas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kelas whereTahunAjaranId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kelas whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kelas whereWaliKelasId($value)
 */
	class Kelas extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $judul
 * @property string|null $penjelasan_kurikulum
 * @property string|null $file_jadwal_path Path/Nama file PDF/Gambar Jadwal Pelajaran Umum
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kurikulum newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kurikulum newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kurikulum query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kurikulum whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kurikulum whereFileJadwalPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kurikulum whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kurikulum whereJudul($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kurikulum wherePenjelasanKurikulum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kurikulum whereUpdatedAt($value)
 */
	class Kurikulum extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $aksi
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogAdmin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogAdmin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogAdmin query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogAdmin whereAksi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogAdmin whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogAdmin whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogAdmin whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogAdmin whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogAdmin whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogAdmin whereUserId($value)
 */
	class LogAdmin extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $nama_mapel
 * @property int|null $jurusan_id
 * @property string|null $tipe_mapel
 * @property string|null $kategori_mapel
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GuruMapel> $guruMapel
 * @property-read int|null $guru_mapel_count
 * @property-read \App\Models\Jurusan|null $jurusan
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MataPelajaran newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MataPelajaran newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MataPelajaran query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MataPelajaran whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MataPelajaran whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MataPelajaran whereJurusanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MataPelajaran whereKategoriMapel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MataPelajaran whereNamaMapel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MataPelajaran whereTipeMapel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MataPelajaran whereUpdatedAt($value)
 */
	class MataPelajaran extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $album_id
 * @property string|null $media_path Nama file (Foto) atau URL Embed (Video)
 * @property string|null $jenis_media
 * @property string|null $keterangan
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Album|null $album
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereAlbumId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereJenisMedia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereKeterangan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereMediaPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereUpdatedAt($value)
 */
	class Media extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string|null $nama_lengkap
 * @property string|null $telepon
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Siswa> $siswa
 * @property-read int|null $siswa_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\OrangtuaFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Orangtua newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Orangtua newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Orangtua query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Orangtua whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Orangtua whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Orangtua whereNamaLengkap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Orangtua whereTelepon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Orangtua whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Orangtua whereUserId($value)
 */
	class Orangtua extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $judul
 * @property string|null $isi_pengumuman
 * @property \Illuminate\Support\Carbon|null $tanggal_publikasi
 * @property bool|null $penting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $author
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pengumuman newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pengumuman newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pengumuman query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pengumuman whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pengumuman whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pengumuman whereIsiPengumuman($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pengumuman whereJudul($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pengumuman wherePenting($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pengumuman whereTanggalPublikasi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pengumuman whereUpdatedAt($value)
 */
	class Pengumuman extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $nama_lengkap
 * @property string $email
 * @property string|null $subjek
 * @property string $isi_pesan
 * @property string|null $status
 * @property string|null $tanggal_kirim
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pesan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pesan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pesan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pesan whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pesan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pesan whereIsiPesan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pesan whereNamaLengkap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pesan whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pesan whereSubjek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pesan whereTanggalKirim($value)
 */
	class Pesan extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $siswa_id
 * @property int|null $guru_id
 * @property string $tanggal
 * @property string $indikator
 * @property int|null $poin_positif
 * @property int|null $poin_negatif
 * @property int|null $tahun_ajaran_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GuruStaf|null $guruStaf
 * @property-read \App\Models\Siswa $siswa
 * @property-read \App\Models\TahunAjaran|null $tahunAjaran
 * @method static \Database\Factories\PoinSiswaFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa whereGuruId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa whereIndikator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa wherePoinNegatif($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa wherePoinPositif($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa whereSiswaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa whereTahunAjaranId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa whereTanggal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PoinSiswa whereUpdatedAt($value)
 */
	class PoinSiswa extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $nama_platform
 * @property string|null $url_link
 * @property string|null $tipe
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortalSosmed newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortalSosmed newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortalSosmed query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortalSosmed whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortalSosmed whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortalSosmed whereNamaPlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortalSosmed whereTipe($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortalSosmed whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortalSosmed whereUrlLink($value)
 */
	class PortalSosmed extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $url_link Link ke sistem PPDB eksternal
 * @property bool|null $status_ppdb
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PpdbLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PpdbLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PpdbLink query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PpdbLink whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PpdbLink whereStatusPpdb($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PpdbLink whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PpdbLink whereUrlLink($value)
 */
	class PpdbLink extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $siswa_id
 * @property int|null $guru_id
 * @property string $tanggal
 * @property string $status
 * @property string|null $keterangan
 * @property int|null $tahun_ajaran_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Siswa $siswa
 * @property-read \App\Models\TahunAjaran|null $tahunAjaran
 * @method static \Database\Factories\PresensiFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi whereGuruId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi whereKeterangan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi whereSiswaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi whereTahunAjaranId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi whereTanggal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presensi whereUpdatedAt($value)
 */
	class Presensi extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $guru_mapel_id
 * @property int $kelas_id
 * @property int $mata_pelajaran_id
 * @property string $tanggal
 * @property string $jam_masuk
 * @property string $jam_keluar
 * @property string|null $materi
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GuruMapel $guruMapel
 * @property-read \App\Models\Kelas $kelas
 * @property-read \App\Models\MataPelajaran $mataPelajaran
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PresensiSiswaDetail> $rincianSiswa
 * @property-read int|null $rincian_siswa_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel whereGuruMapelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel whereJamKeluar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel whereJamMasuk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel whereKelasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel whereMataPelajaranId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel whereMateri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel whereTanggal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiGuruMapel whereUpdatedAt($value)
 */
	class PresensiGuruMapel extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $presensi_guru_mapel_id
 * @property int $siswa_id
 * @property string $status
 * @property string|null $catatan
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\PresensiGuruMapel $presensiGuruMapel
 * @property-read \App\Models\Siswa $siswa
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiSiswaDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiSiswaDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiSiswaDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiSiswaDetail whereCatatan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiSiswaDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiSiswaDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiSiswaDetail wherePresensiGuruMapelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiSiswaDetail whereSiswaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiSiswaDetail whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PresensiSiswaDetail whereUpdatedAt($value)
 */
	class PresensiSiswaDetail extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $judul
 * @property int|null $tahun
 * @property string|null $tingkat
 * @property string|null $kategori
 * @property string|null $foto Foto utama prestasi
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi whereFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi whereJudul($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi whereKategori($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi whereTahun($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi whereTingkat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prestasi whereUpdatedAt($value)
 */
	class Prestasi extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $sejarah
 * @property string|null $visi
 * @property string|null $misi
 * @property string|null $npsn
 * @property string|null $akreditasi
 * @property int|null $guru_staf_id
 * @property string|null $sambutan_kepsek Teks sambutan Kepala Sekolah
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah whereAkreditasi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah whereGuruStafId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah whereMisi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah whereNpsn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah whereSambutanKepsek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah whereSejarah($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfilSekolah whereVisi($value)
 */
	class ProfilSekolah extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $key_name
 * @property int $attempts
 * @property \Illuminate\Support\Carbon $last_attempt
 * @property \Illuminate\Support\Carbon $created_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimits newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimits newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimits query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimits whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimits whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimits whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimits whereKeyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimits whereLastAttempt($value)
 */
	class RateLimits extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $role_name Nama peran (Contoh: Super Admin, Kurikulum, Operator)
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereRoleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SekolahSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SekolahSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SekolahSetting query()
 */
	class SekolahSetting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $nis
 * @property string|null $nama_lengkap
 * @property string|null $tempat_lahir
 * @property string|null $tanggal_lahir
 * @property string|null $jenis_kelamin
 * @property int|null $kelas_id
 * @property int|null $jurusan_id
 * @property int|null $orangtua_id
 * @property string|null $foto
 * @property string|null $no_telp_siswa
 * @property string|null $alamat
 * @property string|null $status_aktif
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Jurusan|null $jurusan
 * @property-read \App\Models\Kelas|null $kelas
 * @property-read \App\Models\Orangtua|null $orangtua
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PoinSiswa> $poinSiswa
 * @property-read int|null $poin_siswa_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Presensi> $presensi
 * @property-read int|null $presensi_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\SiswaFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereAlamat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereJenisKelamin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereJurusanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereKelasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereNamaLengkap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereNis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereNoTelpSiswa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereOrangtuaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereStatusAktif($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereTanggalLahir($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereTempatLahir($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Siswa whereUserId($value)
 */
	class Siswa extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $guru_staf_id
 * @property string|null $nama_jabatan_struktural Contoh: Kepala Sekolah, Wakasek Kurikulum
 * @property \Illuminate\Support\Carbon|null $periode_mulai
 * @property int|null $urutan_tampil
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GuruStaf|null $guru
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrukturJabatan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrukturJabatan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrukturJabatan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrukturJabatan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrukturJabatan whereGuruStafId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrukturJabatan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrukturJabatan whereNamaJabatanStruktural($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrukturJabatan wherePeriodeMulai($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrukturJabatan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrukturJabatan whereUrutanTampil($value)
 */
	class StrukturJabatan extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $nama
 * @property string|null $semester
 * @property int|null $aktif
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\JamSekolah> $jamSekolah
 * @property-read int|null $jam_sekolah_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Kelas> $kelas
 * @property-read int|null $kelas_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PoinSiswa> $poinSiswa
 * @property-read int|null $poin_siswa_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Presensi> $presensi
 * @property-read int|null $presensi_count
 * @method static \Database\Factories\TahunAjaranFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TahunAjaran newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TahunAjaran newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TahunAjaran query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TahunAjaran whereAktif($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TahunAjaran whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TahunAjaran whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TahunAjaran whereNama($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TahunAjaran whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TahunAjaran whereUpdatedAt($value)
 */
	class TahunAjaran extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $username Username untuk login
 * @property string $password Disimpan dalam bentuk hash (Wajib Aman)
 * @property string|null $nama_lengkap Nama yang ditampilkan di dashboard
 * @property int|null $role_id
 * @property int|null $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GuruStaf|null $guru
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\Orangtua|null $orangtua
 * @property-read \App\Models\Role|null $role
 * @property-read \App\Models\Siswa|null $siswa
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNamaLengkap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 */
	class User extends \Eloquent {}
}

