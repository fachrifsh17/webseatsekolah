<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\Auth;

class StorePresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $user = Auth::user();
        $guru = $user->guruStaf;

        if ($guru && !$this->has('kelas_id')) {
            $taAktif = TahunAjaran::where('is_active', true)->first();
            
            if ($taAktif) {
                $kelas = Kelas::where('wali_kelas_id', $guru->id)
                    ->where('tahun_ajaran_id', $taAktif->id)
                    ->where('is_active', 1)
                    ->first();

                if ($kelas) {
                    $this->merge([
                        'kelas_id' => $kelas->id,
                    ]);
                }
            }
        }

        if (!$this->has('tanggal')) {
            $this->merge([
                'tanggal' => date('Y-m-d'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'string'],
            'tanggal' => ['required', 'date'],
            'data_presensi' => ['required', 'array', 'min:1'],
            'data_presensi.*.siswa_id' => ['required', 'string'],
            'data_presensi.*.status' => ['required', 'in:Hadir,Izin,Sakit,Alpa'],
            'data_presensi.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'kelas_id.required' => 'ID Kelas tidak ditemukan atau Anda bukan Wali Kelas aktif.',
            'tanggal.required' => 'Tanggal absensi wajib diisi.',
            'tanggal.date' => 'Format tanggal tidak valid.',
            'data_presensi.required' => 'Data presensi tidak boleh kosong.',
            'data_presensi.array' => 'Format data harus berupa array.',
            'data_presensi.*.siswa_id.required' => 'Siswa harus dipilih.',
            'data_presensi.*.status.required' => 'Status kehadiran harus diisi.',
            'data_presensi.*.status.in' => 'Status harus berupa Hadir, Izin, Sakit, atau Alpa.',
        ];
    }

    public function attributes(): array
    {
        return [
            'kelas_id' => 'ID Kelas',
            'tanggal' => 'Tanggal absensi',
            'data_presensi' => 'Daftar Presensi',
            'data_presensi.*.siswa_id' => 'Siswa',
            'data_presensi.*.status' => 'Status kehadiran',
            'data_presensi.*.keterangan' => 'Keterangan',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}