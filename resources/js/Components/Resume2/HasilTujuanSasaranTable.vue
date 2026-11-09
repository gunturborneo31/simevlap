<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th>No</th>
        <th>Tujuan</th>
        <th>Sasaran</th>
        <th>Indikator</th>
        <th>Satuan</th>
        <th>Target RPJMD</th>
        <th>Target RKPD</th>
        <th>Capaian Tahun {{ tahun }}</th>
      </tr>
      <tr>
        <th v-for="n in 8" :key="n">({{ n }})</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="tujuan in data.tujuan" :key="tujuan.no">
        <template v-for="(sasaran, sIndex) in tujuan.sasaran" :key="sasaran.nama">
          <tr v-for="(indikator, iIndex) in sasaran.indikator" :key="indikator.nama">
            <template v-if="sIndex === 0 && iIndex === 0">
              <td :rowspan="indikatorCount(tujuan)">{{ tujuan.no }}</td>
              <td :rowspan="indikatorCount(tujuan)" class="text-left">{{ tujuan.nama }}</td>
            </template>
            <td v-if="iIndex === 0" :rowspan="sasaran.indikator.length" class="text-left">{{ sasaran.nama }}</td>
            <td class="text-left">{{ indikator.nama }}</td>
            <td>{{ indikator.satuan ?? '-' }}</td>
            <td>{{ formatAngka(indikator.target_rpjmd) }}</td>
            <td>{{ formatAngka(indikator.target_rkpd) }}</td>
            <td>{{ formatAngka(indikator.capaian) }}</td>
          </tr>
        </template>
      </template>
    </tbody>
  </table>
</template>

<script setup>
import { formatAngka } from '@/utils/format';

defineProps({
  data: { type: Object, required: true },
  tahun: { type: Number, default: null },
  tw: { type: Number, default: null },
});

const indikatorCount = (tujuan) => tujuan.sasaran.reduce((sum, s) => sum + s.indikator.length, 0);
</script>
