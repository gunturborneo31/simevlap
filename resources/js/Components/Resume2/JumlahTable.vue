<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th rowspan="2" class="w-[2px]">No</th>
        <th rowspan="2" class="capitalize">{{ entityLabel }}</th>
        <th v-for="doc in documents" :key="doc.key">{{ doc.label }}</th>
        <th v-for="cmp in comparisons" :key="cmp.key" colspan="2">{{ cmp.label }}</th>
      </tr>
      <tr>
        <th v-for="doc in documents" :key="doc.key">Jumlah {{ unit }}</th>
        <template v-for="cmp in comparisons" :key="cmp.key">
          <th>Jumlah {{ unit }} Yang Sama</th>
          <th>Jumlah {{ unit }} Yang Tidak Sama</th>
        </template>
      </tr>
      <tr>
        <th v-for="n in columnCount" :key="n">({{ n }})</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="row in data.rows" :key="row.no">
        <td>{{ row.no }}</td>
        <td class="text-left">{{ row.entitas }}</td>
        <td v-for="key in cellKeys" :key="key" class="r2-click" @click="show(row, key)">
          {{ row.cells[key].length }}
        </td>
      </tr>
      <tr v-if="!data.rows.length">
        <td :colspan="columnCount">Tidak ada data yang ditemukan.</td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: { type: Object, required: true },
  entityLabel: { type: String, required: true },
  unit: { type: String, required: true },
  documents: { type: Array, required: true },
  comparisons: { type: Array, required: true },
  tahun: { type: Number, default: null },
});

const emit = defineEmits(['show-list']);

const cellKeys = computed(() => [
  ...props.documents.map((d) => d.key),
  ...props.comparisons.flatMap((c) => [`${c.key}_sama`, `${c.key}_beda`]),
]);
const columnCount = computed(() => 2 + cellKeys.value.length);

function show(row, key) {
  emit('show-list', { title: `Rincian ${props.unit} - ${row.entitas}`, items: row.cells[key] });
}
</script>
