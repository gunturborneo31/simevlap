<template>
  <AppLayout title="Lampiran Resume">
    <section class="space-y-6">
      <div class="rounded-2xl border border-emerald-100 bg-white/90 p-6 shadow-md">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-600">Lampiran</p>
            <h2 class="text-2xl font-bold text-emerald-900">Lampiran untuk Resume</h2>
            <p class="text-sm text-slate-600">View: {{ props.currentView }} — Table: {{ props.currentTable }}</p>
          </div>

          <div class="flex gap-3">
            <Link :href="route('resume.index', { view: props.currentView, table: props.currentTable })" class="inline-flex items-center justify-center rounded-lg bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 transition-colors hover:bg-emerald-100">Kembali</Link>
          </div>
        </div>

        <div class="mt-4 text-sm text-slate-700">
          <p>Berikut placeholder untuk daftar lampiran terkait halaman resume yang dipilih.</p>
          <ul class="mt-3 list-disc pl-5 text-slate-600">
            <li v-if="props.opd_id">Lampiran OPD ID: {{ props.opd_id }}</li>
            <li v-if="props.year">Tahun: {{ props.year }}</li>
            <li v-else>Tidak ada filter tambahan.</li>
          </ul>

          <div class="mt-4 p-4 rounded bg-slate-50 border">
            <em class="text-sm text-slate-500">(Belum ada lampiran terdaftar — ini hanya tampilan awal)</em>
          </div>
        </div>
      </div>
    </section>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  currentView: { type: String, default: '' },
  currentTable: { type: String, default: '' },
  opd_id: { type: [Number, String], default: null },
  year: { type: [Number, String], default: null },
});
</script>
