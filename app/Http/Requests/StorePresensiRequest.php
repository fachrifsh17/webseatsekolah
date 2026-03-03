<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
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
        $semesterActive = Semester::where('is_active', true)->first();

        if (!$this->has('tanggal')) {
            $this->merge(['tanggal' => date('Y-m-d')]);
        }

        if ($user->hasRole('Admin')) {
            $requestKelasId = $this->input('kelas_id');
            
            if ($requestKelasId && $semesterActive) {
                $waliKelasRecord = KelasWaliKelas::where('kelas_id', $requestKelasId)
                    ->where('semester_id', $semesterActive->id)
                    ->where('is_active', 1)
                    ->first();

                if ($waliKelasRecord) {
                    $this->merge(['kelas_wali_id' => $waliKelasRecord->id]);
                }
            }
        } else {
            $guru = $user->guruStaf;
            if ($guru && $semesterActive) {
                $waliKelasRecord = KelasWaliKelas::where('guru_staf_id', $guru->id)
                    ->where('semester_id', $semesterActive->id)
                    ->where('is_active', 1)
                    ->first();

                if ($waliKelasRecord) {
                    $this->merge(['kelas_wali_id' => $waliKelasRecord->id]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'kelas_wali_id' => [
                'required',
                'integer',
                'exists:kelas_wali_kelas,id',
                function ($attribute, $value, $fail) {
                    $record = KelasWaliKelas::find($value);
                    if ($record && $record->is_active == 0) {
                        $fail('Data wali kelas tersebut sudah tidak aktif.');
                    }
                },
            ],
            'tanggal' => ['required', 'date'],
            'data_presensi' => ['required', 'array', 'min:1'],
            'data_presensi.*.siswa_id' => ['required', 'string', 'exists:siswa,id'],
            'data_presensi.*.status' => ['required', 'in:Hadir,Izin,Sakit,Alpa'],
            'data_presensi.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'kelas_wali_id.required' => 'Data Wali Kelas tidak ditemukan untuk kelas ini pada semester aktif.',
            'kelas_wali_id.exists' => 'Data Wali Kelas tidak valid.',
            'tanggal.required' => 'Tanggal absensi wajib diisi.',
            'data_presensi.required' => 'Data detail presensi tidak boleh kosong.',
            'data_presensi.*.siswa_id.exists' => 'Salah satu siswa tidak terdaftar di database.',
            'data_presensi.*.status.in' => 'Status harus berupa Hadir, Izin, Sakit, atau Alpa.',
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