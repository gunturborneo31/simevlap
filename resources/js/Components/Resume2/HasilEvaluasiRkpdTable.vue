<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th rowspan="3">No</th>
        <th rowspan="3">{{ data.groupLabel ?? 'Sasaran' }}</th>
        <th rowspan="3">Kode</th>
        <th rowspan="3">Urusan/Bidang Urusan Pemerintahan Daerah/Program</th>
        <th rowspan="3">Indikator Kinerja Program</th>
        <th rowspan="2" colspan="2">Target RPJMD pada Tahun {{ tahun }}</th>
        <th rowspan="2" colspan="2">Realisasi Kinerja RPJMD sampai dengan tahun lalu</th>
        <th rowspan="2" colspan="2">Target Kinerja dan Anggaran RKPD Tahun {{ tahun }}</th>
        <th colspan="8">Realisasi Kinerja RKPD/Renja pada Triwulan</th>
        <th rowspan="2" colspan="2">Realisasi Capaian Kinerja dan Anggaran RKPD Tahun {{ tahun }}</th>
        <th rowspan="2" colspan="2">Realisasi Kinerja dan Anggaran RPJMD sampai dengan Tahun {{ tahun }}</th>
        <th rowspan="2" colspan="2">Tingkat Capaian Kinerja dan Realisasi Anggaran RPJMD sampai dengan Tahun {{ tahun }}</th>
        <th rowspan="3">Perangkat Daerah Penanggung Jawab</th>
      </tr>
      <tr>
        <th v-for="tw in ['I', 'II', 'III', 'IV']" :key="tw" colspan="2">Triwulan {{ tw }}</th>
      </tr>
      <tr>
        <template v-for="n in 9" :key="n">
          <th>Kinerja</th>
          <th>Rp</th>
        </template>
        <th>Kinerja (%)</th>
        <th>Rp (%)</th>
      </tr>
      <tr>
        <th v-for="label in numbering" :key="label">({{ label }})</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="sasaran in data.sasaran" :key="sasaran.no">
        <template v-for="(program, pIndex) in sasaran.programs" :key="pIndex">
          <tr v-for="(line, iIndex) in program.indikator" :key="iIndex">
            <template v-if="pIndex === 0 && iIndex === 0">
              <td :rowspan="lineCount(sasaran)">{{ sasaran.no }}</td>
              <td :rowspan="lineCount(sasaran)" class="min-w-[200px] text-left">{{ sasaran.nama }}</td>
            </template>
            <template v-if="iIndex === 0">
              <td :rowspan="program.indikator.length" class="whitespace-nowrap">{{ program.kode }}</td>
              <td :rowspan="program.indikator.length" class="min-w-[220px] text-left">{{ program.nama }}</td>
            </template>
            <td class="min-w-[200px] text-left">{{ line.indikator }}</td>
            <EvaluasiCells :line="line" />
            <td v-if="iIndex === 0" :rowspan="program.indikator.length" class="min-w-[200px] text-left">{{ program.opd }}</td>
          </tr>
        </template>
      </template>
      <tr v-if="!data.sasaran.length">
        <td colspan="26">Tidak ada data yang ditemukan.</td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
import EvaluasiCells from './EvaluasiCells.vue';

defineProps({
  data: { type: Object, required: true },
  tahun: { type: Number, default: null },
  tw: { type: Number, default: null },
});

const numbering = [
  ...Array.from({ length: 21 }, (_, i) => String(i + 1)),
  '22 = 8 + 20',
  '23 = 9 + 21',
  '24 = 22/6 x 100%',
  '25 = 23/7 x 100%',
  '26',
];

const lineCount = (sasaran) => sasaran.programs.reduce((sum, p) => sum + p.indikator.length, 0);
</script>
