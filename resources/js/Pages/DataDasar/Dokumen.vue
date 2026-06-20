<template>
  <AppLayout
    title="Dokumen"
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Dokumen', href: route('dokumen.index') }
    ]"
  >
    <section class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-4">
      <Link
        v-for="item in dokumenMenu"
        :key="item.label"
        :href="item.route"
        class="group rounded-2xl border border-emerald-100 bg-white/90 p-4 text-center shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
      >
        <div :class="item.iconBg + ' mx-auto mb-2 inline-flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-lg'">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
          </svg>
        </div>
        <h3 class="text-lg font-bold text-emerald-900 transition-colors group-hover:text-emerald-700">{{ item.label }}</h3>
      </Link>
    </section>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

// Semua ikon diganti inline SVG di template agar selalu tampil
const dokumenMenu = [
  { label: 'IKU', route: '/data-dasar/dokumen/iku', iconBg: 'bg-blue-500' },
  { label: 'IKK', route: '/data-dasar/dokumen/ikk', iconBg: 'bg-blue-400' },
  { label: 'RENSTRA', route: '/data-dasar/dokumen/renstra', iconBg: 'bg-yellow-500' },
  { label: 'RENJA', route: '/data-dasar/dokumen/renja', iconBg: 'bg-yellow-400' },
  { label: 'DPA', route: '/data-dasar/dokumen/dpa', iconBg: 'bg-yellow-600' },
];
// Note: RENSTRA sudah menggunakan route baru /data-dasar/dokumen/renstra
</script>

<style scoped>
@reference "../../../css/app.css";
</style>
