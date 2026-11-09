<template>
  <!-- 20 kolom angka evaluasi (kinerja & Rp) yang sama untuk tabel evaluasi RKPD dan Renja. -->
  <template v-for="key in pairKeys" :key="key">
    <td>{{ formatAngka(line[key].k) }}</td>
    <td><RupiahCell :value="line[key].rp" /></td>
  </template>
  <template v-for="(tw, index) in line.tw" :key="`tw${index}`">
    <td>{{ formatAngka(tw.k) }}</td>
    <td><RupiahCell :value="tw.rp" /></td>
  </template>
  <template v-for="key in totalKeys" :key="key">
    <td>{{ formatAngka(line[key].k) }}</td>
    <td><RupiahCell :value="line[key].rp" /></td>
  </template>
  <td>{{ formatAngka(line.tingkat.k) }}</td>
  <td>{{ formatAngka(line.tingkat.rp) }}</td>
</template>

<script setup>
import { formatAngka } from '@/utils/format';
import RupiahCell from './RupiahCell.vue';

defineProps({
  line: { type: Object, required: true },
});

const pairKeys = ['target_periode', 'realisasi_lalu', 'target_tahun'];
const totalKeys = ['realisasi_tahun', 'kumulatif'];
</script>
