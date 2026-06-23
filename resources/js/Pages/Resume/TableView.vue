<template>
  <AppLayout title="Resume Monitoring">
    <section class="space-y-6">
      <div class="rounded-2xl border border-emerald-100 bg-white/90 p-6 shadow-md">
        <div class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
            <h2 v-if="viewTitle" class="text-2xl font-bold text-emerald-900">{{ viewTitle }}</h2>
            <p v-if="currentTableLabel" class="mt-1 text-sm font-semibold text-slate-500">{{ currentTableLabel }}</p>
          </div>
              <div class="flex flex-col gap-3 md:items-end">
            <Link
              :href="route('resume.index', { view: currentView })"
              class="inline-flex items-center justify-center rounded-lg bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 transition-colors hover:bg-emerald-100"
            >
              Kembali ke Daftar Tabel
            </Link>

            

            <div v-if="(currentView === 'realisasi' && currentTable === 'iku') || shouldShowExportButtons" class="flex flex-col gap-3 md:flex-row md:items-center">
              <a
                :href="buildExportUrl('pdf')"
                class="inline-flex items-center justify-center rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 transition-colors hover:bg-red-100"
              >
                Download PDF
              </a>

              <a
                :href="buildExportUrl('excel')"
                class="inline-flex items-center justify-center rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 transition-colors hover:bg-emerald-100"
              >
                Download Excel
              </a>
            </div>

            <!-- Basis filter: Perangkat Daerah / Bidang Urusan -->
            <div class="flex gap-3">
              <div v-if="['konsistensi-rpjmd-rkpd','konsistensi-rkpd-apbd'].includes(currentView) && ['tabel-1','tabel-2','tabel-3','tabel-4','tabel-5','tabel-6','tabel-7','tabel-8','tabel-9','tabel-10'].includes(currentTable)" class="mt-2 md:mt-0 md:ml-4">
              <label class="text-xs text-slate-600 mr-2">Tampilkan berdasarkan</label>
              <select v-model="basisValue" @change="applyFilters" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                <option value="perangkat-daerah">Perangkat Daerah</option>
                <option value="bidang-urusan">Bidang Urusan</option>
              </select>
            </div>

            <!-- Year filter: show for hasil-pelaksanaan-rkpd and konsistensi-rkpd-apbd tabel-1..tabel-10 -->
            <div v-if="(currentView === 'hasil-pelaksanaan-rkpd' && currentTable && currentTable.startsWith('tabel')) || (currentView === 'konsistensi-rkpd-apbd' && ['tabel-1','tabel-2','tabel-3','tabel-4','tabel-5','tabel-6','tabel-7','tabel-8','tabel-9','tabel-10'].includes(currentTable))" class="mt-2 md:mt-0 md:ml-4">
              <label class="text-xs text-slate-600 mr-2">Tahun</label>
              <select v-model="yearValue" @change="applyFilters" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                <option :value="null">Semua</option>
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
            </div>

            
          </div>
        </div>

        <!-- Tabel 10: match requested 11-column layout with double-header style -->
        <div v-if="currentTable === 'tabel-10' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
          <table class="min-w-[1200px] w-full border-collapse text-sm">
            <thead class="sticky top-0 bg-emerald-50">
              <tr>
                <th rowspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
                <th rowspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Bidang Urusan / Perangkat Daerah</th>
                <th rowspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Program/Kegiatan/Sub Kegiatan</th>
                <th class="border border-emerald-200 px-3 py-3 text-center font-bold">RKPD/Renja (Tahun {{ yearValue || 2026 }})</th>
                <th class="border border-emerald-200 px-3 py-3 text-center font-bold">APBD (Tahun {{ yearValue || 2026 }})</th>
                <th colspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Konsistensi RKPD/Renja - APBD</th>
                <th colspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Konsistensi RPJMD - RKPD/Renja</th>
              </tr>
              <tr class="bg-emerald-100">
                <th class="border border-emerald-200 px-3 py-2 text-center font-semibold">Pagu Anggaran</th>
                <th class="border border-emerald-200 px-3 py-2 text-center font-semibold">Pagu Anggaran</th>
                <th class="border border-emerald-200 px-3 py-2 text-center font-semibold">Selisih Pagu Anggaran</th>
                <th class="border border-emerald-200 px-3 py-2 text-center font-semibold">Status</th>
                <th class="border border-emerald-200 px-3 py-2 text-center font-semibold">Selisih Pagu Anggaran</th>
                <th class="border border-emerald-200 px-3 py-2 text-center font-semibold">Status</th>
              </tr>
              <tr class="bg-emerald-100">
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(1)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(2)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(3)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(4)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(5)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(6)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(7)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(8)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(9)</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, idx) in tableData" :key="`group-${idx}`">
                <template v-for="(program, pIdx) in getUniquePrograms([ ...(row?.rpjmd_programs||[]), ...(row?.renstra_programs||[]), ...(row?.rkpd_programs||[]), ...(row?.dpa_programs||[]) ])" :key="`prog-${idx}-${pIdx}`">
                  <tr :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                    <td v-if="pIdx === 0" :rowspan="getUniquePrograms([ ...(row?.rpjmd_programs||[]), ...(row?.renstra_programs||[]), ...(row?.rkpd_programs||[]), ...(row?.dpa_programs||[]) ]).length" class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
                    <td v-if="pIdx === 0" :rowspan="getUniquePrograms([ ...(row?.rpjmd_programs||[]), ...(row?.renstra_programs||[]), ...(row?.rkpd_programs||[]), ...(row?.dpa_programs||[]) ]).length" class="border border-emerald-200 px-3 py-3 font-medium">{{ formatEntityLabel(row.entitas) }}</td>
                    <td class="border border-emerald-200 px-3 py-3">{{ program?.nama ?? program?.program_nama ?? program?.indikator ?? '-' }}</td>

                    <!-- RKPD pagu (value + presence) -->
                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(sumPaguForProgram(row, 'rkpd_programs', program)) }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center">{{ sumPaguForProgram(row, 'rkpd_programs', program) > 0 ? 'Ada' : '-' }}</td>

                    <!-- APBD pagu (value + presence) -->
                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(sumPaguForProgram(row, 'dpa_programs', program)) }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center">{{ sumPaguForProgram(row, 'dpa_programs', program) > 0 ? 'Ada' : '-' }}</td>

                    <!-- Konsistensi RKPD-APBD: selisih + status -->
                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(Math.abs(sumPaguForProgram(row, 'rkpd_programs', program) - sumPaguForProgram(row, 'dpa_programs', program))) }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center"><span :class="getStatusClass(statusForProgram(row, program, 'rkpd_programs','dpa_programs'))">{{ statusForProgram(row, program, 'rkpd_programs','dpa_programs') }}</span></td>

                    <!-- Konsistensi RPJMD-RKPD: selisih + status -->
                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(Math.abs(sumPaguForProgram(row, 'rpjmd_programs', program) - sumPaguForProgram(row, 'rkpd_programs', program))) }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center"><span :class="getStatusClass(statusForProgram(row, program, 'rpjmd_programs','rkpd_programs'))">{{ statusForProgram(row, program, 'rpjmd_programs','rkpd_programs') }}</span></td>
                  </tr>
                </template>
                <!-- fallback when no programs found: render a single empty program row -->
                <tr v-if="getUniquePrograms([ ...(row?.rpjmd_programs||[]), ...(row?.renstra_programs||[]), ...(row?.rkpd_programs||[]), ...(row?.dpa_programs||[]) ]).length === 0" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                  <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
                  <td class="border border-emerald-200 px-3 py-3 font-medium">{{ formatEntityLabel(row.entitas) }}</td>
                  <td class="border border-emerald-200 px-3 py-3">-</td>
                  <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(sumPagu(row, 'rkpd_programs')) }}</td>
                  <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(sumPagu(row, 'dpa_programs')) }}</td>
                  <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(Math.abs(sumPagu(row, 'rkpd_programs') - sumPagu(row, 'dpa_programs'))) }}</td>
                  <td class="border border-emerald-200 px-3 py-3 text-center"><span :class="getStatusClass(getStatusByKeyPlaceholder(row))">{{ getStatusByKeyPlaceholder(row) }}</span></td>
                  <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(Math.abs(sumPagu(row, 'rpjmd_programs') - sumPagu(row, 'rkpd_programs'))) }}</td>
                  <td class="border border-emerald-200 px-3 py-3 text-center"><span :class="getStatusClass(getStatusByKeyPlaceholder(row))">{{ getStatusByKeyPlaceholder(row) }}</span></td>
                  <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(Math.abs(sumPagu(row, 'renstra_programs') - sumPagu(row, 'rkpd_programs'))) }}</td>
                  <td class="border border-emerald-200 px-3 py-3 text-center"><span :class="getStatusClass(getStatusByKeyPlaceholder(row))">{{ getStatusByKeyPlaceholder(row) }}</span></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Rekap Permasalahan - Berdasarkan Sasaran -->
      <div v-if="currentView === 'rekap-permasalahan' && currentTable === 'berdasarkan-sasaran' && tableData" class="overflow-x-auto rounded-2xl border border-amber-100 bg-white/90 shadow-md">
        <table class="min-w-[1100px] w-full border-collapse text-sm">
          <thead class="bg-amber-50">
            <tr>
              <th class="border border-amber-200 px-3 py-3 text-center font-bold">No</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">Sasaran</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">Program</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">Faktor Penghambat</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">Faktor Pendorong</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">Faktor Tindak Lanjut</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">OPD</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in tableData" :key="`rekap-${idx}`" :class="idx % 2 === 0 ? 'bg-white' : 'bg-amber-50'">
              <td class="border border-amber-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
              <td class="border border-amber-200 px-3 py-3">{{ row.sasaran ?? '-' }}</td>
              <td class="border border-amber-200 px-3 py-3">{{ row.opd ? (row.opd + ' — ' + (row.program ?? '-')) : (row.program ?? '-') }}</td>
              <td class="border border-amber-200 px-3 py-3">{{ row.faktor_penghambat ?? '-' }}</td>
              <td class="border border-amber-200 px-3 py-3">{{ row.faktor_pendorong ?? '-' }}</td>
              <td class="border border-amber-200 px-3 py-3">{{ row.faktor_tindak_lanjut ?? '-' }}</td>
              <td class="border border-amber-200 px-3 py-3">{{ row.opd ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>


      <!-- Rekap Permasalahan - Berdasarkan Bidang Urusan -->
      <div v-if="currentView === 'rekap-permasalahan' && currentTable === 'berdasarkan-bidang-urusan' && tableData" class="overflow-x-auto rounded-2xl border border-amber-100 bg-white/90 shadow-md">
        <table class="min-w-[1100px] w-full border-collapse text-sm">
          <thead class="bg-amber-50">
            <tr>
              <th class="border border-amber-200 px-3 py-3 text-center font-bold">No</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">Bidang Urusan</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">Program</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">Faktor Penghambat</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">Faktor Pendorong</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">Faktor Tindak Lanjut</th>
              <th class="border border-amber-200 px-3 py-3 text-left font-bold">OPD</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(group) in groupedByBidang" :key="`group-${group.bidang}`">
              <template v-for="(row, idx) in group.programs" :key="`g-${group.bidang}-${idx}`">
                <tr :class="row._global_index % 2 === 0 ? 'bg-amber-50' : 'bg-white'">
                  <td v-if="idx === 0" :rowspan="group.programs.length" class="border border-amber-200 px-3 py-3 text-center font-semibold">{{ group.group_index }}</td>
                  <td v-if="idx === 0" :rowspan="group.programs.length" class="border border-amber-200 px-3 py-3 font-medium">{{ group.bidang ?? '-' }}</td>
                  <td class="border border-amber-200 px-3 py-3">{{ row.opd ? (row.opd + ' — ' + (row.program ?? '-')) : (row.program ?? '-') }}</td>
                  <td class="border border-amber-200 px-3 py-3">{{ row.faktor_penghambat ?? '-' }}</td>
                  <td class="border border-amber-200 px-3 py-3">{{ row.faktor_pendorong ?? '-' }}</td>
                  <td class="border border-amber-200 px-3 py-3">{{ row.faktor_tindak_lanjut ?? '-' }}</td>
                  <td class="border border-amber-200 px-3 py-3">{{ row.opd ?? '-' }}</td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>

      <div
        v-if="isDokumenView && tableData"
        class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md"
      >
        <table class="min-w-[1400px] w-full border-collapse text-sm">
          <thead>
            <tr class="bg-emerald-50">
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">No</th>
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-left font-bold text-emerald-900">OPD</th>
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">Renstra</th>
              <th colspan="2" v-for="year in dokumenYears" :key="`head-${year}`" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">
                {{ year }}
              </th>
            </tr>
            <tr class="bg-emerald-50">
              <template v-for="year in dokumenYears" :key="`sub-${year}`">
                <th class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">Renja</th>
                <th class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">DPA</th>
              </template>
            </tr>
          </thead>
          <tbody>
              <tr v-for="(row, idx) in tableData" :key="`dokumen-${idx}`" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
              <td class="border border-emerald-200 px-3 py-3 align-top text-center font-semibold text-slate-700">{{ row.no }}</td>
              <td class="border border-emerald-200 px-3 py-3 align-top font-medium text-slate-900">{{ row.opd }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">
                <DokumenCell :cell="row.renstra" />
              </td>
              <template v-for="year in dokumenYears" :key="`cell-${row.no}-${year}`">
                <td class="border border-emerald-200 px-3 py-3 text-center">
                  <DokumenCell :cell="row.years?.[year]?.renja" />
                </td>
                <td class="border border-emerald-200 px-3 py-3 text-center">
                  <DokumenCell :cell="row.years?.[year]?.dpa" />
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="currentView === 'konsistensi-rkpd-apbd' && ['tabel-1', 'tabel-2', 'tabel-3', 'tabel-4', 'tabel-5', 'tabel-6', 'tabel-7','tabel-8','tabel-9'].includes(currentTable) && tableData">

        <!-- Tabel 1: Ringkasan indikator per entitas (compact counts) -->
        <div v-if="currentTable === 'tabel-1'" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
          <table class="w-full table-fixed border-collapse text-sm">
            <thead>
              <tr class="bg-emerald-50">
                <th rowspan="2" class="min-w-[70px] border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
                <th rowspan="2" class="min-w-[230px] border border-emerald-200 px-3 py-3 text-left font-bold">{{ entityHeaderLabel }}</th>
                <th class="min-w-[160px] border border-emerald-200 px-3 py-3 text-center font-bold">RKPD/Renja (Tahun {{ yearValue || 2026 }})</th>
                <th class="min-w-[160px] border border-emerald-200 px-3                                                                                   xt-center font-bold">APBD (Tahun {{ yearValue || 2026 }})</th>
                <th colspan="2" class="min-w-[220px] border border-emerald-200 px-3 py-3 text-center font-bold">Konsistensi RKPD/Renja - APBD</th>
              </tr>
              <tr class="bg-emerald-100">
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah {{ metricLabel }}</th>
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah {{ metricLabel }}</th>
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah Program Yang Sama</th>
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah Program Yang Tidak Sama</th>
              </tr>
              <tr class="bg-emerald-100">
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(1)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(2)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(3)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(4)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(5)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(6)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
                <td class="border border-emerald-200 px-3 py-3 font-medium">{{ formatEntityLabel(row.entitas) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center cursor-pointer text-emerald-800 font-semibold" @click="openLineContent(row, 'rkpd_programs', `List ${metricLabel} RKPD/Renja`)">
                  <div class="font-semibold">{{ Number(row.rkpd_count || 0) }}</div>
                  <div class="text-xs text-slate-600 mt-1 break-words" v-if="getIndicatorsForRow(row, 'rkpd').length">
                    {{ getIndicatorsForRow(row, 'rkpd').slice(0,3).join(', ') }}<span v-if="getIndicatorsForRow(row, 'rkpd').length > 3">, ...</span>
                  </div>
                </td>
                <td class="border border-emerald-200 px-3 py-3 text-center cursor-pointer text-emerald-800 font-semibold" @click="openLineContent(row, 'dpa_programs', `List ${metricLabel} APBD`)">
                  <div class="font-semibold">{{ Number(row.dpa_count || 0) }}</div>
                  <div class="text-xs text-slate-600 mt-1 break-words" v-if="getIndicatorsForRow(row, 'dpa').length">
                    {{ getIndicatorsForRow(row, 'dpa').slice(0,3).join(', ') }}<span v-if="getIndicatorsForRow(row, 'dpa').length > 3">, ...</span>
                  </div>
                </td>
                <td class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold" role="button" tabindex="0" @click="openComparisonModal(row, 'rkpd_programs', 'dpa_programs', 'RKPD/Renja', 'APBD', 'same')">
                  <span :class="(getSameCountByKeys(row, 'rkpd_programs','dpa_programs')>0) ? 'text-emerald-700' : 'text-slate-500'">{{ getSameCountByKeys(row, 'rkpd_programs','dpa_programs') }}</span>
                </td>
                <td class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold" role="button" tabindex="0" @click="openComparisonModal(row, 'rkpd_programs', 'dpa_programs', 'RKPD/Renja', 'APBD', 'diff')">
                  <span :class="(getDifferentCountByKeys(row, 'rkpd_programs','dpa_programs')>0) ? 'text-amber-700' : 'text-slate-500'">{{ getDifferentCountByKeys(row, 'rkpd_programs','dpa_programs') }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Tabel 2: Program-level comparison (counts, different styling) -->
        <div v-else-if="currentTable === 'tabel-2'" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
          <table class="w-full table-fixed border-collapse text-sm">
            <thead>
              <tr class="bg-emerald-50">
                <th rowspan="2" class="min-w-[70px] border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
                <th rowspan="2" class="min-w-[230px] border border-emerald-200 px-3 py-3 text-left font-bold">{{ entityHeaderLabel }}</th>
                <th class="min-w-[160px] border border-emerald-200 px-3 py-3 text-center font-bold">RKPD/Renja (Tahun {{ yearValue || 2026 }})</th>
                <th class="min-w-[160px] border border-emerald-200 px-3                                                                                   xt-center font-bold">APBD (Tahun {{ yearValue || 2026 }})</th>
                <th colspan="2" class="min-w-[220px] border border-emerald-200 px-3 py-3 text-center font-bold">Konsistensi RKPD/Renja - APBD</th>
              </tr>
              <tr class="bg-emerald-100">
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah {{ metricLabel }}</th>
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah {{ metricLabel }}</th>
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah {{ metricLabel }} Yang Sama</th>
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah {{ metricLabel }} Yang Tidak Sama</th>
              </tr>
              <tr class="bg-emerald-100">
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(1)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(2)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(3)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(4)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(5)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(6)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
                <td class="border border-emerald-200 px-3 py-3 font-medium">{{ formatEntityLabel(row.entitas) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center cursor-pointer text-emerald-800 font-semibold" @click="openLineContent(row, 'rkpd_programs', `List ${metricLabel} RKPD/Renja`)">{{ Number(row.rkpd_count || 0) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center cursor-pointer text-emerald-800 font-semibold" @click="openLineContent(row, 'dpa_programs', `List ${metricLabel} APBD`)">{{ Number(row.dpa_count || 0) }}</td>
                <td class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold" role="button" tabindex="0" @click="openComparisonModal(row, 'rkpd_programs', 'dpa_programs', 'RKPD/Renja', 'APBD', 'same')">
                  <span :class="(getSameCountByKeys(row, 'rkpd_programs','dpa_programs')>0) ? 'text-emerald-700' : 'text-slate-500'">{{ getSameCountByKeys(row, 'rkpd_programs','dpa_programs') }}</span>
                </td>
                <td class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold" role="button" tabindex="0" @click="openComparisonModal(row, 'rkpd_programs', 'dpa_programs', 'RKPD/Renja', 'APBD', 'diff')">
                  <span :class="(getDifferentCountByKeys(row, 'rkpd_programs','dpa_programs')>0) ? 'text-amber-700' : 'text-slate-500'">{{ getDifferentCountByKeys(row, 'rkpd_programs','dpa_programs') }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Tabel 3: Detailed list per entitas (compact lists inside cells) -->
        <div v-else-if="currentTable === 'tabel-3'" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
          <table class="w-full table-fixed border-collapse text-sm">
            <thead>
              <tr class="bg-emerald-50">
                <th rowspan="2" class="min-w-[70px] border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
                <th rowspan="2" class="min-w-[230px] border border-emerald-200 px-3 py-3 text-left font-bold">{{ entityHeaderLabel }}</th>
                <th class="min-w-[160px] border border-emerald-200 px-3 py-3 text-center font-bold">RKPD/Renja (Tahun {{ yearValue || 2026 }})</th>
                <th class="min-w-[160px] border border-emerald-200 px-3                                                                                   xt-center font-bold">APBD (Tahun {{ yearValue || 2026 }})</th>
                <th colspan="2" class="min-w-[220px] border border-emerald-200 px-3 py-3 text-center font-bold">Konsistensi RKPD/Renja - APBD</th>
              </tr>
              <tr class="bg-emerald-100">
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah {{ metricLabel }}</th>
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah {{ metricLabel }}</th>
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah {{ metricLabel }} Yang Sama</th>
                <th class="border border-emerald-200 px-3 py-2 text-center text-sm font-semibold text-emerald-700">Jumlah {{ metricLabel }} Yang Tidak Sama</th>
              </tr>
              <tr class="bg-emerald-100">
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(1)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(2)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(3)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(4)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(5)</th>
                <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(6)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
                <td class="border border-emerald-200 px-3 py-3 font-medium">{{ formatEntityLabel(row.entitas) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center cursor-pointer text-emerald-800 font-semibold" @click="openLineContent(row, 'rkpd_programs', `List ${metricLabel} RKPD/Renja`)">{{ Number(row.rkpd_count || 0) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center cursor-pointer text-emerald-800 font-semibold" @click="openLineContent(row, 'dpa_programs', `List ${metricLabel} APBD`)">{{ Number(row.dpa_count || 0) }}</td>
                <td class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold" role="button" tabindex="0" @click="openComparisonModal(row, 'rkpd_programs', 'dpa_programs', 'RKPD/Renja', 'APBD', 'same')">
                  <span :class="(getSameCountByKeys(row, 'rkpd_programs','dpa_programs')>0) ? 'text-emerald-700' : 'text-slate-500'">{{ getSameCountByKeys(row, 'rkpd_programs','dpa_programs') }}</span>
                </td>
                <td class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold" role="button" tabindex="0" @click="openComparisonModal(row, 'rkpd_programs', 'dpa_programs', 'RKPD/Renja', 'APBD', 'diff')">
                  <span :class="(getDifferentCountByKeys(row, 'rkpd_programs','dpa_programs')>0) ? 'text-amber-700' : 'text-slate-500'">{{ getDifferentCountByKeys(row, 'rkpd_programs','dpa_programs') }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Tabel 4: Program-level anggaran comparison (shows program column) -->
        <div v-else-if="['tabel-4','tabel-5','tabel-6','tabel-7','tabel-8','tabel-9'].includes(currentTable)" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
          <table class="w-full table-fixed border-collapse text-sm">
            <thead>
                <tr class="bg-emerald-50">
                  <th class="px-3 py-3 border border-emerald-200 text-center font-bold text-emerald-900" rowspan="2">No</th>
                  <th class="px-3 py-3 border border-emerald-200 text-center font-bold text-emerald-900" rowspan="2">Bidang Urusan</th>
                  <th class="px-3 py-3 border border-emerald-200 text-center font-bold text-emerald-900" rowspan="2">{{ metricLabel }}</th>
                  <th :colspan="showTargetColumns ? 2 : 1" class="px-3 py-3 border border-emerald-200 text-center font-bold text-emerald-900">RKPD/Renja (Tahun {{ yearValue || 2026 }})</th>
                  <th :colspan="showTargetColumns ? 2 : 1" class="px-3 py-3 border border-emerald-200 text-center font-bold text-emerald-900">APBD (Tahun {{ yearValue || 2026 }})</th>
                  <th class="px-3 py-3 border border-emerald-200 text-center font-bold text-emerald-900" rowspan="2">Status Konsistensi RKPD/Renja - APBD</th>
                </tr>
                <!-- single 'Indikator Program' header row retained -->
                <tr class="bg-emerald-100">
                  <th class="px-3 py-2 border border-emerald-200 text-center text-sm font-semibold text-emerald-700">{{ isIndikatorMode ? 'Indikator Program' : 'Indikator ' + metricLabel }}</th>
                  <th v-if="showTargetColumns" class="px-3 py-2 border border-emerald-200 text-center text-sm font-semibold text-emerald-700">Target</th>
                  <th class="px-3 py-2 border border-emerald-200 text-center text-sm font-semibold text-emerald-700">{{ isIndikatorMode ? 'Indikator Program' : 'Indikator ' + metricLabel }}</th>
                  <th v-if="showTargetColumns" class="px-3 py-2 border border-emerald-200 text-center text-sm font-semibold text-emerald-700">Target</th>
                </tr>
                <tr class="bg-emerald-100" v-if="showTargetColumns">
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(1)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(2)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(3)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(4)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(5)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(6)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(7)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(8)</th>
                </tr>
                <tr class="bg-emerald-100" v-else>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(1)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(2)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(3)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(4)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(5)</th>
                  <th class="px-3 py-1 border border-emerald-200 text-center text-xs font-semibold text-emerald-700">(6)</th>
                </tr>
            </thead>
            <tbody>
              <template v-for="(row, idx) in tableData" :key="'group-' + idx">
                <template v-for="(line, lineIdx) in getProgramLines(row)" :key="'line-' + idx + '-' + line.key">
                  <template v-for="(indRow, indIdx) in getIndicatorRowsForLine(line, row)" :key="'ind-' + idx + '-' + line.key + '-' + indIdx">
                    <tr :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                      <td v-if="lineIdx === 0 && indIdx === 0" :rowspan="getTotalDisplayRows(row)" class="border border-emerald-200 px-3 py-2 text-center align-top">{{ row.no }}</td>
                      <td v-if="lineIdx === 0 && indIdx === 0" :rowspan="getTotalDisplayRows(row)" class="border border-emerald-200 px-3 py-2 align-top">{{ formatEntityLabel(row.entitas) }}</td>
                      <td v-if="indIdx === 0" :rowspan="getIndicatorRowsForLine(line, row).length" class="border border-emerald-200 px-3 py-2 align-top text-sm font-medium break-words">{{ formatReadableText(line.name) }}</td>
                      <td class="border border-emerald-200 px-3 py-2 align-top text-sm text-emerald-700 break-words">{{ formatReadableText(indRow.rkpd) }}</td>
                      <td v-if="showTargetColumns" class="border border-emerald-200 px-3 py-2 align-top text-center text-sm text-emerald-700 break-words">{{ indRow.rkpd_target ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 align-top text-sm text-emerald-700 break-words">{{ formatReadableText(indRow.dpa) }}</td>
                      <td v-if="showTargetColumns" class="border border-emerald-200 px-3 py-2 align-top text-center text-sm text-emerald-700 break-words">{{ indRow.dpa_target ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-center"><span :class="getStatusClass(determineIndicatorStatus(indRow))">{{ determineIndicatorStatus(indRow) }}</span></td>
                    </tr>
                  </template>
                </template>
              </template>
            </tbody>
          </table>
        </div>

        <!-- tabel-5 uses the same layout as tabel-4 (copied) -->

      </div>

      <div v-else-if="currentView === 'realisasi' && currentTable === 'iku' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
        <table class="w-full table-fixed border-collapse text-sm">
          <thead>
            <tr class="bg-emerald-50">
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
              <th class="border border-emerald-200 px-3 py-3 text-left font-bold">Indikator</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Satuan</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Target 2026</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Realisasi Tahun</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Fisik</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Keuangan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
              <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ row.indikator }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.satuan }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target_2026 }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.realisasi_tahun ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.realisasi_fisik ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.realisasi_keuangan ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- New: tabel-1 for hasil-pelaksanaan-rkpd view -->
      <div v-else-if="currentView === 'hasil-pelaksanaan-rkpd' && currentTable === 'tabel-1' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
        <table class="w-full table-fixed border-collapse text-sm">
          <thead>
            <tr class="bg-emerald-50">
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Tujuan</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Sasaran</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Indikator</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Satuan</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Target RPJMD</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Target RKPD</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Tahun {{ yearValue || 2026 }}</th>
            </tr>
            <tr class="bg-emerald-100">
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(1)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(2)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(3)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(4)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(5)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(6)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(7)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(8)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
              <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
              <td v-if="row.tujuan_first" :rowspan="row.tujuan_rowspan" class="border border-emerald-200 px-3 py-3 align-top">{{ row.tujuan ?? '-' }}</td>
              <td v-else class="hidden"></td>
              <td class="border border-emerald-200 px-3 py-3">{{ row.sasaran ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ row.indikator ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.satuan ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target_rpjmd ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target_rkpd ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.capaian_tahun ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- New: tabel-2 for hasil-pelaksanaan-rkpd view (Program Aksi Kepala Daerah - top 10) -->
      <div v-else-if="currentView === 'hasil-pelaksanaan-rkpd' && currentTable === 'tabel-2' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
        <table class="w-full table-fixed border-collapse text-sm">
          <thead>
            <tr class="bg-emerald-50">
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">10 Program Aksi Kepala Daerah</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Tahun {{ yearValue || 2026 }}</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Anggaran Tahun {{ yearValue || 2026 }}</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Program Prioritas (Pendukung)</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Indikator Program Prioritas (RPJMD)</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Target Tahun {{ yearValue || 2026 }}</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Satuan</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Tahun {{ yearValue || 2026 }}</th>
            </tr>
            <tr class="bg-emerald-100">
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(1)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(2)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(3)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(4)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(5)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(6)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(7)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(8)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(9)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
              <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
              <td v-if="row.program_first" :rowspan="row.program_rowspan" class="border border-emerald-200 px-3 py-3 align-top">{{ row.program ?? '-' }}</td>
              <td v-else class="hidden"></td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.capaian_fisik ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.capaian_keuangan ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-sm">{{ (row.prioritas_programs || []).join(', ') || '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ row.rpjmd_indikator ?? row.indikator ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.rpjmd_target ?? row.target ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.rpjmd_satuan ?? row.satuan ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.rpjmd_capaian ?? row.indikator_capaian ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-else-if="currentView === 'konsistensi-rpjmd-rkpd' && ['tabel-1', 'tabel-2'].includes(currentTable) && tableData"
        class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md"
      >
        <table class="w-full table-fixed border-collapse text-sm">
          <thead>
            <tr class="bg-emerald-50">
              <th rowspan="2" class="min-w-[70px] border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">No</th>
              <th rowspan="2" class="min-w-[230px] border border-emerald-200 bg-emerald-100 px-3 py-3 text-left font-bold text-emerald-900">{{ entityHeaderLabel }}</th>
              <th rowspan="1" class="min-w-[170px] border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">RPJMD (2026-2030)</th>
              <th rowspan="1" class="min-w-[170px] border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">Renstra (Tahun 2026)</th>
              <th rowspan="1" class="min-w-[170px] border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">RKPD/Renja (Tahun 2026)</th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">
                Konsistensi RPJMD - Renstra
              </th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">
                Konsistensi RPJMD - RKPD/Renja
              </th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">
                Status Konsistensi Renstra - RKPD/Renja
              </th>
            </tr>
            <tr class="bg-emerald-50">
              <th class="min-w-[160px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">Jumlah {{ metricLabel }}</th>
              <th class="min-w-[160px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">Jumlah {{ metricLabel }}</th>
              <th class="min-w-[160px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">Jumlah {{ metricLabel }}</th>
              <th class="min-w-[160px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">Jumlah {{ metricLabel }} Yang Sama</th>
              <th class="min-w-[180px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">Jumlah {{ metricLabel }} Yang Tidak Sama</th>
              <th class="min-w-[160px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">Jumlah {{ metricLabel }} Yang Sama</th>
              <th class="min-w-[180px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">Jumlah {{ metricLabel }} Yang Tidak Sama</th>
              <th class="min-w-[160px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">Jumlah {{ metricLabel }} Yang Sama</th>
              <th class="min-w-[180px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-center font-bold text-emerald-900">Jumlah {{ metricLabel }} Yang Tidak Sama</th>
            </tr>
            <tr class="bg-emerald-100">
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(1)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(2)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(3)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(4)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(5)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(6)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(7)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(8)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(9)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(10)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(11)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
              <td class="border border-emerald-200 px-3 py-3 text-center font-semibold text-slate-700">{{ row.no }}</td>
              <td class="border border-emerald-200 px-3 py-3 font-medium text-slate-900">{{ formatEntityLabel(row.entitas) }}</td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openProgramList(row, 'rpjmd')"
                @keydown.enter.prevent="openProgramList(row, 'rpjmd')"
                @keydown.space.prevent="openProgramList(row, 'rpjmd')"
              >
                {{ getComparableTotalByKey(row, 'rpjmd_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openProgramList(row, 'renstra')"
                @keydown.enter.prevent="openProgramList(row, 'renstra')"
                @keydown.space.prevent="openProgramList(row, 'renstra')"
              >
                {{ getComparableTotalByKey(row, 'renstra_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openProgramList(row, 'rkpd')"
                @keydown.enter.prevent="openProgramList(row, 'rkpd')"
                @keydown.space.prevent="openProgramList(row, 'rkpd')"
              >
                {{ getComparableTotalByKey(row, 'rkpd_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openComparisonModal(row, 'rpjmd_programs', 'renstra_programs', 'RPJMD', 'Renstra', 'same')"
                @keydown.enter.prevent="openComparisonModal(row, 'rpjmd_programs', 'renstra_programs', 'RPJMD', 'Renstra', 'same')"
                @keydown.space.prevent="openComparisonModal(row, 'rpjmd_programs', 'renstra_programs', 'RPJMD', 'Renstra', 'same')"
              >
                {{ getSameCountByKeys(row, 'rpjmd_programs', 'renstra_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openComparisonModal(row, 'rpjmd_programs', 'renstra_programs', 'RPJMD', 'Renstra', 'diff')"
                @keydown.enter.prevent="openComparisonModal(row, 'rpjmd_programs', 'renstra_programs', 'RPJMD', 'Renstra', 'diff')"
                @keydown.space.prevent="openComparisonModal(row, 'rpjmd_programs', 'renstra_programs', 'RPJMD', 'Renstra', 'diff')"
              >
                {{ getDifferentCountByKeys(row, 'rpjmd_programs', 'renstra_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', 'RKPD/Renja', 'same')"
                @keydown.enter.prevent="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', 'RKPD/Renja', 'same')"
                @keydown.space.prevent="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', 'RKPD/Renja', 'same')"
              >
                {{ getSameCountByKeys(row, 'rpjmd_programs', 'rkpd_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', 'RKPD/Renja', 'diff')"
                @keydown.enter.prevent="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', 'RKPD/Renja', 'diff')"
                @keydown.space.prevent="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', 'RKPD/Renja', 'diff')"
              >
                {{ getDifferentCountByKeys(row, 'rpjmd_programs', 'rkpd_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', 'RKPD/Renja', 'same')"
                @keydown.enter.prevent="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', 'RKPD/Renja', 'same')"
                @keydown.space.prevent="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', 'RKPD/Renja', 'same')"
              >
                {{ getSameCountByKeys(row, 'renstra_programs', 'rkpd_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', 'RKPD/Renja', 'diff')"
                @keydown.enter.prevent="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', 'RKPD/Renja', 'diff')"
                @keydown.space.prevent="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', 'RKPD/Renja', 'diff')"
              >
                {{ getDifferentCountByKeys(row, 'renstra_programs', 'rkpd_programs') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      

      <div
        v-else-if="currentView === 'konsistensi-rpjmd-rkpd' && currentTable === 'tabel-3' && tableData"
        class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md"
      >
        <table class="w-full table-fixed border-collapse text-sm">
          <colgroup>
            <col class="w-[52px]" />
            <col class="w-[160px]" />
            <col class="w-[200px]" />
            <col class="w-[288px]" />
            <col class="w-[76px]" />
            <col class="w-[288px]" />
            <col class="w-[76px]" />
            <col class="w-[288px]" />
            <col class="w-[76px]" />
            <col class="w-[128px]" />
            <col class="w-[128px]" />
            <col class="w-[128px]" />
          </colgroup>
          <thead>
            <tr class="bg-emerald-50">
              <th rowspan="2" class="w-[52px] border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">No</th>
              <th rowspan="2" class="w-[160px] border border-emerald-200 bg-emerald-100 px-2 py-2 text-left font-bold text-emerald-900">{{ entityHeaderLabel }}</th>
              <th rowspan="2" class="w-[200px] border border-emerald-200 bg-emerald-100 px-2 py-2 text-left font-bold text-emerald-900">Program</th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">RPJMD - Tahun {{ yearValue || 2026 }}</th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">Renstra - Tahun {{ yearValue || 2026 }}</th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">RKPD - Tahun {{ yearValue || 2026 }}</th>
              <th rowspan="2" class="w-[140px] border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Status Konsistensi RPJMD - Renstra</th>
              <th rowspan="2" class="w-[140px] border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Status Konsistensi RPJMD - RKPD/Renja</th>
              <th rowspan="2" class="w-[140px] border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Status Konsistensi Renstra - RKPD/Renja</th>
            </tr>
            <tr class="bg-emerald-50">
              <th class="w-[288px] border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Indikator</th>
              <th class="w-[80px] border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Target</th>
              <th class="w-[288px] border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Indikator</th>
              <th class="w-[80px] border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Target</th>
              <th class="w-[288px] border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Indikator</th>
              <th class="w-[80px] border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Target</th>
            </tr>
            <tr class="bg-emerald-100">
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(1)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(2)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(3)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(4)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(5)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(6)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(7)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(8)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(9)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(10)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(11)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(12)</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(row, idx) in tableData" :key="'group-' + idx">
              <tr
                v-for="(line, lineIdx) in getAlignedIndicatorRows(row)"
                :key="'line-' + idx + '-' + lineIdx"
                :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'"
              >
                <td v-if="lineIdx === 0" :rowspan="getAlignedIndicatorRows(row).length" class="border border-emerald-200 px-3 py-3 align-top text-center font-semibold text-slate-700">{{ row.no }}</td>
                <td v-if="lineIdx === 0" :rowspan="getAlignedIndicatorRows(row).length" class="border border-emerald-200 px-3 py-3 align-top font-medium text-slate-900">{{ formatEntityLabel(row.entitas) }}</td>
                <td v-if="lineIdx === 0" :rowspan="getAlignedIndicatorRows(row).length" class="border border-emerald-200 px-3 py-3 align-top font-semibold text-slate-700 break-words whitespace-normal">{{ formatReadableText(getRowProgramName(row)) }}</td>

                <td class="border border-emerald-200 px-2 py-2 align-top text-sm text-slate-800 break-words whitespace-normal">
                  <div class="rounded-md border border-emerald-200 bg-emerald-50/40 px-2 py-1 break-words whitespace-normal leading-snug">{{ formatReadableText(line.rpjmdName) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-center text-sm font-semibold text-slate-700 break-words whitespace-normal">
                  <div class="rounded-md border border-emerald-200 bg-white px-2 py-1 break-words whitespace-normal leading-snug">{{ line.rpjmdTarget }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-sm text-slate-800 break-words whitespace-normal">
                  <div class="rounded-md border border-emerald-200 bg-emerald-50/40 px-2 py-1 break-words whitespace-normal leading-snug">{{ formatReadableText(line.renstraName) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-center text-sm font-semibold text-slate-700 break-words whitespace-normal">
                  <div class="rounded-md border border-emerald-200 bg-white px-2 py-1 break-words whitespace-normal leading-snug">{{ line.renstraTarget }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-sm text-slate-800 break-words whitespace-normal">
                  <div class="rounded-md border border-emerald-200 bg-emerald-50/40 px-2 py-1 break-words whitespace-normal leading-snug">{{ formatReadableText(line.rkpdName) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-center text-sm font-semibold text-slate-700 break-words whitespace-normal">
                  <div class="rounded-md border border-emerald-200 bg-white px-2 py-1 break-words whitespace-normal leading-snug">{{ line.rkpdTarget }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-center text-sm font-semibold break-words whitespace-normal">
                  <div :class="[getStatusClass(line.statusRpjmdRenstra), 'rounded-md border border-emerald-200 bg-white px-2 py-1 break-words whitespace-normal leading-snug']">{{ line.statusRpjmdRenstra }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-center text-sm font-semibold break-words whitespace-normal">
                  <div :class="[getStatusClass(line.statusRpjmdRkpd), 'rounded-md border border-emerald-200 bg-white px-2 py-1 break-words whitespace-normal leading-snug']">{{ line.statusRpjmdRkpd }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-center text-sm font-semibold break-words whitespace-normal">
                  <div :class="[getStatusClass(line.statusRenstraRkpd), 'rounded-md border border-emerald-200 bg-white px-2 py-1 break-words whitespace-normal leading-snug']">{{ line.statusRenstraRkpd }}</div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div
        v-else-if="currentView === 'konsistensi-rpjmd-rkpd' && currentTable === 'tabel-4' && tableData"
        class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md"
      >
        <table class="w-full table-fixed border-collapse text-sm">
          <colgroup>
            <col class="w-[52px]" />
            <col class="w-[170px]" />
            <col class="w-[260px]" />
            <col class="w-[150px]" />
            <col class="w-[150px]" />
            <col class="w-[150px]" />
            <col class="w-[150px]" />
            <col class="w-[120px]" />
            <col class="w-[150px]" />
            <col class="w-[120px]" />
            <col class="w-[150px]" />
            <col class="w-[120px]" />
          </colgroup>
          <thead>
            <tr class="bg-emerald-50">
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">No</th>
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-left font-bold text-emerald-900">{{ entityHeaderLabel }}</th>
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-left font-bold text-emerald-900">Program</th>
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Pagu RPJMD ({{ yearValue || 2026 }})</th>
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Pagu Renstra ({{ yearValue || 2026 }})</th>
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Pagu RKPD/Renja ({{ yearValue || 2026 }})</th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Konsistensi RPJMD - Renstra</th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Konsistensi RPJMD - RKPD/Renja</th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Konsistensi Renstra - RKPD/Renja</th>
            </tr>
            <tr class="bg-emerald-50">
              <th class="border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Selisih Anggaran</th>
              <th class="border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Status</th>
              <th class="border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Selisih Anggaran</th>
              <th class="border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Status</th>
              <th class="border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Selisih Anggaran</th>
              <th class="border border-emerald-200 bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-900">Status</th>
            </tr>
            <tr class="bg-emerald-100">
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(1)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(2)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(3)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(4)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(5)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(6)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(7)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(8)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(9)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(10)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(11)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold text-emerald-700">(12)</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(row, idx) in tableData" :key="'budget-group-' + idx">
              <tr
                v-for="(line, lineIdx) in getAlignedAnggaranRows(row)"
                :key="'budget-line-' + idx + '-' + lineIdx"
                :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'"
              >
                <td v-if="lineIdx === 0" :rowspan="getAlignedAnggaranRows(row).length" class="border border-emerald-200 px-2 py-2 align-top text-center font-semibold text-slate-700">{{ row.no }}</td>
                <td v-if="lineIdx === 0" :rowspan="getAlignedAnggaranRows(row).length" class="border border-emerald-200 px-2 py-2 align-top font-medium text-slate-900 break-words whitespace-normal">{{ formatEntityLabel(row.entitas) }}</td>

                <td class="border border-emerald-200 px-2 py-2 align-top text-sm text-slate-800 break-words whitespace-normal">
                  <div class="rounded-md border border-emerald-200 bg-emerald-50/40 px-2 py-1 break-words whitespace-normal leading-snug">{{ formatReadableText(line.programName) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-right text-sm font-semibold text-slate-700">
                  <div class="rounded-md border border-emerald-200 bg-white px-2 py-1">{{ formatRupiah(line.rpjmdPagu) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-right text-sm font-semibold text-slate-700">
                  <div class="rounded-md border border-emerald-200 bg-white px-2 py-1">{{ formatRupiah(line.renstraPagu) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-right text-sm font-semibold text-slate-700">
                  <div class="rounded-md border border-emerald-200 bg-white px-2 py-1">{{ formatRupiah(line.rkpdPagu) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-right text-sm font-semibold text-slate-700">
                  <div class="rounded-md border border-emerald-200 bg-white px-2 py-1">{{ formatRupiah(line.diffRpjmdRenstra) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-center text-sm font-semibold">
                  <div :class="[getStatusClass(line.statusRpjmdRenstra), 'rounded-md border border-emerald-200 bg-white px-2 py-1']">{{ line.statusRpjmdRenstra }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-right text-sm font-semibold text-slate-700">
                  <div class="rounded-md border border-emerald-200 bg-white px-2 py-1">{{ formatRupiah(line.diffRpjmdRkpd) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-center text-sm font-semibold">
                  <div :class="[getStatusClass(line.statusRpjmdRkpd), 'rounded-md border border-emerald-200 bg-white px-2 py-1']">{{ line.statusRpjmdRkpd }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-right text-sm font-semibold text-slate-700">
                  <div class="rounded-md border border-emerald-200 bg-white px-2 py-1">{{ formatRupiah(line.diffRenstraRkpd) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-center text-sm font-semibold">
                  <div :class="[getStatusClass(line.statusRenstraRkpd), 'rounded-md border border-emerald-200 bg-white px-2 py-1']">{{ line.statusRenstraRkpd }}</div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div v-if="showProgramModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeProgramModal">
        <div class="max-h-[80vh] w-full max-w-3xl overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-emerald-100 px-5 py-4">
            <div>
              <h3 class="text-lg font-bold text-emerald-900">{{ selectedProgramTitle }}</h3>
              <p class="text-sm text-slate-500">{{ selectedEntity }}</p>
            </div>
            <button type="button" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100" @click="closeProgramModal">
              Tutup
            </button>
          </div>

          <div class="max-h-[60vh] overflow-y-auto px-5 py-4">
            <template v-if="modalMode === 'single'">
              <div v-if="selectedPrograms.length === 0" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Tidak ada list {{ metricLabelLower }}.
              </div>
              <ul v-else class="space-y-2">
                <li v-for="(program, index) in selectedPrograms" :key="program.kode + '-' + program.nama + '-' + index" class="rounded-lg border border-emerald-100 bg-emerald-50/50 px-4 py-3">
                  <p class="text-sm font-semibold text-slate-900">{{ index + 1 }}. {{ program.nama }}</p>
                  <p class="text-xs text-slate-600">
                    Kode: {{ program.kode }} | Dokumen: {{ program.dokumen }}
                    <span v-if="isIndikatorMode && program.jenis"> | Jenis: {{ program.jenis }}</span>
                    <span v-if="program.tahun"> | Tahun: {{ program.tahun }}</span>
                  </p>
                </li>
              </ul>
                

            </template>

            <template v-else-if="modalMode === 'same'">
              <div v-if="selectedCompareSamePrograms.length === 0" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Tidak ada {{ metricLabelLower }} yang sama di kedua dokumen.
              </div>
              <ul v-else class="space-y-2">
                <li v-for="(program, index) in selectedCompareSamePrograms" :key="program.kode + '-' + program.nama + '-' + index" class="rounded-lg border border-emerald-100 bg-emerald-50/50 px-4 py-3">
                  <p class="text-sm font-semibold text-slate-900">{{ index + 1 }}. {{ program.nama }}</p>
                  <p class="text-xs text-slate-600">Kode: {{ program.kode }}</p>
                </li>
              </ul>
            </template>

            <template v-else>
              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50/40 p-3">
                  <p class="text-sm font-bold text-emerald-900">{{ selectedLeftLabel }} (tidak ada di {{ selectedRightLabel }})</p>
                  <div v-if="selectedLeftOnlyPrograms.length === 0" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
                    Semua {{ metricLabelLower }} {{ selectedLeftLabel }} ada di {{ selectedRightLabel }}.
                  </div>
                  <ul v-else class="space-y-2">
                    <li v-for="(program, index) in selectedLeftOnlyPrograms" :key="'left-' + program.kode + '-' + program.nama + '-' + index" class="rounded-lg border border-emerald-100 bg-white px-3 py-2">
                      <p class="text-sm font-semibold text-slate-900">{{ index + 1 }}. {{ program.nama }}</p>
                      <p class="text-xs text-slate-600">Kode: {{ program.kode }}</p>
                    </li>
                  </ul>
                </div>

                <div class="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50/40 p-3">
                  <p class="text-sm font-bold text-emerald-900">{{ selectedRightLabel }} (tidak ada di {{ selectedLeftLabel }})</p>
                  <div v-if="selectedRightOnlyPrograms.length === 0" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
                    Semua {{ metricLabelLower }} {{ selectedRightLabel }} ada di {{ selectedLeftLabel }}.
                  </div>
                  <ul v-else class="space-y-2">
                    <li v-for="(program, index) in selectedRightOnlyPrograms" :key="'right-' + program.kode + '-' + program.nama + '-' + index" class="rounded-lg border border-emerald-100 bg-white px-3 py-2">
                      <p class="text-sm font-semibold text-slate-900">{{ index + 1 }}. {{ program.nama }}</p>
                      <p class="text-xs text-slate-600">Kode: {{ program.kode }}</p>
                    </li>
                  </ul>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <div
        v-else
        class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900 shadow-md"
      >
        Tampilan untuk {{ currentTableLabel }} pada {{ viewTitle }} sedang disiapkan.
      </div>
    </section>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, h, ref } from 'vue';

const props = defineProps({
  currentView: {
    type: String,
    default: '',
  },
  currentTable: {
    type: String,
    default: '',
  },
  viewTitle: {
    type: String,
    default: 'Resume',
  },
  filterBasis: {
    type: String,
    default: 'perangkat-daerah',
  },
  selectedYear: {
    type: Number,
    default: null,
  },
  selectedTw: {
    type: Number,
    default: null,
  },
  selectedBidang: {
    type: [String, Number],
    default: null,
  },
  bidangUrusans: {
    type: Array,
    default: () => [],
  },
  availableYears: {
    type: Array,
    default: () => [],
  },
  tableData: {
    type: Array,
    default: null,
  },
  tableMetricType: {
    type: String,
    default: 'program',
  },
});

// DEBUG: log first row to inspect indicators presence (temporary)
if (typeof console !== 'undefined') {
  console.debug('resume.tableData.firstRow', props.tableData?.[0] ?? null);
}

const basisValue = ref(props.filterBasis);
const yearValue = ref(props.selectedYear);
const twValue = ref(props.selectedTw);
const showProgramModal = ref(false);
const selectedPrograms = ref([]);
const selectedProgramTitle = ref('List Program');
const selectedEntity = ref('');
const modalMode = ref('single');
const selectedCompareSamePrograms = ref([]);
const selectedLeftOnlyPrograms = ref([]);
const selectedRightOnlyPrograms = ref([]);
const selectedLeftLabel = ref('');
const selectedRightLabel = ref('');
const dokumenYears = [2026, 2027, 2028, 2029, 2030];
const availableTws = [1, 2, 3, 4];

const DokumenCell = {
  props: {
    cell: {
      type: Object,
      default: () => ({ has_file: false, view_url: null, judul: null }),
    },
  },
  setup(localProps) {
    return () => {
      if (localProps.cell?.has_file && localProps.cell?.view_url) {
        return h('a', {
          href: localProps.cell.view_url,
          target: '_blank',
          title: localProps.cell.judul || 'Lihat file',
          class: 'inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-2 text-emerald-700 transition-colors hover:bg-emerald-100',
        }, h('svg', {
          xmlns: 'http://www.w3.org/2000/svg',
          class: 'h-4 w-4',
          fill: 'currentColor',
          viewBox: '0 0 24 24',
        }, h('path', { d: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z' })));
      }

      return h('span', {
        title: 'File belum tersedia',
        class: 'inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 font-bold text-red-600',
      }, 'X');
    };
  },
};

const entityHeaderLabel = computed(() => {
  return basisValue.value === 'perangkat-daerah' ? 'Perangkat Daerah' : 'Bidang Urusan';
});

function computeProgramRowspans(lines) {
  if (!Array.isArray(lines) || lines.length === 0) return [];
  const spans = new Array(lines.length).fill(0);
  let i = 0;
  while (i < lines.length) {
    const name = (lines[i]?.program_name ?? '') || '';
    let j = i + 1;
    while (j < lines.length && ((lines[j]?.program_name ?? '') || '') === name) {
      j++;
    }
    spans[i] = j - i;
    i = j;
  }
  return spans;
}

const isDokumenView = computed(() => props.currentView === 'dokumen' && props.currentTable === 'monitoring');

const isIndikatorMode = computed(() => {
  return props.tableMetricType === 'indikator';
});

const metricLabel = computed(() => {
  if (props.tableMetricType === 'indikator') return 'Indikator Program';
  if (props.tableMetricType === 'kegiatan') return 'Kegiatan';
  if (props.tableMetricType === 'sub_kegiatan') return 'Sub Kegiatan';
  return 'Program';
});

const metricLabelLower = computed(() => {
  if (isIndikatorMode.value) return 'indikator program';
  if (props.tableMetricType === 'kegiatan') return 'kegiatan';
  if (props.tableMetricType === 'sub_kegiatan') return 'sub kegiatan';
  return 'program';
});

const shouldShowExportButtons = computed(() => {
  return (['konsistensi-rpjmd-rkpd', 'konsistensi-rkpd-apbd'].includes(props.currentView)
    && ['tabel-1', 'tabel-2', 'tabel-3', 'tabel-4', 'tabel-5', 'tabel-6', 'tabel-7','tabel-8','tabel-9', 'tabel-10'].includes(props.currentTable)
    && Array.isArray(props.tableData)) || (props.currentView === 'realisasi' && props.currentTable === 'iku' && Array.isArray(props.tableData));
});

const showTargetColumns = computed(() => {
  // Hide RKPD/DPA target columns only when viewing tabel-4 on konsistensi-rkpd-apbd
  return !(props.currentView === 'konsistensi-rkpd-apbd' && props.currentTable === 'tabel-4');
});

const groupedByBidang = computed(() => {
  if (!Array.isArray(props.tableData)) return [];
  const map = {};
  props.tableData.forEach((r) => {
    const key = r.bidang ?? r.entitas ?? 'Lainnya';
    if (!map[key]) map[key] = [];
    map[key].push(r);
  });

  const groups = [];
  let programCounter = 0;
  Object.keys(map).forEach((k, gi) => {
    const progs = map[k].map((row) => {
      programCounter += 1;
      return { ...row, _global_index: programCounter };
    });
    groups.push({ bidang: k, programs: progs, group_index: gi + 1 });
  });

  return groups;
});

const buildExportUrl = (format) => {
  return route('resume.export', {
    view: props.currentView,
    table: props.currentTable,
    basis: basisValue.value,
    year: yearValue.value,
    tw: twValue.value,
    format,
  });
};

const applyFilters = () => {
  router.get(
    route('resume.index'),
    {
      view: props.currentView,
      table: props.currentTable,
      basis: basisValue.value,
      year: yearValue.value,
      tw: twValue.value,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
};

const openProgramList = (row, type) => {
  const subject = metricLabel.value;
  const labels = {
    rpjmd: `List ${subject} RPJMD (Program Prioritas Renstra)`,
    renstra: `List ${subject} Renstra`,
    rkpd: `List ${subject} RKPD/Renja`,
  };

  modalMode.value = 'single';
  selectedProgramTitle.value = labels[type] ?? 'List Program';
  selectedEntity.value = formatEntityLabel(row.entitas);
  selectedPrograms.value = getUniquePrograms(row[`${type}_programs`] ?? []);
  showProgramModal.value = true;
};

const openLineContent = (subject, key, title) => {
  modalMode.value = 'single';
  selectedProgramTitle.value = title;
  selectedEntity.value = subject?.entitas ? formatEntityLabel(subject.entitas) : '';

  const content = subject?.[key];
  if (Array.isArray(content)) {
    selectedPrograms.value = content.map((it) => {
      if (!it) return { nama: '-' };
      return { nama: it.nama ?? it.indikator ?? it.uraian ?? String(it) };
    });
  } else {
    const text = String(content ?? '').trim();
    selectedPrograms.value = text ? [{ nama: text }] : [];
  }

  showProgramModal.value = true;
};

const openComparisonModal = (row, leftKey, rightKey, leftLabel, rightLabel, mode) => {
  const comparison = compareProgramLists(row?.[leftKey], row?.[rightKey]);

  modalMode.value = mode;
  selectedEntity.value = formatEntityLabel(row?.entitas);
  selectedLeftLabel.value = leftLabel;
  selectedRightLabel.value = rightLabel;
  selectedCompareSamePrograms.value = comparison.samePrograms;
  selectedLeftOnlyPrograms.value = comparison.leftOnlyPrograms;
  selectedRightOnlyPrograms.value = comparison.rightOnlyPrograms;

  if (mode === 'same') {
    selectedProgramTitle.value = `List ${metricLabel.value} Yang Sama (${leftLabel} - ${rightLabel})`;
  } else {
    selectedProgramTitle.value = `List ${metricLabel.value} Yang Tidak Sama (${leftLabel} - ${rightLabel})`;
  }

  showProgramModal.value = true;
};

const closeProgramModal = () => {
  showProgramModal.value = false;
};

const normalizeText = (value) => {
  return String(value ?? '')
    .replace(/\s+/g, ' ')
    .trim()
    .toUpperCase();
};

const normalizeComparableText = (value) => {
  return normalizeText(value)
    .replace(/[^A-Z0-9]/g, '');
};

// Extract indicator names from various DB shapes (KomponenAnggaran.indikator relation,
// IndikatorAnggaran fields, or simple string fields). Returns array of strings.
const extractIndicatorNamesGlobal = (item) => {
  const out = [];
  if (!item) return out;
  if (Array.isArray(item.indikator)) {
    item.indikator.forEach((it) => {
      if (!it) return;
      if (typeof it === 'string') out.push(it.trim());
      else if (it.nama_indikator) out.push(String(it.nama_indikator).trim());
      else if (it.nama) out.push(String(it.nama).trim());
    });
    return out.filter(Boolean);
  }

  if (typeof item.indikator === 'string' && item.indikator.trim() !== '') {
    out.push(item.indikator.trim());
    return out;
  }

  if (item.nama_indikator) out.push(String(item.nama_indikator).trim());
  if (item.indikator_nama) out.push(String(item.indikator_nama).trim());

  const fallback = item?.uraian || item?.nama || item?.program_nama || item?.indikator;
  if (fallback) out.push(String(fallback).trim());

  return out.filter(Boolean);
};

const buildProgramKey = (program) => {
  if (isIndikatorMode.value) {
    // Untuk indikator, ID antar sumber berbeda (indikator vs indikator_anggaran),
    // jadi persamaan ditentukan dari nama indikator yang dinormalisasi.
    return normalizeComparableText(program?.nama);
  }

  return `${normalizeText(program?.kode)}|${normalizeText(program?.nama)}`;
};

const buildUniqueProgramMap = (programs) => {
  const map = new Map();

  (programs ?? []).forEach((program) => {
    const key = buildProgramKey(program);

    if (!key || key === '|') {
      return;
    }

    if (!map.has(key)) {
      map.set(key, program);
    }
  });

  return map;
};

// Build maps keyed by full program key and by normalized name to allow
// fuzzy matching when codes differ between sources.
const buildProgramMaps = (programs) => {
  const full = new Map();
  const name = new Map();

  (programs ?? []).forEach((program) => {
    const fullKey = buildProgramKey(program);
    const nameKey = normalizeComparableText(program?.nama || program?.program_nama || '');

    if (fullKey && fullKey !== '|') {
      if (!full.has(fullKey)) full.set(fullKey, program);
    }

    if (nameKey) {
      if (!name.has(nameKey)) name.set(nameKey, program);
    }
  });

  return { full, name };
};

const getUniquePrograms = (programs) => {
  return Array.from(buildUniqueProgramMap(programs).values());
};

const getComparableTotalByKey = (row, key) => {
  return buildUniqueProgramMap(row?.[key]).size;
};

const getIndicatorItems = (row, key) => {
  return getUniquePrograms(row?.[key]);
};

const formatIndicatorTarget = (item) => {
  const target = item?.target;

  return target === undefined || target === null || String(target).trim() === '' ? '-' : String(target);
};

const toNumber = (value) => {
  if (value === null || value === undefined || value === '') {
    return 0;
  }

  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : 0;
  }

  const cleaned = String(value).replace(/[^0-9.-]/g, '');
  const parsed = Number.parseFloat(cleaned);

  return Number.isFinite(parsed) ? parsed : 0;
};

const formatRupiah = (value) => {
  const amount = Math.round(toNumber(value));
  return amount.toLocaleString('id-ID');
};

const formatReadableText = (value) => {
  const text = String(value ?? '').trim();
  if (text === '') {
    return '';
  }

  return text
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/([A-Z])([A-Z][a-z])/g, '$1 $2')
    .replace(/([0-9])([A-Za-z])/g, '$1 $2')
    .replace(/([A-Za-z])([0-9])/g, '$1 $2')
    .replace(/\s+/g, ' ')
    .trim();
};

const getRowProgramName = (row) => {
  const lists = [
    row?.rpjmd_programs,
    row?.renstra_programs,
    row?.rkpd_programs,
  ];

  for (const list of lists) {
    for (const item of list ?? []) {
      const programName = String(item?.program_nama ?? '').trim();
      if (programName !== '') {
        return programName;
      }
    }
  }

  return '-';
};

const getProgramNamesForRow = (row) => {
  const combined = [ ...(row?.rpjmd_programs ?? []), ...(row?.renstra_programs ?? []), ...(row?.rkpd_programs ?? []), ...(row?.dpa_programs ?? []) ];
  const uniq = getUniquePrograms(combined || []);
  return uniq.map(p => (p?.nama ?? p?.program_nama ?? p?.indikator ?? '').trim()).filter(Boolean);
};

const getIndicatorsForRow = (row, type) => {
  const list = row?.[`${type}_programs`] ?? [];
  const items = [];
  const extractIndicatorNames = (item) => {
    const out = [];
    if (!item) return out;
    // relation `indikator` often an array of objects with `nama_indikator`
    if (Array.isArray(item.indikator)) {
      item.indikator.forEach((it) => {
        if (!it) return;
        if (typeof it === 'string') out.push(it.trim());
        else if (it.nama_indikator) out.push(String(it.nama_indikator).trim());
        else if (it.nama) out.push(String(it.nama).trim());
      });
      return out;
    }

    // sometimes indikator stored as single string
    if (typeof item.indikator === 'string' && item.indikator.trim() !== '') {
      out.push(item.indikator.trim());
      return out;
    }

    // direct fields from KomponenAnggaran / Indikator model
    if (item.nama_indikator) out.push(String(item.nama_indikator).trim());
    if (item.indikator_nama) out.push(String(item.indikator_nama).trim());

    // fallback to descriptive fields
    const fallback = item?.uraian || item?.nama || item?.program_nama || item?.indikator;
    if (fallback) out.push(String(fallback).trim());

    return out.filter(Boolean);
  };

  (list || []).forEach((p) => {
    if (!p) return;
    const names = extractIndicatorNames(p);
    names.forEach(n => items.push(n));
  });

  // unique and filter empty
  return Array.from(new Set(items.map(s => String(s || '').trim()))).filter(Boolean);
};

const getRawIndicatorsForProgram = (row, type, key) => {
  const list = row?.[`${type}_programs`] ?? [];
  const items = [];
  const extractIndicatorNames = (item) => {
    const out = [];
    if (!item) return out;
    if (Array.isArray(item.indikator)) {
      item.indikator.forEach((it) => {
        if (!it) return;
        if (typeof it === 'string') out.push(it.trim());
        else if (it.nama_indikator) out.push(String(it.nama_indikator).trim());
        else if (it.nama) out.push(String(it.nama).trim());
      });
      return out;
    }

    if (typeof item.indikator === 'string' && item.indikator.trim() !== '') {
      out.push(item.indikator.trim());
      return out;
    }

    if (item.nama_indikator) out.push(String(item.nama_indikator).trim());
    if (item.indikator_nama) out.push(String(item.indikator_nama).trim());

    const fallback = item?.uraian || item?.nama || item?.program_nama || item?.indikator;
    if (fallback) out.push(String(fallback).trim());

    return out.filter(Boolean);
  };

  (list || []).forEach((p) => {
    if (!p) return;
    const k = buildProgramKey(p) || '';
    if (k !== key) return;
    const names = extractIndicatorNames(p);
    names.forEach(n => items.push(n));
  });

  return Array.from(new Set(items.map(s => String(s || '').trim()))).filter(Boolean);
};

// If RKPD entries are missing, try to find indicators from matching DPA entries (fallback)
const getRawIndicatorsForProgramWithFallback = (row, type, key) => {
  const base = getRawIndicatorsForProgram(row, type, key);
  if (base.length > 0) return base;
  if (type === 'rkpd') {
    const alt = getRawIndicatorsForProgram(row, 'dpa', key);
    return alt;
  }
  return base;
};

const getProgramLines = (row) => {
  const map = new Map();

  // if server prepared `lines` exist, pre-populate map entries so we can still
  // merge indicator arrays from rkpd_programs/dpa_programs afterwards
  if (Array.isArray(row?.lines) && row.lines.length > 0) {
    row.lines.forEach((l, idx) => {
      const name = String(l.program_name ?? l.programName ?? '').trim() || '-';
      const key = `${name}::${idx}`;
      if (!map.has(key)) {
        map.set(key, {
          key,
          name,
          rpjmdIndicators: [],
          renstraIndicators: [],
          rkpdIndicators: l.rkpd_name ? [String(l.rkpd_name).trim()] : [],
          dpaIndicators: l.dpa_name ? [String(l.dpa_name).trim()] : [],
        });
      }
    });
  }

  const pushProgram = (p, srcType) => {
    if (!p) return;
    const key = buildProgramKey(p) || `__${Math.random().toString(36).slice(2,8)}`;
    if (!map.has(key)) {
      map.set(key, {
        key,
        name: (p?.nama ?? p?.program_nama ?? p?.nama_program ?? '').trim() || (p?.program_nama ?? p?.nama ?? '') || '-',
        rpjmdIndicators: [],
        renstraIndicators: [],
        rkpdIndicators: [],
        dpaIndicators: [],
      });
    }

    const entry = map.get(key);

    const collectIndicatorsFrom = (item) => {
      if (props.tableMetricType === 'program') return [];
      return extractIndicatorNamesGlobal(item);
    };

    const inds = collectIndicatorsFrom(p);
    // fallback: for non-program metric types, if this is an RKPD item but has no indicators,
    // try to find matching DPA program and borrow indicators (useful for kegiatan/sub_kegiatan views)
    if (props.tableMetricType !== 'program') {
      if ((srcType === 'rkpd') && inds.length === 0 && Array.isArray(row?.dpa_programs)) {
        const k = buildProgramKey(p) || '';
        const match = row.dpa_programs.find(q => (buildProgramKey(q) || '') === k);
        if (match) {
          const alt = collectIndicatorsFrom(match);
          if (alt.length > 0) inds.push(...alt);
        }
      }
      // loose fallback: match by program name if key match failed
      if ((srcType === 'rkpd') && inds.length === 0 && Array.isArray(row?.dpa_programs)) {
        const name = String(p?.nama ?? p?.program_nama ?? '').trim();
        if (name) {
          const matchByName = row.dpa_programs.find(q => String((q?.nama ?? q?.program_nama ?? '')).trim() === name);
          if (matchByName) {
            const alt2 = collectIndicatorsFrom(matchByName);
            if (alt2.length > 0) inds.push(...alt2);
          }
        }
      }
    }
    if (inds.length === 0 && (p?.indikator_text || p?.indikator_nama)) {
      inds.push(String(p.indikator_text || p.indikator_nama).trim());
    }

    if (srcType === 'rpjmd') entry.rpjmdIndicators.push(...inds);
    if (srcType === 'renstra') entry.renstraIndicators.push(...inds);
    if (srcType === 'rkpd') entry.rkpdIndicators.push(...inds);
    if (srcType === 'dpa') entry.dpaIndicators.push(...inds);
  };

  (row?.rpjmd_programs || []).forEach(p => pushProgram(p, 'rpjmd'));
  (row?.renstra_programs || []).forEach(p => pushProgram(p, 'renstra'));
  (row?.rkpd_programs || []).forEach(p => pushProgram(p, 'rkpd'));
  (row?.dpa_programs || []).forEach(p => pushProgram(p, 'dpa'));

  const lines = Array.from(map.values()).map((e) => ({
    key: e.key,
    name: e.name || '-',
    rpjmdIndicators: Array.from(new Set(e.rpjmdIndicators.map(s => String(s || '').trim()))).filter(Boolean),
    renstraIndicators: Array.from(new Set(e.renstraIndicators.map(s => String(s || '').trim()))).filter(Boolean),
    rkpdIndicators: Array.from(new Set(e.rkpdIndicators.map(s => String(s || '').trim()))).filter(Boolean),
    dpaIndicators: Array.from(new Set(e.dpaIndicators.map(s => String(s || '').trim()))).filter(Boolean),
  }));

  // If RKPD indicators are absent but DPA indicators exist for the same program,
  // mirror DPA indicators into RKPD so the RKPD column (kolom 4) shows values.
  // Only do this for non-program metric types (we should not create program-level indicators).
  if (props.tableMetricType !== 'program') {
    lines.forEach((l) => {
      if ((!l.rkpdIndicators || l.rkpdIndicators.length === 0) && (l.dpaIndicators && l.dpaIndicators.length > 0)) {
        l.rkpdIndicators = [...l.dpaIndicators];
      }
    });
  }

  return lines.length > 0 ? lines : [{ key: '__none', name: '-', rpjmdIndicators: [], renstraIndicators: [], rkpdIndicators: [], dpaIndicators: [] }];
};

const getAlignedIndicatorsForProgram = (line) => {
  const left = (line?.rkpdIndicators || []).map(s => String(s || '').trim()).filter(Boolean);
  const right = (line?.dpaIndicators || []).map(s => String(s || '').trim()).filter(Boolean);

  const normalize = (s) => String(s || '').replace(/\s+/g, ' ').trim().toUpperCase();

  const matchedRight = new Set();
  const pairs = [];

  // match left items to first matching right item
  for (let i = 0; i < left.length; i += 1) {
    const l = left[i];
    let found = -1;
    for (let j = 0; j < right.length; j += 1) {
      if (matchedRight.has(j)) continue;
      if (normalize(l) === normalize(right[j])) { found = j; break; }
    }
    if (found >= 0) {
      matchedRight.add(found);
      pairs.push({ rkpd: l, dpa: right[found] });
    } else {
      pairs.push({ rkpd: l, dpa: null });
    }
  }

  // remaining right-only items
  for (let j = 0; j < right.length; j += 1) {
    if (!matchedRight.has(j)) {
      pairs.push({ rkpd: null, dpa: right[j] });
    }
  }

  return pairs;
};

function getIndicatorRowsForLine(line, row) {
  // Align indicators across RKPD and DPA (and use RPJMD/Renstra as hints) by
  // normalizing indicator texts and merging matches so identical indicators
  // appear on the same row.
  const normalize = (s) => String(s || '').replace(/\s+/g, ' ').trim().toUpperCase();

  const collect = (arr) => (Array.isArray(arr) ? arr.map(s => String(s || '').trim()).filter(Boolean) : []);
  const rkpdList = collect(line?.rkpdIndicators);
  const dpaList = collect(line?.dpaIndicators);
  const rpjmdList = collect(line?.rpjmdIndicators);
  const renstraList = collect(line?.renstraIndicators);

  // Build maps from normalized indicator text -> original text
  const rkpdMap = new Map();
  rkpdList.forEach(i => rkpdMap.set(normalize(i), i));
  const dpaMap = new Map();
  dpaList.forEach(i => dpaMap.set(normalize(i), i));

  // Also collect per-indicator targets from the original program items (if available)
  const rkpdTargetMap = new Map();
  (row?.rkpd_programs || []).forEach((p) => {
    const names = extractIndicatorNamesGlobal(p);
    names.forEach((n) => {
      const k = normalize(n);
      if (!rkpdTargetMap.has(k)) {
        // prefer common keys: try several possible field names
        let t = p?.target ?? p?.indikator_target ?? p?.target_indikator ?? null;
        // if indikator array contains objects with targets, try to find matching name
        if ((t === null || t === undefined) && Array.isArray(p?.indikator)) {
          for (let el of p.indikator) {
            if (!el) continue;
            const elName = (typeof el === 'string') ? String(el).trim() : (el.nama_indikator ?? el.nama ?? '');
            if (elName && normalize(elName) === k) {
              t = el.target_indikator ?? el.target ?? t;
              break;
            }
          }
        }
        // also accept a mapping object `indikator_targets` keyed by name
        if ((t === null || t === undefined) && p?.indikator_targets && typeof p.indikator_targets === 'object') {
          const mapped = p.indikator_targets[k] ?? p.indikator_targets[elName] ?? null;
          if (mapped !== undefined) t = mapped;
        }

        rkpdTargetMap.set(k, t ?? null);
      }
    });
  });

  const dpaTargetMap = new Map();
  (row?.dpa_programs || []).forEach((p) => {
    const names = extractIndicatorNamesGlobal(p);
    names.forEach((n) => {
      const k = normalize(n);
      if (!dpaTargetMap.has(k)) {
        let t = p?.target ?? p?.indikator_target ?? p?.target_indikator ?? null;
        if ((t === null || t === undefined) && Array.isArray(p?.indikator)) {
          for (let el of p.indikator) {
            if (!el) continue;
            const elName = (typeof el === 'string') ? String(el).trim() : (el.nama_indikator ?? el.nama ?? '');
            if (elName && normalize(elName) === k) {
              t = el.target_indikator ?? el.target ?? t;
              break;
            }
          }
        }
        if ((t === null || t === undefined) && p?.indikator_targets && typeof p.indikator_targets === 'object') {
          const mapped = p.indikator_targets[k] ?? null;
          if (mapped !== undefined) t = mapped;
        }
        dpaTargetMap.set(k, t ?? null);
      }
    });
  });
  const rpjmdMap = new Map();
  rpjmdList.forEach(i => rpjmdMap.set(normalize(i), i));
  const renstraMap = new Map();
  renstraList.forEach(i => renstraMap.set(normalize(i), i));

  // Create ordered union: prefer rkpd order, then dpa, then rpjmd, then renstra
  const seen = new Set();
  const keys = [];
  const pushKey = (k) => { if (k && !seen.has(k)) { seen.add(k); keys.push(k); } };
  rkpdList.forEach(i => pushKey(normalize(i)));
  dpaList.forEach(i => pushKey(normalize(i)));
  rpjmdList.forEach(i => pushKey(normalize(i)));
  renstraList.forEach(i => pushKey(normalize(i)));

  if (keys.length === 0) {
    // fallback: keep single empty row so table renders
    return [{ rkpd: '', dpa: '' }];
  }

  return keys.map((k) => ({
    rkpd: rkpdMap.get(k) || '',
    dpa: dpaMap.get(k) || '',
    rkpd_target: rkpdTargetMap.has(k) ? rkpdTargetMap.get(k) : null,
    dpa_target: dpaTargetMap.has(k) ? dpaTargetMap.get(k) : null,
  }));
}

function getTotalDisplayRows(row) {
  const lines = getProgramLines(row || []);
  let total = 0;
  lines.forEach((l) => {
    total += getIndicatorRowsForLine(l, row).length;
  });
  return Math.max(total, 1);
}

function determineIndicatorStatus(indRow) {
  const normalize = (s) => String(s || '').replace(/\s+/g, ' ').trim().toUpperCase();
  const l = String(indRow?.rkpd || '').trim();
  const r = String(indRow?.dpa || '').trim();

  if (!l && !r) return '-';
  if (normalize(l) === normalize(r) && l && r) return 'Konsisten';
  return 'Tidak Konsisten';
}

const getStatusByKey = (item, comparatorMap) => {
  if (!item) {
    return '-';
  }

  return comparatorMap.has(buildProgramKey(item)) ? 'Konsisten' : 'Tidak Konsisten';
};

const getAlignedIndicatorRows = (row) => {
  const rpjmdItems = getIndicatorItems(row, 'rpjmd_programs');
  const renstraItems = getIndicatorItems(row, 'renstra_programs');
  const rkpdItems = getIndicatorItems(row, 'rkpd_programs');

  const rpjmdMaps = buildProgramMaps(rpjmdItems);
  const renstraMaps = buildProgramMaps(renstraItems);
  const rkpdMaps = buildProgramMaps(rkpdItems);

  const nameKeys = new Set([
    ...Array.from(rpjmdMaps.name.keys()),
    ...Array.from(renstraMaps.name.keys()),
    ...Array.from(rkpdMaps.name.keys()),
  ]);

  const rowKeys = nameKeys.size > 0 ? Array.from(nameKeys) : [''];
  const rows = [];

  for (let index = 0; index < rowKeys.length; index += 1) {
    const nameKey = rowKeys[index];
    const rpjmdItem = rpjmdMaps.name.get(nameKey) ?? null;
    const renstraItem = renstraMaps.name.get(nameKey) ?? null;
    const rkpdItem = rkpdMaps.name.get(nameKey) ?? null;

    const rpjmdPagu = toNumber(rpjmdItem?.pagu);
    const renstraPagu = toNumber(renstraItem?.pagu);
    const rkpdPagu = toNumber(rkpdItem?.pagu);

    const diffRpjmdRenstra = Math.abs(rpjmdPagu - renstraPagu);
    const diffRpjmdRkpd = Math.abs(rpjmdPagu - rkpdPagu);
    const diffRenstraRkpd = Math.abs(renstraPagu - rkpdPagu);

    rows.push({
      programName: rpjmdItem?.nama ?? renstraItem?.nama ?? rkpdItem?.nama ?? '-',
      rpjmdPagu,
      renstraPagu,
      rkpdPagu,
      diffRpjmdRenstra,
      diffRpjmdRkpd,
      diffRenstraRkpd,
      statusRpjmdRenstra: diffRpjmdRenstra === 0 ? 'Konsisten' : 'Tidak Konsisten',
      statusRpjmdRkpd: diffRpjmdRkpd === 0 ? 'Konsisten' : 'Tidak Konsisten',
      statusRenstraRkpd: diffRenstraRkpd === 0 ? 'Konsisten' : 'Tidak Konsisten',
    });
  }

  return rows;
};

// Helpers for tabel-10
function sumPagu(row, key) {
  const list = row?.[key] ?? [];
  let total = 0;
  (list || []).forEach((it) => {
    const p = toNumber(it?.pagu || it?.pagu_tahunan || 0);
    total += p;
  });
  return total;
}

function sumPaguForProgram(row, key, program) {
  const list = row?.[key] ?? [];
  const progKey = buildProgramKey(program) || '';
  let total = 0;
  (list || []).forEach((it) => {
    const k = buildProgramKey(it) || '';
    if (k === progKey) {
      total += toNumber(it?.pagu || it?.pagu_tahunan || 0);
    }
  });
  return total;
}

function statusForProgram(row, program, leftKey, rightKey) {
  const left = sumPaguForProgram(row, leftKey, program);
  const right = sumPaguForProgram(row, rightKey, program);
  if (left === 0 && right === 0) return '-';
  return left === right ? 'Konsisten' : 'Tidak Konsisten';
}

function totalStatus(row) {
  const rkpd = sumPagu(row, 'rkpd_programs');
  const dpa = sumPagu(row, 'dpa_programs');
  if (rkpd === 0 && dpa === 0) return '-';
  if (rkpd === dpa) return 'Konsisten';
  return 'Tidak Konsisten';
}

// Placeholder: reuse comparison logic (detailed status per-key is handled elsewhere)
function getStatusByKeyPlaceholder(row) {
  // Simple heuristic: compare totals
  return totalStatus(row);
}




const getIndicatorStatusRows = (row, leftKey, rightKey) => {
  const leftMap = buildUniqueProgramMap(row?.[leftKey]);
  const rightMap = buildUniqueProgramMap(row?.[rightKey]);
  const statusRows = [];

  leftMap.forEach((leftItem, key) => {
    statusRows.push({
      nama: leftItem?.nama ?? '-',
      status: rightMap.has(key) ? 'Konsisten' : 'Tidak Konsisten',
    });
  });

  return statusRows;
};

const getStatusClass = (status) => {
  if (status === 'Konsisten') {
    return 'text-emerald-700';
  }

  if (status === 'Tidak Konsisten') {
    return 'text-amber-700';
  }

  return 'text-slate-500';
};

const compareProgramLists = (leftPrograms, rightPrograms) => {
  const leftMap = buildUniqueProgramMap(leftPrograms);
  const rightMap = buildUniqueProgramMap(rightPrograms);

  const samePrograms = [];
  const leftOnlyPrograms = [];
  const rightOnlyPrograms = [];

  leftMap.forEach((leftProgram, key) => {
    if (rightMap.has(key)) {
      samePrograms.push(leftProgram);
      return;
    }

    leftOnlyPrograms.push(leftProgram);
  });

  rightMap.forEach((rightProgram, key) => {
    if (!leftMap.has(key)) {
      rightOnlyPrograms.push(rightProgram);
    }
  });

  return {
    samePrograms,
    leftOnlyPrograms,
    rightOnlyPrograms,
  };
};

const getSameCountByKeys = (row, leftKey, rightKey) => {
  return compareProgramLists(row?.[leftKey], row?.[rightKey]).samePrograms.length;
};

const getLeftDisplayedCount = (row, leftKey) => {
  return getComparableTotalByKey(row, leftKey);
};

const getDifferentCountByKeys = (row, leftKey, rightKey) => {
  const sameCount = getSameCountByKeys(row, leftKey, rightKey);
  const leftDisplayedCount = getLeftDisplayedCount(row, leftKey);

  // Paksa konsistensi aritmetika tabel: Sama + Tidak Sama = Total kolom kiri.
  return Math.max(leftDisplayedCount - sameCount, 0);
};

const formatEntityLabel = (value) => {
  if (!value) return '';

  return value
    .replace(/^\s*URUSAN\s+PEMERINTAHAN\s+BIDANG\s+/i, '')
    .trim();
};

const currentTableLabel = computed(() => {
  if (!props.currentTable) {
    return 'Detail Tabel';
  }

  if (isDokumenView.value) {
    return 'Monitoring Dokumen OPD';
  }

  if (props.currentTable.startsWith('tabel-')) {
    return props.currentTable.replace('tabel-', 'Tabel ');
  }

  return props.currentTable;
});
</script>
