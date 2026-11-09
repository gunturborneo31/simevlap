<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th>No</th>
        <th>10 Program Aksi Kepala Daerah</th>
        <th>Capaian Tahun {{ tahun }}</th>
        <th>Capaian Anggaran Tahun {{ tahun }}</th>
        <th>Program Prioritas (Pendukung)</th>
        <th>Indikator Program Prioritas (RPJMD)</th>
        <th>Target Tahun {{ tahun }}</th>
        <th>Satuan</th>
        <th>Capaian Tahun {{ tahun }}</th>
      </tr>
      <tr>
        <th v-for="n in 9" :key="n">({{ n }})</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="aksi in data.aksi" :key="aksi.no">
        <tr v-for="(program, index) in aksi.programs" :key="program.program">
          <template v-if="index === 0">
            <td :rowspan="aksi.programs.length">{{ aksi.no }}</td>
            <td :rowspan="aksi.programs.length" class="min-w-[280px] text-left">{{ aksi.nama }}</td>
            <td :rowspan="aksi.programs.length">{{ aksi.capaian === null ? '-' : `${formatAngka(aksi.capaian)}%` }}</td>
            <td :rowspan="aksi.programs.length"><RupiahCell :value="aksi.anggaran" /></td>
          </template>
          <td class="text-left">{{ program.program ?? '-' }}</td>
          <td class="text-left">{{ program.indikator ?? '-' }}</td>
          <td>{{ formatAngka(program.target) }}</td>
          <td>{{ program.satuan ?? '-' }}</td>
          <td>{{ formatAngka(program.capaian) }}</td>
        </tr>
      </template>
    </tbody>
  </table>
</template>

<script setup>
import { formatAngka } from '@/utils/format';
import RupiahCell from './RupiahCell.vue';

defineProps({
  data: { type: Object, required: true },
  tahun: { type: Number, default: null },
  tw: { type: Number, default: null },
});
</script>
