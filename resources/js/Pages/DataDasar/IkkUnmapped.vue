<template>
  <AppLayout
    title="Validasi IKK Belum Terhubung OPD"
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Bank Data', href: route('data-dasar.bank-data') },
      { label: 'Validasi IKK Belum Terhubung OPD', href: route('data-dasar.ikk-unmapped.index') },
    ]"
  >
    <section class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between gap-3">
        <h3 class="text-base font-semibold text-gray-700">Validasi IKK Belum Terhubung OPD</h3>
        <div class="flex w-full max-w-xl items-center gap-2">
          <input
            v-model="search"
            @keyup.enter="applySearch"
            type="text"
            placeholder="Cari indikator, urusan, satuan..."
            class="input-base"
          />
          <button
            @click="applySearch"
            class="shrink-0 rounded-lg border border-amber-200 px-4 py-2 text-sm text-amber-700 hover:bg-amber-50"
          >
            Cari
          </button>
        </div>
      </div>

      <div class="overflow-hidden rounded-lg border border-gray-200">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-4 py-3 text-center font-semibold text-gray-600 w-12">No</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Urusan 1</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Urusan 2</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Indikator IKK</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Satuan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Saran OPD</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Pilih OPD</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="rows.data.length === 0">
              <td colspan="8" class="px-4 py-8 text-center text-gray-400">Tidak ada data IKK yang belum terhubung OPD.</td>
            </tr>
            <tr v-for="(item, i) in rows.data" :key="item.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-center text-gray-500">{{ Number(rows.from || 1) + i }}</td>
              <td class="px-4 py-3 text-gray-700">{{ item.urusan_1 || '-' }}</td>
              <td class="px-4 py-3 text-gray-700">{{ item.urusan_2 || '-' }}</td>
              <td class="px-4 py-3 text-gray-900">{{ item.uraian }}</td>
              <td class="px-4 py-3 text-gray-700">{{ item.satuan }}</td>
              <td class="px-4 py-3 text-gray-700">{{ item.suggested_opd_name || '-' }}</td>
              <td class="px-4 py-3">
                <select v-model="selectedOpd[item.id]" class="input-base">
                  <option :value="null">Pilih OPD...</option>
                  <option v-for="opd in opds" :key="opd.id" :value="opd.id">{{ opd.nama }}</option>
                </select>
              </td>
              <td class="px-4 py-3 text-center">
                <button
                  @click="assign(item.id)"
                  :disabled="!selectedOpd[item.id] || loadingId === item.id"
                  class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {{ loadingId === item.id ? 'Menyimpan...' : 'Simpan' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination :links="rows.links" @navigate="navigatePage" />
    </section>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  rows: Object,
  opds: Array,
  filters: Object,
});

const search = ref(props.filters?.search ?? '');
const loadingId = ref(null);

const selectedOpd = reactive(
  Object.fromEntries(
    (props.rows?.data ?? []).map((item) => [item.id, item.suggested_opd_id ?? null])
  )
);

function applySearch() {
  router.get(
    route('data-dasar.ikk-unmapped.index'),
    { search: search.value },
    { preserveState: true, replace: true, preserveScroll: true }
  );
}

function navigatePage(url) {
  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}

function assign(indikatorId) {
  const opdId = selectedOpd[indikatorId];
  if (!opdId) return;

  loadingId.value = indikatorId;

  router.put(
    route('data-dasar.ikk-unmapped.assign-opd', { indikator: indikatorId }),
    { opd_id: opdId },
    {
      preserveScroll: true,
      onFinish: () => {
        loadingId.value = null;
      },
    }
  );
}
</script>

<style scoped>
@reference "../../../css/app.css";

.input-base {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500;
}
</style>
