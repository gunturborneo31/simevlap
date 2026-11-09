<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th>No</th>
        <th>{{ groupLabel }}</th>
        <th>Program</th>
        <th>Faktor Pendorong</th>
        <th>Faktor Penghambat</th>
        <th>Tindak Lanjut</th>
        <th>Unit/Perangkat Daerah</th>
      </tr>
      <tr>
        <th v-for="n in 7" :key="n">({{ n }})</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in data.groups" :key="group.no">
        <tr v-for="(program, index) in group.programs" :key="index">
          <template v-if="index === 0">
            <td :rowspan="group.programs.length">{{ group.no }}</td>
            <td :rowspan="group.programs.length" class="min-w-[200px] text-left">{{ group.nama }}</td>
          </template>
          <td class="min-w-[220px] text-left">{{ program.nama }}</td>
          <td v-for="field in fields" :key="field" class="min-w-[220px] text-left">
            <template v-if="asList(program[field]).length > 1">
              <ul class="list-disc pl-4">
                <li v-for="(text, i) in asList(program[field])" :key="i">{{ text }}</li>
              </ul>
            </template>
            <template v-else>{{ asList(program[field])[0] ?? '-' }}</template>
          </td>
          <td class="min-w-[180px] text-left">{{ program.opd }}</td>
        </tr>
      </template>
      <tr v-if="!data.groups.length">
        <td colspan="7">Tidak ada data yang ditemukan.</td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
defineProps({
  data: { type: Object, required: true },
  groupLabel: { type: String, required: true },
});

const fields = ['pendorong', 'penghambat', 'tindak_lanjut'];

// Data aplikasi berupa daftar catatan per program; data dummy lama berupa satu teks ("-" = kosong).
const asList = (value) => {
  if (Array.isArray(value)) return value;
  return value && value !== '-' ? [value] : [];
};
</script>
