<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th rowspan="2" class="w-[2px] px-2">No</th>
        <th rowspan="2" class="capitalize">{{ entityLabel }}</th>
        <th rowspan="2" class="px-7">{{ unit }}</th>
        <th :colspan="withTarget ? 2 : 1" class="px-7">RKPD/Renja (Tahun {{ tahun }})</th>
        <th :colspan="withTarget ? 2 : 1" class="px-7">APBD (Tahun {{ tahun }})</th>
        <th rowspan="2" class="px-7">Status Konsistensi RKPD/Renja - APBD</th>
      </tr>
      <tr>
        <template v-for="doc in docs" :key="doc">
          <th>Indikator {{ unit }}</th>
          <th v-if="withTarget">Target</th>
        </template>
      </tr>
      <tr>
        <th v-for="n in columnCount" :key="n">({{ n }})</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in data.groups" :key="group.no">
        <template v-for="(item, iIndex) in group.items" :key="iIndex">
          <tr v-for="(line, lIndex) in item.lines" :key="lIndex">
            <template v-if="iIndex === 0 && lIndex === 0">
              <td :rowspan="lineCount(group)">{{ group.no }}</td>
              <td :rowspan="lineCount(group)" class="text-left">{{ group.entitas }}</td>
            </template>
            <td v-if="lIndex === 0" :rowspan="item.lines.length" class="min-w-[220px] text-left">{{ item.nama }}</td>
            <template v-for="doc in docs" :key="doc">
              <td class="min-w-[250px] text-left">{{ line[doc]?.indikator ?? 'Tidak Ada' }}</td>
              <td v-if="withTarget" class="whitespace-nowrap">{{ formatTarget(line[doc]) }}</td>
            </template>
            <td :class="line.status === 'Sama' ? 'text-green-700' : 'text-red-600'">{{ line.status }}</td>
          </tr>
        </template>
      </template>
      <tr v-if="!data.groups.length">
        <td :colspan="columnCount">Tidak ada data yang ditemukan.</td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
import { computed } from 'vue';
import { formatAngka } from '@/utils/format';

const props = defineProps({
  data: { type: Object, required: true },
  entityLabel: { type: String, required: true },
  unit: { type: String, required: true },
  withTarget: { type: Boolean, default: false },
  tahun: { type: Number, default: null },
});

const docs = ['rkpd', 'apbd'];
const columnCount = computed(() => (props.withTarget ? 8 : 6));

const lineCount = (group) => group.items.reduce((sum, item) => sum + item.lines.length, 0);

// Target bisa angka atau teks; satuan ditampilkan bila ada dan belum tercantum di target.
function formatTarget(item) {
  if (!item || item.target === null || item.target === undefined || item.target === '') return item ? '-' : 'Tidak Ada';
  const target = formatAngka(item.target);
  return item.satuan && !String(target).includes(item.satuan) ? `${target} ${item.satuan}` : target;
}
</script>
