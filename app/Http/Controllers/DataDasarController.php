<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\Kepmen;
use App\Models\Misi;
use App\Models\Opd;
use App\Models\Program;
use App\Models\Sasaran;
use App\Models\Strategi;
use App\Models\SubKegiatan;
use App\Models\Tujuan;
use App\Models\Visi;
use App\Models\ArahKebijakan;
use App\Models\DataDasarRelasi;
use App\Models\Indikator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DataDasarController extends Controller
{
    // Toggle prioritas program (AJAX/post)
    public function togglePrioritas(Request $request, Program $program)
    {
        $program->is_prioritas = !$program->is_prioritas;
        $program->save();
        return response()->json(['success' => true, 'is_prioritas' => $program->is_prioritas]);
    }

    // Halaman daftar program prioritas per OPD
    public function programPrioritas(Request $request)
    {
        $opdId = $request->get('opd_id');
        $query = Program::with('opd')
            ->where('is_prioritas', true)
            ->where('jenis_program', 'program');
        if ($opdId) {
            $query->where('opd_id', $opdId);
        }
        // Search dan sort sederhana
        if ($search = $request->get('search')) {
            $query->where(function($q) use ($search) {
                $q->where('nama_rincian', 'like', "%$search%")
                  ->orWhere('kode_rek', 'like', "%$search%")
                  ->orWhere('deskripsi', 'like', "%$search%") ;
            });
        }
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');
        $query->orderBy($sort, $direction);
        $programs = $query->paginate(10)->withQueryString();
        $opds = Opd::where('is_active', true)->get(['id', 'nama', 'singkatan']);
        return Inertia::render('DataDasar/ProgramPrioritas', [
            'programs' => $programs,
            'opds' => $opds,
            'filters' => [
                'opd_id' => $opdId,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function index(Request $request): Response
    {
        $documentType = $request->get('document_type', 'rpjmd');
        $visi = Visi::with([
            'misi.tujuan.sasaran.strategi.arahKebijakan',
        ])->where('document_type', $documentType)->get();

        $program = Program::with(['kepmen', 'kegiatan.subKegiatan', 'opd'])
            ->where('document_type', $documentType)
            ->get();

        $kepmen = Kepmen::all(['id', 'kode', 'nama']);
        $opds = Opd::where('is_active', true)->get(['id', 'nama', 'singkatan']);

        return Inertia::render('DataDasar/Index', compact('visi', 'program', 'kepmen', 'opds', 'documentType'));
    }

    public function menu(Request $request): Response
    {
        $activeKepmen = Kepmen::find($request->session()->get('active_kepmen_id'));

        return Inertia::render('DataDasar/Menu', [
            'activePeraturan' => $activeKepmen ? [
                'id' => $activeKepmen->id,
                'kode' => $activeKepmen->kode,
                'nama' => $activeKepmen->nama,
            ] : null,
        ]);
    }

    public function ikkUnmapped(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));

        $query = Indikator::withoutGlobalScopes()
            ->where('jenis_indikator', 'IKK')
            ->whereNull('opd_id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('uraian', 'like', "%{$search}%")
                    ->orWhere('satuan', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        $rows = $query
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(function ($r) {
                $meta = json_decode((string) ($r->keterangan ?? ''), true);
                $urusan1 = is_array($meta) ? ($meta['urusan_1'] ?? '-') : '-';
                $urusan2 = is_array($meta) ? ($meta['urusan_2'] ?? '-') : '-';
                $suggestedOpdName = is_string($urusan2) ? $this->resolveIkkOpdNameFromUrusan2($urusan2) : null;

                $suggestedOpdId = null;
                if ($suggestedOpdName) {
                    $candidate = Opd::withoutGlobalScopes()
                        ->where('is_active', true)
                        ->where('nama', $suggestedOpdName)
                        ->first(['id']);
                    $suggestedOpdId = $candidate?->id;
                }

                return [
                    'id' => $r->id,
                    'uraian' => $r->uraian,
                    'satuan' => $r->satuan,
                    'urusan_1' => $urusan1,
                    'urusan_2' => $urusan2,
                    'suggested_opd_name' => $suggestedOpdName,
                    'suggested_opd_id' => $suggestedOpdId,
                ];
            });

        $opds = Opd::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'singkatan']);

        return Inertia::render('DataDasar/IkkUnmapped', [
            'rows' => $rows,
            'opds' => $opds,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function assignIkkOpd(Request $request, Indikator $indikator)
    {
        abort_if($indikator->jenis_indikator !== 'IKK', 404);

        $data = $request->validate([
            'opd_id' => 'required|exists:opds,id',
        ]);

        $indikator->update([
            'opd_id' => (int) $data['opd_id'],
        ]);

        return redirect()->back()->with('success', 'OPD untuk IKK berhasil diperbarui.');
    }

    public function level(Request $request, string $level): Response
    {
        $level = $this->normalizeLevel($level);
        abort_if($level === null, 404);

        $search = trim((string) $request->get('search', ''));

        $activeKepmen = Kepmen::find($request->session()->get('active_kepmen_id'));

        [$rows, $parents] = $this->buildLevelData($level, $activeKepmen?->id, $search);

        return Inertia::render('DataDasar/Level', [
            'level' => $level,
            'rows' => $rows,
            'parents' => $parents,
            'filters' => [
                'search' => $search,
            ],
            'activePeraturan' => $activeKepmen ? [
                'id' => $activeKepmen->id,
                'kode' => $activeKepmen->kode,
                'nama' => $activeKepmen->nama,
            ] : null,
        ]);
    }

    public function storeLevel(Request $request, string $level)
    {
        $level = $this->normalizeLevel($level);
        abort_if($level === null, 404);

        $activeKepmenId = $request->session()->get('active_kepmen_id');
        $opdId = $request->user()?->opd_id;
        $currentYear = (int) now()->year;

        if (in_array($level, ['iku', 'ikk'], true)) {
            $validated = $request->validate([
                'uraian' => 'required|string',
                'satuan' => 'required|string|max:100',
                'keterangan' => 'nullable|string',
            ]);
            Indikator::create([
                'opd_id' => $opdId,
                'document_type' => 'rpjmd',
                'jenis_indikator' => strtoupper($level),
                'uraian' => $validated['uraian'],
                'satuan' => $validated['satuan'],
                'jenis' => 'output',
                'sifat' => 'maximize',
                'keterangan' => $validated['keterangan'] ?? null,
            ]);
        } elseif ($level === 'visi') {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string',
                'deskripsi' => 'required|string',
            ]);
            Visi::create([
                'opd_id' => $opdId,
                'document_type' => 'rpjmd',
                'kode' => $validated['kode'],
                'uraian' => $validated['uraian'],
                'deskripsi' => $validated['deskripsi'],
                'tahun_awal' => $currentYear,
                'tahun_akhir' => $currentYear + 4,
            ]);
        } elseif ($level === 'misi') {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string',
                'deskripsi' => 'required|string',
            ]);
            Misi::create(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        } elseif ($level === 'tujuan') {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string',
                'deskripsi' => 'required|string',
            ]);
            Tujuan::create(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        } elseif ($level === 'sasaran') {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string',
                'deskripsi' => 'required|string',
            ]);
            Sasaran::create(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        } elseif ($level === 'strategi') {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string',
                'deskripsi' => 'required|string',
            ]);
            Strategi::create(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        } elseif ($level === 'arah-kebijakan') {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string',
                'deskripsi' => 'required|string',
            ]);
            ArahKebijakan::create(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        } elseif ($this->isProgramLevel($level)) {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string|max:500',
            ]);
            $jenis = $this->resolveProgramType($level);
            $data = [
                'opd_id' => $opdId,
                'document_type' => 'rpjmd',
                'jenis_program' => $jenis,
                'kode_rek' => $validated['kode'],
                'nama_rincian' => $validated['uraian'],
            ];
            if ($jenis === 'program') {
                $data['kepmen_id'] = $activeKepmenId;
            }
            Program::create($data);
        } elseif ($level === 'kegiatan') {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string|max:500',
            ]);
            Kegiatan::create([
                'program_id' => null,
                'opd_id' => $opdId,
                'kepmen_id' => $activeKepmenId,
                'document_type' => 'rpjmd',
                'kode_rek' => $validated['kode'],
                'nama_rincian' => $validated['uraian'],
            ]);
        } elseif ($level === 'sub-kegiatan') {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string|max:500',
            ]);
            SubKegiatan::create([
                'kegiatan_id' => null,
                'opd_id' => $opdId,
                'kepmen_id' => $activeKepmenId,
                'document_type' => 'rpjmd',
                'kode_rek' => $validated['kode'],
                'nama_rincian' => $validated['uraian'],
            ]);
        }

        return redirect()->back()->with('success', 'Data berhasil ditambahkan.');
    }

    public function updateLevel(Request $request, string $level, int $id)
    {
        $level = $this->normalizeLevel($level);
        abort_if($level === null, 404);

        if (in_array($level, ['iku', 'ikk'], true)) {
            $validated = $request->validate([
                'uraian' => 'required|string',
                'satuan' => 'required|string|max:100',
                'keterangan' => 'nullable|string',
            ]);
        } elseif (in_array($level, ['visi', 'misi', 'tujuan', 'sasaran', 'strategi', 'arah-kebijakan'], true)) {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string',
                'deskripsi' => 'required|string',
            ]);
        } elseif ($this->isProgramLevel($level)) {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string|max:500',
                'deskripsi' => 'required|string',
            ]);
        } else {
            $validated = $request->validate([
                'kode' => 'required|string|max:50',
                'uraian' => 'required|string|max:500',
                'deskripsi' => 'required|string',
                'pagu' => 'required|numeric|min:0',
            ]);
        }

        if (in_array($level, ['iku', 'ikk'], true)) $this->findIndikatorByJenisOrFail(strtoupper($level), $id)->update(['uraian' => $validated['uraian'], 'satuan' => $validated['satuan'], 'keterangan' => $validated['keterangan'] ?? null]);
        if ($level === 'visi') Visi::findOrFail($id)->update(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        if ($level === 'misi') Misi::findOrFail($id)->update(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        if ($level === 'tujuan') Tujuan::findOrFail($id)->update(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        if ($level === 'sasaran') Sasaran::findOrFail($id)->update(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        if ($level === 'strategi') Strategi::findOrFail($id)->update(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        if ($level === 'arah-kebijakan') ArahKebijakan::findOrFail($id)->update(['kode' => $validated['kode'], 'uraian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi']]);
        if ($this->isProgramLevel($level)) $this->findProgramByLevelOrFail($level, $id)->update(['kode_rek' => $validated['kode'], 'nama_rincian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi'], 'pagu' => 0]);
        if ($level === 'kegiatan') Kegiatan::findOrFail($id)->update(['kode_rek' => $validated['kode'], 'nama_rincian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi'], 'pagu' => $validated['pagu']]);
        if ($level === 'sub-kegiatan') SubKegiatan::findOrFail($id)->update(['kode_rek' => $validated['kode'], 'nama_rincian' => $validated['uraian'], 'deskripsi' => $validated['deskripsi'], 'pagu' => $validated['pagu']]);

        return redirect()->back()->with('success', 'Data berhasil diperbarui.');
    }

    public function destroyLevel(string $level, int $id)
    {
        $level = $this->normalizeLevel($level);
        abort_if($level === null, 404);

        if (in_array($level, ['iku', 'ikk'], true)) $this->findIndikatorByJenisOrFail(strtoupper($level), $id)->delete();
        if ($level === 'visi') Visi::findOrFail($id)->delete();
        if ($level === 'misi') Misi::findOrFail($id)->delete();
        if ($level === 'tujuan') Tujuan::findOrFail($id)->delete();
        if ($level === 'sasaran') Sasaran::findOrFail($id)->delete();
        if ($level === 'strategi') Strategi::findOrFail($id)->delete();
        if ($level === 'arah-kebijakan') ArahKebijakan::findOrFail($id)->delete();
        if ($this->isProgramLevel($level)) $this->findProgramByLevelOrFail($level, $id)->delete();
        if ($level === 'kegiatan') Kegiatan::findOrFail($id)->delete();
        if ($level === 'sub-kegiatan') SubKegiatan::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Data berhasil dihapus.');
    }

    private function normalizeLevel(string $level): ?string
    {
        $allowed = ['iku', 'ikk', 'visi', 'misi', 'tujuan', 'sasaran', 'strategi', 'arah-kebijakan', 'program', 'program-aksi', 'program-prioritas', 'kegiatan', 'sub-kegiatan'];
        return in_array($level, $allowed, true) ? $level : null;
    }

    private function isProgramLevel(string $level): bool
    {
        return in_array($level, ['program', 'program-aksi', 'program-prioritas'], true);
    }

    private function resolveProgramType(string $level): ?string
    {
        return $this->isProgramLevel($level) ? $level : null;
    }

    private function findProgramByLevelOrFail(string $level, int $id): Program
    {
        $query = Program::query();

        if ($level === 'program') {
            $query->whereIn('jenis_program', ['program', 'utama']);
        } else {
            $query->where('jenis_program', $this->resolveProgramType($level));
        }

        return $query->findOrFail($id);
    }

    private function findIndikatorByJenisOrFail(string $jenisIndikator, int $id): Indikator
    {
        return Indikator::query()
            ->where('jenis_indikator', $jenisIndikator)
            ->findOrFail($id);
    }

    private function buildLevelData(string $level, ?int $activeKepmenId, ?string $search = null): array
    {
        if (in_array($level, ['iku', 'ikk'], true)) {
            $query = Indikator::query()
                ->where('jenis_indikator', strtoupper($level))
                ->with('opd:id,nama,singkatan');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('uraian', 'like', "%{$search}%")
                        ->orWhere('satuan', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%");
                });
            }

            if ($level === 'ikk') {
                $query->orderBy('keterangan')->orderBy('uraian');
            }

            $rows = $query
                ->when($level !== 'ikk', fn ($q) => $q->latest())
                ->paginate(10)
                ->withQueryString()
                ->through(function ($r) {
                    $meta = json_decode((string) ($r->keterangan ?? ''), true);
                    $urusan1 = is_array($meta) ? ($meta['urusan_1'] ?? '-') : '-';
                    $urusan2 = is_array($meta) ? ($meta['urusan_2'] ?? '-') : '-';
                    $kodeIndikator = is_array($meta) ? ($meta['kode_indikator'] ?? '-') : '-';
                    $targetTahunan = is_array($meta) && is_array($meta['target_tahunan'] ?? null)
                        ? $meta['target_tahunan']
                        : [];
                    $opdNama = $r->opd?->nama;

                    if (!$opdNama && is_string($urusan2) && $urusan2 !== '-') {
                        $opdNama = $this->resolveIkkOpdNameFromUrusan2($urusan2);
                    }

                    $displayKeterangan = is_array($meta)
                        ? ($meta['catatan'] ?? null)
                        : $r->keterangan;

                    return [
                        'id' => $r->id,
                        'uraian' => $r->uraian,
                        'satuan' => $r->satuan,
                        'opd_nama' => $opdNama ?? '-',
                        'kode_indikator' => $kodeIndikator,
                        'target_tahunan' => [
                            '2025' => $targetTahunan['2025'] ?? null,
                            '2026' => $targetTahunan['2026'] ?? null,
                            '2027' => $targetTahunan['2027'] ?? null,
                            '2028' => $targetTahunan['2028'] ?? null,
                            '2029' => $targetTahunan['2029'] ?? null,
                            '2030' => $targetTahunan['2030'] ?? null,
                        ],
                        'jenis' => $r->jenis,
                        'sifat' => $r->sifat,
                        'keterangan' => $displayKeterangan,
                        'keterangan_raw' => $r->keterangan,
                        'urusan_1' => $urusan1,
                        'urusan_2' => $urusan2,
                    ];
                });

            return [$rows, collect()];
        }

        if ($level === 'visi') {
            $rows = Visi::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian, 'deskripsi' => $r->deskripsi]);
            return [$rows, collect()];
        }

        if ($level === 'misi') {
            $rows = Misi::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian, 'deskripsi' => $r->deskripsi]);
            return [$rows, collect()];
        }

        if ($level === 'tujuan') {
            $rows = Tujuan::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian, 'deskripsi' => $r->deskripsi]);
            return [$rows, collect()];
        }

        if ($level === 'sasaran') {
            $rows = Sasaran::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian, 'deskripsi' => $r->deskripsi]);
            return [$rows, collect()];
        }

        if ($level === 'strategi') {
            $rows = Strategi::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian, 'deskripsi' => $r->deskripsi]);
            return [$rows, collect()];
        }

        if ($level === 'arah-kebijakan') {
            $rows = ArahKebijakan::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian, 'deskripsi' => $r->deskripsi]);
            return [$rows, collect()];
        }

        if ($level === 'urusan') {
            $rows = \App\Models\Urusan::latest()->get()->map(fn ($r) => [
                'id' => $r->id,
                'kode' => $r->kode,
                'nama' => $r->nama,
            ]);
            return [$rows, collect()];
        }

        if ($level === 'bidang-urusan') {
            $rows = \App\Models\BidangUrusan::with('urusan')->latest()->get()->map(fn ($r) => [
                'id' => $r->id,
                'kode' => $r->kode,
                'nama' => $r->nama,
                'urusan' => $r->urusan ? ['id' => $r->urusan->id, 'kode' => $r->urusan->kode, 'nama' => $r->urusan->nama] : null,
            ]);
            $parents = \App\Models\Urusan::orderBy('kode')->get(['id', 'kode', 'nama']);
            return [$rows, $parents];
        }

        // Special handling: program-aksi should return both program-aksi rows and program-prioritas parents
        

        if ($this->isProgramLevel($level)) {
            $jenis = $this->resolveProgramType($level);
            $query = Program::query()->latest();

            if ($level === 'program') {
                // RENSTRA lama menggunakan jenis_program=utama, jadi tampilkan keduanya.
                $query->whereIn('jenis_program', ['program', 'utama']);
            } else {
                $query->where('jenis_program', $jenis);
            }

            // Hanya filter kepmen_id untuk program saja
            if ($jenis === 'program' && $activeKepmenId) $query->where('kepmen_id', $activeKepmenId);
            $rows = $query->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode_rek, 'uraian' => $r->nama_rincian, 'deskripsi' => $r->deskripsi, 'pagu' => $r->pagu]);
            return [$rows, collect()];
        }

        if ($level === 'program-aksi') {
            // allow OPD filtering via query ?opd_id=...
            $opdId = request()->get('opd_id');
            $opdFilterIds = $this->resolveOpdFilterIds($opdId);

            $rowsQuery = Program::where('jenis_program', 'program-aksi')->latest();
            if (!empty($opdFilterIds)) $rowsQuery->whereIn('opd_id', $opdFilterIds);
            $rows = $rowsQuery->get()->map(fn($r) => ['id' => $r->id, 'kode' => $r->kode_rek, 'uraian' => $r->nama_rincian, 'deskripsi' => $r->deskripsi, 'pagu' => $r->pagu, 'opd_id' => $r->opd_id]);

            // parents: use Renstra programs stored in KomponenAnggaran (document_type = 'renstra', jenis = 'program')
            // Prefer Program.nama_rincian if a Program row exists (kode_rek + opd_id), otherwise use nama_komponen
            $komponens = \App\Models\KomponenAnggaran::where('document_type', 'renstra')->where('jenis', 'program')->orderBy('kode')->get();
            if ($komponens->isNotEmpty()) {
                $parents = $komponens->map(function ($k) {
                    $labelName = $k->nama_komponen;
                    $programMatch = Program::where('kode_rek', $k->kode)->where('opd_id', $k->opd_id)->first();
                    if ($programMatch && $programMatch->nama_rincian) {
                        $labelName = $programMatch->nama_rincian;
                    }
                    $opdName = null;
                    if ($k->opd_id) {
                        $opd = Opd::find($k->opd_id, ['id', 'nama']);
                        $opdName = $opd?->nama;
                    }
                    return [
                        'id' => (int) $k->id,
                        'label' => (($k->kode ? $k->kode . ' - ' : '') . $labelName),
                        'opd_id' => $k->opd_id,
                        'opd_name' => $opdName,
                        'kode' => $k->kode,
                        'uraian' => $labelName,
                    ];
                });
            } else {
                // Fallback: if no KomponenAnggaran rows, try to load referensi/apbd/program.json
                $parents = collect();
                $jsonPath = base_path('referensi/apbd/program.json');
                if (file_exists($jsonPath)) {
                    $data = json_decode((string)@file_get_contents($jsonPath), true);
                    if (is_array($data)) {
                        $counter = -1;
                        foreach ($data as $row) {
                            $kode = $row['KODE_PROGRAM'] ?? null;
                            $nama = $row['NAMA_PROGRAM'] ?? ($row['NAMA_PROGRAM'] ?? null);
                            $label = (($kode) ? ($kode . ' - ') : '') . ($nama ?? '');
                            $parents->push([
                                'id' => $counter--,
                                'label' => $label,
                                'opd_id' => null,
                                'kode' => $kode,
                                'uraian' => $nama,
                            ]);
                        }
                    }
                }
            }

            return $this->attachRelasiAssignments($level, 'komponen_anggaran', $rows, $parents);
        }

        if ($level === 'kegiatan') {
            $query = Kegiatan::latest();
            if ($activeKepmenId) $query->where('kepmen_id', $activeKepmenId);
            $rows = $query->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode_rek, 'uraian' => $r->nama_rincian, 'deskripsi' => $r->deskripsi, 'pagu' => $r->pagu]);
            return [$rows, collect()];
        }

        $query = SubKegiatan::latest();
        if ($activeKepmenId) $query->where('kepmen_id', $activeKepmenId);
        $rows = $query->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode_rek, 'uraian' => $r->nama_rincian, 'deskripsi' => $r->deskripsi, 'pagu' => $r->pagu]);
        return [$rows, collect()];
    }

    /**
     * Temporary debug endpoint: return program-aksi rows and parents as JSON
     * (no role check). For local inspection only.
     */
    public function debugRelasiProgramAksi(Request $request)
    {
        [$rows, $parents] = $this->buildLevelData('program-aksi', null, null);

        $rowsArr = is_object($rows) && method_exists($rows, 'values') ? $rows->values()->all() : (is_array($rows) ? $rows : (array) $rows);
        $parentsArr = is_object($parents) && method_exists($parents, 'values') ? $parents->values()->all() : (is_array($parents) ? $parents : (array) $parents);

        return response()->json(['rows' => $rowsArr, 'parents' => $parentsArr]);
    }

    public function storeVisi(Request $request)
    {
        $validated = $request->validate([
            'opd_id' => 'nullable|exists:opds,id',
            'document_type' => 'nullable|in:rpjmd,renstra,renja,dpa',
            'kode' => 'required|string|max:50',
            'uraian' => 'required|string',
            'deskripsi' => 'required|string',
            'tahun_awal' => 'required|integer',
            'tahun_akhir' => 'required|integer|gte:tahun_awal',
        ]);
        
        $validated['opd_id'] = $validated['opd_id'] ?? $request->user()?->opd_id;
        $validated['document_type'] = $validated['document_type'] ?? 'rpjmd';
        
        Visi::create($validated);
        return redirect()->back()->with('success', 'Visi berhasil ditambahkan.');
    }

    public function updateVisi(Request $request, Visi $visi)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50',
            'uraian' => 'required|string',
            'deskripsi' => 'required|string',
            'tahun_awal' => 'required|integer',
            'tahun_akhir' => 'required|integer|gte:tahun_awal',
        ]);
        $visi->update($validated);
        return redirect()->back()->with('success', 'Visi berhasil diperbarui.');
    }

    public function destroyVisi(Visi $visi)
    {
        $visi->delete();
        return redirect()->back()->with('success', 'Visi berhasil dihapus.');
    }

    public function storeProgram(Request $request)
    {
        $validated = $request->validate([
            'opd_id' => 'nullable|exists:opds,id',
            'kepmen_id' => 'required|exists:kepmen,id',
            'document_type' => 'required|in:rpjmd,renstra,renja,dpa',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
        ]);
        Program::create($validated);
        return redirect()->back()->with('success', 'Program berhasil ditambahkan.');
    }

    public function updateProgram(Request $request, Program $program)
    {
        $validated = $request->validate([
            'kepmen_id' => 'required|exists:kepmen,id',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
            'catatan_evaluasi' => 'nullable|string',
        ]);
        $program->update($validated);
        return redirect()->back()->with('success', 'Program berhasil diperbarui.');
    }

    public function destroyProgram(Program $program)
    {
        $program->delete();
        return redirect()->back()->with('success', 'Program berhasil dihapus.');
    }

    public function storeKegiatan(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:program,id',
            'opd_id' => 'nullable|exists:opds,id',
            'kepmen_id' => 'required|exists:kepmen,id',
            'document_type' => 'required|in:rpjmd,renstra,renja,dpa',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
        ]);
        Kegiatan::create($validated);
        return redirect()->back()->with('success', 'Kegiatan berhasil ditambahkan.');
    }

    public function updateKegiatan(Request $request, Kegiatan $kegiatan)
    {
        $validated = $request->validate([
            'kepmen_id' => 'required|exists:kepmen,id',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
        ]);
        $kegiatan->update($validated);
        return redirect()->back()->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroyKegiatan(Kegiatan $kegiatan)
    {
        $kegiatan->delete();
        return redirect()->back()->with('success', 'Kegiatan berhasil dihapus.');
    }

    public function storeSubKegiatan(Request $request)
    {
        $validated = $request->validate([
            'kegiatan_id' => 'required|exists:kegiatan,id',
            'opd_id' => 'nullable|exists:opds,id',
            'kepmen_id' => 'required|exists:kepmen,id',
            'document_type' => 'required|in:rpjmd,renstra,renja,dpa',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
        ]);
        SubKegiatan::create($validated);
        return redirect()->back()->with('success', 'Sub Kegiatan berhasil ditambahkan.');
    }

    public function updateSubKegiatan(Request $request, SubKegiatan $subKegiatan)
    {
        $validated = $request->validate([
            'kepmen_id' => 'required|exists:kepmen,id',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
        ]);
        $subKegiatan->update($validated);
        return redirect()->back()->with('success', 'Sub Kegiatan berhasil diperbarui.');
    }

    public function destroySubKegiatan(SubKegiatan $subKegiatan)
    {
        $subKegiatan->delete();
        return redirect()->back()->with('success', 'Sub Kegiatan berhasil dihapus.');
    }

    // ─── Relasi Section ───────────────────────────────────────────────────────

    public function relasiMenu(Request $request): Response
    {
        $activeKepmen = Kepmen::find($request->session()->get('active_kepmen_id'));

        return Inertia::render('DataDasar/RelasiMenu', [
            'activePeraturan' => $activeKepmen ? [
                'id' => $activeKepmen->id,
                'kode' => $activeKepmen->kode,
                'nama' => $activeKepmen->nama,
            ] : null,
        ]);
    }

    public function relasiRingkasan(Request $request): Response
    {
        $activeKepmen = Kepmen::find($request->session()->get('active_kepmen_id'));
        $activeKepmenId = $activeKepmen?->id;

        $levelLabels = [
            'misi' => 'Misi',
            'tujuan' => 'Tujuan',
            'sasaran' => 'Sasaran',
            'strategi' => 'Strategi',
            'arah-kebijakan' => 'Arah Kebijakan',
            'kegiatan' => 'Kegiatan',
            'sub-kegiatan' => 'Sub Kegiatan',
        ];

        $parentLabels = [
            'misi' => 'Visi',
            'tujuan' => 'Misi',
            'sasaran' => 'Tujuan',
            'strategi' => 'Sasaran',
            'arah-kebijakan' => 'Strategi',
            'kegiatan' => 'Program',
            'sub-kegiatan' => 'Kegiatan',
        ];

        $levels = collect(array_keys($levelLabels))->map(function ($level) use ($activeKepmenId, $levelLabels, $parentLabels) {
            [$rows] = $this->buildRelasiData($level, $activeKepmenId);

            $totalData = $rows->count();
            $totalRelations = $rows->sum(fn ($row) => count($row['parent_ids'] ?? []));
            $linkedData = $rows->filter(fn ($row) => count($row['parent_ids'] ?? []) > 0)->count();
            $unlinkedData = $totalData - $linkedData;

            return [
                'level' => $level,
                'label' => $levelLabels[$level],
                'parent_label' => $parentLabels[$level],
                'total_data' => $totalData,
                'linked_data' => $linkedData,
                'unlinked_data' => $unlinkedData,
                'total_relations' => $totalRelations,
            ];
        })->values();

        $totals = [
            'total_data' => $levels->sum('total_data'),
            'linked_data' => $levels->sum('linked_data'),
            'unlinked_data' => $levels->sum('unlinked_data'),
            'total_relations' => $levels->sum('total_relations'),
        ];

        return Inertia::render('DataDasar/RelasiRingkasan', [
            'levels' => $levels,
            'totals' => $totals,
            'activePeraturan' => $activeKepmen ? [
                'id' => $activeKepmen->id,
                'kode' => $activeKepmen->kode,
                'nama' => $activeKepmen->nama,
            ] : null,
        ]);
    }

    public function relasiLevel(Request $request, string $level): Response
    {
        $level = $this->normalizeRelasiLevel($level);
        abort_if($level === null, 404);

        // program-aksi relasi management allowed only for superadmin
        if ($level === 'program-aksi') {
            abort_unless($request->user()?->hasRole('superadmin'), 403);
        }

        $activeKepmen = Kepmen::find($request->session()->get('active_kepmen_id'));
        // For program-aksi, use the bank-data logic so listing/filtering matches
        if ($level === 'program-aksi') {
            $search = trim((string) $request->get('search', ''));
            [$rows, $parents] = $this->buildLevelData('program-aksi', $activeKepmen?->id, $search);
        } else {
            [$rows, $parents] = $this->buildRelasiData($level, $activeKepmen?->id);
        }

        $opds = collect();
        if ($level === 'program-aksi') {
            $opds = Opd::where('is_active', true)->orderBy('nama')->get(['id', 'nama']);
        }

        return Inertia::render('DataDasar/RelasiLevel', [
            'level' => $level,
            'rows' => $rows,
            'parents' => $parents,
            'opds' => $opds,
            'activePeraturan' => $activeKepmen ? [
                'id' => $activeKepmen->id,
                'kode' => $activeKepmen->kode,
                'nama' => $activeKepmen->nama,
            ] : null,
        ]);
    }

    public function updateRelasi(Request $request, string $level, int $id)
    {
        $level = $this->normalizeRelasiLevel($level);
        abort_if($level === null, 404);
        abort_unless($request->user()?->hasRole('superadmin'), 403);

        $validated = $request->validate([
            'parent_ids' => 'nullable|array',
            'parent_ids.*' => 'integer',
        ]);

        $activeKepmenId = $request->session()->get('active_kepmen_id');
        $this->findRelasiChildOrFail($level, $id);

        $parentIds = collect($validated['parent_ids'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $parents = $this->buildRelasiParentOptions($level, $activeKepmenId);
        $allowedParentIds = $parents->pluck('id')->map(fn ($v) => (int) $v);
        $invalidParentIds = $parentIds->diff($allowedParentIds);

        abort_if($invalidParentIds->isNotEmpty(), 422, 'Pilihan relasi tidak valid.');

        $parentType = $this->relasiParentType($level);

        DB::transaction(function () use ($id, $level, $parentType, $parentIds): void {
            DataDasarRelasi::query()
                ->where('child_type', $level)
                ->where('child_id', $id)
                ->where('parent_type', $parentType)
                ->delete();

            if ($parentIds->isEmpty()) {
                return;
            }

            $now = now();
            $rows = $parentIds->map(fn ($parentId) => [
                'child_type' => $level,
                'child_id' => $id,
                'parent_type' => $parentType,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DataDasarRelasi::query()->insert($rows);

            // If we're relating program-aksi -> komponen_anggaran, mark/create corresponding Program as prioritas
            if ($level === 'program-aksi' && $parentType === 'komponen_anggaran') {
                foreach ($parentIds as $komponenId) {
                    $k = \App\Models\KomponenAnggaran::find((int) $komponenId);
                    if (!$k) continue;

                    $existingProgram = Program::where('kode_rek', $k->kode)->where('opd_id', $k->opd_id)->first();
                    if ($existingProgram) {
                        $existingProgram->update([ 'is_prioritas' => 1, 'document_type' => 'renstra' ]);
                    } else {
                        Program::create([
                            'opd_id' => $k->opd_id,
                            'kepmen_id' => null,
                            'document_type' => 'renstra',
                            'jenis_program' => 'program',
                            'kode_rek' => $k->kode,
                            'nama_rincian' => $k->nama_komponen,
                            'deskripsi' => null,
                            'pagu' => 0,
                            'tahun' => null,
                            'is_prioritas' => 1,
                        ]);
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Relasi berhasil diperbarui.');
    }

    public function updateRelasiByParent(Request $request, string $level, int $parentId)
    {
        $level = $this->normalizeRelasiLevel($level);
        abort_if($level === null, 404);
        abort_unless($request->user()?->hasRole('superadmin'), 403);

        $validated = $request->validate([
            'child_ids' => 'nullable|array',
            'child_ids.*' => 'integer',
        ]);

        $activeKepmenId = $request->session()->get('active_kepmen_id');
        $parentType = $this->relasiParentType($level);

        $parents = $this->buildRelasiParentOptions($level, $activeKepmenId);
        $allowedParentIds = $parents->pluck('id')->map(fn ($v) => (int) $v);
        abort_unless($allowedParentIds->contains($parentId), 422, 'Pilihan konekting atas tidak valid.');

        [$rows] = $this->buildRelasiData($level, $activeKepmenId);
        $allowedChildIds = $rows->pluck('id')->map(fn ($v) => (int) $v);

        $childIds = collect($validated['child_ids'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $invalidChildIds = $childIds->diff($allowedChildIds);
        abort_if($invalidChildIds->isNotEmpty(), 422, 'Pilihan data relasi tidak valid.');

        DB::transaction(function () use ($level, $parentType, $parentId, $childIds): void {
            DataDasarRelasi::query()
                ->where('child_type', $level)
                ->where('parent_type', $parentType)
                ->where('parent_id', $parentId)
                ->delete();

            if ($childIds->isEmpty()) {
                return;
            }

            $now = now();
            $rows = $childIds->map(fn ($childId) => [
                'child_type' => $level,
                'child_id' => $childId,
                'parent_type' => $parentType,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DataDasarRelasi::query()->insert($rows);
        });

        return redirect()->back()->with('success', 'Relasi dari konekting atas berhasil diperbarui.');
    }

    private function normalizeRelasiLevel(string $level): ?string
    {
        $allowed = ['urusan', 'bidang-urusan', 'program', 'program-aksi', 'misi', 'tujuan', 'sasaran', 'strategi', 'arah-kebijakan', 'kegiatan', 'sub-kegiatan'];
        return in_array($level, $allowed, true) ? $level : null;
    }

    private function buildRelasiData(string $level, ?int $activeKepmenId): array
    {
        if ($level === 'urusan') {
            $rows = \App\Models\Urusan::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->nama]);
            $parents = collect();
            return $this->attachRelasiAssignments($level, '', $rows, $parents);
        }

        if ($level === 'bidang-urusan') {
            $rows = \App\Models\BidangUrusan::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->nama]);
            $parents = \App\Models\Urusan::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->nama]);
            return $this->attachRelasiAssignments($level, 'urusan', $rows, $parents);
        }

        if ($level === 'program') {
            $rows = \App\Models\Program::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode_rek, 'uraian' => $r->nama_rincian]);
            $parents = \App\Models\BidangUrusan::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->nama]);
            return $this->attachRelasiAssignments($level, 'bidang-urusan', $rows, $parents);
        }

        $parentType = $this->relasiParentType($level);

        if ($level === 'misi') {
            $rows = Misi::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian]);
            $parents = Visi::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->uraian]);
            return $this->attachRelasiAssignments($level, $parentType, $rows, $parents);
        }

        if ($level === 'tujuan') {
            $rows = Tujuan::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian]);
            $parents = Misi::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->uraian]);
            return $this->attachRelasiAssignments($level, $parentType, $rows, $parents);
        }

        if ($level === 'sasaran') {
            $rows = Sasaran::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian]);
            $parents = Tujuan::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->uraian]);
            return $this->attachRelasiAssignments($level, $parentType, $rows, $parents);
        }

        if ($level === 'strategi') {
            $rows = Strategi::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian]);
            $parents = Sasaran::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->uraian]);
            return $this->attachRelasiAssignments($level, $parentType, $rows, $parents);
        }

        if ($level === 'arah-kebijakan') {
            $rows = ArahKebijakan::latest()->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode, 'uraian' => $r->uraian]);
            $parents = Strategi::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->uraian]);
            return $this->attachRelasiAssignments($level, $parentType, $rows, $parents);
        }

        if ($level === 'kegiatan') {
            $query = Kegiatan::latest();
            if ($activeKepmenId) $query->where('kepmen_id', $activeKepmenId);
            $rows = $query->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode_rek, 'uraian' => $r->nama_rincian]);
            $parentsQuery = Program::where('jenis_program', 'program')->latest();
            if ($activeKepmenId) $parentsQuery->where('kepmen_id', $activeKepmenId);
            $parents = $parentsQuery->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode_rek ? $p->kode_rek . ' - ' : '') . $p->nama_rincian]);
            return $this->attachRelasiAssignments($level, $parentType, $rows, $parents);
        }

        if ($level === 'program-aksi') {
            // Parent options for program-aksi come from KomponenAnggaran (renstra programs)
            $komponens = \App\Models\KomponenAnggaran::where('document_type', 'renstra')->where('jenis', 'program')->orderBy('kode')->get();
            if ($komponens->isNotEmpty()) {
                $parents = $komponens->map(fn($k) => ['id' => (int) $k->id, 'label' => (($k->kode ? $k->kode . ' - ' : '') . $k->nama_komponen), 'opd_id' => $k->opd_id, 'kode' => $k->kode, 'uraian' => $k->nama_komponen]);
                return $parents;
            }

            // Fallback: if no KomponenAnggaran rows, load referensi/apbd/program.json
            $parents = collect();
            $jsonPath = base_path('referensi/apbd/program.json');
            if (file_exists($jsonPath)) {
                $data = json_decode((string)@file_get_contents($jsonPath), true);
                if (is_array($data)) {
                    $counter = -1;
                    foreach ($data as $row) {
                        $kode = $row['KODE_PROGRAM'] ?? null;
                        $nama = $row['NAMA_PROGRAM'] ?? null;
                        $kodeSkpd = $row['KODE_SKPD'] ?? null;
                        $namaOpd = $row['NAMA_OPD'] ?? null;
                        $label = (($kode) ? ($kode . ' - ') : '') . ($nama ?? '');

                        $opdId = null;
                        $opdName = null;
                        if ($kodeSkpd) {
                            $opd = Opd::where('kode', $kodeSkpd)->first(['id', 'nama']);
                            $opdId = $opd?->id ?? null;
                            $opdName = $opd?->nama ?? null;
                        }
                        if (!$opdId && $namaOpd) {
                            $opd = Opd::where('nama', 'like', '%' . trim($namaOpd) . '%')->first(['id', 'nama']);
                            $opdId = $opd?->id ?? null;
                            $opdName = $opd?->nama ?? $opdName;
                        }

                        $parents->push([
                            'id' => $counter--,
                            'label' => $label,
                            'opd_id' => $opdId,
                            'opd_name' => $opdName,
                            'kode' => $kode,
                            'uraian' => $nama,
                        ]);
                    }
                }
            }

            return $parents;
        }

        // sub-kegiatan
        $query = SubKegiatan::latest();
        if ($activeKepmenId) $query->where('kepmen_id', $activeKepmenId);
        $rows = $query->get()->map(fn ($r) => ['id' => $r->id, 'kode' => $r->kode_rek, 'uraian' => $r->nama_rincian]);
        $parentsQuery = Kegiatan::latest();
        if ($activeKepmenId) $parentsQuery->where('kepmen_id', $activeKepmenId);
        $parents = $parentsQuery->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode_rek ? $p->kode_rek . ' - ' : '') . $p->nama_rincian]);
        return $this->attachRelasiAssignments($level, $parentType, $rows, $parents);
    }

    private function relasiParentType(string $level): string
    {
        return match ($level) {
            'urusan' => '',
            'bidang-urusan' => 'urusan',
            'program' => 'bidang-urusan',
            'program-aksi' => 'komponen_anggaran',
            'misi' => 'visi',
            'tujuan' => 'misi',
            'sasaran' => 'tujuan',
            'strategi' => 'sasaran',
            'arah-kebijakan' => 'strategi',
            'kegiatan' => 'program',
            'sub-kegiatan' => 'kegiatan',
            default => throw new \InvalidArgumentException('Level relasi tidak valid.'),
        };
    }

    private function findRelasiChildOrFail(string $level, int $id): void
    {
        match ($level) {
            'urusan' => \App\Models\Urusan::findOrFail($id),
            'bidang-urusan' => \App\Models\BidangUrusan::findOrFail($id),
            'program' => \App\Models\Program::findOrFail($id),
            'program-aksi' => \App\Models\Program::findOrFail($id),
            'misi' => Misi::findOrFail($id),
            'tujuan' => Tujuan::findOrFail($id),
            'sasaran' => Sasaran::findOrFail($id),
            'strategi' => Strategi::findOrFail($id),
            'arah-kebijakan' => ArahKebijakan::findOrFail($id),
            'kegiatan' => Kegiatan::findOrFail($id),
            'sub-kegiatan' => SubKegiatan::findOrFail($id),
            default => abort(422),
        };
    }

    private function buildRelasiParentOptions(string $level, ?int $activeKepmenId)
    {
        if ($level === 'urusan') {
            return collect();
        }
        if ($level === 'bidang-urusan') {
            return \App\Models\Urusan::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->nama]);
        }
        if ($level === 'misi') {
            return Visi::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->uraian]);
        }
        if ($level === 'tujuan') {
            return Misi::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->uraian]);
        }
        if ($level === 'sasaran') {
            return Tujuan::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->uraian]);
        }
        if ($level === 'strategi') {
            return Sasaran::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->uraian]);
        }
        if ($level === 'arah-kebijakan') {
            return Strategi::latest()->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode ? $p->kode . ' - ' : '') . $p->uraian]);
        }
        if ($level === 'kegiatan') {
            $query = Program::where('jenis_program', 'program')->latest();
            if ($activeKepmenId) $query->where('kepmen_id', $activeKepmenId);
            return $query->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode_rek ? $p->kode_rek . ' - ' : '') . $p->nama_rincian]);
        }
        $query = Kegiatan::latest();
        if ($activeKepmenId) $query->where('kepmen_id', $activeKepmenId);
        return $query->get()->map(fn ($p) => ['id' => $p->id, 'label' => ($p->kode_rek ? $p->kode_rek . ' - ' : '') . $p->nama_rincian]);
    }

    private function resolveIkkOpdNameFromUrusan2(string $urusan2): ?string
    {
        $u = strtoupper(trim($urusan2));
        $u = preg_replace('/[^A-Z0-9\s]/', ' ', $u);
        $u = preg_replace('/\s+/', ' ', (string) $u);
        $u = trim((string) $u);

        if (str_contains($u, 'KECAMATAN LONG APARAI')) return 'Kecamatan Long Apari';
        if (str_contains($u, 'KECAMATAN LONG PAHANGAI')) return 'Kecamatan Long Pahangai';
        if (str_contains($u, 'KECAMATAN LONG BAGUN')) return 'Kecamatan Long Bagun';
        if (str_contains($u, 'KECAMATAN LONG HUBUNG')) return 'Kecamatan Long Hubung';
        if (str_contains($u, 'KECAMATAN LONG LAHAM') || str_contains($u, 'KECAMATAN LAHAM')) return 'Kecamatan Laham';

        if (str_contains($u, 'SEKRETARIAT DPRD')) return 'Sekretariat DPRD';
        if (str_contains($u, 'SEKRETARIAT DAERAH')) return 'Sekretariat Daerah';
        if (str_contains($u, 'PERENCANAAN') || str_contains($u, 'PENELITIAN') || str_contains($u, 'PENGEMBANGAN')) return 'Badan Perencanaan Pembangunan, Penelitian dan Pengembangan Daerah';
        if (str_contains($u, 'KEUANGAN')) return 'Badan Pengelola Keuangan dan Aset Daerah';
        if (str_contains($u, 'KEPEGAWAIAN') || str_contains($u, 'PENDIDIKAN DAN PELATIHAN')) return 'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia';
        if (str_contains($u, 'PERBATASAN')) return 'Badan Pengelola Perbatasan Daerah';
        if (str_contains($u, 'INSPEKTORA') || str_contains($u, 'INSPEKTORAT')) return 'Inspektorat';
        if (str_contains($u, 'KESATUAN BANGSA') || str_contains($u, 'POLITIK')) return 'Badan Kesatuan Bangsa dan Politik';

        if (str_contains($u, 'PENDIDIKAN') || str_contains($u, 'KEBUDAYAAN') || str_contains($u, 'PERPUSTAKAAN') || str_contains($u, 'KEARSIPAN')) return 'Dinas Pendidikan dan Kebudayaan';
        if (str_contains($u, 'KESEHATAN') || str_contains($u, 'KELUARGA BERENCANA') || str_contains($u, 'PENGENDALIAN PENDUDUK')) return 'Dinas Kesehatan, Pengendalian Penduduk dan KB';
        if (str_contains($u, 'PEKERJAAN UMUM') || str_contains($u, 'PENATAAN RUANG') || str_contains($u, 'PERUMAHAN') || str_contains($u, 'KAWASAN PEMUKIMAN')) return 'Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Pemukiman';
        if (str_contains($u, 'KETENTERAMAN') || str_contains($u, 'KETERTIBAN')) return 'Satuan Polisi Pamong Praja';
        if (str_contains($u, 'BENCANA')) return 'Badan Penanggulangan Bencana Daerah';
        if (str_contains($u, 'SOSIAL') || str_contains($u, 'PEREMPUAN') || str_contains($u, 'ANAK')) return 'Dinas Sosial, Pemberdayaan Perempuan Perlindungan Anak';
        if (str_contains($u, 'PANGAN') || str_contains($u, 'PERTANIAN') || str_contains($u, 'KELAUTAN') || str_contains($u, 'PERIKANAN')) return 'Dinas Ketahanan Pangan dan Pertanian';
        if (str_contains($u, 'LINGKUNGAN HIDUP')) return 'Dinas Lingkungan Hidup';
        if (str_contains($u, 'KEPENDUDUKAN') || str_contains($u, 'PENCATATAN SIPIL')) return 'Dinas Kependudukan dan Pencatatan Sipil';
        if (str_contains($u, 'PEMBERDAYAAN MASYARAKAT') || str_contains($u, 'DESA') || str_contains($u, 'KAMPUNG')) return 'Dinas Pemberdayaan Masyarakat dan Pemerintahan Kampung';
        if (str_contains($u, 'PERHUBUNGAN')) return 'Dinas Perhubungan';
        if (str_contains($u, 'KOMUNIKASI') || str_contains($u, 'INFORMATIKA') || str_contains($u, 'STATISTIK') || str_contains($u, 'PERSANDIAN')) return 'Dinas Komunikasi dan Informatika, Statistik, dan Persandian';
        if (str_contains($u, 'PENANAMAN MODAL')) return 'Dinas Penanaman Modal dan Pelayanan Perijinan Terpadu';
        if (str_contains($u, 'PARIWISATA') || str_contains($u, 'KEPEMUDAAN') || str_contains($u, 'OLAHRAGA')) return 'Dinas Pariwisata, Pemuda dan Olahraga';
        if (str_contains($u, 'KOPERASI') || str_contains($u, 'USAHA KECIL') || str_contains($u, 'MENENGAH') || str_contains($u, 'PERDAGANGAN') || str_contains($u, 'PERINDUSTRIAN')) return 'Bagian Perekonomian dan Sumber Daya Alam';

        return null;
    }

    private function attachRelasiAssignments(string $level, string $parentType, $rows, $parents): array
    {
        $parentLabelMap = $parents->pluck('label', 'id');

        $assignments = DataDasarRelasi::query()
            ->where('child_type', $level)
            ->where('parent_type', $parentType)
            ->whereIn('child_id', $rows->pluck('id'))
            ->get(['child_id', 'parent_id'])
            ->groupBy('child_id');

        $rows = $rows->map(function ($row) use ($assignments, $parentLabelMap) {
            $assigned = $assignments->get($row['id'], collect());
            $parentIds = $assigned->pluck('parent_id')->map(fn ($v) => (int) $v)->values()->all();
            $parentLabels = collect($parentIds)
                ->map(fn ($pid) => $parentLabelMap[$pid] ?? null)
                ->filter()
                ->values()
                ->all();

            return [
                ...$row,
                'parent_ids' => $parentIds,
                'parent_labels' => $parentLabels,
            ];
        });

        return [$rows, $parents];
    }
}
