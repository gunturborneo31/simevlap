<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th rowspan="2" class="w-[2px]">No</th>
        <th rowspan="2" class="capitalize">{{ entityLabel }}</th>
        <th rowspan="2">Program</th>
        <th colspan="2">RPJMD (Tahun {{ tahun }})</th>
        <th colspan="2">Renstra (Tahun {{ tahun }})</th>
        <th colspan="2">RKPD (Tahun {{ tahun }})</th>
        <th rowspan="2">Status Konsistensi RPJMD - Renstra</th>
        <th rowspan="2">Status Konsistensi RPJMD - RKPD/Renja</th>
        <th rowspan="2">Status Konsistensi Renstra - RKPD/Renja</th>
      </tr>
      <tr>
        <template v-for="doc in docs" :key="doc">
          <th>Indikator</th>
          <th>Target</th>
        </template>
      </tr>
      <tr>
        <th v-for="n in 12" :key="n">({{ n }})</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in data.groups" :key="group.no">
        <template v-for="(program, pIndex) in group.programs" :key="program.nama">
          <tr v-for="(line, lIndex) in program.lines" :key="lIndex">
            <template v-if="pIndex === 0 && lIndex === 0">
              <td :rowspan="lineCount(group)">{{ group.no }}</td>
              <td :rowspan="lineCount(group)" class="text-left">{{ group.entitas }}</td>
            </template>
            <td v-if="lIndex === 0" :rowspan="program.lines.length" class="text-left">{{ program.nama }}</td>
            <template v-for="doc in docs" :key="doc">
              <td class="min-w-[250px] max-w-[300px] text-left">{{ line[doc]?.indikator ?? 'Tidak Ada' }}</td>
              <td class="whitespace-nowrap">{{ formatTarget(line[doc]) }}</td>
            </template>
            <td v-for="key in statusKeys" :key="key" :class="statusClass(line.status[key])">{{ line.status[key] }}</td>
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
  entityLabel: { type: String, required: true },
  tahun: { type: Number, default: null },
});

const docs = ['rpjmd', 'renstra', 'rkpd'];
const statusKeys = ['rpjmd_renstra', 'rpjmd_rkpd', 'renstra_rkpd'];

const lineCount = (group) => group.programs.reduce((sum, p) => sum + p.lines.length, 0);

// Target bisa angka atau teks (mis. "BB"); satuan ditampilkan bila ada dan belum tercantum di target.
function formatTarget(item) {
  if (!item || item.target === null || item.target === undefined || item.target === '') return '-';
  const target = formatAngka(item.target);
  return item.satuan && !String(target).includes(item.satuan) ? `${target} ${item.satuan}` : target;
}
const statusClass = (status) => (status === 'Sama' ? 'text-green-700' : 'text-red-600');
</script>
