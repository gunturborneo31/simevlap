<template>
  <AppLayout
    title="IKU"
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Dokumen', href: '/data-dasar/dokumen' },
      { label: 'IKU', href: route('iku.index') }
    ]"
  >
    <div class="bg-white rounded-2xl shadow-md p-6 mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
        <h3 class="text-lg font-bold text-gray-800 mb-2 sm:mb-0">Daftar IKU</h3>
        <div class="flex flex-1 items-center gap-2 sm:justify-end">
          <form class="w-full sm:w-auto" @submit.prevent="cari">
            <input v-model="search" type="text" class="w-full sm:w-80 px-5 py-2 rounded-lg border-2 border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none transition text-sm" placeholder="Cari indikator..." />
          </form>
          <Link :href="route('iku.create')" class="px-5 py-2 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition whitespace-nowrap">+ Tambah</Link>
        </div>
      </div>
      <div class="overflow-x-auto rounded-xl border border-emerald-100">
        <table class="min-w-full text-sm bg-white">
          <thead class="bg-gray-50 border-b border-emerald-100">
            <tr>
              <th class="px-4 py-3 text-center font-bold text-gray-700">No</th>
              <th class="px-4 py-3 font-bold text-gray-700">Indikator</th>
              <th class="px-4 py-3 font-bold text-gray-700">Satuan</th>
              <th class="px-4 py-3 font-bold text-gray-700">Capaian 2024</th>
              <th class="px-4 py-3 font-bold text-gray-700">2025</th>
              <th class="px-4 py-3 font-bold text-gray-700">2026</th>
              <th class="px-4 py-3 font-bold text-gray-700">2027</th>
              <th class="px-4 py-3 font-bold text-gray-700">2028</th>
              <th class="px-4 py-3 font-bold text-gray-700">2029</th>
              <th class="px-4 py-3 font-bold text-gray-700">2030</th>
              <th class="px-4 py-3 text-center font-bold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <TableEmptyState v-if="ikus.data.length === 0" :colspan="11" :showReset="!!search" @reset="reset" />
            <tr v-for="(iku, i) in ikus.data" :key="iku.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-2 text-center">{{ ikus.from + i }}</td>
              <td class="px-4 py-2">{{ iku.indikator }}</td>
              <td class="px-4 py-2">{{ iku.satuan }}</td>
              <td class="px-4 py-2">{{ iku.capaian_2024 }}</td>
              <td class="px-4 py-2">{{ iku.target_2025 }}</td>
              <td class="px-4 py-2">{{ iku.target_2026 }}</td>
              <td class="px-4 py-2">{{ iku.target_2027 }}</td>
              <td class="px-4 py-2">{{ iku.target_2028 }}</td>
              <td class="px-4 py-2">{{ iku.target_2029 }}</td>
              <td class="px-4 py-2">{{ iku.target_2030 }}</td>
              <td class="px-4 py-2 text-center">
                <Link :href="route('iku.edit', iku.id)" class="inline-block px-3 py-1 rounded-lg bg-blue-100 text-blue-700 font-medium hover:bg-blue-200 transition mr-2">Edit</Link>
                <button @click="hapus(iku.id)" class="inline-block px-3 py-1 rounded-lg bg-red-100 text-red-700 font-medium hover:bg-red-200 transition">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="ikus.links" />
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import TableEmptyState from '@/Components/TableEmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
const props = defineProps({ ikus: Object });
const search = ref('');
if (typeof window !== 'undefined') {
  const url = new URL(window.location.href);
  search.value = url.searchParams.get('search') || '';
}
function cari() {
  router.get(route('iku.index'), { search: search.value }, { preserveState: true, replace: true });
}
function reset() {
  search.value = '';
  cari();
}
function hapus(id) {
  if (confirm('Yakin ingin menghapus data ini?')) {
    router.delete(route('iku.destroy', id));
  }
}
</script>
