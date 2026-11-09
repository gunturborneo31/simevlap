<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th rowspan="2">No</th>
        <th rowspan="2">Program/Kegiatan/Sub Kegiatan RKPD (Tahun {{ tahun }})</th>
        <th rowspan="2">Indikator</th>
        <th rowspan="2">Satuan</th>
        <th rowspan="2">Target (Tahun {{ tahun }})</th>
        <th rowspan="2">Pagu Anggaran (Tahun {{ tahun }}) (Rp)</th>
        <th colspan="2">Realisasi Capaian Kinerja (Tahun {{ tahun }}) TW {{ tw }}</th>
        <th colspan="2">Realisasi Keuangan (Tahun {{ tahun }}) TW {{ tw }}</th>
        <th colspan="2">Predikat</th>
        <th rowspan="2">PD Penanggung Jawab Program</th>
      </tr>
      <tr>
        <th>Capaian Kinerja</th>
        <th>%</th>
        <th>Capaian Keuangan (Rp)</th>
        <th>%</th>
        <th>Kinerja</th>
        <th>Keuangan</th>
      </tr>
      <tr>
        <th v-for="n in 13" :key="n">({{ n }})</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="(group, gIndex) in groups" :key="gIndex">
        <tr v-for="(line, index) in group.lines" :key="index" :class="rowClass[line.level]">
          <td>{{ offsets[gIndex] + index + 1 }}</td>
          <td class="min-w-[260px] text-left" :class="indentClass[line.level]">
            <span class="text-gray-500">{{ line.kode }}</span> {{ line.nama }}
          </td>
          <td class="min-w-[220px] text-left">
            <div v-for="(item, i) in asList(line.indikator)" :key="i">{{ item }}</div>
          </td>
          <td><div v-for="(item, i) in asList(line.satuan)" :key="i">{{ item ?? '-' }}</div></td>
          <td><div v-for="(item, i) in asList(line.target)" :key="i">{{ formatAngka(item) }}</div></td>
          <td><RupiahCell :value="line.pagu" /></td>
          <td>{{ formatAngka(line.capaian) }}</td>
          <td>{{ formatAngka(line.persen_kinerja) }}</td>
          <td><RupiahCell :value="line.realisasi" /></td>
          <td>{{ formatAngka(line.persen_keuangan) }}</td>
          <td :class="predikatClass(line.predikat_kinerja)">{{ line.predikat_kinerja ?? '-' }}</td>
          <td :class="predikatClass(line.predikat_keuangan)">{{ line.predikat_keuangan ?? '-' }}</td>
          <td v-if="index === 0" :rowspan="group.lines.length" class="min-w-[180px] text-left">{{ group.opd }}</td>
        </tr>
      </template>
      <tr v-if="!groups.length">
        <td colspan="13">Tidak ada data yang ditemukan.</td>
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

// Data aplikasi dikelompokkan per perangkat daerah; data dummy lama berupa satu daftar dengan satu OPD.
const groups = computed(() => props.data.groups ?? [{ opd: props.data.opd, lines: props.data.lines ?? [] }]);
const offsets = computed(() => {
  let total = 0;
  return groups.value.map((g) => {
    const start = total;
    total += g.lines.length;
    return start;
  });
});

const asList = (value) => {
  if (Array.isArray(value)) return value.length ? value : ['-'];
  return [value ?? '-'];
};

const rowClass = { program: 'font-semibold', kegiatan: '', sub_kegiatan: 'text-gray-700' };
const indentClass = { program: '', kegiatan: 'pl-4', sub_kegiatan: 'pl-8' };

function predikatClass(predikat) {
  return {
    'Sangat Tinggi': 'text-green-700',
    Tinggi: 'text-green-600',
    Sedang: 'text-amber-600',
    Rendah: 'text-orange-600',
    'Sangat Rendah': 'text-red-600',
  }[predikat];
}
</script>
