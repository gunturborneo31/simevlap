<template>
  <div>
    <h1 class="h4 mb-3">Daftar Program Prioritas</h1>
    <div class="card shadow-sm rounded-lg mb-4 p-3">
      <form class="row g-2 align-items-end" @submit.prevent="submitFilter">
        <div class="col-md-4">
          <label class="form-label">OPD</label>
          <select v-model="filters.opd_id" class="form-select rounded-lg">
            <option value="">Semua OPD</option>
            <option v-for="opd in opds" :key="opd.id" :value="opd.id">{{ opd.nama }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Cari</label>
          <input v-model="filters.search" type="text" class="form-control rounded-lg" placeholder="Cari nama/kode/deskripsi...">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary rounded-lg w-100" type="submit">Filter</button>
        </div>
        <div class="col-md-2">
          <button class="btn btn-secondary rounded-lg w-100" type="button" @click="resetFilter">Reset</button>
        </div>
      </form>
    </div>
    <div class="table-responsive rounded-lg overflow-hidden">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead>
          <tr>
            <th @click="sort('kode_rek')" style="cursor:pointer">Kode <SortIcon :active="filters.sort==='kode_rek'" :direction="filters.direction" /></th>
            <th @click="sort('nama_rincian')" style="cursor:pointer">NAMA_PROGRAM <SortIcon :active="filters.sort==='nama_rincian'" :direction="filters.direction" /></th>
            <th>OPD</th>
            <th>Deskripsi</th>
            <th @click="sort('created_at')" style="cursor:pointer">Tanggal Input <SortIcon :active="filters.sort==='created_at'" :direction="filters.direction" /></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="program in programs.data" :key="program.id">
            <td>{{ program.kode_rek }}</td>
            <td>{{ program.nama_rincian }}</td>
            <td>{{ program.opd?.nama || '-' }}</td>
            <td>{{ program.deskripsi }}</td>
            <td>{{ formatTanggal(program.created_at) }}</td>
          </tr>
          <tr v-if="programs.data.length === 0">
            <td colspan="5" class="text-center py-4">Tidak ada data yang ditemukan.<br><button v-if="isFiltered" class="btn btn-link" @click="resetFilter">Reset Filter</button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="programs.links" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import SortIcon from '@/Components/SortIcon.vue';
import { formatTanggal } from '@/utils/format';

const props = defineProps({
  programs: Object,
  opds: Array,
  filters: Object,
});

const filters = ref({ ...props.filters });
const opds = props.opds;

const isFiltered = computed(() => {
  return filters.value.opd_id || filters.value.search;
});

function submitFilter() {
  router.get(route('data-dasar.program-prioritas.index'), filters.value, { preserveState: true, preserveScroll: true });
}
function resetFilter() {
  filters.value = { opd_id: '', search: '', sort: 'created_at', direction: 'desc' };
  submitFilter();
}
function sort(field) {
  if (filters.value.sort === field) {
    filters.value.direction = filters.value.direction === 'asc' ? 'desc' : 'asc';
  } else {
    filters.value.sort = field;
    filters.value.direction = 'asc';
  }
  submitFilter();
}
</script>
