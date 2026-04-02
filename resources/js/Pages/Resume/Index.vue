<template>
  <AppLayout title="Resume Monitoring">
    <div class="flex flex-wrap items-end gap-4 mb-6">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Jenis Dokumen</label>
        <select v-model="filters.document_type" @change="applyFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="rpjmd">RPJMD</option>
          <option value="renstra">Renstra</option>
          <option value="renja">Renja</option>
          <option value="dpa">DPA</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Tahun</label>
        <input v-model="filters.tahun" type="number" @change="applyFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-28" />
      </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
        <h3 class="font-semibold text-gray-700">
          Resume Program — {{ filters.document_type.toUpperCase() }} Tahun {{ filters.tahun }}
        </h3>
        <span class="text-sm text-gray-500">{{ data.length }} program</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Kode Rek.</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Nama Program</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">OPD</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600">Pagu (Rp)</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Real. Fisik (%)</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600">Real. Keuangan (Rp)</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Penyerapan (%)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="data.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-gray-400">Belum ada data program untuk filter ini.</td>
            </tr>
            <tr v-for="row in data" :key="row.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-gray-600 text-xs">{{ row.kode_rek }}</td>
              <td class="px-4 py-3 text-gray-800 max-w-xs">{{ row.nama_rincian }}</td>
              <td class="px-4 py-3 text-gray-600">{{ row.opd }}</td>
              <td class="px-4 py-3 text-right text-gray-700">{{ formatCurrency(row.pagu) }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-2">
                  <div class="w-20 bg-gray-200 rounded-full h-1.5">
                    <div class="bg-blue-500 h-1.5 rounded-full" :style="{ width: Math.min(row.realisasi_fisik, 100) + '%' }"></div>
                  </div>
                  <span :class="getStatusColor(row.realisasi_fisik)" class="text-xs font-medium w-10 text-right">{{ row.realisasi_fisik }}%</span>
                </div>
              </td>
              <td class="px-4 py-3 text-right text-gray-700">{{ formatCurrency(row.realisasi_keuangan) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="getStatusColor(penyerapan(row))" class="text-xs font-medium">
                  {{ penyerapan(row) }}%
                </span>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="data.length > 0" class="bg-gray-50 border-t-2 border-gray-300">
            <tr>
              <td colspan="3" class="px-4 py-3 font-semibold text-gray-700 text-sm">Total</td>
              <td class="px-4 py-3 text-right font-semibold text-gray-700">{{ formatCurrency(totalPagu) }}</td>
              <td class="px-4 py-3 text-center font-semibold text-gray-700">{{ avgFisik }}%</td>
              <td class="px-4 py-3 text-right font-semibold text-gray-700">{{ formatCurrency(totalKeuangan) }}</td>
              <td class="px-4 py-3 text-center font-semibold text-gray-700">{{ avgPenyerapan }}%</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
  data: Array,
  documentType: String,
  tahun: [Number, String],
  opds: Array,
});

const filters = ref({
  document_type: props.documentType,
  tahun: props.tahun,
});

function applyFilter() {
  router.get(route('resume.index'), filters.value, { preserveState: true, replace: true });
}

function formatCurrency(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}

function roundOneDecimal(val) {
  return Math.round(val * 10) / 10;
}

function penyerapan(row) {
  if (!row.pagu || row.pagu === 0) return 0;
  return roundOneDecimal((row.realisasi_keuangan / row.pagu) * 100);
}

function getStatusColor(val) {
  if (val >= 90) return 'text-green-600';
  if (val >= 60) return 'text-yellow-600';
  return 'text-red-600';
}

const totalPagu = computed(() => props.data.reduce((s, r) => s + Number(r.pagu || 0), 0));
const totalKeuangan = computed(() => props.data.reduce((s, r) => s + Number(r.realisasi_keuangan || 0), 0));
const avgFisik = computed(() => {
  if (!props.data.length) return 0;
  return roundOneDecimal(props.data.reduce((s, r) => s + Number(r.realisasi_fisik || 0), 0) / props.data.length);
});
const avgPenyerapan = computed(() => {
  if (!totalPagu.value) return 0;
  return roundOneDecimal((totalKeuangan.value / totalPagu.value) * 100);
});
</script>
