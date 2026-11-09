<?php

namespace App\Http\Controllers;

use App\Services\Resume2\HasilPelaksanaanData;
use App\Services\Resume2\KonsistensiRkpdApbdData;
use App\Services\Resume2\KonsistensiRpjmdRkpdData;
use App\Services\Resume2\RekapPermasalahanData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resume 2: duplikat tampilan menu Resume SIMEVLAP v1 untuk 4 bagian
 * (Konsistensi RPJMD - RKPD, Konsistensi Keg RKPD - APBD, Hasil Pelaksanaan RKPD, Rekap Permasalahan).
 * Isi tabel diambil dari data aplikasi lewat service di App\Services\Resume2.
 */
class Resume2Controller extends Controller
{
    private const TAHUN_OPTIONS = [2021, 2022, 2023, 2024, 2025, 2026];

    /**
     * Konfigurasi tiap bagian: judul, jumlah tabel, filter per tabel, tabel yang punya tombol Excel,
     * serta keterangan singkat per tabel.
     */
    private const SECTIONS = [
        'konsistensi-rpjmd-rkpd' => [
            'title' => 'Konsistensi RPJMD - RKPD',
            'filters' => ['*' => ['filter', 'tahun']],
            'excel' => [1, 2, 3, 4],
            'tables' => [
                1 => 'Jumlah program yang sama dan tidak sama antara RPJMD, Renstra, dan RKPD/Renja',
                2 => 'Jumlah indikator program yang sama dan tidak sama antara RPJMD, Renstra, dan RKPD/Renja',
                3 => 'Indikator dan target program pada RPJMD, Renstra, dan RKPD/Renja',
                4 => 'Pagu anggaran program pada RPJMD, Renstra, dan RKPD/Renja',
            ],
        ],
        'konsistensi-keg-rkpd-apbd' => [
            'title' => 'Konsistensi Keg RKPD - APBD',
            'filters' => ['*' => ['filter', 'tahun']],
            'excel' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            'tables' => [
                1 => 'Jumlah program RKPD/Renja dan APBD',
                2 => 'Jumlah kegiatan RKPD/Renja dan APBD',
                3 => 'Jumlah sub kegiatan RKPD/Renja dan APBD',
                4 => 'Indikator program RKPD/Renja dan APBD',
                5 => 'Indikator kegiatan RKPD/Renja dan APBD',
                6 => 'Indikator sub kegiatan RKPD/Renja dan APBD',
                7 => 'Indikator dan target program RKPD/Renja dan APBD',
                8 => 'Indikator dan target kegiatan RKPD/Renja dan APBD',
                9 => 'Indikator dan target sub kegiatan RKPD/Renja dan APBD',
                10 => 'Pagu anggaran program, kegiatan, dan sub kegiatan RKPD/Renja dan APBD',
            ],
        ],
        'hasil-pelaksanaan-rkpd' => [
            'title' => 'Hasil Pelaksanaan RKPD',
            'filters' => [
                1 => ['tahun'],
                2 => ['tahun'],
                3 => ['tw', 'tahun'],
                4 => ['bidang', 'tw', 'tahun'],
                5 => ['tahun'],
                6 => ['opd', 'tahun'],
            ],
            'excel' => [],
            'tables' => [
                1 => 'Capaian indikator tujuan dan sasaran RPJMD',
                2 => 'Capaian 10 program aksi kepala daerah',
                3 => 'Capaian program prioritas RPJMD',
                4 => 'Realisasi kinerja dan keuangan program/kegiatan/sub kegiatan RKPD',
                5 => 'Evaluasi hasil pelaksanaan RKPD per sasaran',
                6 => 'Evaluasi hasil pelaksanaan Renja perangkat daerah',
            ],
        ],
        'rekap-permasalahan' => [
            'title' => 'Rekap Permasalahan',
            // Catatan permasalahan tidak menyimpan triwulan, sehingga filter triwulan v1 tidak dipakai.
            'filters' => ['*' => ['dokumen', 'tahun']],
            'excel' => [1],
            'tables' => [
                1 => 'Rekap permasalahan program berdasarkan perangkat daerah (program belum dipetakan ke sasaran RPJMD)',
                2 => 'Rekap permasalahan program berdasarkan bidang urusan',
            ],
        ],
    ];

    /** @var array<string, array> opsi filter yang sudah dimuat dalam request ini */
    private array $optionsCache = [];

    public function __construct(
        private readonly KonsistensiRpjmdRkpdData $konsistensiRpjmdRkpd,
        private readonly KonsistensiRkpdApbdData $konsistensiRkpdApbd,
        private readonly HasilPelaksanaanData $hasilPelaksanaan,
        private readonly RekapPermasalahanData $rekapPermasalahan,
    ) {
    }

    public function index(): Response
    {
        $items = [];
        foreach (self::SECTIONS as $slug => $section) {
            $items[] = ['label' => $section['title'], 'href' => route('resume2.section', $slug)];
        }

        return Inertia::render('Resume2/Index', [
            'breadcrumbs' => [['label' => 'Resume 2', 'href' => route('resume2.index')]],
            'items' => $items,
        ]);
    }

    public function section(string $section): Response
    {
        $config = $this->sectionConfig($section);

        $items = [];
        foreach (array_keys($config['tables']) as $tabel) {
            $items[] = ['label' => "Tabel {$tabel}", 'href' => route('resume2.table', [$section, "tabel-{$tabel}"])];
        }

        return Inertia::render('Resume2/Index', [
            'breadcrumbs' => [
                ['label' => 'Resume 2', 'href' => route('resume2.index')],
                ['label' => $config['title'], 'href' => route('resume2.section', $section)],
            ],
            'items' => $items,
        ]);
    }

    public function table(Request $request, string $section, string $tabel): Response
    {
        $config = $this->sectionConfig($section);
        $nomor = (int) substr($tabel, strlen('tabel-'));
        abort_unless(isset($config['tables'][$nomor]), 404);

        $filterNames = $config['filters'][$nomor] ?? $config['filters']['*'];
        $filters = $this->resolveFilters($request, $filterNames);
        $data = $this->tableData($section, $nomor, $filters);

        return Inertia::render('Resume2/Table', [
            'breadcrumbs' => [
                ['label' => 'Resume 2', 'href' => route('resume2.index')],
                ['label' => $config['title'], 'href' => route('resume2.section', $section)],
                ['label' => "Tabel {$nomor}", 'href' => route('resume2.table', [$section, $tabel])],
            ],
            'section' => $section,
            'tabel' => $nomor,
            'description' => $config['tables'][$nomor],
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($filterNames),
            'canExport' => in_array($nomor, $config['excel'], true),
            'data' => $data,
        ]);
    }

    /**
     * Ambil isi tabel dari service bagian yang bersangkutan.
     *
     * @return array<string, mixed>
     */
    private function tableData(string $section, int $nomor, array $filters): array
    {
        $basis = $filters['filter'] ?? null;
        $tahun = $filters['tahun'];

        return match ($section) {
            'konsistensi-rpjmd-rkpd' => match ($nomor) {
                1 => $this->konsistensiRpjmdRkpd->jumlahProgram($basis, $tahun),
                2 => $this->konsistensiRpjmdRkpd->jumlahIndikator($basis, $tahun),
                3 => $this->konsistensiRpjmdRkpd->indikatorProgram($basis, $tahun),
                default => $this->konsistensiRpjmdRkpd->paguProgram($basis, $tahun),
            },
            'konsistensi-keg-rkpd-apbd' => match (true) {
                $nomor <= 3 => $this->konsistensiRkpdApbd->jumlah($this->levelOf($nomor), $basis, $tahun),
                $nomor <= 9 => $this->konsistensiRkpdApbd->indikator($this->levelOf($nomor), $basis, $tahun, withTarget: $nomor >= 7),
                default => $this->konsistensiRkpdApbd->pagu($basis, $tahun),
            },
            'hasil-pelaksanaan-rkpd' => match ($nomor) {
                1 => $this->hasilPelaksanaan->tujuanSasaran($tahun),
                2 => $this->hasilPelaksanaan->programAksi($tahun),
                3 => $this->hasilPelaksanaan->programPrioritas($tahun, $filters['tw']),
                4 => $this->hasilPelaksanaan->realisasiBidang((string) $filters['bidang'], $tahun, $filters['tw']),
                5 => $this->hasilPelaksanaan->evaluasiRkpd($tahun),
                default => $this->hasilPelaksanaan->evaluasiRenja((int) $filters['opd'], $tahun),
            },
            default => $this->rekapPermasalahan->rekap(
                $nomor === 1 ? 'perangkat-daerah' : 'bidang-urusan',
                (string) $filters['dokumen'],
                $tahun
            ),
        };
    }

    /**
     * Konsistensi Keg RKPD - APBD: tabel 1/4/7 = program, 2/5/8 = kegiatan, 3/6/9 = sub kegiatan.
     */
    private function levelOf(int $nomor): string
    {
        return ['program', 'kegiatan', 'sub_kegiatan'][($nomor - 1) % 3];
    }

    private function sectionConfig(string $section): array
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        return self::SECTIONS[$section];
    }

    /**
     * Ambil nilai filter dari query string; nilai yang tidak valid diganti dengan default seperti di v1.
     *
     * @param  array<int, string>  $names
     * @return array<string, int|string>
     */
    private function resolveFilters(Request $request, array $names): array
    {
        $filters = [];
        foreach ($names as $name) {
            $allowed = array_column($this->optionsFor($name), 'value');
            $default = $this->defaultFor($name);
            if (! in_array($default, $allowed, true)) {
                $default = $allowed[0] ?? $default;
            }
            $value = $request->query($name === 'dokumen' ? 'filter' : $name, $default);
            // Hanya bilangan bulat yang diubah ke int; kode seperti "1.01" (bidang urusan) tetap string.
            $value = is_string($value) && ctype_digit($value) ? (int) $value : $value;
            $filters[$name] = in_array($value, $allowed, true) ? $value : $default;
        }

        return $filters;
    }

    /**
     * Nilai default seperti tautan menu di v1. Bidang urusan / perangkat daerah memakai opsi pertama.
     */
    private function defaultFor(string $name): int|string|null
    {
        return match ($name) {
            'filter' => 'bidang-urusan',
            'dokumen' => 'renstra',
            'tahun' => 2026,
            'tw' => 1,
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, array{name: string, param: string, label: string, options: array}>
     */
    private function filterOptions(array $names): array
    {
        $labels = [
            'filter' => 'Tampilkan Berdasarkan',
            'dokumen' => 'Dokumen',
            'tahun' => 'Tahun',
            'tw' => 'Triwulan',
            'bidang' => 'Bidang Urusan',
            'opd' => 'Perangkat Daerah',
        ];

        return array_map(fn (string $name) => [
            'name' => $name,
            'param' => $name === 'dokumen' ? 'filter' : $name,
            'label' => $labels[$name],
            'options' => $this->optionsFor($name),
        ], $names);
    }

    /**
     * @return array<int, array{value: int|string, label: string}>
     */
    private function optionsFor(string $name): array
    {
        return $this->optionsCache[$name] ??= match ($name) {
            'filter' => [
                ['value' => 'bidang-urusan', 'label' => 'Berdasarkan Bidang Urusan'],
                ['value' => 'perangkat-daerah', 'label' => 'Berdasarkan Perangkat Daerah'],
            ],
            'dokumen' => [
                ['value' => 'renstra', 'label' => 'Renstra'],
                ['value' => 'renja', 'label' => 'Renja'],
                ['value' => 'dpa', 'label' => 'DPA'],
            ],
            'tahun' => array_map(fn (int $y) => ['value' => $y, 'label' => "Tahun {$y}"], self::TAHUN_OPTIONS),
            'tw' => array_map(fn (int $tw, string $romawi) => ['value' => $tw, 'label' => "Triwulan {$romawi}"], [1, 2, 3, 4], ['I', 'II', 'III', 'IV']),
            'bidang' => $this->hasilPelaksanaan->bidangOptions(),
            'opd' => $this->hasilPelaksanaan->opdOptions(),
        };
    }
}
