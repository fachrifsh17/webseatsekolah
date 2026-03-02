<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Semester;
use App\Models\KelasWaliKelas;

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
        $semesterActive = Semester::where('is_active', true)->first();

        // Logika untuk menentukan kelas_id jika admin/wali kelas tidak mengirimkannya
        if ($guru && !$this->has('kelas_id') && $semesterActive) {
            $waliKelasRecord = KelasWaliKelas::where('guru_staf_id', $guru->id)
                ->where('semester_id', $semesterActive->id)
                ->where('is_active', 1)
                ->first();

            if ($waliKelasRecord) {
                $this->merge([
                    'kelas_id' => $waliKelasRecord->kelas_id,
                ]);
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
            // Validasi keberadaan kelas_id yang dikirim atau di-merge
            'kelas_id' => [
                'required', 
                'string',
                function ($attribute, $value, $fail) {
                    // Validasi tambahan: Pastikan wali kelas tersebut benar-benar aktif untuk kelas ini di semester berjalan
                    $semesterActive = Semester::where('is_active', true)->first();
                    if (!$semesterActive) {
                        return $fail('Tidak ada semester yang sedang aktif.');
                    }

                    $exists = KelasWaliKelas::where('kelas_id', $value)
                        ->where('semester_id', $semesterActive->id)
                        ->where('is_active', 1)
                        ->whereHas('guruStaf', function ($query) {
                            $query->where('is_active', 1);
                        })
                        ->exists();

                    if (!$exists) {
                        $fail('Wali kelas tidak ditemukan atau tidak aktif untuk kelas ini pada semester berjalan.');
                    }
                },
            ],
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