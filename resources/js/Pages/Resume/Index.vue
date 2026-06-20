<template>
  <AppLayout title="Resume Monitoring">
    <section v-if="!hasTableView" class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
      <Link
        v-for="item in resumeMenu"
        :key="item.label"
        :href="route('resume.index', item.query)"
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

    <section v-else class="space-y-6">
      <div class="rounded-2xl border border-emerald-100 bg-white/90 p-6 shadow-md">
        <div class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-600">Resume</p>
            <h2 class="text-2xl font-bold text-emerald-900">{{ currentViewTitle }}</h2>
          </div>
          <Link
            :href="route('resume.index')"
            class="inline-flex items-center justify-center rounded-lg bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 transition-colors hover:bg-emerald-100"
          >
            Kembali ke Menu Resume
          </Link>
        </div>

        <p class="text-sm text-slate-500">
          Pilih salah satu tabel di bawah untuk membuka resume {{ currentViewTitle.toLowerCase() }}.
        </p>
      </div>

      <section :class="[
        'grid gap-6',
        tableMenu.length >= 10 
          ? 'grid-cols-1 md:grid-cols-2 xl:grid-cols-5'
          : tableMenu.length >= 6
          ? 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3'
          : tableMenu.length <= 2
          ? 'grid-cols-1 md:grid-cols-2 xl:grid-cols-2'
          : 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4'
      ]">
        <Link
          v-for="item in tableMenu"
          :key="item.label"
          :href="route('resume.index', { view: props.currentView, table: item.value })"
          :class="[
            'group rounded-2xl border bg-white/90 p-4 text-center shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl',
            currentTable === item.value
              ? 'border-emerald-500 ring-2 ring-emerald-200'
              : 'border-emerald-100'
          ]"
        >
          <div :class="item.iconBg + ' mx-auto mb-2 inline-flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-lg'">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-6 w-6">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 6.75h12m-12 5.25h12m-12 5.25h12M3.75 6.75h.008v.008H3.75V6.75Zm0 5.25h.008v.008H3.75V12Zm0 5.25h.008v.008H3.75v-.008Z" />
            </svg>
          </div>
          <h3 class="text-lg font-bold text-emerald-900 transition-colors group-hover:text-emerald-700">{{ item.label }}</h3>
        </Link>
      </section>

    </section>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  currentView: {
    type: String,
    default: '',
  },
  currentTable: {
    type: String,
    default: '',
  },
});

const resumeMenu = [
  { label: 'Konsistensi RPJMD - RKPD', query: { view: 'konsistensi-rpjmd-rkpd' }, iconBg: 'bg-blue-500' },
  { label: 'Konsistensi RKPD - APBD', query: { view: 'konsistensi-rkpd-apbd' }, iconBg: 'bg-teal-500' },
  { label: 'Hasil Pelaksanaan RKPD', query: { view: 'hasil-pelaksanaan-rkpd' }, iconBg: 'bg-emerald-600' },
  { label: 'Rekap Permasalahan', query: { view: 'rekap-permasalahan' }, iconBg: 'bg-amber-500' },
  { label: 'Realisasi', query: { view: 'realisasi' }, iconBg: 'bg-yellow-600' },
  { label: 'Kertas Kerja', query: { view: 'kertas-kerja' }, iconBg: 'bg-lime-600' },
];

// View configuration dengan jumlah tabel dan warna yang berbeda
const viewConfigs = {
  'konsistensi-rpjmd-rkpd': {
    title: 'Konsistensi RPJMD - RKPD',
    tables: 4,
    colors: ['bg-blue-500', 'bg-teal-500', 'bg-emerald-600', 'bg-amber-500'],
  },
  'konsistensi-rkpd-apbd': {
    title: 'Konsistensi RKPD - APBD',
    tables: 10,
    colors: [
      'bg-blue-500', 'bg-teal-500', 'bg-emerald-600', 'bg-amber-500', 'bg-yellow-600',
      'bg-rose-500', 'bg-purple-500', 'bg-indigo-500', 'bg-cyan-500', 'bg-lime-500',
    ],
  },
  'hasil-pelaksanaan-rkpd': {
    title: 'Hasil Pelaksanaan RKPD',
    tables: 6,
    colors: ['bg-blue-500', 'bg-teal-500', 'bg-emerald-600', 'bg-amber-500', 'bg-rose-500', 'bg-purple-500'],
  },
  'rekap-permasalahan': {
    title: 'Rekap Permasalahan',
    items: [
      { label: 'Berdasarkan Sasaran', value: 'berdasarkan-sasaran', iconBg: 'bg-blue-500' },
      { label: 'Berdasarkan Bidang Urusan', value: 'berdasarkan-bidang-urusan', iconBg: 'bg-amber-500' },
    ],
  },
  'realisasi': {
    title: 'Realisasi',
    items: [
      { label: 'IKU', value: 'iku', iconBg: 'bg-blue-500' },
      { label: 'IKK', value: 'ikk', iconBg: 'bg-teal-500' },
      { label: 'RPJMD', value: 'rpjmd', iconBg: 'bg-emerald-600' },
      { label: 'RKPD', value: 'rkpd', iconBg: 'bg-amber-500' },
      { label: 'APBD', value: 'apbd', iconBg: 'bg-yellow-600' },
      { label: 'RENSTRA', value: 'renstra', iconBg: 'bg-rose-500' },
      { label: 'RENJA', value: 'renja', iconBg: 'bg-purple-500' },
      { label: 'DPA', value: 'dpa', iconBg: 'bg-indigo-500' },
    ],
  },
};

// Generate table menu dinamis berdasarkan current view
const generateTableMenu = (viewKey) => {
  const config = viewConfigs[viewKey];
  if (!config) return [];
  
  // Jika ada items custom, gunakan itu
  if (config.items) {
    return config.items;
  }
  
  // Otherwise, generate numbered tables
  const tables = [];
  for (let i = 1; i <= config.tables; i++) {
    tables.push({
      label: `Tabel ${i}`,
      value: `tabel-${i}`,
      iconBg: config.colors[(i - 1) % config.colors.length],
    });
  }
  return tables;
};

const tableMenu = computed(() => generateTableMenu(props.currentView));

// Deteksi apakah current view adalah salah satu yang punya custom table view
const hasTableView = computed(() => Object.keys(viewConfigs).includes(props.currentView));

// Get title untuk current view
const currentViewTitle = computed(() => {
  const config = viewConfigs[props.currentView];
  return config?.title || '';
});
</script>
