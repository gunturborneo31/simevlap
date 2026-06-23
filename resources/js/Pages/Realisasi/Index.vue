<template>
  <AppLayout
    title="Realisasi"
    :breadcrumbs="[
      { label: 'Realisasi', href: route('realisasi.index') }
    ]"
  >
    <section v-if="isIkkMode" class="space-y-6">
      <div class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="space-y-1">
            <h2 class="text-xl font-semibold text-emerald-950">Realisasi IKK</h2>
            <p class="text-sm text-slate-500">Pilih OPD terkait, lalu isi realisasi pada tiap IKK yang muncul di bawah.</p>
          </div>
          <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[520px]">
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">OPD</label>
              <select
                v-model="selectedOpdId"
                @change="applyIkkFilter"
                class="input-base"
                :disabled="isOwnOpdOnly"
              >
                <option value="">Pilih OPD</option>
                <option v-for="opd in opds" :key="opd.id" :value="opd.id">{{ opd.nama }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun</label>
              <input v-model="filters.tahun" @change="applyIkkFilter" type="number" class="input-base" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Triwulan</label>
              <div class="grid grid-cols-4 gap-2">
                <button
                  v-for="tw in [1, 2, 3, 4]"
                  :key="tw"
                  type="button"
                  @click="chooseTriwulan(tw)"
                  class="rounded-lg border px-3 py-2 text-xs font-semibold transition"
                  :class="filters.triwulan === tw ? 'border-emerald-500 bg-emerald-500 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700'"
                >
                  TW {{ tw }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <h3 class="text-base font-semibold text-slate-800">Daftar IKK Terkait</h3>
            <p class="text-sm text-slate-500">Target ditampilkan dari relasi indikator, realisasi dapat diubah langsung per baris.</p>
          </div>
          <div class="flex items-center gap-3">
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ ikkRows.length }} data</span>
            <button
              type="button"
              @click="saveAllIkkRows"
              :disabled="savingAllRows || ikkRows.length === 0"
              class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {{ savingAllRows ? 'Menyimpan...' : 'Simpan Semua' }}
            </button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="px-4 py-3 text-center font-semibold">No</th>
                <th class="px-4 py-3 text-left font-semibold">IKK</th>
                <th class="px-4 py-3 text-left font-semibold">Target</th>
                <th class="px-4 py-3 text-left font-semibold">Realisasi</th>
                <th class="px-4 py-3 text-left font-semibold">Satuan</th>
                <th class="px-4 py-3 text-left font-semibold">Sumber</th>
                <th class="px-4 py-3 text-center font-semibold">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="ikkRows.length === 0">
                <td colspan="7" class="px-4 py-10 text-center text-slate-400">Tidak ada data IKK untuk OPD dan periode ini.</td>
              </tr>
              <tr v-for="(row, index) in ikkRows" :key="row.pivot_id" class="align-top hover:bg-slate-50">
                <td class="px-4 py-3 text-center text-slate-500">{{ index + 1 }}</td>
                <td class="px-4 py-3 text-slate-800">{{ row.indikator_uraian }}</td>
                <td class="px-4 py-3 font-semibold text-slate-700">{{ formatNumber(row.target) }}</td>
                <td class="px-4 py-3">
                  <input
                    v-model="rowDrafts[row.pivot_id].realisasi"
                    type="number"
                    step="0.01"
                    min="0"
                    class="input-base text-right"
                    placeholder="0"
                  />
                </td>
                <td class="px-4 py-3 text-slate-600">{{ row.indikator_satuan || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">
                  {{ describeIndicatorable(row.indicatorable_type) }} #{{ row.indicatorable_id }}
                </td>
                <td class="px-4 py-3 text-center">
                  <button
                    type="button"
                    @click="saveIkkRow(row)"
                    :disabled="savingRowId === row.pivot_id"
                    class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    {{ savingRowId === row.pivot_id ? 'Menyimpan...' : 'Simpan' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section v-else class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-4">
      <a
        v-for="item in visibleRealisasiMenu"
        :key="item.label"
        :href="item.value === 'dpa'
          ? route('realisasi.index', readonlyDpaQuery)
          : route('realisasi.index', { document_type: item.value, tahun: filters.tahun, triwulan: filters.triwulan })"
        class="group rounded-2xl border bg-white/90 p-4 text-center shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
        :aria-current="filters.document_type === item.value ? 'page' : undefined"
        :class="filters.document_type === item.value ? 'border-emerald-400 ring-2 ring-emerald-200' : 'border-emerald-100'"
      >
        <div :class="item.iconBg + ' mx-auto mb-2 inline-flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-lg'">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
          </svg>
        </div>
        <h3 class="text-lg font-bold text-emerald-900 transition-colors group-hover:text-emerald-700">{{ item.label }}</h3>
      </a>
    </section>
  </AppLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputField from '@/Components/InputField.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  program: Array,
  documentType: String,
  tahun: [Number, String],
  triwulan: [Number, String],
  opds: {
    type: Array,
    default: () => [],
  },
  selectedOpdId: [Number, String],
  ikkRows: {
    type: Array,
    default: () => [],
  },
});

const isIkkMode = computed(() => props.documentType === 'ikk');

const filters = ref({
  document_type: props.documentType,
  tahun: props.tahun,
  triwulan: props.triwulan,
});

const selectedOpdId = ref(props.selectedOpdId ?? '');
const rowDrafts = reactive({});
const savingRowId = ref(null);
const savingAllRows = ref(false);
const isOwnOpdOnly = computed(() => props.opds.length === 1);

watch(
  () => props.ikkRows,
  (rows) => {
    Object.keys(rowDrafts).forEach((key) => delete rowDrafts[key]);
    (rows || []).forEach((row) => {
      rowDrafts[row.pivot_id] = {
        realisasi: row.realisasi ?? '',
      };
    });
  },
  { immediate: true }
);

const page = usePage();
const userRoles = computed(() => page.props.auth?.user?.roles ?? []);
const isSuperadmin = computed(() => userRoles.value.includes('superadmin'));
const isAdmin = computed(() => userRoles.value.includes('admin'));
const readonlyDpaQuery = computed(() => ({
  document_type: 'dpa',
  readonly: 1,
  triwulan: 'all',
  tahun: filters.value.tahun || new Date().getFullYear(),
  opd_id: page.props.auth?.user?.opd_id || undefined,
}));

const realisasiMenu = [
  { label: 'IKU', value: 'iku', iconBg: 'bg-blue-500' },
  { label: 'IKK', value: 'ikk', iconBg: 'bg-blue-400' },
  { label: 'REALISASI', value: 'dpa', iconBg: 'bg-yellow-600' },
];

const visibleRealisasiMenu = computed(() => {
  if (isSuperadmin.value) {
    return realisasiMenu;
  }

  if (isAdmin.value) {
    return realisasiMenu.filter((item) => item.value === 'iku');
  }

  return realisasiMenu.filter((item) => item.value !== 'iku');
});

function applyFilter() {
  router.get(route('realisasi.index'), filters.value, { preserveState: true, replace: true });
}

function applyIkkFilter() {
  router.get(
    route('realisasi.index'),
    {
      document_type: 'ikk',
      tahun: filters.value.tahun,
      triwulan: filters.value.triwulan,
      opd_id: selectedOpdId.value || undefined,
    },
    { preserveState: true, replace: true, preserveScroll: true }
  );
}

function chooseTriwulan(tw) {
  filters.value.triwulan = tw;
  applyIkkFilter();
}

function describeIndicatorable(type) {
  if (type === 'App\\Models\\Program') return 'Program';
  if (type === 'App\\Models\\Kegiatan') return 'Kegiatan';
  if (type === 'App\\Models\\SubKegiatan') return 'Sub Kegiatan';
  return 'IKK';
}

function formatNumber(value) {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function saveIkkRow(row) {
  const draft = rowDrafts[row.pivot_id] || {};
  savingRowId.value = row.pivot_id;

  router.post(route('realisasi.store'), {
    document_type: 'ikk',
    indikator_id: row.indikator_id,
    indicatorable_type: row.indicatorable_type,
    indicatorable_id: row.indicatorable_id,
    tahun: filters.value.tahun,
    triwulan: filters.value.triwulan,
    target: row.target,
    realisasi: draft.realisasi === '' || draft.realisasi === null ? 0 : draft.realisasi,
    catatan: row.catatan ?? null,
  }, {
    preserveScroll: true,
    onFinish: () => {
      savingRowId.value = null;
    },
  });
}

function saveAllIkkRows() {
  if (savingAllRows.value || ikkRows.length === 0) {
    return;
  }

  savingAllRows.value = true;

  const rows = [...props.ikkRows];

  const saveNext = (index) => {
    if (index >= rows.length) {
      savingAllRows.value = false;
      return;
    }

    const row = rows[index];
    const draft = rowDrafts[row.pivot_id] || {};

    router.post(route('realisasi.store'), {
      document_type: 'ikk',
      indikator_id: row.indikator_id,
      indicatorable_type: row.indicatorable_type,
      indicatorable_id: row.indicatorable_id,
      tahun: filters.value.tahun,
      triwulan: filters.value.triwulan,
      target: row.target,
      realisasi: draft.realisasi === '' || draft.realisasi === null ? 0 : draft.realisasi,
      catatan: row.catatan ?? null,
    }, {
      preserveScroll: true,
      onFinish: () => saveNext(index + 1),
    });
  };

  saveNext(0);
}

const showModal = ref(false);
const showDeleteModal = ref(false);
const editing = ref(false);
const selectedProgram = ref(null);
const selectedReal = ref(null);

const form = useForm({
  realisaseable_id: '',
  realisaseable_type: 'App\\Models\\Program',
  document_type: '',
  tahun: '',
  triwulan: '',
  realisasi_fisik: '',
  realisasi_keuangan: '',
  sisa_anggaran: '',
  catatan: '',
});

function openAdd(prog) {
  editing.value = false;
  selectedProgram.value = prog;
  form.realisaseable_id = prog.id;
  form.realisaseable_type = 'App\\Models\\Program';
  form.document_type = filters.value.document_type;
  form.tahun = filters.value.tahun;
  form.triwulan = filters.value.triwulan;
  form.realisasi_fisik = '';
  form.realisasi_keuangan = '';
  form.sisa_anggaran = '';
  form.catatan = '';
  showModal.value = true;
}

function openEdit(prog, real) {
  editing.value = true;
  selectedProgram.value = prog;
  selectedReal.value = real;
  form.realisasi_fisik = real.realisasi_fisik;
  form.realisasi_keuangan = real.realisasi_keuangan ?? '';
  form.sisa_anggaran = real.sisa_anggaran ?? '';
  form.catatan = real.catatan ?? '';
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  form.clearErrors();
}

function submit() {
  if (editing.value) {
    form.put(route('realisasi.update', selectedReal.value.id), {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post(route('realisasi.store'), {
      onSuccess: () => closeModal(),
    });
  }
}

function confirmDelete(real) {
  selectedReal.value = real;
  showDeleteModal.value = true;
}

function deleteReal() {
  router.delete(route('realisasi.destroy', selectedReal.value.id), {
    onSuccess: () => { showDeleteModal.value = false; },
  });
}

function formatCurrency(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}
</script>

<style scoped>
@reference "../../../css/app.css";

.input-base {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500;
}
.input-filter {
  @apply border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500;
}
</style>
