<template>
  <table class="r2-table">
    <thead>
      <tr>
        <th rowspan="2" class="w-[2px] px-2">No</th>
        <th rowspan="2">Bidang Urusan / Perangkat Daerah</th>
        <th rowspan="2" class="px-7">Program/Kegiatan/Sub Kegiatan</th>
        <th class="px-7">RKPD/Renja (Tahun {{ tahun }})</th>
        <th class="px-7">APBD (Tahun {{ tahun }})</th>
        <th colspan="2" class="px-7">Konsistensi RKPD/Renja - APBD</th>
        <th colspan="2" class="px-7">Konsistensi RPJMD - RKPD/Renja</th>
        <th colspan="2" class="px-7">Konsistensi Renstra - RKPD/Renja</th>
      </tr>
      <tr>
        <th>Pagu Anggaran</th>
        <th>Pagu Anggaran</th>
        <template v-for="key in selisihKeys" :key="key">
          <th>Selisih Pagu Anggaran</th>
          <th>Status</th>
        </template>
      </tr>
      <tr>
        <th v-for="n in 11" :key="n">({{ n }})</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in data.groups" :key="group.no">
        <tr v-for="(line, index) in group.lines" :key="index" :class="rowClass[line.level]">
          <template v-if="index === 0">
            <td :rowspan="group.lines.length">{{ group.no }}</td>
            <td :rowspan="group.lines.length" class="text-left">{{ group.entitas }}</td>
          </template>
          <td class="text-left" :class="indentClass[line.level]">
            <span class="text-gray-500">{{ line.kode }}</span> {{ line.nama }}
          </td>
          <td><RupiahCell :value="line.rkpd" /></td>
          <td><RupiahCell :value="line.apbd" /></td>
          <template v-for="key in selisihKeys" :key="key">
            <template v-if="line.selisih[key] === null">
              <td>-</td>
              <td>-</td>
            </template>
            <template v-else>
              <td><RupiahCell :value="line.selisih[key]" /></td>
              <td :class="line.selisih[key] === 0 ? 'text-green-700' : 'text-red-600'">
                {{ line.selisih[key] === 0 ? 'Sama' : 'Tidak Sama' }}
              </td>
            </template>
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
  entityLabel: { type: String, default: '' },
  tahun: { type: Number, default: null },
});

const selisihKeys = ['rkpd_apbd', 'rpjmd_rkpd', 'renstra_rkpd'];
const rowClass = { program: 'font-semibold', kegiatan: '', sub_kegiatan: 'text-gray-700' };
const indentClass = { program: '', kegiatan: 'pl-4', sub_kegiatan: 'pl-8' };
</script>
