<template>
  <AppLayout
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Bank Data', href: route('data-dasar.bank-data') }
    ]"
  >
    <section class="rounded-2xl p-2">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <Link
          v-for="item in hierarchyItems"
          :key="item"
          :href="route('data-dasar.bank-data', { document_type: selectedDocType, level: item })"
          class="group rounded-2xl border border-emerald-100 bg-white/90 p-4 text-center shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
        >
          <div class="mx-auto mb-2 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <ellipse cx="12" cy="6" rx="8" ry="3" stroke="currentColor" stroke-width="1.5"/>
              <path stroke-linecap="round" d="M4 6v5c0 1.657 3.582 3 8 3s8-1.343 8-3V6"/>
              <path stroke-linecap="round" d="M4 11v5c0 1.657 3.582 3 8 3s8-1.343 8-3v-5"/>
            </svg>
          </div>
          <h3 class="text-lg font-bold text-emerald-900 transition-colors group-hover:text-emerald-700">{{ item }}</h3>
        </Link>
      </div>
    </section>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  documentType: {
    type: String,
    default: 'rpjmd',
  },
});

const selectedDocType = ref(props.documentType);

const hierarchyItems = [
  'Visi',
  'Misi',
  'Tujuan',
  'Sasaran',
  'Strategi',
  'Arah Kebijakan',
  'Urusan',
  'Bidang Urusan',
  'Program',
  'Kegiatan',
  'Sub Kegiatan',
];
</script>

<style scoped>
@reference "../../../css/app.css";
</style>
