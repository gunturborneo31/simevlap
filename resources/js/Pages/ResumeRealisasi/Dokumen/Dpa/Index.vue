<template>
  <AppLayout title="Anggaran DPA (Clone)">
    <div class="mb-4">
      <div v-if="$page.props && $page.props.payload_summary" class="text-sm text-gray-700 bg-yellow-50 border border-yellow-200 rounded p-2">
        <strong>Debug:</strong>
        <span class="ml-2">ops: {{ $page.props.payload_summary.opds_count }} — rows: {{ $page.props.payload_summary.data_count }}</span>
      </div>
    </div>
    <component :is="DpaIndex" v-bind="$page.props" />
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DpaIndex from '@/Pages/DataDasar/Dokumen/Dpa/Index.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>
