<template>
  <AppLayout title="Resume" :breadcrumbs="breadcrumbs" :subtitle="description">
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-end">
      <div v-for="filter in filterOptions" :key="filter.name" class="w-full lg:w-auto lg:min-w-[220px]">
        <label :for="`r2-filter-${filter.name}`" class="mb-1 block text-xs font-medium text-gray-900">{{ filter.label }}</label>
        <select
          :id="`r2-filter-${filter.name}`"
          class="r2-select"
          :value="filters[filter.name]"
          @change="applyFilter(filter.param, $event.target.value)"
        >
          <option v-for="option in filter.options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
      <button v-if="canExport" type="button" class="r2-btn-primary h-[38px]" @click="exportExcel">Excel</button>
    </div>

    <div ref="tableWrapper" class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
      <component :is="tableView.component" v-bind="tableView.props" :data="data" @show-list="openList" />
    </div>

    <ListModal :show="modal.show" :title="modal.title" :items="modal.items" @close="modal.show = false" />
  </AppLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ListModal from '@/Components/Resume2/ListModal.vue';
import JumlahTable from '@/Components/Resume2/JumlahTable.vue';
import RpjmdRkpdIndikatorTable from '@/Components/Resume2/RpjmdRkpdIndikatorTable.vue';
import RpjmdRkpdPaguTable from '@/Components/Resume2/RpjmdRkpdPaguTable.vue';
import RkpdApbdIndikatorTable from '@/Components/Resume2/RkpdApbdIndikatorTable.vue';
import RkpdApbdPaguTable from '@/Components/Resume2/RkpdApbdPaguTable.vue';
import HasilTujuanSasaranTable from '@/Components/Resume2/HasilTujuanSasaranTable.vue';
import HasilProgramAksiTable from '@/Components/Resume2/HasilProgramAksiTable.vue';
import HasilProgramPrioritasTable from '@/Components/Resume2/HasilProgramPrioritasTable.vue';
import HasilRealisasiTable from '@/Components/Resume2/HasilRealisasiTable.vue';
import HasilEvaluasiRkpdTable from '@/Components/Resume2/HasilEvaluasiRkpdTable.vue';
import HasilEvaluasiRenjaTable from '@/Components/Resume2/HasilEvaluasiRenjaTable.vue';
import RekapPermasalahanTable from '@/Components/Resume2/RekapPermasalahanTable.vue';

const props = defineProps({
  breadcrumbs: { type: Array, required: true },
  section: { type: String, required: true },
  tabel: { type: Number, required: true },
  description: { type: String, default: '' },
  filters: { type: Object, required: true },
  filterOptions: { type: Array, required: true },
  canExport: { type: Boolean, default: false },
  data: { type: Object, required: true },
});

const tableWrapper = ref(null);
const modal = reactive({ show: false, title: '', items: [] });

const tahun = computed(() => props.filters.tahun);
const entityLabel = computed(() => (props.filters.filter === 'perangkat-daerah' ? 'Perangkat Daerah' : 'Bidang Urusan'));
const levelLabels = ['Program', 'Kegiatan', 'Sub Kegiatan'];

// Pilih komponen tabel beserta props-nya berdasarkan bagian dan nomor tabel.
const tableView = computed(() => {
  const t = props.tabel;
  const common = { tahun: tahun.value, entityLabel: entityLabel.value };

  switch (props.section) {
    case 'konsistensi-rpjmd-rkpd': {
      if (t <= 2) {
        return {
          component: JumlahTable,
          props: {
            ...common,
            unit: t === 1 ? 'Program' : 'Indikator Program',
            documents: [
              { key: 'rpjmd', label: 'RPJMD (2021-2026)' },
              { key: 'renstra', label: `Renstra (Tahun ${tahun.value})` },
              { key: 'rkpd', label: `RKPD/Renja (Tahun ${tahun.value})` },
            ],
            comparisons: [
              { key: 'rpjmd_renstra', label: 'Konsistensi RPJMD - Renstra' },
              { key: 'rpjmd_rkpd', label: 'Konsistensi RPJMD - RKPD/Renja' },
              { key: 'renstra_rkpd', label: 'Status Konsistensi Renstra - RKPD/Renja' },
            ],
          },
        };
      }
      return { component: t === 3 ? RpjmdRkpdIndikatorTable : RpjmdRkpdPaguTable, props: common };
    }
    case 'konsistensi-keg-rkpd-apbd': {
      if (t <= 3) {
        return {
          component: JumlahTable,
          props: {
            ...common,
            unit: levelLabels[t - 1],
            documents: [
              { key: 'rkpd', label: `RKPD/Renja (Tahun ${tahun.value})` },
              { key: 'apbd', label: `APBD (Tahun ${tahun.value})` },
            ],
            comparisons: [{ key: 'rkpd_apbd', label: 'Konsistensi RKPD/Renja - APBD' }],
          },
        };
      }
      if (t <= 9) {
        return {
          component: RkpdApbdIndikatorTable,
          props: { ...common, unit: levelLabels[(t - 4) % 3], withTarget: t >= 7 },
        };
      }
      return { component: RkpdApbdPaguTable, props: common };
    }
    case 'hasil-pelaksanaan-rkpd': {
      const components = [
        HasilTujuanSasaranTable,
        HasilProgramAksiTable,
        HasilProgramPrioritasTable,
        HasilRealisasiTable,
        HasilEvaluasiRkpdTable,
        HasilEvaluasiRenjaTable,
      ];
      return { component: components[t - 1], props: { tahun: tahun.value, tw: props.filters.tw } };
    }
    default:
      return { component: RekapPermasalahanTable, props: { groupLabel: t === 1 ? 'Perangkat Daerah' : 'Bidang Urusan' } };
  }
});

function applyFilter(param, value) {
  const query = {};
  props.filterOptions.forEach((f) => {
    query[f.param] = props.filters[f.name];
  });
  query[param] = value;
  router.get(window.location.pathname, query, { preserveScroll: true });
}

function openList({ title, items }) {
  modal.title = title;
  modal.items = items;
  modal.show = true;
}

// Ekspor tabel yang sedang tampil ke file .xls (HTML table yang dapat dibuka Excel).
function exportExcel() {
  const table = tableWrapper.value?.querySelector('table');
  if (!table) return;

  const title = props.breadcrumbs.map((b) => b.label).join(' / ');
  const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8"><style>table{border-collapse:collapse}th,td{border:1px solid #999;padding:4px;vertical-align:top}th{background:#3c8f4f;color:#fff}</style></head>
<body><h3>${title}</h3><p>${props.description}</p>${table.outerHTML}</body></html>`;

  const blob = new Blob(['﻿', html], { type: 'application/vnd.ms-excel' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `resume2_${props.section}_tabel-${props.tabel}_${tahun.value ?? ''}.xls`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
</script>
