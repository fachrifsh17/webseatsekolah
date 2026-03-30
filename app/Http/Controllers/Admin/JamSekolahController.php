<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{JamSekolah, Semester, ProfilSekolah, DataKontak};
use App\Http\Resources\JamSekolahResource;
use App\Http\Requests\{StoreJamSekolahRequest, UpdateJamSekolahRequest};
use App\Exports\JamSekolahExport;
use App\Imports\JamSekolahImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JamSekolahController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['update', 'store', 'import', 'destroy', 'bulkDelete']);

        $this->authorizeResource(JamSekolah::class, 'jam_sekolah');
    }

    private function resolveSemesterId(Request $request)
    {
        if ($request->has('semester_id') && !empty($request->semester_id)) {
            return $request->semester_id;
        }

        $aktif = Semester::where('is_active', true)->first();
        return $aktif ? $aktif->id : null;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $semesterId = $this->resolveSemesterId($request);
            $perPage = $request->query('per_page', 10);
            
            $query = JamSekolah::with('semester.tahunAjaran');

            if ($semesterId) {
                $query->where('semester_id', $semesterId);
            }

            $data = $query->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')")
                          ->orderBy('waktu_mulai')
                          ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => JamSekolahResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                    'filter_semester_id' => $semesterId,
                    'is_auto_selected'   => !$request->has('semester_id')
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jam sekolah.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $this->authorize('viewAny', JamSekolah::class);

            $semesterId = $this->resolveSemesterId($request);
            
            if (!$semesterId) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Semester tidak ditentukan.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $sm = Semester::with('tahunAjaran')->find($semesterId);
            
            if ($sm) {
                $namaTA = str_replace(['/', '\\', ' ', '-'], '_', $sm->tahunAjaran->nama);
                $namaSem = str_replace(['/', '\\', ' ', '-'], '_', $sm->nama);
                $labelFile = strtoupper($namaTA . '_' . $namaSem);
            } else {
                $labelFile = date('Ymd_His');
            }

            $profil = ProfilSekolah::first();
            $kontak = DataKontak::first();
            
            $fileName = 'JAM_SEKOLAH_' . $labelFile . '.xlsx';

            return Excel::download(new JamSekolahExport($profil, $kontak, $semesterId), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal ekspor.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', JamSekolah::class);

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
            'semester_id' => 'nullable|exists:semesters,id'
        ]);

        try {
            $semesterId = $request->input('semester_id') ?? $this->resolveSemesterId($request);
            $import = new JamSekolahImport($semesterId);
            
            DB::transaction(fn() => Excel::import($import, $request->file('file')));

            return response()->json([
                'success'   => true,
                'message'   => 'Impor selesai.',
                'conflicts' => $import->getMessages()
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal impor: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function importPreview(Request $request): JsonResponse
    {
        $this->authorize('create', JamSekolah::class);
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
            'semester_id' => 'nullable'
        ]);

        try {
            $semesterId = $request->query('semester_id') ?? $this->resolveSemesterId($request);
            $rows = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\WithHeadingRow {
                public function headingRow(): int { return 1; }
            }, $request->file('file'))[0] ?? [];

            $existingData = JamSekolah::where('semester_id', $semesterId)->get();
            $previewData = [];
            $processedInFile = [];

            foreach ($rows as $index => $row) {
                $errors = [];
                $hari = trim($row['hari'] ?? '');
                $jamKe = $row['jam_ke'] ?? null;
                $mulaiRaw = $row['waktu_mulai'] ?? null;
                $selesaiRaw = $row['waktu_selesai'] ?? null;
                $jenis = trim($row['jenis'] ?? '');
                
                $mulai = null;
                $selesai = null;

                if (!in_array($hari, ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'])) {
                    $errors[] = "Hari '{$hari}' tidak valid.";
                }

                $validJenis = ['Pelajaran', 'Istirahat', 'Kegiatan', 'Upacara'];
                if (!in_array(ucfirst(strtolower($jenis)), $validJenis)) {
                    $errors[] = "Jenis '{$jenis}' tidak valid.";
                }

                try {
                    if ($mulaiRaw && $selesaiRaw) {
                        $mulai = \Carbon\Carbon::parse($mulaiRaw)->format('H:i:s');
                        $selesai = \Carbon\Carbon::parse($selesaiRaw)->format('H:i:s');
                        if ($selesai <= $mulai) {
                            $errors[] = "Waktu selesai harus lebih besar dari waktu mulai.";
                        }
                    } else {
                        $errors[] = "Waktu mulai/selesai kosong.";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Format waktu tidak valid.";
                }

                if ($jamKe) {
                    $keyJam = $hari . '_jam_' . $jamKe;
                    if (isset($processedInFile['jam'][$keyJam])) {
                        $errors[] = "Jam ke-{$jamKe} hari {$hari} duplikat di file.";
                    }
                    $processedInFile['jam'][$keyJam] = true;

                    foreach ($existingData as $exist) {
                        if ($exist->hari === $hari && $exist->jam_ke == $jamKe) {
                            $errors[] = "Jam ke-{$jamKe} hari {$hari} sudah ada di database.";
                            break;
                        }
                    }
                }

                if ($mulai && $selesai) {
                    if (isset($processedInFile['waktu'][$hari])) {
                        foreach ($processedInFile['waktu'][$hari] as $time) {
                            if ($mulai < $time['selesai'] && $selesai > $time['mulai']) {
                                $errors[] = "Waktu bertabrakan dengan baris lain di file.";
                                break;
                            }
                        }
                    }
                    $processedInFile['waktu'][$hari][] = ['mulai' => $mulai, 'selesai' => $selesai];

                    foreach ($existingData as $exist) {
                        if ($exist->hari === $hari) {
                            if ($mulai < $exist->waktu_selesai && $selesai > $exist->waktu_mulai) {
                                $errors[] = "Waktu bertabrakan dengan database.";
                                break;
                            }
                        }
                    }
                }

                $previewData[] = [
                    'hari' => $hari,
                    'jam_ke' => $jamKe,
                    'waktu_mulai' => $mulaiRaw,
                    'waktu_selesai' => $selesaiRaw,
                    'jenis' => $jenis,
                    'keterangan' => $row['keterangan'] ?? null,
                    'errors' => $errors,
                    'is_valid' => empty($errors)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $previewData,
                'meta' => [
                    'semester_id' => $semesterId,
                    'total_rows' => count($previewData),
                    'any_error' => collect($previewData)->contains('is_valid', false)
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal preview: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorize('delete', JamSekolah::class);
        $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:jam_sekolah,id']);

        try {
            DB::transaction(fn() => JamSekolah::whereIn('id', $request->ids)->delete());
            return response()->json([
                'success' => true,
                'message' => count($request->ids) . ' data berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal hapus massal.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreJamSekolahRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        if (!isset($validated['semester_id'])) {
            $semesterAktif = Semester::where('is_active', true)->first();
            if (!$semesterAktif) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tidak ada semester aktif.'
                ], Response::HTTP_BAD_REQUEST);
            }
            $validated['semester_id'] = $semesterAktif->id;
        }

        if ($validated['waktu_selesai'] <= $validated['waktu_mulai']) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu selesai harus lebih besar dari waktu mulai.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $exists = JamSekolah::where('semester_id', $validated['semester_id'])
            ->where('hari', $validated['hari'])
            ->where('jam_ke', $validated['jam_ke'])
            ->whereNotNull('jam_ke')
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false, 
                'message' => 'Nomor jam ' . $validated['jam_ke'] . ' sudah ada di hari ' . $validated['hari'] . '.'
            ], Response::HTTP_CONFLICT);
        }

        $overlap = JamSekolah::where('semester_id', $validated['semester_id'])
            ->where('hari', $validated['hari'])
            ->where(function ($query) use ($validated) {
                $query->where('waktu_mulai', '<', $validated['waktu_selesai'])
                      ->where('waktu_selesai', '>', $validated['waktu_mulai']);
            })->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu bertabrakan dengan jadwal lain di hari yang sama.'
            ], Response::HTTP_CONFLICT);
        }

        try {
            $jamSekolah = DB::transaction(fn() => JamSekolah::create($validated));
            return response()->json([
                'success' => true, 
                'data' => new JamSekolahResource($jamSekolah->load('semester.tahunAjaran'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal simpan.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(JamSekolah $jamSekolah): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new JamSekolahResource($jamSekolah->load('semester.tahunAjaran'))
        ], Response::HTTP_OK);
    }

    public function update(UpdateJamSekolahRequest $request, JamSekolah $jamSekolah): JsonResponse
    {
        $validated = $request->validated();
        $semesterId = $validated['semester_id'] ?? $jamSekolah->semester_id;
        $hari = $validated['hari'] ?? $jamSekolah->hari;
        $mulai = $validated['waktu_mulai'] ?? $jamSekolah->waktu_mulai;
        $selesai = $validated['waktu_selesai'] ?? $jamSekolah->waktu_selesai;
        $jamKe = $validated['jam_ke'] ?? $jamSekolah->jam_ke;

        if ($selesai <= $mulai) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu selesai harus lebih besar dari waktu mulai.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $exists = JamSekolah::where('semester_id', $semesterId)
            ->where('hari', $hari)
            ->where('jam_ke', $jamKe)
            ->whereNotNull('jam_ke')
            ->where('id', '!=', $jamSekolah->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false, 
                'message' => 'Nomor jam ke-' . $jamKe . ' sudah digunakan di hari ' . $hari . '.'
            ], Response::HTTP_CONFLICT);
        }

        $overlap = JamSekolah::where('semester_id', $semesterId)
            ->where('hari', $hari)
            ->where('id', '!=', $jamSekolah->id)
            ->where(function ($query) use ($mulai, $selesai) {
                $query->where('waktu_mulai', '<', $selesai)
                      ->where('waktu_selesai', '>', $mulai);
            })->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu bertabrakan dengan jadwal lain di hari yang sama.'
            ], Response::HTTP_CONFLICT);
        }

        try {
            DB::transaction(fn() => $jamSekolah->update($validated));
            return response()->json([
                'success' => true, 
                'data' => new JamSekolahResource($jamSekolah->load('semester.tahunAjaran'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal update.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(JamSekolah $jamSekolah): JsonResponse
    {
        try {
            DB::transaction(fn() => $jamSekolah->delete());
            return response()->json([
                'success' => true, 
                'message' => 'Berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal hapus.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}