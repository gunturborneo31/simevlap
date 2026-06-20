<template>
  <AppLayout
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Relasi', href: route('data-dasar.relasi') },
      { label: 'Ringkasan', href: route('data-dasar.relasi.ringkasan') }
    ]"
    :right-info="peraturanLabel"
  >
    <section class="space-y-4">
      <div class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
        <h3 class="text-base font-semibold text-gray-700">Ringkasan Graf Relasi</h3>
        <p class="mt-1 text-xs text-gray-500">
          Ringkasan ini membantu melihat kualitas koneksi antar level data. Klik level untuk langsung mengatur relasi.
        </p>

        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
          <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3">
            <p class="text-xs uppercase tracking-wide text-indigo-700">Total Data</p>
            <p class="mt-1 text-2xl font-semibold text-indigo-900">{{ totals.total_data }}</p>
          </div>
          <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
            <p class="text-xs uppercase tracking-wide text-emerald-700">Linked</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ totals.linked_data }}</p>
          </div>
          <div class="rounded-xl border border-amber-100 bg-amber-50/60 p-3">
            <p class="text-xs uppercase tracking-wide text-amber-700">Unlinked</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ totals.unlinked_data }}</p>
          </div>
          <div class="rounded-xl border border-cyan-100 bg-cyan-50/60 p-3">
            <p class="text-xs uppercase tracking-wide text-cyan-700">Total Relasi</p>
            <p class="mt-1 text-2xl font-semibold text-cyan-900">{{ totals.total_relations }}</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between gap-2">
            <h4 class="text-sm font-semibold text-gray-700">Alur Relasi</h4>
            <div class="flex items-center gap-2 text-[11px] text-gray-500">
              <span class="inline-flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-emerald-500"></i> Sehat</span>
              <span class="inline-flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-amber-500"></i> Perlu perhatian</span>
              <span class="inline-flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-rose-500"></i> Kritis</span>
            </div>
          </div>

          <div class="mt-3 space-y-3">
            <div
              v-for="level in levels"
              :key="`flow-${level.level}`"
              class="rounded-xl border border-gray-100 bg-gray-50/70 p-3"
            >
              <div class="flex items-center justify-between gap-2">
                <div>
                  <p class="text-sm font-medium text-gray-800">{{ level.label }}</p>
                  <p class="text-xs text-gray-500">Terhubung ke {{ level.parent_label }}</p>
                </div>
                <Link
                  :href="route('data-dasar.relasi.level', { level: level.level })"
                  class="rounded-md border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700 hover:bg-teal-100"
                >
                  Atur
                </Link>
              </div>

              <div class="mt-2 flex items-center gap-2">
                <span class="text-[11px] font-medium text-gray-500">{{ level.label }}</span>
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="statusBarClass(level)"
                    :style="{ width: `${Math.max(level.coverage, 2)}%` }"
                  ></div>
                </div>
                <span class="text-[11px] font-medium text-gray-500">{{ level.parent_label }}</span>
              </div>

              <div class="mt-1 flex items-center justify-between text-[11px]">
                <span :class="statusTextClass(level)">{{ statusLabel(level) }}</span>
                <span class="text-gray-500">{{ level.coverage }}% data sudah punya relasi</span>
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
          <h4 class="text-sm font-semibold text-gray-700">Matriks Keterhubungan</h4>
          <div class="mt-3 overflow-hidden rounded-lg border border-gray-200">
            <table class="w-full text-sm">
              <thead class="border-b border-gray-200 bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-semibold text-gray-600">Level</th>
                  <th class="px-3 py-2 text-right font-semibold text-gray-600">Data</th>
                  <th class="px-3 py-2 text-right font-semibold text-gray-600">Linked</th>
                  <th class="px-3 py-2 text-right font-semibold text-gray-600">Unlinked</th>
                  <th class="px-3 py-2 text-right font-semibold text-gray-600">Relasi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="level in levels" :key="`matrix-${level.level}`" class="hover:bg-gray-50">
                  <td class="px-3 py-2 text-gray-700">{{ level.label }}</td>
                  <td class="px-3 py-2 text-right text-gray-700">{{ level.total_data }}</td>
                  <td class="px-3 py-2 text-right text-emerald-700">{{ level.linked_data }}</td>
                  <td class="px-3 py-2 text-right text-amber-700">{{ level.unlinked_data }}</td>
                  <td class="px-3 py-2 text-right text-cyan-700">{{ level.total_relations }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  levels: Array,
  totals: Object,
  activePeraturan: Object,
});

const levels = computed(() => {
  return (props.levels ?? []).map((level) => {
    const total = Number(level.total_data ?? 0);
    const linked = Number(level.linked_data ?? 0);
    const coverage = total === 0 ? 0 : Math.round((linked / total) * 100);

    return {
      ...level,
      coverage,
    };
  });
});

const peraturanLabel = computed(() => {
  if (!props.activePeraturan?.kode) return '';
  return `Peraturan ( ${props.activePeraturan.kode} - ${props.activePeraturan.nama} )`;
});

function statusLabel(level) {
  if (level.coverage >= 80) return 'Sehat';
  if (level.coverage >= 50) return 'Perlu perhatian';
  return 'Kritis';
}

function statusBarClass(level) {
  if (level.coverage >= 80) return 'bg-emerald-500';
  if (level.coverage >= 50) return 'bg-amber-500';
  return 'bg-rose-500';
}

function statusTextClass(level) {
  if (level.coverage >= 80) return 'font-medium text-emerald-700';
  if (level.coverage >= 50) return 'font-medium text-amber-700';
  return 'font-medium text-rose-700';
}
</script>

<style scoped>
@reference "../../../css/app.css";
</style>
