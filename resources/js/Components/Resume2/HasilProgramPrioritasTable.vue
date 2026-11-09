<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th rowspan="2">No</th>
        <th rowspan="2">Program Prioritas</th>
        <th rowspan="2">Indikator Kinerja (outcome)</th>
        <th rowspan="2">Kondisi Awal (Tahun {{ data.tahunAwal ?? '-' }})</th>
        <th :colspan="years.length">Pagu RPJMD</th>
        <th :colspan="years.length">Target RPJMD</th>
        <th colspan="2">Capaian Kinerja</th>
        <th colspan="2">Rata - Rata Capaian Kinerja (Tahun {{ data.tahunTarget }} TW {{ tw }})</th>
        <th rowspan="2">PD Penanggung Jawab Program</th>
      </tr>
      <tr>
        <th v-for="year in years" :key="`p${year}`">Target {{ year }}</th>
        <th v-for="year in years" :key="`t${year}`">Target {{ year }}</th>
        <th>Rp</th>
        <th>Capaian Kinerja</th>
        <th>Rp (%)</th>
        <th>Capaian Kinerja (%)</th>
      </tr>
      <tr>
        <th v-for="n in columnCount" :key="n">({{ n }})</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="opd in data.opds" :key="opd.opd">
        <template v-for="(program, pIndex) in opd.programs" :key="program.no">
          <tr v-for="(indikator, iIndex) in program.indikator" :key="iIndex">
            <template v-if="iIndex === 0">
              <td :rowspan="program.indikator.length">{{ program.no }}</td>
              <td :rowspan="program.indikator.length" class="min-w-[220px] text-left">{{ program.nama }}</td>
            </template>
            <td class="min-w-[220px] text-left">{{ indikator.nama }}</td>
            <td>{{ formatAngka(indikator.kondisi_awal) }}</td>
            <template v-if="iIndex === 0">
              <td v-for="year in years" :key="`p${year}`" :rowspan="program.indikator.length">
                <RupiahCell :value="program.pagu[year]" />
              </td>
            </template>
            <td v-for="year in years" :key="`t${year}`">{{ formatAngka(indikator.target[year]) }}</td>
            <td v-if="iIndex === 0" :rowspan="program.indikator.length"><RupiahCell :value="program.realisasi_rp" /></td>
            <td>{{ formatAngka(indikator.capaian) }}</td>
            <td v-if="iIndex === 0" :rowspan="program.indikator.length">{{ formatAngka(program.rata_rp) }}</td>
            <td>{{ formatAngka(indikator.rata_kinerja) }}</td>
            <td v-if="pIndex === 0 && iIndex === 0" :rowspan="indikatorCount(opd)" class="min-w-[180px] text-left">{{ opd.opd }}</td>
          </tr>
        </template>
      </template>
      <tr v-if="!data.opds.length">
        <td :colspan="columnCount">Tidak ada data yang ditemukan.</td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
import { computed } from 'vue';
import { formatAngka } from '@/utils/format';
import RupiahCell from './RupiahCell.vue';

const props = defineProps({
  data: { type: Object, required: true },
  tahun: { type: Number, default: null },
  tw: { type: Number, default: null },
});

// Tahun target mengikuti periode di data (mis. 2026-2030); dummy lama memakai 2022-2026.
const years = computed(() => props.data.years ?? [2022, 2023, 2024, 2025, 2026]);
const columnCount = computed(() => 9 + years.value.length * 2);
const indikatorCount = (opd) => opd.programs.reduce((sum, p) => sum + p.indikator.length, 0);
</script>
