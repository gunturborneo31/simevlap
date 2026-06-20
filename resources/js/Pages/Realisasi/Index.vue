<template>
  <AppLayout
    title="Realisasi"
    :breadcrumbs="[
      { label: 'Realisasi', href: route('realisasi.index') }
    ]"
  >
    <section class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-4">
      <Link
        v-for="item in visibleRealisasiMenu"
        :key="item.label"
        :href="item.value === 'dpa'
          ? route('realisasi.index', readonlyDpaQuery)
          : route('realisasi.index', { document_type: item.value, tahun: filters.tahun, triwulan: filters.triwulan })"
        class="group rounded-2xl border bg-white/90 p-4 text-center shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
        :class="filters.document_type === item.value ? 'border-emerald-400 ring-2 ring-emerald-200' : 'border-emerald-100'"
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
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputField from '@/Components/InputField.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  program: Array,
  documentType: String,
  tahun: [Number, String],
  triwulan: [Number, String],
});

const filters = ref({
  document_type: props.documentType,
  tahun: props.tahun,
  triwulan: props.triwulan,
});

const page = usePage();
const userRoles = computed(() => page.props.auth?.user?.roles ?? []);
const isSuperadmin = computed(() => userRoles.value.includes('superadmin'));
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

  return realisasiMenu.filter((item) => item.value !== 'iku');
});

function applyFilter() {
  router.get(route('realisasi.index'), filters.value, { preserveState: true, replace: true });
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
