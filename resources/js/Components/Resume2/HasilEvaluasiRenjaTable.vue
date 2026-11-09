<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th rowspan="3">No</th>
        <th rowspan="3">Sasaran</th>
        <th rowspan="3">Program/Kegiatan</th>
        <th rowspan="3">Indikator Kinerja Program/Kegiatan</th>
        <th rowspan="2" colspan="2">Target Renstra Perangkat Daerah pada Tahun {{ tahun }}</th>
        <th rowspan="2" colspan="2">Realisasi Capaian Kinerja Renstra Perangkat Daerah sampai dengan tahun lalu</th>
        <th rowspan="2" colspan="2">Target Kinerja dan Anggaran Renja Perangkat Daerah Tahun {{ tahun }}</th>
        <th colspan="8">Realisasi Kinerja RKPD/Renja pada Triwulan</th>
        <th rowspan="2" colspan="2">Realisasi Capaian Kinerja dan Anggaran Renja Perangkat Daerah Tahun {{ tahun }}</th>
        <th rowspan="2" colspan="2">Realisasi Kinerja dan Anggaran Renstra Perangkat Daerah sampai dengan Tahun {{ tahun }}</th>
        <th rowspan="2" colspan="2">Tingkat Capaian Kinerja dan Realisasi Anggaran Renstra Perangkat Daerah sampai dengan Tahun {{ tahun }}</th>
        <th rowspan="3">Unit Perangkat Daerah Penanggung Jawab</th>
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
      <tr v-for="(line, index) in data.lines" :key="index" :class="line.level === 'program' ? 'font-semibold' : ''">
        <td>{{ index + 1 }}</td>
        <td v-if="index === 0" :rowspan="data.lines.length" class="min-w-[200px] text-left">{{ data.sasaran ?? '-' }}</td>
        <td class="min-w-[220px] text-left" :class="line.level === 'kegiatan' ? 'pl-4' : ''">{{ line.nama }}</td>
        <td class="min-w-[200px] text-left">{{ line.indikator }}</td>
        <EvaluasiCells :line="line" />
        <td v-if="index === 0" :rowspan="data.lines.length" class="min-w-[200px] text-left">{{ data.opd }}</td>
      </tr>
      <tr v-if="!data.lines.length">
        <td colspan="25">Tidak ada data yang ditemukan.</td>
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
  ...Array.from({ length: 20 }, (_, i) => String(i + 1)),
  '21 = 7 + 19',
  '22 = 8 + 20',
  '23 = 21/5 x 100%',
  '24 = 22/6 x 100%',
  '25',
];
</script>
