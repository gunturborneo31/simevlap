<template>
  <AppLayout title="Resume Monitoring">
    <section class="space-y-6">
      <div class="rounded-2xl border border-emerald-100 bg-white/90 p-6 shadow-md">
            <div class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
            <h2 v-if="viewTitle" class="text-2xl font-bold text-emerald-900">{{ viewTitle }}</h2>
            <p v-if="currentTableLabel" class="mt-1 text-sm font-semibold text-slate-500">{{ currentTableLabel }}</p>
              <!-- DEBUG BANNER: tampilkan props singkat untuk verifikasi klien -->
              <div class="mt-2 rounded px-3 py-2 text-xs text-slate-600 bg-amber-50 border border-amber-100">
                Debug: view={{ currentView }} — table={{ currentTable }} — rows={{ (tableData && tableData.length) || 0 }} — activeBranch={{ activeBranch }}
              </div>
          </div>
              <div class="flex flex-col gap-3 md:items-end">
            <Link
              :href="route('resume.index', { view: currentView })"
              class="inline-flex items-center justify-center rounded-lg bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 transition-colors hover:bg-emerald-100"
            >
              Kembali ke Daftar Tabel
            </Link>

            <!-- Tombol Lampiran (paksa navigasi dengan anchor biasa) -->
            <a
              :href="route('resume.attachments', { view: currentView, table: currentTable, opd_id: selectedOpd, year: yearValue })"
              class="inline-flex items-center justify-center rounded-lg bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition-colors hover:bg-blue-100 ml-2"
            >
              Lampiran
            </a>

            

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
              <button
                type="button"
                @click="exportRealisasiExcel"
                class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 ml-2"
              >
                Export Excel (Client)
              </button>
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
            <!-- OPD filter + Triwulan for tabel-7 -->
            <div v-if="currentView === 'hasil-pelaksanaan-rkpd' && (currentTable === 'tabel-7' || currentTable === 'tabel-3' || currentTable === 'tabel-4' || currentTable === 'tabel-5')" class="mt-2 md:mt-0 md:ml-4 flex items-center gap-3">
              <div>
                <label class="text-xs text-slate-600 mr-2">OPD</label>
                <select v-model="selectedOpd" @change="applyFilters" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                  <option value="">Semua OPD</option>
                  <option v-for="o in opds" :key="o.id" :value="o.id">{{ o.nama }}</option>
                </select>
              </div>

              <div>
                <label class="text-xs text-slate-600 mr-2">Triwulan</label>
                <div class="inline-flex items-center gap-1">
                  <button v-for="tw in ['all',1,2,3,4]" :key="tw" type="button" @click="chooseTriwulan(tw)" :class="twValue === tw ? 'rounded-lg border px-3 py-2 text-xs font-semibold bg-emerald-500 text-white' : 'rounded-lg border px-3 py-2 text-xs font-semibold bg-white text-slate-600'">{{ tw === 'all' ? 'Semua' : 'TW ' + tw }}</button>
                </div>
              </div>
              
              <div class="flex items-center">
                <button type="button" @click="toggleFullpage" :title="isFullpage ? 'Keluar Fullpage' : 'Tampilkan Fullpage'" class="ml-2 rounded-md px-3 py-2 text-xs font-semibold border bg-white text-slate-700 hover:bg-emerald-50">
                  <span v-if="!isFullpage">Fullpage</span>
                  <span v-else>Exit Fullpage</span>
                </button>
              </div>
            </div>
            </div>

            
          </div>
        </div>

        <!-- Tabel 10: match requested 11-column layout with double-header style -->
        <div v-if="currentTable === 'tabel-10' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
          <table class="min-w-[1000px] w-full border-collapse text-sm">
            <thead class="sticky top-0 bg-emerald-50">
              <tr>
                <th rowspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
                <th rowspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Bidang Urusan / Perangkat Daerah</th>
                <th rowspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Program/Kegiatan/Sub Kegiatan</th>
                <th class="border border-emerald-200 px-3 py-3 text-center font-bold">{{ rkpdLabel }} (Tahun {{ yearValue || 2030 }})</th>
                <th class="border border-emerald-200 px-3 py-3 text-center font-bold">{{ apbdLabel }} (Tahun {{ yearValue || 2030 }})</th>
                <th colspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Konsistensi {{ rkpdLabel }} - {{ apbdLabel }}</th>
                <th colspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Konsistensi RPJMD - {{ rkpdLabel }}</th>
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
                    <td class="border border-emerald-200 px-3 py-3 max-w-[520px] break-words" :title="formatReadableText(program?.nama || program?.program_nama || program?.indikator)">{{ program?.nama ?? program?.program_nama ?? program?.indikator ?? '-' }}</td>

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
            <!-- Row 3: numbering for realisasi table -->
            <tr class="bg-gray-700 text-white">
              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(1)</td>
              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(2)</td>

              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(3)</td>
              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(4)</td>
              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(5)</td>
              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(6)</td>

              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(7)</td>
              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(8)</td>
              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(9)</td>
              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(10)</td>

              <!-- Realisasi TW columns numbering: dynamic based on twValue -->
              <template v-if="twValue">
                <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(11)</td>
                <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(12)</td>
              </template>
              <template v-else>
                <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(11)</td>
                <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(12)</td>
                <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(13)</td>
                <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(14)</td>
                <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(15)</td>
                <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(16)</td>
                <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(17)</td>
                <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(18)</td>
              </template>

              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(19)</td>
              <td class="border border-emerald-200 px-2 py-1 text-center text-xs font-semibold">(20)</td>
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
                <th class="min-w-[160px] border border-emerald-200 px-3 py-3 text-center font-bold">RKPD/Renja (Tahun {{ yearValue || 2030 }})</th>
                <th class="min-w-[160px] border border-emerald-200 px-3                                                                                   xt-center font-bold">APBD (Tahun {{ yearValue || 2030 }})</th>
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
                <td class="border border-emerald-200 px-3 py-3 text-center cursor-pointer text-emerald-800 font-semibold" @click="openLineContent(row, 'rkpd_programs', `List ${metricLabel} ${rkpdLabel}`)">
                  <div class="font-semibold">{{ Number(row.rkpd_count || 0) }}</div>
                </td>
                <td class="border border-emerald-200 px-3 py-3 text-center cursor-pointer text-emerald-800 font-semibold" @click="openLineContent(row, 'dpa_programs', `List ${metricLabel} ${apbdLabel}`)">
                  <div class="font-semibold">{{ Number(row.dpa_count || 0) }}</div>
                </td>
                <td class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold" role="button" tabindex="0" @click="openComparisonModal(row, 'rkpd_programs', 'dpa_programs', rkpdLabel, apbdLabel, 'same')">
                  <span :class="(getSameCountByKeys(row, 'rkpd_programs','dpa_programs')>0) ? 'text-emerald-700' : 'text-slate-500'">{{ getSameCountByKeys(row, 'rkpd_programs','dpa_programs') }}</span>
                </td>
                <td class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold" role="button" tabindex="0" @click="openComparisonModal(row, 'rkpd_programs', 'dpa_programs', rkpdLabel, apbdLabel, 'diff')">
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
                <th class="min-w-[160px] border border-emerald-200 px-3 py-3 text-center font-bold">RKPD/Renja (Tahun {{ yearValue || 2030 }})</th>
                <th class="min-w-[160px] border border-emerald-200 px-3                                                                                   xt-center font-bold">APBD (Tahun {{ yearValue || 2030 }})</th>
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
                <td class="border border-emerald-200 px-3 py-3 text-center cursor-pointer text-emerald-800 font-semibold" @click="openLineContent(row, 'rkpd_programs', `List ${metricLabel} ${rkpdLabel}`)">{{ Number(row.rkpd_count || 0) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center cursor-pointer text-emerald-800 font-semibold" @click="openLineContent(row, 'dpa_programs', `List ${metricLabel} ${apbdLabel}`)">{{ Number(row.dpa_count || 0) }}</td>
                <td class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold" role="button" tabindex="0" @click="openComparisonModal(row, 'rkpd_programs', 'dpa_programs', rkpdLabel, apbdLabel, 'same')">
                  <span :class="(getSameCountByKeys(row, 'rkpd_programs','dpa_programs')>0) ? 'text-emerald-700' : 'text-slate-500'">{{ getSameCountByKeys(row, 'rkpd_programs','dpa_programs') }}</span>
                </td>
                <td class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold" role="button" tabindex="0" @click="openComparisonModal(row, 'rkpd_programs', 'dpa_programs', rkpdLabel, apbdLabel, 'diff')">
                  <span :class="(getDifferentCountByKeys(row, 'rkpd_programs','dpa_programs')>0) ? 'text-amber-700' : 'text-slate-500'">{{ getDifferentCountByKeys(row, 'rkpd_programs','dpa_programs') }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Tabel 3: Detailed list per entitas (compact lists inside cells) -->
        <div v-else-if="currentTable === 'tabel-3'" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
          <table class="w-full table-fixed border-collapse text-sm">
            <thead style="background-color:#166534; color: white; font-weight: bold; vertical-align: middle;">
              <tr>
                <th rowspan="2" style="width: 3%;">No</th>
                <th rowspan="2" style="width: 15%;">Program Prioritas</th>
                <th rowspan="2" style="width: 20%;">Indikator Kinerja (Outcome)</th>
                <th rowspan="2" style="width: 5%;">Kondisi Awal (Tahun 2025)</th>
                <th colspan="5">Pagu RPJMD (Rp)</th>
                <th colspan="5">Target RPJMD</th>
                <th colspan="2">Capaian Kinerja</th>
                <th colspan="2">Rata - Rata Capaian Kinerja (Tahun {{ yearValue || 2030 }} TW {{ twValue === 'all' ? 1 : twValue }})</th>
                <th rowspan="2" style="width: 12%;">PD Penanggung Jawab Program</th>
              </tr>
              <tr>
                <th>2026</th>
                <th>2027</th>
                <th>2028</th>
                <th>2029</th>
                <th>2030</th>
                <th>2026</th>
                <th>2027</th>
                <th>2028</th>
                <th>2029</th>
                <th>2030</th>
                <th>Rp</th>
                <th>Capaian Kinerja</th>
                <th>Rp</th>
                <th>Capaian Kinerja</th>
              </tr>
              <tr style="background-color:#0f172a; color: white;">
                <td>(1)</td>
                <td>(2)</td>
                <td>(3)</td>
                <td>(4)</td>
                <td>(5)</td>
                <td>(6)</td>
                <td>(7)</td>
                <td>(8)</td>
                <td>(9)</td>
                <td>(10)</td>
                <td>(11)</td>
                <td>(12)</td>
                <td>(13)</td>
                <td>(14)</td>
                <td>(15)</td>
                <td>(16)</td>
                <td>(17)</td>
                <td>(18)</td>
                <td>(19)</td>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
                <td class="border border-emerald-200 px-3 py-3 font-medium">{{ row.program_prioritas ?? row.program ?? '-' }}</td>
                <td class="border border-emerald-200 px-3 py-3">{{ getPreferredIndicatorLabel(row) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.kondisi_awal ?? '-' }}</td>

                <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah((row.pagu && row.pagu['2026']) ? row.pagu['2026'] : 0) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah((row.pagu && row.pagu['2027']) ? row.pagu['2027'] : 0) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah((row.pagu && row.pagu['2028']) ? row.pagu['2028'] : 0) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah((row.pagu && row.pagu['2029']) ? row.pagu['2029'] : 0) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah((row.pagu && row.pagu['2030']) ? row.pagu['2030'] : 0) }}</td>

                <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target?.['2026'] ?? '-' }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target?.['2027'] ?? '-' }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target?.['2028'] ?? '-' }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target?.['2029'] ?? '-' }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target?.['2026'] ?? '-' }}</td>

                <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(row.capaian_rp ?? 0) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.capaian_kinerja ?? '-' }}</td>

                <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(row.rata_rp ?? 0) }}</td>
                <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.rata_capaian ?? '-' }}</td>

                <td class="border border-emerald-200 px-3 py-3">{{ row.pd_penanggungjawab ?? row.opd ?? '-' }}</td>
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
                  <th :colspan="showTargetColumns ? 2 : 1" class="px-3 py-3 border border-emerald-200 text-center font-bold text-emerald-900">RKPD/Renja (Tahun {{ yearValue || 2030 }})</th>
                  <th :colspan="showTargetColumns ? 2 : 1" class="px-3 py-3 border border-emerald-200 text-center font-bold text-emerald-900">APBD (Tahun {{ yearValue || 2030 }})</th>
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

        <!-- tabel-8: Visual-only DPA (realisasi payload) -->
        <div v-if="currentView === 'hasil-pelaksanaan-rkpd' && currentTable === 'tabel-8' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
          <div class="px-5 py-4 border-b border-emerald-100 bg-emerald-50">
            <strong class="text-emerald-800">DEBUG: Visual-only tabel-8 (read-only DPA view)</strong>
          </div>
          <table class="w-full table-fixed border-collapse text-sm">
            <thead>
              <tr class="bg-emerald-50">
                <th class="border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
                <th class="border border-emerald-200 px-3 py-3 text-left font-bold">OPD</th>
                <th class="border border-emerald-200 px-3 py-3 text-left font-bold">Kode Rek</th>
                <th class="border border-emerald-200 px-3 py-3 text-left font-bold">Program / Kegiatan</th>
                <th class="border border-emerald-200 px-3 py-3 text-right font-bold">Pagu (DPA)</th>
                <th class="border border-emerald-200 px-3 py-3 text-left font-bold">Indikator</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(group, gIdx) in tableData" :key="`dpa-group-${gIdx}`">
                <template v-if="Array.isArray(group.dpa_programs) && group.dpa_programs.length > 0">
                  <tr v-for="(prog, pIdx) in group.dpa_programs" :key="`prog-${gIdx}-${pIdx}`" :class="gIdx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                    <td v-if="pIdx === 0" :rowspan="group.dpa_programs.length" class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ group.no }}</td>
                    <td v-if="pIdx === 0" :rowspan="group.dpa_programs.length" class="border border-emerald-200 px-3 py-3 font-medium">{{ formatEntityLabel(group.entitas) }}</td>
                    <td class="border border-emerald-200 px-3 py-3">{{ prog.kode ?? prog.kode_program ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3">{{ prog.nama ?? prog.program_nama ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(prog.pagu ?? prog.pagu_tahunan ?? 0) }}</td>
                    <td class="border border-emerald-200 px-3 py-3">{{ (prog.indikator || []).map(i => (i.nama_indikator || i.nama || i || '') ).filter(Boolean).slice(0,3).join(', ') }}<span v-if="(prog.indikator || []).length > 3">, ...</span></td>
                  </tr>
                </template>
                <tr v-else :class="gIdx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                  <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ group.no }}</td>
                  <td class="border border-emerald-200 px-3 py-3 font-medium">{{ formatEntityLabel(group.entitas) }}</td>
                  <td class="border border-emerald-200 px-3 py-3">-</td>
                  <td class="border border-emerald-200 px-3 py-3">-</td>
                  <td class="border border-emerald-200 px-3 py-3 text-right">-</td>
                  <td class="border border-emerald-200 px-3 py-3">-</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- tabel-5: Hasil Pelaksanaan RKPD (dengan header RKPD / APBD / Realisasi / Capaian) -->
        <div v-if="currentView === 'hasil-pelaksanaan-rkpd' && currentTable === 'tabel-5' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
          <table class="w-full table-fixed border-collapse text-sm">
            <thead style="color: white; font-weight: bold; vertical-align: middle; background-color:#0b5e40;">
              <tr>
                <th rowspan="3" style="width: 3%;">No</th>
                <th rowspan="3" style="width: 12%;">Sasaran</th>
                <th rowspan="3" style="width: 5%;">Kode</th>
                <th rowspan="3" style="width: 15%;">Urusan/Bidang Urusan Pemerintahan Daerah/Program</th>
                <th rowspan="3" style="width: 15%;">Indikator Kinerja Program</th>
                <th colspan="2" rowspan="2">Target RPJMD Pada Tahun {{ yearValue || selectedYear || 2030 }}</th>
                <th colspan="2" rowspan="2">Realisasi Kinerja RPJMD Sampai Dengan Tahun Lalu</th>
                <th colspan="2" rowspan="2">Target Kinerja Dan Anggaran RKPD Tahun {{ yearValue || selectedYear || 2030 }}</th>
                <th colspan="8">Realisasi Kinerja RKPD/Renja Pada Triwulan</th>
                <th colspan="2" rowspan="2">Realisasi Capaian Kinerja Dan Anggaran RKPD Tahun {{ yearValue || selectedYear || 2030 }}</th>
                <th colspan="2" rowspan="2">Realisasi Kinerja Dan Anggaran RPJMD Sampai Dengan Tahun {{ yearValue || selectedYear || 2030 }}</th>
                <th colspan="2" rowspan="2">Tingkat Capaian Kinerja Dan Realisasi Anggaran RPJMD Sampai Dengan Tahun {{ yearValue || selectedYear || 2030 }}</th>
                <th rowspan="3" style="width: 10%;">Perangkat Daerah Penanggung Jawab</th>
              </tr>
              <tr>
                <th colspan="2">Triwulan I</th>
                <th colspan="2">Triwulan II</th>
                <th colspan="2">Triwulan III</th>
                <th colspan="2">Triwulan IV</th>
              </tr>
              <tr>
                <th>Kinerja</th><th>Rp</th>
                <th>Kinerja</th><th>Rp</th>
                <th>Kinerja</th><th>Rp</th>
                <th>Kinerja</th><th>Rp</th>
                <th>Kinerja</th><th>Rp</th>
                <th>Kinerja</th><th>Rp</th>
                <th>Kinerja (%)</th><th>Rp (%)</th>
              </tr>
              <tr style=" color: white; background-color:#064e3b;">
                <td>(1)</td><td>(2)</td><td>(3)</td><td>(4)</td><td>(5)</td>
                <td>(6)</td><td>(7)</td><td>(8)</td><td>(9)</td><td>(10)</td>
                <td>(11)</td><td>(12)</td><td>(13)</td><td>(14)</td><td>(15)</td>
                <td>(16)</td><td>(17)</td><td>(18)</td><td>(19)</td><td>(20)</td>
                <td>(21)</td><td>(22)</td><td>(23)</td><td>(24)</td><td>(25)</td><td>(26)</td>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, idx) in tableData" :key="'t5-' + idx">
                <template v-for="(line, lineIdx) in getProgramLines(row)" :key="'t5-line-' + idx + '-' + line.key">
                  <template v-for="(indRow, indIdx) in getIndicatorRowsForLine(line, row)" :key="'t5-ind-' + idx + '-' + line.key + '-' + indIdx">
                    <tr :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                      <td v-if="lineIdx === 0 && indIdx === 0" :rowspan="getTotalDisplayRows(row)" class="border border-emerald-200 px-3 py-2 text-center align-top">{{ row.no ?? idx+1 }}</td>
                      <td v-if="lineIdx === 0 && indIdx === 0" :rowspan="getTotalDisplayRows(row)" class="border border-emerald-200 px-3 py-2 align-top">{{ formatEntityLabel(row.sasaran || row.entitas) }}</td>
                      <td v-if="indIdx === 0" :rowspan="getIndicatorRowsForLine(line, row).length" class="border border-emerald-200 px-3 py-2 align-top text-sm font-medium break-words">{{ line.kode ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 align-top text-sm">{{ formatReadableText(line.name) }}</td>
                      <td class="border border-emerald-200 px-3 py-2 align-top text-sm">{{ (indRow.nama_indikator ?? indRow.indikator) || '-' }}</td>

                      <!-- Target RPJMD -->
                      <td class="border border-emerald-200 px-3 py-2 text-center">{{ indRow.rpjmd_target ?? indRow.target_rpjmd ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-right">{{ formatRupiah(indRow.rpjmd_pagu || indRow.rpjmd_rp || 0) }}</td>

                      <!-- Realisasi RPJMD sampai lalu -->
                      <td class="border border-emerald-200 px-3 py-2 text-center">{{ indRow.rpjmd_realisasi_kinerja ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-right">{{ formatRupiah(indRow.rpjmd_realisasi_rp || 0) }}</td>

                      <!-- Target RKPD -->
                      <td class="border border-emerald-200 px-3 py-2 text-center">{{ indRow.rkpd_target ?? indRow.rkpd_target_ind ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-right">{{ formatRupiah(indRow.rkpd_pagu || indRow.rkpd_rp || 0) }}</td>

                      <!-- Triwulan I-IV (Kinerja / Rp) -->
                      <td class="border border-emerald-200 px-3 py-2 text-center">{{ indRow.tw1_kinerja ?? indRow.tw1?.kinerja ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-right">{{ formatRupiah(indRow.tw1_rp ?? indRow.tw1?.rp ?? 0) }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-center">{{ indRow.tw2_kinerja ?? indRow.tw2?.kinerja ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-right">{{ formatRupiah(indRow.tw2_rp ?? indRow.tw2?.rp ?? 0) }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-center">{{ indRow.tw3_kinerja ?? indRow.tw3?.kinerja ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-right">{{ formatRupiah(indRow.tw3_rp ?? indRow.tw3?.rp ?? 0) }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-center">{{ indRow.tw4_kinerja ?? indRow.tw4?.kinerja ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-right">{{ formatRupiah(indRow.tw4_rp ?? indRow.tw4?.rp ?? 0) }}</td>

                      <!-- Realisasi RKPD total -->
                      <td class="border border-emerald-200 px-3 py-2 text-center">{{ indRow.rkpd_realisasi_kinerja ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-right">{{ formatRupiah(indRow.rkpd_realisasi_rp ?? 0) }}</td>

                      <!-- Realisasi RPJMD total -->
                      <td class="border border-emerald-200 px-3 py-2 text-center">{{ indRow.rpjmd_realisasi_kinerja ?? '-' }}</td>
                      <td class="border border-emerald-200 px-3 py-2 text-right">{{ formatRupiah(indRow.rpjmd_realisasi_rp ?? 0) }}</td>

                      <!-- Tingkat capaian (Kinerja % & Rp %) computed -->
                      <td :class="getCapaianClass(computeCapaianKinerja(indRow, row))" class="border border-emerald-200 px-3 py-2 text-center">{{ computeCapaianKinerja(indRow, row) }}</td>
                      <td :class="getCapaianClass(computeCapaianKeuangan(indRow, row))" class="border border-emerald-200 px-3 py-2 text-center">{{ computeCapaianKeuangan(indRow, row) }}</td>

                      <td class="border border-emerald-200 px-3 py-2">{{ row.pd_penanggungjawab ?? row.opd ?? '-' }}</td>
                    </tr>
                  </template>
                </template>
              </template>
            </tbody>
          </table>
        </div>

      </div>

      <div v-else-if="currentView === 'realisasi' && currentTable === 'iku' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
        <table class="w-full table-fixed border-collapse text-sm">
          <thead>
            <tr class="bg-emerald-50">
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
              <th class="border border-emerald-200 px-3 py-3 text-left font-bold">Indikator</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Satuan</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Target 2030</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Realisasi Tahun</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Fisik</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Keuangan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
              <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ getPreferredIndicatorLabel(row) }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.satuan }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target_2030 }}</td>
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
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Tahun {{ yearValue || 2030 }}</th>
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
              <td class="border border-emerald-200 px-3 py-3">{{ getPreferredIndicatorLabel(row) }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.satuan ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target_rpjmd ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.target_rkpd ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.capaian_tahun ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Hasil Pelaksanaan RKPD - Tabel 3: header 3-baris sesuai permintaan -->
      <div v-else-if="currentView === 'hasil-pelaksanaan-rkpd' && currentTable === 'tabel-3' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
        <table class="w-full table-fixed border-collapse text-sm">
          <thead class="text-white font-semibold align-middle bg-emerald-800">
            <tr>
              <th rowspan="3" class="border border-emerald-200 px-3 py-3 text-center w-12">No</th>
              <th rowspan="3" class="border border-emerald-200 px-3 py-3 text-left min-w-[220px]">Program Prioritas</th>
              <th rowspan="3" class="border border-emerald-200 px-3 py-3 text-left min-w-[300px]">Indikator Kinerja (Outcome)</th>
              <th rowspan="3" class="border border-emerald-200 px-3 py-3 text-center w-28">Kondisi Awal (Tahun 2021)</th>
              <th colspan="5" class="border border-emerald-200 px-3 py-3 text-center">Pagu RPJMD (Rp)</th>
              <th colspan="5" class="border border-emerald-200 px-3 py-3 text-center">Target RPJMD</th>
              <th colspan="2" class="border border-emerald-200 px-3 py-3 text-center">Capaian Kinerja</th>
              <th colspan="2" class="border border-emerald-200 px-3 py-3 text-center">Rata - Rata Capaian Kinerja (Tahun {{ yearValue || selectedYear || 2030 }} TW {{ twValue === 'all' ? 1 : twValue }})</th>
              <th rowspan="3" class="border border-emerald-200 px-3 py-3 text-left">PD Penanggung Jawab Program</th>
            </tr>
            <tr class="bg-emerald-700 text-white">
              <th class="border border-emerald-200 px-3 py-2 text-center w-20">2026</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-20">2027</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-20">2028</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-20">2029</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-20">2026</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-20">2026</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-20">2027</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-20">2028</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-20">2029</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-20">2026</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-24">Rp</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-24">Capaian Kinerja</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-24">Rp</th>
              <th class="border border-emerald-200 px-3 py-2 text-center w-24">Capaian Kinerja</th>
            </tr>
            <!-- numbering row removed as requested -->
          </thead>
          <tbody>
            <template v-for="(row, idx) in tableData" :key="'t3-'+idx">
              <template v-for="(line, lineIdx) in getProgramLines(row)" :key="'t3-line-'+idx+'-'+line.key">
                <template v-for="(indRow, indIdx) in getIndicatorRowsForLine(line, row)" :key="'t3-ind-'+idx+'-'+line.key+'-'+indIdx">
                  <tr :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
                    <td v-if="lineIdx === 0 && indIdx === 0" :rowspan="getTotalDisplayRows(row)" class="border border-emerald-200 px-3 py-3 text-center align-top font-semibold">{{ row.no ?? idx+1 }}</td>
                    <td v-if="lineIdx === 0 && indIdx === 0" :rowspan="getTotalDisplayRows(row)" class="border border-emerald-200 px-3 py-3 align-top font-medium">{{ row.program_prioritas ?? row.program ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 align-top">{{ (indRow.nama_indikator ?? indRow.indikator) || '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center">{{ indRow.kondisi_awal ?? row.kondisi_awal ?? '-' }}</td>

                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah((indRow.pagu?.['2026'] ?? row.pagu?.['2026']) || 0) }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah((indRow.pagu?.['2027'] ?? row.pagu?.['2027']) || 0) }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah((indRow.pagu?.['2028'] ?? row.pagu?.['2028']) || 0) }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah((indRow.pagu?.['2029'] ?? row.pagu?.['2029']) || 0) }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah((indRow.pagu?.['2026'] ?? row.pagu?.['2026']) || 0) }}</td>

                    <td class="border border-emerald-200 px-3 py-3 text-center">{{ indRow.target?.['2026'] ?? row.target?.['2026'] ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center">{{ indRow.target?.['2027'] ?? row.target?.['2027'] ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center">{{ indRow.target?.['2028'] ?? row.target?.['2028'] ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center">{{ indRow.target?.['2029'] ?? row.target?.['2029'] ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center">{{ indRow.target?.['2026'] ?? row.target?.['2026'] ?? '-' }}</td>

                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(indRow.capaian_rp ?? row.capaian_rp ?? 0) }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center">{{ indRow.capaian_kinerja ?? row.capaian_kinerja ?? '-' }}</td>

                    <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(indRow.rata_rp ?? row.rata_rp ?? 0) }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center">{{ indRow.rata_capaian ?? row.rata_capaian ?? '-' }}</td>

                    <td class="border border-emerald-200 px-3 py-3">{{ row.pd_penanggungjawab ?? row.opd ?? '-' }}</td>
                  </tr>
                </template>
              </template>
            </template>
          </tbody>
        </table>
      </div>

      <!-- New: tabel-2 for hasil-pelaksanaan-rkpd view (Program Aksi Kepala Daerah - top 10) -->
      <div v-else-if="currentView === 'hasil-pelaksanaan-rkpd' && currentTable === 'tabel-2' && tableData" class="overflow-x-auto rounded-2xl   border border-emerald-100 bg-white/90 shadow-md">
        <table class="w-full table-fixed border-collapse text-sm">
          <thead>
            <tr class="bg-emerald-50">
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">10 Program Aksi Kepala Daerah</th>
              <th colspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Utama (Tahun {{ yearValue || 2030 }})</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Program Prioritas (Pendukung)</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Indikator Program Prioritas (RPJMD)</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Target Tahun {{ yearValue || 2030 }}</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Satuan</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Tahun {{ yearValue || 2030 }}</th>
            </tr>
            <tr class="bg-emerald-100">
              <th></th>
              <th></th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">Kinerja</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">Anggaran</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(5)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(6)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(7)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(8)</th>
              <th class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(9)</th>
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
              <td class="border border-emerald-200 px-3 py-3">{{ getPreferredIndicatorLabel(row) }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.rpjmd_target ?? row.target ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.rpjmd_satuan ?? row.satuan ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.rpjmd_capaian ?? row.indikator_capaian ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- New: tabel-4 for hasil-pelaksanaan-rkpd (dedicated block) -->
      <div v-else-if="currentView === 'hasil-pelaksanaan-rkpd' && currentTable === 'tabel-4' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
        <table class="w-full table-fixed border-collapse text-sm">
          <thead>
            <tr class="bg-emerald-50">
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
              <th class="border border-emerald-200 px-3 py-3 text-left font-bold">Program / Kode</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">RENJA Indikator</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">RENJA Target</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">DPA Indikator</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">DPA Target</th>
              <th class="border border-emerald-200 px-3 py-3 text-right font-bold">Pagu</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Realisasi Fisik</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Realisasi Keu</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Kinerja</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Keuangan</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">PD</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
              <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no ?? idx + 1 }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ row.nama || row.program || row.kode || '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ getPreferredIndicatorLabel(row) }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ (row.rkpd_target ?? row.target) || '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ (row.dpa_indikator ?? row.dpa_nama_indikator ?? row.dpa?.[0]?.nama_indikator) || '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ (row.dpa_target ?? row.dpa?.[0]?.target_indikator) || '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(row.pagu ?? row.dpa_pagu ?? 0) }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.realisasi_fisik ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(row.realisasi_keu ?? 0) }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ (row.capaian_kinerja ?? row.capaian ?? '-') }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ (row.capaian_keuangan ?? '-') }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ row.pd_penanggungjawab ?? row.opd ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- New: tabel-6 for hasil-pelaksanaan-rkpd (dedicated block) -->
      <div v-else-if="currentView === 'hasil-pelaksanaan-rkpd' && currentTable === 'tabel-6' && tableData" class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md">
        <table class="w-full table-fixed border-collapse text-sm">
          <thead>
            <tr class="bg-emerald-50">
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">No</th>
              <th class="border border-emerald-200 px-3 py-3 text-left font-bold">Program / Kode</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Indikator</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Target</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Satuan</th>
              <th class="border border-emerald-200 px-3 py-3 text-right font-bold">Pagu</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Realisasi Fisik</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Realisasi Keu</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Kinerja</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian Keuangan</th>
              <th class="border border-emerald-200 px-3 py-3 text-center font-bold">PD</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in tableData" :key="idx" :class="idx % 2 === 0 ? 'bg-white' : 'bg-emerald-50'">
              <td class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.no ?? idx + 1 }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ row.nama || row.program || row.kode || '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ getPreferredIndicatorLabel(row) }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ (row.target ?? row.target_indikator) || '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.satuan ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(row.pagu ?? row.dpa_pagu ?? 0) }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.realisasi_fisik ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-right">{{ formatRupiah(row.realisasi_keu ?? 0) }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.capaian_kinerja ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3 text-center">{{ row.capaian_keuangan ?? '-' }}</td>
              <td class="border border-emerald-200 px-3 py-3">{{ row.pd_penanggungjawab ?? row.opd ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- New: tabel-7 for hasil-pelaksanaan-rkpd view (uses standard table styling) -->
      <div v-else-if="currentView === 'hasil-pelaksanaan-rkpd' && currentTable !== 'tabel-7'" :class="isFullpage ? 'fixed inset-0 p-6 bg-white z-50 overflow-auto' : 'overflow-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md p-4 max-h-[65vh]'">
        <table class="w-full min-w-[2600px] table-fixed border-collapse text-sm">
          <thead>
            <tr class="sticky top-0 z-50 bg-white" style="box-shadow: 0 3px 0 rgba(16,185,129,1);">
              <th rowspan="2" class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-3 text-center font-bold bg-white">Kode Rek
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">1</span>
              </th>
              <th rowspan="2" class="relative border border-emerald-200 border-b-4 border-emerald-400 px-3 py-3 text-left font-bold bg-white min-w-[1260px]">Program / Kegiatan / Sub Kegiatan
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">2</span>
              </th>

              <th colspan="4" class="border border-emerald-200 border-b-4 border-emerald-400 px-3 py-3 text-center font-bold bg-white">RENJA (Tahun {{ yearValue || selectedYear || 2030 }})</th>
              <th colspan="4" class="border border-emerald-200 border-b-4 border-emerald-400 px-3 py-3 text-center font-bold bg-white">DPA (Tahun {{ yearValue || selectedYear || 2030 }})</th>

              <th colspan="2" class="border border-emerald-200 border-b-4 border-emerald-400 px-3 py-3 text-center font-bold bg-white">Realisasi</th>
              <th colspan="2" class="border border-emerald-200 border-b-4 border-emerald-400 px-3 py-3 text-center font-bold bg-white">Capaian</th>
            </tr>

            <tr class="sticky top-[48px] z-50 bg-white" style="box-shadow: 0 3px 0 rgba(16,185,129,1);">
              <!-- RKPD -->
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Indikator
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">3</span>
              </th>
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Target
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">4</span>
              </th>
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Satuan
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">5</span>
              </th>
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Pagu
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">6</span>
              </th>

              <!-- APBD -->
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Indikator
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">7</span>
              </th>
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Target
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">8</span>
              </th>
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Satuan
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">9</span>
              </th>
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Pagu
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">10</span>
              </th>

              <!-- Realisasi -->
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Kinerja
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">11</span>
              </th>
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Keuangan
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">12</span>
              </th>

              <!-- Capaian -->
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Kinerja
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">13</span>
              </th>
              <th class="relative border border-emerald-200 border-b-2 border-emerald-300 px-3 py-2 text-center font-semibold bg-white">Keuangan
                <span class="absolute right-2 top-1 text-[10px] font-semibold text-slate-700 bg-white rounded-full w-5 h-5 flex items-center justify-center border border-slate-200">14</span>
              </th>
            </tr>
            <!-- Row 3: Column numbering -->
            <tr class="bg-emerald-50">
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(1)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(2)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(3)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(4)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(5)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(6)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(7)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(8)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(9)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(10)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(11)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(12)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(13)</td>
              <td class="border border-emerald-200 px-3 py-1 text-center text-xs font-semibold">(14)</td>
            </tr>
          </thead>
          <tbody>
            <template v-for="(row, idx) in tableData" :key="'t7-' + idx">
              <!-- OPD row: show once before program lines (only when opd changes) -->
              <tr v-if="shouldRenderOpdHeader(idx, row)" class="bg-orange-200">
                <td :colspan="14" class="border border-emerald-200 px-3 py-2 text-sm font-semibold text-orange-800">{{ getOpdName(row.opd_id) }}</td>
              </tr>
              <template v-for="(line, lineIdx) in getProgramLines(row)" :key="'t7-line-' + idx + '-' + line.key">
                <template v-for="(indRow, indIdx) in getIndicatorRowsForLine(line, row)" :key="'t7-ind-' + idx + '-' + line.key + '-' + indIdx">
                    <tr :class="line.level === 'program' ? 'bg-emerald-100' : (line.level === 'kegiatan' ? 'bg-yellow-200' : 'bg-white')">
                    <td v-if="lineIdx === 0 && indIdx === 0" :rowspan="getTotalDisplayRows(row)" class="border border-emerald-200 px-3 py-3 text-center font-semibold">{{ row.kode ?? row.kode_rek ?? '-' }}</td>
                      <td v-if="indIdx === 0" :rowspan="getIndicatorRowsForLine(line, row).length" class="border border-emerald-200 px-3 py-3 align-top text-sm font-medium break-words">{{ formatReadableText(line.name) }}</td>

                    <!-- RKPD -->
                    <td class="border border-emerald-200 px-3 py-3 align-top text-sm text-emerald-700 break-words whitespace-pre-line">{{ indRow.rkpd || '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 align-top text-center text-sm text-emerald-700">{{ indRow.rkpd_target ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 align-top text-center text-sm text-emerald-700">{{ indRow.rkpd_satuan ?? indRow.satuan ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 align-top text-right text-sm text-emerald-700">{{ formatRupiah(indRow.rkpd_pagu || 0) }}</td>

                    <!-- APBD -->
                    <td class="border border-emerald-200 px-3 py-3 align-top text-sm text-emerald-700 break-words whitespace-pre-line">{{ indRow.dpa || '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 align-top text-center text-sm text-emerald-700">{{ indRow.dpa_target ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 align-top text-center text-sm text-emerald-700">{{ indRow.dpa_satuan ?? indRow.satuan ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 align-top text-right text-sm text-emerald-700">{{ formatRupiah(indRow.dpa_pagu || 0) }}</td>

                    <!-- Realisasi -->
                    <td class="border border-emerald-200 px-3 py-3 text-center align-top">{{ indRow.realisasi_kinerja ?? row.realisasi_kinerja ?? '-' }}</td>
                    <td class="border border-emerald-200 px-3 py-3 text-center align-top">{{ indRow.realisasi_keuangan ?? row.realisasi_keuangan ?? '-' }}</td>

                    <!-- Capaian -->
                    <td :class="getCapaianClass(computeCapaianKinerja(indRow, row))" class="border border-emerald-200 px-3 py-3 text-center align-top">{{ computeCapaianKinerja(indRow, row) }}</td>
                    <td :class="getCapaianClass(computeCapaianKeuangan(indRow, row))" class="border border-emerald-200 px-3 py-3 text-center align-top">{{ computeCapaianKeuangan(indRow, row) }}</td>
                  </tr>
                </template>
              </template>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Realisasi table for tabel-7: read-only view of realisasi keuangan dan fisik -->
      <div
        id="realisasi-export-root"
        v-if="currentView === 'hasil-pelaksanaan-rkpd' && currentTable === 'tabel-7' && tableData"
        class="mt-6 rounded-2xl border border-emerald-100 bg-white/90 shadow-md overflow-x-auto"
      >
        <div class="flex items-center justify-between px-6 pt-4">
          <h3 class="text-lg font-bold text-white">Realisasi (Ringkasan)</h3>
          <div class="flex gap-2">
            <button type="button" class="px-3 py-1 bg-blue-600 text-white rounded-md text-sm" @click="exportRealisasiPdf">Export PDF</button>
            <button type="button" class="px-3 py-1 bg-green-600 text-white rounded-md text-sm" @click="exportRealisasiExcel">Export Excel (.xlsx)</button>
            <button type="button" class="px-3 py-1 ml-2 bg-indigo-600 text-white rounded-md text-sm" @click="exportRealisasiExcelImage">Export Excel (Image)</button>
          </div>
        </div>
        <table class="w-full border-collapse text-sm bg-gray-900 text-white">
          <thead class="bg-gray-800 text-white">
            <tr>
              <th rowspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Kode Rek</th>
              <th rowspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Program / Kegiatan / Sub Kegiatan</th>

              <th colspan="4" class="border border-emerald-200 px-3 py-3 text-center font-bold">RENJA</th>
              <th colspan="4" class="border border-emerald-200 px-3 py-3 text-center font-bold">DPA</th>

              <th :colspan="realisasiCols" class="border border-emerald-200 px-3 py-3 text-center font-bold">Realisasi (Triwulan)</th>
              <th colspan="2" class="border border-emerald-200 px-3 py-3 text-center font-bold">Capaian</th>
            </tr>

            <tr class="bg-gray-800 text-white">
              <!-- RENJA -->
              <th class="border border-emerald-600 px-2 py-1 text-center font-semibold text-xs">Indikator</th>
              <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">Target</th>
              <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">Satuan</th>
              <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">Pagu</th>

              <!-- DPA -->
              <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">Indikator</th>
              <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">Target</th>
              <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">Satuan</th>
              <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">Pagu</th>

              <!-- Realisasi TWs (Kinerja / Keuangan) -->
              <template v-if="twValue">
                <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">TW{{ twValue }} Kinerja</th>
                <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">TW{{ twValue }} Keu</th>
              </template>
              <template v-else>
                <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">TW1 Kinerja</th>
                <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">TW1 Keu</th>
                <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">TW2 Kinerja</th>
                <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">TW2 Keu</th>
                <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">TW3 Kinerja</th>
                <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">TW3 Keu</th>
                <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">TW4 Kinerja</th>
                <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">TW4 Keu</th>
              </template>

              <!-- Capaian -->
              <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">Kinerja</th>
              <th class="border border-emerald-200 px-2 py-1 text-center font-semibold text-xs">Keuangan</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(row, idx) in sortedRealisasiTableData" :key="`realisasi-row-${idx}`">
              <!-- Unit header row when OPD/unit changes -->
              <tr v-if="shouldShowUnitHeaderInRealisasi(idx, row)" class="bg-orange-600 border-b-2 border-orange-700 text-white">
                <td colspan="20" class="border border-orange-700 px-4 py-2 text-sm font-bold">
                  {{ getOpdName(row.opd_id) }}
                </td>
              </tr>
              <!-- Program row -->
              <tr :class="getRowClasses(row, idx)">
                <td class="border border-emerald-200 px-3 py-2 text-center font-semibold">{{ row.kode_rek ?? '-' }}</td>
                <td class="border border-emerald-200 px-3 py-2 text-left">{{ row.program_nama ?? '-' }}</td>
                <!-- RENJA: Indikator / Target / Satuan / Pagu -->
                <td :class="(getIndicatorValue((row.renstra_programs || row.rkpd_programs || row.renja_programs) || (row.years && row.years[yearValue.value] && row.years[yearValue.value].renja ? (row.years[yearValue.value].renja.programs || []) : []), row.kode_rek, 'nama_indikator') === '-') ? 'border border-emerald-200 px-3 py-2 text-left text-xs bg-black text-white' : 'border border-emerald-200 px-3 py-2 text-left text-xs'">
                  {{ getIndicatorValue((row.renstra_programs || row.rkpd_programs || row.renja_programs) || (row.years && row.years[yearValue.value] && row.years[yearValue.value].renja ? (row.years[yearValue.value].renja.programs || []) : []), row.kode_rek, 'nama_indikator') }}
                </td>
                <td :class="(getIndicatorValue((row.renstra_programs || row.rkpd_programs || row.renja_programs) || (row.years && row.years[yearValue.value] && row.years[yearValue.value].renja ? (row.years[yearValue.value].renja.programs || []) : []), row.kode_rek, 'target_indikator') === '-') ? 'border border-emerald-200 px-3 py-2 text-right text-xs bg-black text-white' : 'border border-emerald-200 px-3 py-2 text-right text-xs'">
                  {{ getIndicatorValue((row.renstra_programs || row.rkpd_programs || row.renja_programs) || (row.years && row.years[yearValue.value] && row.years[yearValue.value].renja ? (row.years[yearValue.value].renja.programs || []) : []), row.kode_rek, 'target_indikator') }}
                </td>
                <td :class="(getIndicatorValue((row.renstra_programs || row.rkpd_programs || row.renja_programs) || (row.years && row.years[yearValue.value] && row.years[yearValue.value].renja ? (row.years[yearValue.value].renja.programs || []) : []), row.kode_rek, 'satuan') === '-') ? 'border border-emerald-200 px-3 py-2 text-center text-xs bg-black text-white' : 'border border-emerald-200 px-3 py-2 text-center text-xs'">
                  {{ getIndicatorValue((row.renstra_programs || row.rkpd_programs || row.renja_programs) || (row.years && row.years[yearValue.value] && row.years[yearValue.value].renja ? (row.years[yearValue.value].renja.programs || []) : []), row.kode_rek, 'satuan') }}
                </td>
                <td :class="((getPaguFromProgramArray((row.renstra_programs || row.rkpd_programs || row.renja_programs) || (row.years && row.years[yearValue.value] && row.years[yearValue.value].renja ? (row.years[yearValue.value].renja.programs || []) : []), row.kode_rek) || 0) === 0) ? 'border border-emerald-200 px-3 py-2 text-right text-sm bg-black text-white' : 'border border-emerald-200 px-3 py-2 text-right text-sm'">
                  {{ formatRupiah(getPaguFromProgramArray((row.renstra_programs || row.rkpd_programs || row.renja_programs) || (row.years && row.years[yearValue.value] && row.years[yearValue.value].renja ? (row.years[yearValue.value].renja.programs || []) : []), row.kode_rek)) }}
                </td>

                <!-- DPA: Indikator / Target / Satuan / Pagu -->
                <td :class="(getIndicatorValue(row.dpa_programs, row.kode_rek, 'nama_indikator') === '-') ? 'border border-emerald-200 px-3 py-2 text-left text-xs bg-black text-white' : 'border border-emerald-200 px-3 py-2 text-left text-xs'">
                  {{ getIndicatorValue(row.dpa_programs, row.kode_rek, 'nama_indikator') }}
                </td>
                <td :class="(getIndicatorValue(row.dpa_programs, row.kode_rek, 'target_indikator') === '-') ? 'border border-emerald-200 px-3 py-2 text-right text-xs bg-black text-white' : 'border border-emerald-200 px-3 py-2 text-right text-xs'">
                  {{ getIndicatorValue(row.dpa_programs, row.kode_rek, 'target_indikator') }}
                </td>
                <td :class="(getIndicatorValue(row.dpa_programs, row.kode_rek, 'satuan') === '-') ? 'border border-emerald-200 px-3 py-2 text-center text-xs bg-black text-white' : 'border border-emerald-200 px-3 py-2 text-center text-xs'">
                  {{ getIndicatorValue(row.dpa_programs, row.kode_rek, 'satuan') }}
                </td>
                <td :class="((getPaguFromProgramArray(row.dpa_programs, row.kode_rek) || 0) === 0) ? 'border border-emerald-200 px-3 py-2 text-right text-sm bg-black text-white' : 'border border-emerald-200 px-3 py-2 text-right text-sm'">
                  {{ formatRupiah(getPaguFromProgramArray(row.dpa_programs, row.kode_rek)) }}
                </td>
                <!-- Realisasi per TW: Kinerja / Keuangan (show only selected TW if provided) -->
                <template v-if="twValue">
                  <td class="border border-emerald-200 px-2 py-2 text-right text-xs">{{ row.realisasi_data?.[twValue]?.fisik ?? 0 }}</td>
                  <td class="border border-emerald-200 px-2 py-2 text-right text-xs">{{ formatRupiah(row.realisasi_data?.[twValue]?.keuangan ?? 0) }}</td>
                </template>
                <template v-else>
                  <template v-for="tw in [1,2,3,4]" :key="`tw-${tw}`">
                    <td class="border border-emerald-200 px-2 py-2 text-right text-xs">{{ row.realisasi_data?.[tw]?.fisik ?? 0 }}</td>
                    <td class="border border-emerald-200 px-2 py-2 text-right text-xs">{{ formatRupiah(row.realisasi_data?.[tw]?.keuangan ?? 0) }}</td>
                  </template>
                </template>

              <!-- Capaian columns (computed using RENJA denominators) -->
              <td :class="[ 'border border-emerald-200 px-2 py-2 text-right text-xs', renderCapaian(computeCapaianKinerja(null, row)).cls ]">{{ renderCapaian(computeCapaianKinerja(null, row)).text }}</td>
              <td :class="[ 'border border-emerald-200 px-2 py-2 text-right text-xs', renderCapaian(computeCapaianKeuangan(null, row)).cls ]">{{ renderCapaian(computeCapaianKeuangan(null, row)).text }}</td>
            </tr>
            </template>
              <tr v-if="!tableData || tableData.length === 0">
              <td :colspan="totalCols" class="border border-emerald-200 px-3 py-8 text-center text-gray-400">
                Belum ada data realisasi
              </td>
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
                @click="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', rkpdLabel, 'same')"
                @keydown.enter.prevent="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', rkpdLabel, 'same')"
                @keydown.space.prevent="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', rkpdLabel, 'same')"
              >
                {{ getSameCountByKeys(row, 'rpjmd_programs', 'rkpd_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', rkpdLabel, 'diff')"
                @keydown.enter.prevent="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', rkpdLabel, 'diff')"
                @keydown.space.prevent="openComparisonModal(row, 'rpjmd_programs', 'rkpd_programs', 'RPJMD', rkpdLabel, 'diff')"
              >
                {{ getDifferentCountByKeys(row, 'rpjmd_programs', 'rkpd_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', rkpdLabel, 'same')"
                @keydown.enter.prevent="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', rkpdLabel, 'same')"
                @keydown.space.prevent="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', rkpdLabel, 'same')"
              >
                {{ getSameCountByKeys(row, 'renstra_programs', 'rkpd_programs') }}
              </td>
              <td
                class="cursor-pointer border border-emerald-200 px-3 py-3 text-center font-semibold text-emerald-700 transition-colors hover:bg-emerald-100/60 hover:text-emerald-900"
                role="button"
                tabindex="0"
                @click="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', rkpdLabel, 'diff')"
                @keydown.enter.prevent="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', rkpdLabel, 'diff')"
                @keydown.space.prevent="openComparisonModal(row, 'renstra_programs', 'rkpd_programs', 'Renstra', rkpdLabel, 'diff')"
              >
                {{ getDifferentCountByKeys(row, 'renstra_programs', 'rkpd_programs') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      

      <div
        v-else-if="currentView === 'konsistensi-rpjmd-rkpd' && currentTable === 'tabel-3'"
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
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">RPJMD - Tahun {{ yearValue || 2030 }}</th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">Renstra - Tahun {{ yearValue || 2030 }}</th>
              <th colspan="2" class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">RKPD - Tahun {{ yearValue || 2030 }}</th>
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
                  <div class="rounded-md border border-emerald-200 bg-emerald-50/40 px-2 py-1 break-words whitespace-normal leading-snug">{{ useRenjaForLeftColumn.value ? '' : formatReadableText(line.rkpdName) }}</div>
                </td>
                <td class="border border-emerald-200 px-2 py-2 align-top text-center text-sm font-semibold text-slate-700 break-words whitespace-normal">
                  <div class="rounded-md border border-emerald-200 bg-white px-2 py-1 break-words whitespace-normal leading-snug">{{ useRenjaForLeftColumn.value ? '' : line.rkpdTarget }}</div>
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
        v-else-if="currentView === 'konsistensi-rpjmd-rkpd' && currentTable === 'tabel-4'"
        class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md"
      >
        <div v-if="Array.isArray(tableData) && tableData.length === 0" class="p-6 text-sm text-center text-slate-700">
          Tidak ada data untuk tabel ini.
          <div class="mt-3 flex items-center justify-center gap-3">
            <a :href="(window.location.pathname || '/') + '?view=' + currentView + '&table=' + currentTable + '&debug_opd_id=20'" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Tampilkan debug OPD sample</a>
            <a :href="(window.location.pathname || '/') + '?view=' + currentView + '&table=' + currentTable" class="rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reload tanpa filter</a>
          </div>
        </div>
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
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Pagu RPJMD ({{ yearValue || 2030 }})</th>
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Pagu Renstra ({{ yearValue || 2030 }})</th>
              <th rowspan="2" class="border border-emerald-200 bg-emerald-100 px-2 py-2 text-center font-bold text-emerald-900">Pagu RKPD/Renja ({{ yearValue || 2030 }})</th>
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
  opds: {
    type: Array,
    default: () => [],
  },
  selectedOpdId: {
    type: [Number, String],
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
  realisasi_like_payload: {
    type: Object,
    default: null,
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

// expose which branch is active for debugging
const activeBranch = computed(() => {
  if (!props.currentView || !props.currentTable) return 'index';
  if (props.currentView === 'hasil-pelaksanaan-rkpd' && props.currentTable === 'tabel-5') return 'hasil-pelaksanaan-tabel-5';
  if (props.currentView === 'hasil-pelaksanaan-rkpd' && props.currentTable === 'tabel-1') return 'hasil-pelaksanaan-tabel-1';
  if (props.currentView === 'konsistensi-rkpd-apbd') return `konsistensi-rkpd-apbd-${props.currentTable}`;
  if (props.currentView === 'konsistensi-rpjmd-rkpd') return `konsistensi-rpjmd-rkpd-${props.currentTable}`;
  return `${props.currentView}-${props.currentTable}`;
});

// For some resume views/tables we want to alter visible labels
const isRenjaDpaMode = computed(() => props.currentView === 'hasil-pelaksanaan-rkpd' && props.currentTable === 'tabel-7');
// Also allow using Renja indicators for left column when viewing konsistensi tabel-4
const useRenjaForLeftColumn = computed(() => isRenjaDpaMode.value || (props.currentView === 'konsistensi-rkpd-apbd' && props.currentTable === 'tabel-4'));
const rkpdLabel = computed(() => isRenjaDpaMode.value ? 'Renja' : 'RKPD/Renja');
const apbdLabel = computed(() => isRenjaDpaMode.value ? 'DPA' : 'APBD');

// DEBUG: log first row to inspect indicators presence (temporary)
if (typeof console !== 'undefined') {
  console.debug('resume.tableData.firstRow', props.tableData?.[0] ?? null);
}
async function ensureHtml2CanvasLoaded() {
  if (window.html2canvas) return;
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
    s.onload = () => resolve();
    s.onerror = () => reject(new Error('Failed to load html2canvas'));
    document.head.appendChild(s);
  });
}

async function exportRealisasiExcelImage() {
  try {
    await ensureHtml2CanvasLoaded();
    const container = document.getElementById('realisasi-export-root');
    if (!container) return alert('Elemen tabel realisasi tidak ditemukan');
    const table = container.querySelector('table');
    if (!table) return alert('Tabel realisasi tidak ditemukan');

    const canvas = await window.html2canvas(table, { scale: 2, backgroundColor: null });
    const dataUrl = canvas.toDataURL('image/png');

    const html = `<!doctype html><html><head><meta charset="utf-8"></head><body><img src="${dataUrl}" style="display:block;max-width:100%;height:auto;"/></body></html>`;
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const fname = `${props.currentView || 'export'}_${props.currentTable || 'table'}_${new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')}.xls`;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fname;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    console.error('Export Excel (Image) failed', e);
    alert('Export Excel (Image) gagal: ' + (e?.message || e));
  }
}

// effective tableData: prefer props.tableData; if empty and server provided a
// `realisasi_like_payload` for tabel-8, use that payload to build rows grouped by OPD
const tableData = computed(() => {
  try {
    if (Array.isArray(props.tableData) && props.tableData.length > 0) return props.tableData;
    if (props.realisasi_like_payload && props.currentTable === 'tabel-8' && Array.isArray(props.realisasi_like_payload.data)) {
      const grouped = {};
      props.realisasi_like_payload.data.forEach((item) => {
        const opd = item?.opd_id ?? 0;
        if (!grouped[opd]) {
          const opdName = (props.opds || []).find(o => Number(o.id) === Number(opd))?.nama ?? (`OPD ${opd}`);
          grouped[opd] = {
            no: Object.keys(grouped).length + 1,
            entitas: opdName,
            opd_id: opd,
            rkpd_programs: [],
            dpa_programs: [],
            renstra_programs: [],
            rpjmd_programs: [],
          };
        }
        grouped[opd].dpa_programs.push(item);
      });

      return Object.values(grouped);
    }
  } catch (e) {
    // fallback to props.tableData
    return Array.isArray(props.tableData) ? props.tableData : [];
  }
  return Array.isArray(props.tableData) ? props.tableData : [];
});

const basisValue = ref(props.filterBasis);
const yearValue = ref(props.selectedYear);
const twValue = ref(props.selectedTw);
const selectedOpd = ref(props.selectedOpdId ?? '');
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

const realisasiCols = computed(() => {
  try {
    return twValue && Number(twValue.value) ? 2 : 8;
  } catch (e) {
    return 8;
  }
});

const totalCols = computed(() => {
  // base: kode_rek + program (2) + RENJA(4) + DPA(4) + Capaian(2) = 12
  return 12 + Number(realisasiCols.value || 8);
});

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

// fullpage mode flag and toggler
const isFullpage = ref(false);
// initialize from URL
try {
  const qpInit = new URLSearchParams(window.location.search || '');
  const vInit = qpInit.get('fullpage');
  isFullpage.value = (vInit === '1' || vInit === 'true');
} catch (e) {
  isFullpage.value = false;
}

function toggleFullpage() {
  isFullpage.value = !isFullpage.value;
  try {
    const qp = new URLSearchParams(window.location.search || '');
    if (isFullpage.value) qp.set('fullpage', '1'); else qp.delete('fullpage');
    const newUrl = window.location.pathname + (String(qp) ? ('?' + String(qp)) : '');
    window.history.replaceState({}, '', newUrl);
  } catch (e) {
    // ignore
  }
}

function getOpdName(opdId) {
  try {
    const id = Number(opdId);
    const found = (props.opds || []).find(o => Number(o.id) === id);
    return found ? found.nama : null;
  } catch (e) {
    return null;
  }
}

function shouldRenderOpdHeader(idx, row) {
  if (!row || !row.opd_id) return false;
  const prev = (props.tableData || [])[idx - 1];
  if (!prev) return true;
  return String(prev.opd_id) !== String(row.opd_id);
}

function shouldShowUnitHeaderInRealisasi(idx, row) {
  // Show unit header when OPD/unit changes in realisasi table
  if (!row || !row.opd_id) return false;
  const prev = (props.tableData || [])[idx - 1];
  if (!prev) return true; // First row always shows unit
  return String(prev.opd_id) !== String(row.opd_id);
}

function getPaguFromProgramArray(programsArray, kode) {
  // Find pagu from array that matches the kode, or return first item's pagu
  if (!programsArray || programsArray.length === 0) {
    console.log(`[getPagu] kode=${kode} - Empty array`);
    return 0;
  }
  
  // Try to find exact kode match
  const match = programsArray.find(p => String(p?.kode).trim() === String(kode).trim());
  if (match) {
    console.log(`[getPagu] kode=${kode} - Found exact match: ${match.pagu}`);
    return toNumberSafe(match.pagu) || 0;
  }
  
  // Fallback to first item
  const fallbackPagu = programsArray[0]?.pagu || 0;
  console.log(`[getPagu] kode=${kode} - No exact match (array len=${programsArray.length}). Using fallback: ${fallbackPagu}`);
  return toNumberSafe(fallbackPagu) || 0;
}

function sumRealisasiKeuangan(row) {
  try {
    const data = row?.realisasi_data || {};
    const tw = (typeof twValue !== 'undefined' && twValue) ? Number(twValue.value || twValue) : null;
    if (tw && data[tw]) {
      return toNumberSafe(data[tw]?.keuangan) || 0;
    }
    return Object.keys(data).reduce((acc, k) => {
      const v = toNumberSafe(data[k]?.keuangan) || 0;
      return acc + v;
    }, 0);
  } catch (e) {
    return 0;
  }
}

function avgRealisasiFisik(row) {
  try {
    const data = row?.realisasi_data || {};
    const tw = (typeof twValue !== 'undefined' && twValue) ? Number(twValue.value || twValue) : null;
    if (tw && data[tw]) {
      return toNumberSafe(data[tw]?.fisik) || 0;
    }
    const vals = Object.keys(data).map(k => toNumberSafe(data[k]?.fisik) || 0).filter(n => !isNaN(n));
    if (!vals.length) return 0;
    return Math.round(vals.reduce((a,b) => a+b, 0) / vals.length * 100) / 100;
  } catch (e) {
    return 0;
  }
}

function getIndicatorValue(programsArray, kode, key) {
  try {
    if (!programsArray || programsArray.length === 0) return '-';
    const match = programsArray.find(p => String(p?.kode).trim() === String(kode).trim());
    const prog = match || programsArray[0];
    const ind = (prog?.indikator && prog.indikator[0]) || null;
    if (!ind) return '-';
    const val = ind[key];
    if (val === null || val === undefined) return '-';
    return val;
  } catch (e) {
    return '-';
  }
}

function toNumberSafe(val) {
  if (val === null || val === undefined) return null;
  const s = String(val).replace(/[,\s]/g, '');
  const n = Number(s);
  return isNaN(n) ? null : n;
}

function computeCapaianKinerja(indRow, row) {
  try {
    const tw = (typeof twValue !== 'undefined' && twValue) ? Number(twValue.value || twValue) : null;
    let real = 0;
    if (tw && row?.realisasi_data && row.realisasi_data[tw]) {
      real = toNumberSafe(row.realisasi_data[tw].fisik) || 0;
    } else {
      real = avgRealisasiFisik(row);
    }
    real = toNumberSafe(real) || 0;

    // prefer RENJA indicator target (indicator-level or program array), then RKPD, then DPA
    let targetRaw = indRow?.renja_target ?? indRow?.rkpd_target ?? indRow?.target_indikator ?? null;
    if (targetRaw === null || targetRaw === undefined || targetRaw === '-') {
      targetRaw = getIndicatorValue((row.renstra_programs || row.rkpd_programs || row.renja_programs) || (row.years && row.years[yearValue.value] && row.years[yearValue.value].renja ? (row.years[yearValue.value].renja.programs || []) : []), row.kode_rek, 'target_indikator');
    }
    if (targetRaw === null || targetRaw === undefined || targetRaw === '-') {
      targetRaw = getIndicatorValue(row.rkpd_programs, row.kode_rek, 'target_indikator');
    }
    const target = toNumberSafe(targetRaw);
    if (target === null || target === 0) return '-';
    const pct = (Number(real) / Number(target)) * 100;
    if (!isFinite(pct)) return '-';
    return `${Number(pct.toFixed(2))}%`;
  } catch (e) {
    return '-';
  }
}

function computeCapaianKeuangan(indRow, row) {
  try {
    const tw = (typeof twValue !== 'undefined' && twValue) ? Number(twValue.value || twValue) : null;
    let keu = 0;
    if (tw && row?.realisasi_data && row.realisasi_data[tw]) {
      keu = toNumberSafe(row.realisasi_data[tw].keuangan) || 0;
    } else {
      keu = sumRealisasiKeuangan(row);
    }
    keu = toNumberSafe(keu) || 0;

    // prefer RENJA pagu if present (indicator-level or program array), then RKPD, then DPA
    let pagu = toNumberSafe(indRow?.renja_pagu ?? indRow?.rkpd_pagu ?? indRow?.pagu ?? null);
    if (!pagu) {
      // try renstra_programs or renja in years
      const renjaPrograms = row.renstra_programs || row.rkpd_programs || row.renja_programs || (row.years && row.years[yearValue.value] && row.years[yearValue.value].renja ? (row.years[yearValue.value].renja.programs || []) : []);
      pagu = Number(getPaguFromProgramArray(renjaPrograms, row.kode_rek) || 0);
    }
    if (!pagu) {
      pagu = Number(getPaguFromProgramArray(row.rkpd_programs, row.kode_rek) || 0);
    }
    if (pagu === 0) return '-';
    const pct = (Number(keu) / pagu) * 100;
    if (!isFinite(pct)) return '-';
    return `${Number(pct.toFixed(2))}%`;
  } catch (e) {
    return '-';
  }
}

function getCapaianClass(pctString) {
  try {
    if (!pctString || pctString === '-' || pctString === null) return '';
    const s = String(pctString).replace('%', '').replace(/[,\s]/g, '');
    const n = parseFloat(s);
    if (isNaN(n)) return '';
    if (n <= 50) return 'bg-red-600 text-white rounded px-1';
    if (n > 50 && n <= 65) return 'bg-yellow-300 text-black rounded px-1';
    if (n > 65 && n <= 75) return 'bg-orange-400 text-black rounded px-1';
    if (n > 75 && n <= 90) return 'bg-green-600 text-white rounded px-1';
    if (n > 90) return 'bg-blue-600 text-white rounded px-1';
    return '';
  } catch (e) {
    return '';
  }
}

function renderCapaian(pctString) {
  try {
    if (!pctString || pctString === '-' || pctString === null) {
      return { text: 'NA', cls: 'bg-gray-400 text-white rounded px-1' };
    }
    const cls = getCapaianClass(pctString) || '';
    return { text: pctString, cls };
  } catch (e) {
    return { text: 'NA', cls: 'bg-gray-400 text-white rounded px-1' };
  }
}

function getPreferredIndicatorLabel(row, kode) {
  try {
    // prefer renstra program-level indicators when available
    const programs = row?.renstra_programs || row?.rkpd_programs || row?.renja_programs || [];
    if (Array.isArray(programs) && programs.length > 0) {
      const k = kode || row?.kode_rek || row?.kode || (programs[0] && programs[0].kode) || '';
      const v = getIndicatorValue(programs, k, 'nama_indikator');
      if (v && v !== '-') return v;
    }
    // fallback to common fields on the row
    return row?.renstra_indikator ?? row?.rpjmd_indikator ?? row?.rkpd_indikator ?? row?.indikator_program ?? row?.indikator ?? row?.nama_indikator ?? '-';
  } catch (e) {
    return row?.indikator ?? '-';
  }
}

async function ensureHtml2PdfLoaded() {
  if (window.html2pdf) return;
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js';
    s.onload = () => resolve();
    s.onerror = () => reject(new Error('Failed to load html2pdf script'));
    document.head.appendChild(s);
  });
}

async function exportRealisasiPdf() {
  try {
    await ensureHtml2PdfLoaded();
    const container = document.getElementById('realisasi-export-root') || document.getElementById('realisasi-table');
    if (!container) {
      alert('Elemen untuk diekspor tidak ditemukan.');
      return;
    }

    // Clone and prepare wrapper to avoid CSS bleed and ensure full width
    const clone = container.cloneNode(true);
    const wrapper = document.createElement('div');
    wrapper.style.width = '1200px';
    wrapper.style.margin = '0 auto';
    wrapper.style.padding = '16px';
    wrapper.style.background = window.getComputedStyle(document.body).backgroundColor || '#ffffff';
    wrapper.appendChild(clone);
    document.body.appendChild(wrapper);

    const opt = {
      margin:       [10, 10, 10, 10],
      filename:     `${props.currentView || 'export'}_${props.currentTable || 'table'}_${new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')}.pdf`,
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2, useCORS: true, allowTaint: true, backgroundColor: null },
      jsPDF:        { unit: 'pt', format: 'a4', orientation: 'landscape' }
    };

    // generate PDF and cleanup
    await window.html2pdf().set(opt).from(wrapper).save();
    document.body.removeChild(wrapper);
  } catch (e) {
    console.error('Export failed', e);
    alert('Export gagal: ' + (e?.message || e));
  }
}

async function ensureSheetJsLoaded() {
  if (window.XLSX) return;
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
    s.onload = () => resolve();
    s.onerror = () => reject(new Error('Failed to load SheetJS'));
    document.head.appendChild(s);
  });
}

function rgbToHex(rgb) {
  if (!rgb) return null;
  // Handle hex input like #rrggbb or #rgb
  if (rgb[0] === '#') {
    let hex = rgb.slice(1);
    if (hex.length === 3) hex = hex.split('').map(ch => ch + ch).join('');
    return ('FF' + hex).toUpperCase();
  }
  // rgba or rgb
  const m = rgb.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?\)/);
  if (!m) return null;
  let r = Number(m[1]);
  let g = Number(m[2]);
  let b = Number(m[3]);
  const a = typeof m[4] !== 'undefined' ? Number(m[4]) : 1;
  // If transparent, return null
  if (a === 0) return null;
  // Blend alpha over white background to approximate resulting color in Excel
  const blend = (channel) => Math.round((a * channel) + (1 - a) * 255);
  if (a < 1) {
    r = blend(r);
    g = blend(g);
    b = blend(b);
  }
  const hs = [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('').toUpperCase();
  return ('FF' + hs);
}

function cssHex(rgb) {
  if (!rgb) return null;
  if (rgb[0] === '#') {
    // ensure 6 chars
    let hex = rgb.slice(1);
    if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
    return mapToExcelColor('#' + hex.toUpperCase());
  }
  const m = rgb.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?\)/);
  if (!m) return null;
  let r = Number(m[1]), g = Number(m[2]), b = Number(m[3]);
  const a = typeof m[4] !== 'undefined' ? Number(m[4]) : 1;
  if (a < 1) {
    // blend with white
    r = Math.round(a * r + (1 - a) * 255);
    g = Math.round(a * g + (1 - a) * 255);
    b = Math.round(a * b + (1 - a) * 255);
  }
  return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('').toUpperCase();
}

function getCapaianHexFromClass(classStr) {
  if (!classStr) return null;
  const s = String(classStr);
  if (s.includes('bg-red-600') || s.includes('bg-red')) return '#FF0000';
  if (s.includes('bg-yellow-300') || s.includes('bg-yellow')) return '#FFD700';
  if (s.includes('bg-orange-400') || s.includes('bg-orange')) return '#FFA500';
  if (s.includes('bg-green-600') || s.includes('bg-green')) return '#228B22';
  if (s.includes('bg-blue-600') || s.includes('bg-blue')) return '#1E90FF';
  return null;
}

function mapToExcelColor(hex) {
  if (!hex) return hex;
  // Normalize to #RRGGBB
  let h = hex.replace(/^FF/, '');
  if (h[0] !== '#') h = '#' + h;
  if (h.length === 4) h = '#' + h[1] + h[1] + h[2] + h[2] + h[3] + h[3];
  h = h.toUpperCase();
  // Excel-like palette (common web-safe choices + important colors)
  const palette = [
    '#000000','#FFFFFF','#FF0000','#00FF00','#0000FF','#FFFF00','#FFA500','#00FFFF','#800080','#808080',
    '#1E90FF','#228B22','#32CD32','#008000','#FFD700','#F0E68C','#D2691E','#A9A9A9','#2F4F4F','#ADD8E6'
  ];
  const toRgb = hx => {
    const hh = hx.replace('#','');
    return [parseInt(hh.slice(0,2),16), parseInt(hh.slice(2,4),16), parseInt(hh.slice(4,6),16)];
  };
  const t = toRgb(h);
  let best = palette[0];
  let bestDist = Infinity;
  for (const p of palette) {
    const q = toRgb(p);
    const dist = (t[0]-q[0])**2 + (t[1]-q[1])**2 + (t[2]-q[2])**2;
    if (dist < bestDist) { bestDist = dist; best = p; }
  }
  return best;
}

function hexToXlsxRgb(hex) {
  if (!hex) return null;
  // normalize #RRGGBB or RRGGBB to FFRRGGBB
  let h = hex.replace(/^FF/, '').replace('#', '');
  if (h.length === 3) h = h.split('').map(c => c + c).join('');
  h = h.toUpperCase();
  return 'FF' + h;
}

async function exportRealisasiExcel() {
  try {
    await ensureSheetJsLoaded();
    const container = document.getElementById('realisasi-export-root');
    if (!container) {
      alert('Elemen tabel realisasi tidak ditemukan');
      return;
    }
    const table = container.querySelector('table');
    if (!table) {
      alert('Tabel realisasi tidak ditemukan');
      return;
    }

    // Build data array and capture cell styles, merges and row heights
    const rows = Array.from(table.querySelectorAll('tr'));
    const aoa = [];
    const styles = {};
    const merges = [];
    const rowHeights = {};
    for (let r = 0; r < rows.length; r++) {
      const cells = Array.from(rows[r].querySelectorAll('th,td'));
      if (cells.length === 0) continue;
      const rowArr = [];
      let cIndex = 0;
      for (let c = 0; c < cells.length; c++) {
        const cell = cells[c];
        const colspan = Number(cell.getAttribute('colspan') || 1);
        const rowspan = Number(cell.getAttribute('rowspan') || 1);
        const text = cell.innerText.trim();
        // place text at current cIndex
        rowArr[cIndex] = text;
        const comp = window.getComputedStyle(cell);
        const classBased = getCapaianHexFromClass(cell.className || cell.classList?.value);
        const isHeader = (cell.tagName && cell.tagName.toLowerCase() === 'th') || !!cell.closest('thead');
        const rowEl = rows[r];
        // default color resolution
        let bg = classBased || rgbToHex(comp.backgroundColor) || null;
        let color = rgbToHex(comp.color) || null;

        // enforce exact mapping
        if (isHeader) {
          bg = '#FF0000'; color = '#FFFFFF';
        } else if ((cell.innerText || '').trim() === '-' || (cell.innerText || '').trim() === '') {
          bg = '#000000'; color = '#FFFFFF';
        } else if (rowEl && (String(rowEl.className).includes('bg-orange') || String(rowEl.className).includes('bg-orange-600'))) {
          bg = '#FFA500'; color = '#000000';
        } else if (rowEl && (String(rowEl.className).includes('bg-emerald') || String(rowEl.className).includes('bg-green'))) {
          bg = '#228B22'; color = '#FFFFFF';
        } else if (rowEl && String(rowEl.className).includes('bg-yellow')) {
          bg = '#FFD700'; color = '#000000';
        }

        styles[`${r},${cIndex}`] = { bg, color, className: cell.className, isHeader };
        if (colspan > 1 || rowspan > 1) {
          merges.push({ s: { r: r, c: cIndex }, e: { r: r + (rowspan - 1), c: cIndex + (colspan - 1) } });
        }
        cIndex += colspan;
      }
      aoa.push(rowArr);
      // capture row height in points (px -> pt = px * 0.75)
      try { const rect = rows[r].getBoundingClientRect(); const px = rect.height || 20; rowHeights[r] = Math.max(12, Math.round(px * 0.75)); } catch (e) {}
    }

    const ws = window.XLSX.utils.aoa_to_sheet(aoa);

    // Apply styles per captured styles map and enable wrapText
    const colMaxLen = {};
    Object.keys(styles).forEach(k => {
      const [r,c] = k.split(',').map(n => Number(n));
      const cellRef = window.XLSX.utils.encode_cell({ r, c });
      const cell = ws[cellRef];
      if (!cell) return;
      const st = styles[k];
      cell.s = cell.s || {};
      // header override: force gray background and bold black font
      if (st.isHeader) {
        const headerHex = '#D1D5DB'; // light gray
        const xh = hexToXlsxRgb(headerHex);
        if (xh) cell.s.fill = { patternType: 'solid', fgColor: { rgb: xh } };
        cell.s.font = cell.s.font || {};
        cell.s.font.bold = true;
        cell.s.font.color = { rgb: hexToXlsxRgb('#000000') };
      } else {
        // fill with solid pattern (convert captured color to XLSX RGB)
        if (st.bg) {
          const mapped = mapToExcelColor(st.bg) || st.bg;
          const xrgb = hexToXlsxRgb(mapped);
          if (xrgb) cell.s.fill = { patternType: 'solid', fgColor: { rgb: xrgb } };
        }
        if (st.color) {
          const mappedF = mapToExcelColor(st.color) || st.color;
          const xfont = hexToXlsxRgb(mappedF);
          if (xfont) {
            cell.s.font = cell.s.font || {};
            cell.s.font.color = { rgb: xfont };
          }
        }
      }
      // wrap text
      cell.s.alignment = Object.assign({}, cell.s.alignment || {}, { wrapText: true, vertical: 'center' });
      // compute max length per column for width
      const val = String(cell.v || '');
      colMaxLen[c] = Math.max(colMaxLen[c] || 0, val.length);
    });

    // Ensure all cells have wrapText (for cells without explicit styles)
    Object.keys(ws).forEach(k => {
      if (k[0] === '!') return;
      const c = ws[k];
      c.s = c.s || {};
      c.s.alignment = Object.assign({}, c.s.alignment || {}, { wrapText: true, vertical: 'center' });
    });

    // set reasonable column widths based on max content length
    const maxCols = Math.max(...Object.keys(colMaxLen).map(n => Number(n)), 0);
    const cols = [];
    for (let ci = 0; ci <= maxCols; ci++) {
      const len = colMaxLen[ci] || 10;
      // wch = approx characters width; clamp between 10 and 60
      const wch = Math.min(60, Math.max(10, Math.ceil(len * 1.1)));
      cols.push({ wch });
    }
    if (cols.length) ws['!cols'] = cols;

    // Try server-side export first for best Excel fidelity
    try {
      const aoaPayload = aoa;
      const colsPayload = (cols && cols.length) ? cols.map(c => c.wch || 15) : [];
      const payload = { aoa: aoaPayload, styles: styles, cols: colsPayload, merges: merges, rowHeights: rowHeights };
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const res = await fetch('/resume/export-server', {
        method: 'POST',
        headers: Object.assign({ 'Content-Type': 'application/json' }, csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        body: JSON.stringify(payload)
      });
      if (!res.ok) throw new Error('Server export failed');
      const blob = await res.blob();
      const fname = `${props.currentView || 'export'}_${props.currentTable || 'table'}_${new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')}.xlsx`;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = fname;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      return;
    } catch (e) {
      console.warn('Server export failed, falling back to client SheetJS export', e);
    }

    // Fallback: build workbook client-side using SheetJS
    const wb = window.XLSX.utils.book_new();
    window.XLSX.utils.book_append_sheet(wb, ws, 'Realisasi');
    const wbout = window.XLSX.write(wb, { bookType: 'xlsx', type: 'array', cellStyles: true });
    const blob = new Blob([wbout], { type: 'application/octet-stream' });
    const fname = `${props.currentView || 'export'}_${props.currentTable || 'table'}_${new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')}.xlsx`;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fname;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    console.error('Export Excel failed', e);
    alert('Export Excel gagal: ' + (e?.message || e));
  }
}

function inlineComputedStylesToClone(el) {
  const clone = el.cloneNode(true);
  const cells = clone.querySelectorAll('th,td');
  cells.forEach(cell => {
    const orig = el.querySelector(getNodeSelector(cell, clone));
    const comp = window.getComputedStyle(orig || cell);
    const styles = [];
    if (comp.backgroundColor && comp.backgroundColor !== 'rgba(0, 0, 0, 0)' && comp.backgroundColor !== 'transparent') styles.push(`background-color: ${comp.backgroundColor}`);
    if (comp.color) styles.push(`color: ${comp.color}`);
    if (comp.fontWeight) styles.push(`font-weight: ${comp.fontWeight}`);
    if (comp.textAlign) styles.push(`text-align: ${comp.textAlign}`);
    if (comp.verticalAlign) styles.push(`vertical-align: ${comp.verticalAlign}`);
    if (comp.whiteSpace) styles.push(`white-space: ${comp.whiteSpace}`);
    if (comp.fontSize) styles.push(`font-size: ${comp.fontSize}`);
    cell.setAttribute('style', styles.join('; '));
  });
  return clone;
}

function getNodeSelector(node, within) {
  // Try to find equivalent node in original by matching text and position
  // Fallback: use tagName and index
  const tag = node.tagName;
  const all = Array.from(within.querySelectorAll(tag));
  const idx = all.indexOf(node);
  return `${tag}:nth-of-type(${idx + 1})`;
}

function exportRealisasiExcelHtml() {
  try {
    const container = document.getElementById('realisasi-export-root');
    if (!container) return alert('Elemen tabel realisasi tidak ditemukan');
    const table = container.querySelector('table');
    if (!table) return alert('Tabel realisasi tidak ditemukan');
    // Clone table and inline computed styles for fidelity
    const cloned = table.cloneNode(true);

    // Determine logical column count and compute widths
    const rows = Array.from(table.querySelectorAll('tr'));
    let maxCols = 0;
    const colWidths = [];
    rows.forEach(row => {
      const cells = Array.from(row.querySelectorAll('th,td'));
      let colIndex = 0;
      cells.forEach(cell => {
        const colspan = Number(cell.getAttribute('colspan') || 1);
        const rect = cell.getBoundingClientRect();
        const widthPerCol = rect.width / colspan;
        for (let k = 0; k < colspan; k++) {
          colWidths[colIndex + k] = Math.max(colWidths[colIndex + k] || 0, widthPerCol || 50);
        }
        colIndex += colspan;
      });
      maxCols = Math.max(maxCols, colIndex);
    });

    // Insert colgroup into cloned table to fix column widths
    if (colWidths.length) {
      const colgroup = document.createElement('colgroup');
      for (let i = 0; i < colWidths.length; i++) {
        const col = document.createElement('col');
        // set width in pixels
        col.style.width = Math.max(50, Math.round(colWidths[i])) + 'px';
        colgroup.appendChild(col);
      }
      const firstChild = cloned.querySelector('table') ? cloned.querySelector('table') : cloned;
      // If cloned is table element already, prepend colgroup
      if (cloned.tagName && cloned.tagName.toLowerCase() === 'table') {
        cloned.insertBefore(colgroup, cloned.firstChild);
      } else {
        const t = cloned.querySelector('table') || cloned;
        t.insertBefore(colgroup, t.firstChild);
      }
    }

    // Inline styles per cell (including borders)
    const origCells = Array.from(table.querySelectorAll('th,td'));
    const cloneCells = Array.from(cloned.querySelectorAll('th,td'));
    function getEffectiveComputedStyle(el, prop) {
      if (!el) return null;
      // check element, its first child, then ancestors up to table
      const nodes = [];
      nodes.push(el);
      if (el.firstElementChild) nodes.push(el.firstElementChild);
      let p = el.parentElement;
      while (p && p !== table && nodes.length < 10) { nodes.push(p); p = p.parentElement; }
      for (const n of nodes) {
        try {
          const val = window.getComputedStyle(n)[prop];
          if (!val) continue;
          if (prop.toLowerCase().includes('color')) {
            if (val !== 'transparent' && val !== 'rgba(0, 0, 0, 0)') return val;
          } else {
            if (val !== '0px' && val !== 'none' && val !== '') return val;
          }
        } catch (e) {
          // ignore
        }
      }
      return null;
    }
    for (let i = 0; i < origCells.length; i++) {
      const orig = origCells[i];
      const cc = cloneCells[i];
      const inline = [];
      // prefer class-based mapping for capaian cells
      const classBased = getCapaianHexFromClass(orig.className || orig.classList?.value);
      const isHeaderCell = (orig.tagName && orig.tagName.toLowerCase() === 'th') || !!orig.closest('thead');
      const rowEl = orig.closest('tr');
      if (isHeaderCell) {
        inline.push(`background-color: #FF0000`);
        inline.push('font-weight: bold');
        inline.push('color: #FFFFFF');
      } else if ((orig.innerText || '').trim() === '-' || (orig.innerText || '').trim() === '') {
        inline.push(`background-color: #000000`);
        inline.push('color: #FFFFFF');
      } else if (rowEl && (String(rowEl.className).includes('bg-orange') || String(rowEl.className).includes('bg-orange-600'))) {
        inline.push(`background-color: #FFA500`);
        inline.push('color: #000000');
      } else if (rowEl && (String(rowEl.className).includes('bg-emerald') || String(rowEl.className).includes('bg-green'))) {
        inline.push(`background-color: #228B22`);
        inline.push('color: #FFFFFF');
      } else if (rowEl && String(rowEl.className).includes('bg-yellow')) {
        inline.push(`background-color: #FFD700`);
        inline.push('color: #000000');
      } else {
        const bgRaw = classBased || getEffectiveComputedStyle(orig, 'backgroundColor');
        if (bgRaw && bgRaw !== 'transparent' && bgRaw !== 'rgba(0, 0, 0, 0)') {
          const hex = classBased ? classBased : (cssHex(bgRaw) || bgRaw);
          inline.push(`background-color: ${hex}`);
        }
      }
      const colorRaw = getEffectiveComputedStyle(orig, 'color') || getEffectiveComputedStyle(orig, 'foregroundColor');
      if (colorRaw) {
        const hexc = cssHex(colorRaw) || colorRaw;
        inline.push(`color: ${hexc}`);
      }
      const fw = getEffectiveComputedStyle(orig, 'fontWeight'); if (fw) inline.push(`font-weight: ${fw}`);
      const ta = getEffectiveComputedStyle(orig, 'textAlign'); if (ta) inline.push(`text-align: ${ta}`);
      const va = getEffectiveComputedStyle(orig, 'verticalAlign'); if (va) inline.push(`vertical-align: ${va}`);
      // border: check effective border widths and colors
      const bWidth = getEffectiveComputedStyle(orig, 'borderTopWidth') || getEffectiveComputedStyle(orig, 'borderWidth') || '1px';
      const bStyle = getEffectiveComputedStyle(orig, 'borderTopStyle') || 'solid';
      const bColorRaw = getEffectiveComputedStyle(orig, 'borderTopColor') || getEffectiveComputedStyle(orig, 'borderColor') || '#000';
      const bColor = cssHex(bColorRaw) || bColorRaw;
      inline.push(`border: ${bWidth} ${bStyle} ${bColor}`);
      inline.push('white-space: normal; word-wrap: break-word');
      const fs = getEffectiveComputedStyle(orig, 'fontSize'); if (fs) inline.push(`font-size: ${fs}`);
      const ff = getEffectiveComputedStyle(orig, 'fontFamily'); if (ff) inline.push(`font-family: ${ff}`);
      const pd = getEffectiveComputedStyle(orig, 'padding'); if (pd) inline.push(`padding: ${pd}`);
      cc.setAttribute('style', inline.join('; '));
    }

    const tableWidth = Math.round(table.getBoundingClientRect().width || 800);
    const html = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>table{border-collapse:collapse; width: ${tableWidth}px;} th,td{border-collapse:collapse;}</style></head><body>${cloned.outerHTML}</body></html>`;
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const fname = `${props.currentView || 'export'}_${props.currentTable || 'table'}_${new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')}.xls`;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fname;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    console.error('Export Excel (HTML) failed', e);
    alert('Export Excel (HTML) gagal: ' + (e?.message || e));
  }
}

function getPrimaryLevel(row) {
  try {
    const dp = row?.dpa_programs || [];
    if (dp.length && dp[0]?.level) return dp[0].level;
    const rk = row?.rkpd_programs || [];
    if (rk.length && rk[0]?.level) return rk[0].level;
    return 'program';
  } catch (e) {
    return 'program';
  }
}

function getRowClasses(row, idx) {
  const baseStrip = (idx % 2 === 0) ? 'bg-gray-900' : 'bg-gray-800';
  const level = getPrimaryLevel(row);
  let levelClass = '';
  if (level === 'program') levelClass = 'bg-green-600 text-white';
  else if (level === 'kegiatan') levelClass = 'bg-yellow-200 text-black';
  else if (level === 'sub') levelClass = 'bg-white text-black';
  return `${levelClass} border border-emerald-200`;
}

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
  // treat `tabel-4` in `konsistensi-rkpd-apbd` as indikator mode even when
  // `tableMetricType` wasn't explicitly set by the server.
  if (props.tableMetricType === 'indikator') return true;
  if (props.currentView === 'konsistensi-rkpd-apbd' && props.currentTable === 'tabel-4') return true;
  return false;
});

const sortedRealisasiTableData = computed(() => {
  try {
    const data = Array.isArray(props.tableData) ? props.tableData.slice() : (props.tableData ? [props.tableData] : []);
    data.sort((a, b) => {
      const ai = Number(a?.opd_id || 0);
      const bi = Number(b?.opd_id || 0);
      if (ai !== bi) return ai - bi;
      const ak = String(a?.kode_rek ?? a?.kode ?? '').trim();
      const bk = String(b?.kode_rek ?? b?.kode ?? '').trim();
      if (ak === bk) return 0;
      return ak.localeCompare(bk, undefined, { numeric: true, sensitivity: 'base' });
    });
    return data;
  } catch (e) {
    return props.tableData || [];
  }
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
  // Build pagu maps for Renstra (Renja), RKPD and DPA so we can show pagu per-indicator
  const renstraPaguMap = new Map();
  (row?.renstra_programs || []).forEach((p) => {
    const pagu = toNumber(p?.pagu || p?.pagu_tahunan || p?.anggaran || 0);
    const names = extractIndicatorNamesGlobal(p);
    names.forEach((n) => {
      const kk = normalize(n);
      renstraPaguMap.set(kk, (renstraPaguMap.get(kk) || 0) + pagu);
    });
  });

  const rkpdPaguMap = new Map();
  (row?.rkpd_programs || []).forEach((p) => {
    const pagu = toNumber(p?.pagu || p?.pagu_tahunan || p?.anggaran || 0);
    const names = extractIndicatorNamesGlobal(p);
    names.forEach((n) => {
      const kk = normalize(n);
      rkpdPaguMap.set(kk, (rkpdPaguMap.get(kk) || 0) + pagu);
    });
  });

  const dpaPaguMap = new Map();
  (row?.dpa_programs || []).forEach((p) => {
    const pagu = toNumber(p?.pagu || p?.pagu_tahunan || p?.anggaran || 0);
    const names = extractIndicatorNamesGlobal(p);
    names.forEach((n) => {
      const kk = normalize(n);
      dpaPaguMap.set(kk, (dpaPaguMap.get(kk) || 0) + pagu);
    });
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
      triwulan: twValue.value,
      opd_id: selectedOpd.value || undefined,
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
    return normalizeComparableText(program?.nama);
  }

  // Prefer explicit client_key when server provides it (already normalized/uppercased)
  if (program?.client_key) {
    return String(program.client_key).toUpperCase();
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

    // Exact match first
    if (k === key) {
      const names = extractIndicatorNames(p);
      names.forEach(n => items.push(n));
      return;
    }

    // Try relaxed matching by comparing kode portion (before '|') and using prefix rules.
    const extractKode = (full) => String(full || '').split('|')[0] || '';
    const kodeK = extractKode(k);
    const kodeKey = extractKode(key);

    if (kodeK && kodeKey) {
      // consider same base program or sub-program mapping
      if (kodeK === kodeKey || kodeK.startsWith(kodeKey) || kodeKey.startsWith(kodeK)) {
        const names = extractIndicatorNames(p);
        names.forEach(n => items.push(n));
        return;
      }
    }
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
        level: p?.level ?? 'program',
        rpjmdIndicators: [],
        renstraIndicators: [],
        rkpdIndicators: [],
        dpaIndicators: [],
      });
    }

    const entry = map.get(key);

    const collectIndicatorsFrom = (item) => {
      if (!isIndikatorMode.value) return [];
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
    level: e.level || 'program',
    rpjmdIndicators: Array.from(new Set(e.rpjmdIndicators.map(s => String(s || '').trim()))).filter(Boolean),
    renstraIndicators: Array.from(new Set(e.renstraIndicators.map(s => String(s || '').trim()))).filter(Boolean),
    rkpdIndicators: Array.from(new Set(e.rkpdIndicators.map(s => String(s || '').trim()))).filter(Boolean),
    dpaIndicators: Array.from(new Set(e.dpaIndicators.map(s => String(s || '').trim()))).filter(Boolean),
  }));

  // If RKPD indicators are absent but DPA indicators exist for the same program,
  // mirror DPA indicators into RKPD so the RKPD column (kolom 4) shows values.
  // Only do this for non-program metric types and only when RENJA-as-left isn't active.
  // When `useRenjaForLeftColumn` is true we must not create RKPD indicators from DPA,
  // because the left column should strictly reflect RENJA presence (or be empty).
  if (props.tableMetricType !== 'program' && !useRenjaForLeftColumn.value) {
    lines.forEach((l) => {
      if ((!l.rkpdIndicators || l.rkpdIndicators.length === 0) && (l.dpaIndicators && l.dpaIndicators.length > 0)) {
        l.rkpdIndicators = [...l.dpaIndicators];
      }
    });
  }

  // Additional rule: when RENJA-as-left mode is active for tabel-4, treat the DPA
  // dataset as the reference for whether indicators should be shown. If there
  // is no matching DPA program for a line, or the matching DPA program has no
  // indikator entries, clear both RKPD and DPA indicator arrays so the table
  // renders an empty indicator row (per user's DPA-as-acuan request).
  try {
    if (useRenjaForLeftColumn.value) {
      const dpaList = Array.isArray(row?.dpa_programs) ? row.dpa_programs : [];
      lines.forEach((l) => {
        // try to locate a DPA program matching this line by program key or name
        const match = dpaList.find((p) => {
          const k1 = buildProgramKey(p) || '';
          const k2 = l.key || '';
          if (k1 && k2 && k1 === k2) return true;
          const name = (p?.nama ?? p?.program_nama ?? '').trim();
          if (name && l.name && name === l.name) return true;
          return false;
        });

        const hasDpaIndicators = match && Array.isArray(match.indikator) && match.indikator.filter(Boolean).length > 0;
        if (!hasDpaIndicators) {
          l.rkpdIndicators = [];
          l.dpaIndicators = [];
        }
      });
    }
  } catch (e) {}

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

  // Collect Renja indicators for the selected year when in Renja-DPA (tabel-7) mode
  const renjaList = (() => {
    try {
      if (!useRenjaForLeftColumn.value) return [];
      const year = yearValue.value;
      const programs = Array.isArray(row?.rkpd_programs) ? row.rkpd_programs.filter(p => String((p?.dokumen || '')).toUpperCase() === 'RENJA') : [];
      const filtered = programs.filter(p => (year ? Number(p?.tahun) === Number(year) : true));
      return filtered.flatMap(p => extractIndicatorNamesGlobal(p));
    } catch (e) {
      return [];
    }
  })();

  // If RENJA is the required left source but RENJA provides no indicators,
  // do not show indicators from other sources — return a single empty row
  // so the program renders without indicator lines.
  try {
    if (useRenjaForLeftColumn.value && Array.isArray(renjaList) && renjaList.length === 0) {
      return [{ rkpd: '', dpa: '' }];
    }
  } catch (e) {}

  // Build maps from normalized indicator text -> original text
  const rkpdMap = new Map();
  rkpdList.forEach(i => rkpdMap.set(normalize(i), i));
  const renjaMap = new Map();
  renjaList.forEach(i => renjaMap.set(normalize(i), i));
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


  // Build Renja-specific target map (prefer renja program entries for the selected year)
  const renjaTargetMap = new Map();
  try {
    if (useRenjaForLeftColumn.value) {
      const year = yearValue.value;
      (row?.rkpd_programs || []).filter(p => String((p?.dokumen || '')).toUpperCase() === 'RENJA' && (year ? Number(p?.tahun) === Number(year) : true)).forEach((p) => {
        const names = extractIndicatorNamesGlobal(p);
        names.forEach((n) => {
          const k = normalize(n);
          if (!renjaTargetMap.has(k)) {
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
            renjaTargetMap.set(k, t ?? null);
          }
        });
      });
    }
  } catch (e) {
    // ignore
  }

  const rkpdSatuanMap = new Map();
  (row?.rkpd_programs || []).forEach((p) => {
    const names = extractIndicatorNamesGlobal(p);
    names.forEach((n) => {
      const kk = normalize(n);
      // prefer explicit fields
      let s = p?.satuan ?? p?.indikator_satuan ?? null;
      if ((s === null || s === undefined) && Array.isArray(p?.indikator)) {
        for (let el of p.indikator) {
          if (!el) continue;
          const elName = (typeof el === 'string') ? String(el).trim() : (el.nama_indikator ?? el.nama ?? '');
          if (elName && normalize(elName) === kk) {
            s = el.satuan ?? el.satuan_indikator ?? s;
            break;
          }
        }
      }
      rkpdSatuanMap.set(kk, s ?? null);
    });
  });
  // Renja satuan map
  const renjaSatuanMap = new Map();
  try {
    if (useRenjaForLeftColumn.value) {
      const year = yearValue.value;
      (row?.rkpd_programs || []).filter(p => String((p?.dokumen || '')).toUpperCase() === 'RENJA' && (year ? Number(p?.tahun) === Number(year) : true)).forEach((p) => {
        const names = extractIndicatorNamesGlobal(p);
        names.forEach((n) => {
          const kk = normalize(n);
          let s = p?.satuan ?? p?.indikator_satuan ?? null;
          if ((s === null || s === undefined) && Array.isArray(p?.indikator)) {
            for (let el of p.indikator) {
              if (!el) continue;
              const elName = (typeof el === 'string') ? String(el).trim() : (el.nama_indikator ?? el.nama ?? '');
              if (elName && normalize(elName) === kk) {
                s = el.satuan ?? el.satuan_indikator ?? s;
                break;
              }
            }
          }
          renjaSatuanMap.set(kk, s ?? null);
        });
      });
    }
  } catch (e) {}


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
 
  const dpaSatuanMap = new Map();
  (row?.dpa_programs || []).forEach((p) => {
    const names = extractIndicatorNamesGlobal(p);
    names.forEach((n) => {
      const kk = normalize(n);
      let s = p?.satuan ?? p?.indikator_satuan ?? null;
      if ((s === null || s === undefined) && Array.isArray(p?.indikator)) {
        for (let el of p.indikator) {
          if (!el) continue;
          const elName = (typeof el === 'string') ? String(el).trim() : (el.nama_indikator ?? el.nama ?? '');
          if (elName && normalize(elName) === kk) {
            s = el.satuan ?? el.satuan_indikator ?? s;
            break;
          }
        }
      }
      dpaSatuanMap.set(kk, s ?? null);
    });
  });
      // Build per-indicator pagu maps (try several field names and per-indikator fallbacks)
      const renstraPaguMap = new Map();
      const rkpdPaguMap = new Map();
      const dpaPaguMap = new Map();

      const buildPaguFor = (list, store) => {
        (list || []).forEach((p) => {
          const names = extractIndicatorNamesGlobal(p);
          // resolve pagu: prefer p.pagu, then sum of p.pagu_tahunan (array or object), then p.anggaran
          let pagu = p?.pagu ?? null;
          if ((pagu === null || pagu === undefined) && p?.pagu_tahunan) {
            if (Array.isArray(p.pagu_tahunan)) {
              pagu = p.pagu_tahunan.reduce((acc, v) => acc + (Number(v) || 0), 0);
            } else if (typeof p.pagu_tahunan === 'object') {
              pagu = Object.values(p.pagu_tahunan).reduce((acc, v) => acc + (Number(v) || 0), 0);
            }
          }
          if ((pagu === null || pagu === undefined) && (p?.anggaran !== undefined)) pagu = p.anggaran;
          if ((pagu === null || pagu === undefined) && p?.indikator) {
            if (Array.isArray(p.indikator)) {
              for (let el of p.indikator) {
                if (!el) continue;
                const elName = (typeof el === 'string') ? String(el).trim() : (el.nama_indikator ?? el.nama ?? '');
                if (!elName) continue;
                const cand = el.pagu ?? el.pagu_indikator ?? el.pagu_tahunan ?? el.anggaran ?? null;
                if (cand !== undefined && cand !== null) { pagu = cand; break; }
              }
            } else if (typeof p.indikator === 'object') {
              // single object mapping
              Object.values(p.indikator).forEach((el) => {
                if (pagu !== null && pagu !== undefined) return;
                if (!el) return;
                const cand = el.pagu ?? el.pagu_indikator ?? el.pagu_tahunan ?? el.anggaran ?? null;
                if (cand !== undefined && cand !== null) { pagu = cand; }
              });
            }
          }
          const amount = toNumber(pagu || 0);
          names.forEach((n) => {
            const kk = normalize(n);
            store.set(kk, (store.get(kk) || 0) + amount);
          });
        });
      };

      buildPaguFor(row?.renstra_programs || [], renstraPaguMap);
      // Renja-specific pagu map (take from rkpd_programs entries where dokumen === 'RENJA')
      const renjaPaguMap = new Map();
      try {
        const year = yearValue.value;
        const renjaListPrograms = Array.isArray(row?.rkpd_programs) ? row.rkpd_programs.filter(p => String((p?.dokumen||'')).toUpperCase() === 'RENJA' && (year ? Number(p?.tahun) === Number(year) : true)) : [];
        buildPaguFor(renjaListPrograms || [], renjaPaguMap);
      } catch (e) {}
        buildPaguFor(row?.rkpd_programs || [], rkpdPaguMap);
        buildPaguFor(row?.dpa_programs || [], dpaPaguMap);
  const rpjmdMap = new Map();
  rpjmdList.forEach(i => rpjmdMap.set(normalize(i), i));
  const renstraMap = new Map();
  renstraList.forEach(i => renstraMap.set(normalize(i), i));

  // Create ordered union: prefer rkpd order, then dpa, then rpjmd, then renstra
  const seen = new Set();
  const keys = [];
  const pushKey = (k) => { if (k && !seen.has(k)) { seen.add(k); keys.push(k); } };
  // prefer Renja keys first so Renja-only indicators appear in the left column
  renjaList.forEach(i => pushKey(normalize(i)));
  rkpdList.forEach(i => pushKey(normalize(i)));
  dpaList.forEach(i => pushKey(normalize(i)));
  rpjmdList.forEach(i => pushKey(normalize(i)));
  renstraList.forEach(i => pushKey(normalize(i)));

  if (keys.length === 0) {
    // fallback: keep single empty row so table renders
    return [{ rkpd: '', dpa: '' }];
  }

  return keys.map((k) => ({
    // Prefer RENJA for the left RKPD column when requested. If RENJA has no
    // indicator for this key, show '-' explicitly (no fallback).
    rkpd: useRenjaForLeftColumn.value ? (renjaMap.has(k) ? renjaMap.get(k) : '-') : ((renstraMap.get(k) || rkpdMap.get(k) || rpjmdMap.get(k)) || ''),
    dpa: dpaMap.get(k) || '',
    // Targets: when using RENJA as left source, show RENJA target if present,
    // otherwise show '-' to indicate missing RENJA target.
    rkpd_target: useRenjaForLeftColumn.value ? (renjaTargetMap.has(k) ? renjaTargetMap.get(k) : '-') : (rkpdTargetMap.has(k) ? rkpdTargetMap.get(k) : null),
    dpa_target: dpaTargetMap.has(k) ? dpaTargetMap.get(k) : null,
    // Satuan: same handling as targets
    rkpd_satuan: useRenjaForLeftColumn.value ? (renjaSatuanMap.has(k) ? renjaSatuanMap.get(k) : '-') : (rkpdSatuanMap.has(k) ? rkpdSatuanMap.get(k) : null),
    dpa_satuan: dpaSatuanMap.has(k) ? dpaSatuanMap.get(k) : null,
    // pagu: prefer RENJA pagu when using RENJA, otherwise follow previous order
    rkpd_pagu: useRenjaForLeftColumn.value ? (renjaPaguMap.has(k) ? renjaPaguMap.get(k) : 0) : ((renstraPaguMap.get(k) || rkpdPaguMap.get(k) || dpaPaguMap.get(k)) || 0),
    dpa_pagu: dpaPaguMap.get(k) || 0,
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

// chooseTriwulan uses the applyFilters above
function chooseTriwulan(tw) {
  twValue.value = tw;
  applyFilters();
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

// Return indicator names suitable for preview: filter out program names to avoid
// showing program titles where only indicators should appear.
const getIndicatorPreviewForRow = (row, type) => {
  const indicators = getIndicatorsForRow(row, type) || [];
  const programNames = (getProgramNamesForRow(row) || []).map(s => String(s || '').toLowerCase());
  return indicators.filter(i => !programNames.includes(String(i || '').toLowerCase()));
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
