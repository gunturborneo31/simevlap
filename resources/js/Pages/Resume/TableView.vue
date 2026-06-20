<template>
  <AppLayout title="Resume Monitoring">
    <section class="space-y-6">
      <div class="rounded-2xl border border-emerald-100 bg-white/90 p-6 shadow-md">
        <div class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-600">Resume</p>
            <h2 class="text-2xl font-bold text-emerald-900">{{ viewTitle }}</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">{{ currentTableLabel }}</p>
          </div>
          <div class="flex flex-col gap-3 md:items-end">
            <Link
              :href="route('resume.index', { view: currentView })"
              class="inline-flex items-center justify-center rounded-lg bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 transition-colors hover:bg-emerald-100"
            >
              Kembali ke Daftar Tabel
            </Link>
            <div class="flex flex-col gap-3 md:flex-row md:items-center">
              <select
                v-model="basisValue"
                @change="applyFilters"
                class="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 focus:border-emerald-400 focus:outline-none"
              >
                <option value="bidang-urusan">Berdasarkan Bidang Urusan</option>
                <option value="perangkat-daerah">Berdasarkan Perangkat Daerah</option>
              </select>

              <select
                v-model="yearValue"
                @change="applyFilters"
                class="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 focus:border-emerald-400 focus:outline-none"
              >
                <option v-for="year in availableYears" :key="year" :value="year">Tahun {{ year }}</option>
              </select>
            </div>

            
          </div>
        </div>
      </div>

      <div
        v-if="currentView === 'konsistensi-rpjmd-rkpd' && ['tabel-1', 'tabel-2'].includes(currentTable) && tableData"
        class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white/90 shadow-md"
      >
        <table class="w-full table-fixed border-collapse text-sm">
          <thead>
            <tr class="bg-emerald-50">
              <th rowspan="2" class="min-w-[70px] border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">No</th>
              <th rowspan="2" class="min-w-[230px] border border-emerald-200 bg-emerald-100 px-3 py-3 text-left font-bold text-emerald-900">{{ entityHeaderLabel }}</th>
              <th rowspan="2" class="min-w-[170px] border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">RPJMD (2026-2030) - Jumlah {{ metricLabel }}</th>
              <th rowspan="2" class="min-w-[170px] border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">Renstra (Tahun 2026) - Jumlah {{ metricLabel }}</th>
              <th rowspan="2" class="min-w-[170px] border border-emerald-200 bg-emerald-100 px-3 py-3 text-center font-bold text-emerald-900">RKPD/Renja (Tahun 2026) - Jumlah {{ metricLabel }}</th>
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
import { computed, ref } from 'vue';

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
    default: 'bidang-urusan',
  },
  selectedYear: {
    type: Number,
    default: null,
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

const basisValue = ref(props.filterBasis);
const yearValue = ref(props.selectedYear);
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

const entityHeaderLabel = computed(() => {
  return basisValue.value === 'perangkat-daerah' ? 'Perangkat Daerah' : 'Bidang Urusan';
});

const isIndikatorMode = computed(() => {
  return props.tableMetricType === 'indikator' || props.currentTable === 'tabel-2';
});

const metricLabel = computed(() => {
  return isIndikatorMode.value ? 'Indikator Program' : 'Program';
});

const metricLabelLower = computed(() => {
  return isIndikatorMode.value ? 'indikator program' : 'program';
});

const applyFilters = () => {
  router.get(
    route('resume.index'),
    {
      view: props.currentView,
      table: props.currentTable,
      basis: basisValue.value,
      year: yearValue.value,
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

  const rpjmdMap = buildUniqueProgramMap(rpjmdItems);
  const renstraMap = buildUniqueProgramMap(renstraItems);
  const rkpdMap = buildUniqueProgramMap(rkpdItems);

  const matchedKeys = [];

  const pushIfMissing = (key) => {
    if (key && !matchedKeys.includes(key)) {
      matchedKeys.push(key);
    }
  };

  rpjmdMap.forEach((_, key) => {
    if (renstraMap.has(key) || rkpdMap.has(key)) {
      pushIfMissing(key);
    }
  });

  renstraMap.forEach((_, key) => {
    if (rkpdMap.has(key)) {
      pushIfMissing(key);
    }
  });

  const rowKeys = matchedKeys.length > 0 ? matchedKeys : [''];
  const rows = [];

  for (let index = 0; index < rowKeys.length; index += 1) {
    const key = rowKeys[index];
    const rpjmdItem = rpjmdMap.get(key) ?? null;
    const renstraItem = renstraMap.get(key) ?? null;
    const rkpdItem = rkpdMap.get(key) ?? null;

    rows.push({
      rpjmdName: rpjmdItem?.nama ?? '',
      rpjmdTarget: rpjmdItem ? formatIndicatorTarget(rpjmdItem) : '',
      renstraName: renstraItem?.nama ?? '',
      renstraTarget: renstraItem ? formatIndicatorTarget(renstraItem) : '',
      rkpdName: rkpdItem?.nama ?? '',
      rkpdTarget: rkpdItem ? formatIndicatorTarget(rkpdItem) : '',
      statusRpjmdRenstra: getStatusByKey(rpjmdItem, renstraMap),
      statusRpjmdRkpd: getStatusByKey(rpjmdItem, rkpdMap),
      statusRenstraRkpd: getStatusByKey(renstraItem, rkpdMap),
    });
  }

  return rows;
};

const getAlignedAnggaranRows = (row) => {
  const rpjmdItems = getUniquePrograms(row?.rpjmd_programs ?? []);
  const renstraItems = getUniquePrograms(row?.renstra_programs ?? []);
  const rkpdItems = getUniquePrograms(row?.rkpd_programs ?? []);

  const rpjmdMap = buildUniqueProgramMap(rpjmdItems);
  const renstraMap = buildUniqueProgramMap(renstraItems);
  const rkpdMap = buildUniqueProgramMap(rkpdItems);

  const matchedKeys = [];

  const pushIfMissing = (key) => {
    if (key && !matchedKeys.includes(key)) {
      matchedKeys.push(key);
    }
  };

  rpjmdMap.forEach((_, key) => {
    if (renstraMap.has(key) || rkpdMap.has(key)) {
      pushIfMissing(key);
    }
  });

  renstraMap.forEach((_, key) => {
    if (rkpdMap.has(key)) {
      pushIfMissing(key);
    }
  });

  const rowKeys = matchedKeys.length > 0 ? matchedKeys : [''];
  const rows = [];

  for (let index = 0; index < rowKeys.length; index += 1) {
    const key = rowKeys[index];
    const rpjmdItem = rpjmdMap.get(key) ?? null;
    const renstraItem = renstraMap.get(key) ?? null;
    const rkpdItem = rkpdMap.get(key) ?? null;

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

  if (props.currentTable.startsWith('tabel-')) {
    return props.currentTable.replace('tabel-', 'Tabel ');
  }

  return props.currentTable;
});
</script>
