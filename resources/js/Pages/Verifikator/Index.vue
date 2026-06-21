<template>
  <AppLayout
    title="Verifikator"
    :breadcrumbs="[
      { label: 'Verifikator', href: route('verifikator.index') }
    ]"
  >
    <section class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-3">
      <Link
        v-for="item in documentTypes"
        :key="item.value"
        :href="route('verifikator.index', { ...filters, document_type: item.value })"
        class="rounded-xl border px-4 py-3 text-center font-semibold transition"
        :class="filters.document_type === item.value
          ? 'border-emerald-400 bg-emerald-50 text-emerald-800'
          : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300'"
      >
        {{ item.label }}
      </Link>
    </section>

    <section class="mb-4 rounded-xl border border-slate-200 bg-white p-4">
      <form class="grid grid-cols-1 gap-3 md:grid-cols-4" @submit.prevent="applyFilters">
        <input v-model="localFilters.search" type="text" class="input-base" placeholder="Cari OPD atau catatan" />

        <input v-model="localFilters.tahun" type="number" class="input-base" min="2020" max="2100" />

        <select v-model="localFilters.status" class="input-base">
          <option value="">Semua status</option>
          <option value="verified">Terverifikasi</option>
          <option value="unverified">Belum diverifikasi</option>
        </select>

        <div class="flex gap-2">
          <button type="submit" class="btn-primary w-full">Terapkan</button>
          <button type="button" class="btn-secondary w-full" @click="resetFilters">Reset</button>
        </div>
      </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
      <div class="overflow-x-auto">
        <table class="w-full table-auto text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr>
              <th class="px-3 py-2 text-left">OPD</th>
              <th class="px-3 py-2 text-left">Triwulan</th>
              <th class="px-3 py-2 text-left">Fisik (%)</th>
              <th class="px-3 py-2 text-left">Keuangan</th>
              <th class="px-3 py-2 text-left">Catatan OPD</th>
              <th class="px-3 py-2 text-left">Status</th>
              <th class="px-3 py-2 text-left">Aksi</th>
              <th class="px-3 py-2 text-left">Catatan Verifikator</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="realisasi.data.length === 0">
              <td colspan="8" class="px-3 py-8 text-center text-slate-500">Tidak ada data realisasi yang ditemukan.</td>
            </tr>
            <tr v-for="row in realisasi.data" :key="row.id" class="border-t border-slate-100 align-top">
              <td class="px-3 py-2">{{ row.opd?.singkatan || row.opd?.nama || '-' }}</td>
              <td class="px-3 py-2">{{ row.triwulan || '-' }}</td>
              <td class="px-3 py-2">{{ row.realisasi_fisik ?? 0 }}</td>
              <td class="px-3 py-2">{{ formatRupiah(row.realisasi_keuangan) }}</td>
              <td class="px-3 py-2">{{ row.catatan || '-' }}</td>
              <td class="px-3 py-2">
                <span
                  class="rounded-full px-2 py-1 text-xs font-medium"
                  :class="row.is_verified
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-amber-100 text-amber-700'"
                >
                  {{ row.is_verified ? 'Terverifikasi' : 'Belum' }}
                </span>
              </td>
              <td class="px-3 py-2">
                <button class="btn-primary" @click="verify(row)">Verifikasi</button>
              </td>
              <td class="px-3 py-2 min-w-[260px]">
                <textarea
                  v-model="notes[row.id]"
                  class="input-base min-h-[72px]"
                  placeholder="Isi catatan verifikator"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="realisasi.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-600">
        <span>Menampilkan {{ realisasi.from }}-{{ realisasi.to }} dari {{ realisasi.total }} data</span>
        <div class="flex gap-2">
          <Link
            v-if="realisasi.prev_page_url"
            :href="realisasi.prev_page_url"
            class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-50"
          >Sebelumnya</Link>
          <Link
            v-if="realisasi.next_page_url"
            :href="realisasi.next_page_url"
            class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-50"
          >Berikutnya</Link>
        </div>
      </div>
    </section>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
  filters: Object,
  documentTypes: Array,
  realisasi: Object,
});

const localFilters = reactive({
  document_type: props.filters.document_type,
  tahun: props.filters.tahun,
  status: props.filters.status,
  search: props.filters.search,
});

const notes = reactive(
  Object.fromEntries((props.realisasi.data || []).map((row) => [row.id, row.catatan_verifikator || '']))
);

function applyFilters() {
  router.get(route('verifikator.index'), localFilters, { preserveState: true, replace: true });
}

function resetFilters() {
  localFilters.status = '';
  localFilters.search = '';
  applyFilters();
}

function verify(row) {
  router.post(route('verifikator.verify', row.id), {
    catatan_verifikator: notes[row.id] || '',
  }, { preserveScroll: true });
}

function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID').format(Number(value || 0));
}
</script>

<style scoped>
@reference "../../../css/app.css";

.input-base {
  @apply w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500;
}

.btn-primary {
  @apply rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700;
}

.btn-secondary {
  @apply rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50;
}
</style>
