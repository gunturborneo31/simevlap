<template>
  <AppLayout
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Relasi', href: route('data-dasar.relasi') }
    ]"
    :right-info="peraturanLabel"
  >
    <section class="rounded-2xl p-2">
      <p class="mb-4 text-sm text-gray-500">
        Pilih level untuk mengatur koneksi/relasi antar data yang telah diinput di Bank Data.
      </p>
      <div class="mb-4 flex justify-end">
        <Link
          :href="route('data-dasar.relasi.ringkasan')"
          class="inline-flex items-center gap-2 rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100"
        >
          Lihat Ringkasan Graf Relasi
        </Link>
      </div>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <Link
          v-for="item in relasiItems"
          :key="item.slug"
          :href="route('data-dasar.relasi.level', { level: item.slug })"
          class="group rounded-2xl border border-teal-100 bg-white/90 p-4 text-center shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
        >
          <div class="mx-auto mb-2 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-400 to-cyan-600 text-white shadow-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
            </svg>
          </div>
          <h3 class="text-lg font-bold text-teal-900 transition-colors group-hover:text-teal-700">{{ item.label }}</h3>
          <p class="mt-1 text-xs text-gray-500">{{ item.parentLabel }}</p>
        </Link>
      </div>
    </section>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  activePeraturan: Object,
});

const peraturanLabel = computed(() => {
  if (!props.activePeraturan?.kode) return '';
  return `Peraturan ( ${props.activePeraturan.kode} - ${props.activePeraturan.nama} )`;
});

const relasiItems = [
  { slug: 'misi',           label: 'Misi',           parentLabel: 'Relasi ke: Visi' },
  { slug: 'tujuan',         label: 'Tujuan',         parentLabel: 'Relasi ke: Misi' },
  { slug: 'sasaran',        label: 'Sasaran',        parentLabel: 'Relasi ke: Tujuan' },
  { slug: 'strategi',       label: 'Strategi',       parentLabel: 'Relasi ke: Sasaran' },
  { slug: 'arah-kebijakan', label: 'Arah Kebijakan', parentLabel: 'Relasi ke: Strategi' },
  { slug: 'bidang-urusan',  label: 'Bidang Urusan', parentLabel: 'Relasi ke: Urusan' },
  { slug: 'program',        label: 'Program',        parentLabel: 'Relasi ke: Bidang Urusan' },
  { slug: 'program-aksi',   label: 'Program Aksi',   parentLabel: 'Relasi ke: Program Prioritas SKPD' },
  { slug: 'kegiatan',       label: 'Kegiatan',       parentLabel: 'Relasi ke: Program' },
  { slug: 'sub-kegiatan',   label: 'Sub Kegiatan',   parentLabel: 'Relasi ke: Kegiatan' },
];
</script>

<style scoped>
@reference "../../../css/app.css";
</style>
