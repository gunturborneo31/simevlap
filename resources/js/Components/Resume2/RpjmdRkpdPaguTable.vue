<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th rowspan="2" class="w-[2px] px-2">No</th>
        <th rowspan="2" class="capitalize">{{ entityLabel }}</th>
        <th rowspan="2" class="px-7">Program</th>
        <th class="px-7">RPJMD (Tahun {{ tahun }})</th>
        <th class="px-7">Renstra (Tahun {{ tahun }})</th>
        <th class="px-7">RKPD (Tahun {{ tahun }})</th>
        <th colspan="2" class="px-7">Konsistensi RPJMD - Renstra</th>
        <th colspan="2" class="px-7">Konsistensi RPJMD - RKPD/Renja</th>
        <th colspan="2" class="px-7">Konsistensi Renstra - RKPD/Renja</th>
      </tr>
      <tr>
        <th v-for="doc in 3" :key="`p${doc}`">Pagu Anggaran</th>
        <template v-for="key in selisihKeys" :key="key">
          <th>Selisih Anggaran</th>
          <th>Status</th>
        </template>
      </tr>
      <tr>
        <th v-for="n in 12" :key="n">({{ n }})</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in data.groups" :key="group.no">
        <tr v-for="(program, pIndex) in group.programs" :key="program.nama">
          <template v-if="pIndex === 0">
            <td :rowspan="group.programs.length">{{ group.no }}</td>
            <td :rowspan="group.programs.length" class="text-left">{{ group.entitas }}</td>
          </template>
          <td class="text-left">{{ program.nama }}</td>
          <td><RupiahCell :value="program.rpjmd" /></td>
          <td><RupiahCell :value="program.renstra" /></td>
          <td><RupiahCell :value="program.rkpd" /></td>
          <template v-for="key in selisihKeys" :key="key">
            <td><RupiahCell :value="program.selisih[key]" /></td>
            <td :class="program.selisih[key] === 0 ? 'text-green-700' : 'text-red-600'">
              {{ program.selisih[key] === 0 ? 'Sama' : 'Tidak Sama' }}
            </td>
          </template>
        </tr>
      </template>
    </tbody>
  </table>
</template>

<script setup>
import RupiahCell from './RupiahCell.vue';

defineProps({
  data: { type: Object, required: true },
  entityLabel: { type: String, required: true },
  tahun: { type: Number, default: null },
});

const selisihKeys = ['rpjmd_renstra', 'rpjmd_rkpd', 'renstra_rkpd'];
</script>
