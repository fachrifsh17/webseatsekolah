<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrestasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "judul"    => ["required", "string", "max:255"],
            "tahun"    => ["nullable", "digits:4", "integer"],
            "tingkat"  => ["nullable", "string", "max:50"],
            "kategori" => ["nullable", "in:Siswa,Sekolah,Guru"], 
            "foto"     => ["nullable", "file", "image", "max:5120"], 
        ];
    }

    public function messages(): array
    {
        return [
            "judul.required"   => "Judul prestasi wajib diisi.",
            "judul.string"     => "Judul prestasi harus berupa teks.",
            "judul.max"        => "Judul prestasi tidak boleh lebih dari 255 karakter.",

            "tahun.digits"     => "Tahun harus terdiri dari 4 digit.",
            "tahun.integer"    => "Tahun harus berupa angka.",

            "tingkat.string"   => "Tingkat prestasi harus berupa teks.",
            "tingkat.max"      => "Tingkat prestasi maksimal 50 karakter.",

            "kategori.in"      => "Kategori prestasi harus salah satu dari: Siswa, Sekolah, atau Guru.",

            "foto.file"        => "File bukti prestasi harus berupa file.",
            "foto.image"       => "File bukti prestasi harus berupa gambar.",
            "foto.max"         => "Ukuran file bukti prestasi maksimal 5MB.",
        ];
    }

    public function attributes(): array
    {
        return [
            "judul"    => "Judul prestasi",
            "tahun"    => "Tahun",
            "tingkat"  => "Tingkat prestasi",
            "kategori" => "Kategori prestasi",
            "foto"     => "Foto bukti prestasi",
        ];
    }
}
