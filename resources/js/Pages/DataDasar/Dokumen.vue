<template>
  <AppLayout
    title="Dokumen"
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Dokumen', href: route('dokumen.index') }
    ]"
  >
    <section v-if="isOpd" class="space-y-6">
      <div class="rounded-2xl border border-emerald-100 bg-white p-6 shadow-md">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <h2 class="text-lg font-bold text-emerald-900">Daftar Dokumen OPD</h2>
            <p class="mt-1 text-sm text-slate-500">Tabel dokumen tampil lebih dulu. Gunakan tombol upload atau edit untuk mengisi file dokumen.</p>
          </div>
          <button type="button" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700" @click="openCreateModal">Upload Dokumen</button>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200">
          <div class="overflow-x-auto">
            <table class="min-w-full table-auto text-sm">
              <thead class="bg-slate-50 text-slate-600">
                <tr>
                  <th class="px-4 py-3 text-left font-semibold">Jenis Dokumen</th>
                  <th class="px-4 py-3 text-left font-semibold">Judul / Nomor</th>
                  <th class="px-4 py-3 text-left font-semibold">Tahun</th>
                  <th class="px-4 py-3 text-left font-semibold">Status</th>
                  <th class="px-4 py-3 text-left font-semibold">File</th>
                  <th class="px-4 py-3 text-left font-semibold">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="dokumenUploads.length === 0">
                  <td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada dokumen yang diunggah.</td>
                </tr>
                <tr v-for="dokumen in dokumenUploads" :key="dokumen.id" class="border-t border-slate-100 align-top">
                  <td class="px-4 py-3 font-semibold text-emerald-800">{{ formatJenisDokumen(dokumen.document_type) }}</td>
                  <td class="px-4 py-3 text-slate-700">{{ dokumen.judul }}</td>
                  <td class="px-4 py-3 text-slate-700">{{ formatTahunLabel(dokumen.document_type, dokumen.tahun) }}</td>
                  <td class="px-4 py-3">
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">{{ dokumen.status }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <a :href="dokumen.view_url" target="_blank" class="text-xs font-semibold text-emerald-700 underline">Lihat File</a>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-2">
                      <button type="button" class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100" @click="openEditModal(dokumen)">Edit File</button>
                      <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100" @click="hapusDokumen(dokumen.id)">Hapus</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4 backdrop-blur-sm">
        <div class="w-full max-w-2xl rounded-2xl border border-emerald-100 bg-white p-6 shadow-2xl">
          <div class="mb-5 flex items-start justify-between gap-4">
            <div>
              <h3 class="text-lg font-bold text-emerald-900">{{ editingId ? 'Edit Dokumen' : 'Upload Dokumen' }}</h3>
              <p class="mt-1 text-sm text-slate-500">Isi data dokumen lalu simpan. File hanya wajib saat upload baru.</p>
            </div>
            <button type="button" class="text-2xl leading-none text-slate-400 hover:text-slate-700" @click="closeModal">×</button>
          </div>

          <form class="grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submitUpload">
            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-700">Jenis Dokumen *</label>
              <select v-model="uploadForm.document_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Pilih jenis dokumen</option>
                <option v-for="item in uploadDocumentTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-700">Tahun *</label>
              <select v-model="uploadForm.tahun" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Pilih tahun</option>
                <option v-for="item in availableYearOptions" :key="`${uploadForm.document_type}-${item.value}`" :value="item.value">{{ item.label }}</option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="mb-2 block text-sm font-semibold text-slate-700">Judul / Nomor Dokumen *</label>
              <input v-model="uploadForm.judul" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: DPA Tahun 2026" />
            </div>

            <div class="md:col-span-2">
              <label class="mb-2 block text-sm font-semibold text-slate-700">File Dokumen <span class="text-slate-400">{{ editingId ? '(opsional saat edit)' : '*' }}</span></label>
              <input ref="fileInputRef" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-700" @change="onFileChange" />
              <p class="mt-2 text-xs text-slate-500">Format: PDF, DOC, DOCX, XLS, XLSX. Maksimal 10 MB.</p>
            </div>

            <div class="md:col-span-2 flex gap-3">
              <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">{{ editingId ? 'Simpan Perubahan' : 'Upload Dokumen' }}</button>
              <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">Batal</button>
            </div>
          </form>
        </div>
      </div>
    </section>

    <section v-else class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-4">
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
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  dokumenUploads: {
    type: Array,
    default: () => [],
  },
  uploadDocumentTypes: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const isOpd = computed(() => (page.props.auth?.user?.roles ?? []).includes('opd'));
const renstraPeriodValue = 2026;
const yearlyOptions = [2026, 2027, 2028, 2029, 2030].map((year) => ({
  value: year,
  label: String(year),
}));
const fileInputRef = ref(null);
const showModal = ref(false);
const editingId = ref(null);
const uploadForm = ref({
  document_type: '',
  judul: '',
  tahun: new Date().getFullYear(),
  file: null,
});

const availableYearOptions = computed(() => {
  if (uploadForm.value.document_type === 'renstra') {
    return [{ value: renstraPeriodValue, label: '2026 - 2030' }];
  }

  if (['renja', 'dpa'].includes(uploadForm.value.document_type)) {
    return yearlyOptions;
  }

  return [];
});

watch(() => uploadForm.value.document_type, (documentType) => {
  if (!documentType) {
    uploadForm.value.tahun = '';
    return;
  }

  const validValues = availableYearOptions.value.map((item) => Number(item.value));
  const currentValue = Number(uploadForm.value.tahun);
  if (!validValues.includes(currentValue)) {
    uploadForm.value.tahun = availableYearOptions.value[0]?.value ?? '';
  }
});

function resetForm() {
  uploadForm.value = {
    document_type: '',
    judul: '',
    tahun: '',
    file: null,
  };
  editingId.value = null;
  if (fileInputRef.value) {
    fileInputRef.value.value = '';
  }
}

function openCreateModal() {
  resetForm();
  showModal.value = true;
}

function openEditModal(dokumen) {
  uploadForm.value = {
    document_type: dokumen.document_type || '',
    judul: dokumen.judul || '',
    tahun: dokumen.tahun || new Date().getFullYear(),
    file: null,
  };
  editingId.value = dokumen.id;
  if (fileInputRef.value) {
    fileInputRef.value.value = '';
  }
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  resetForm();
}

function onFileChange(event) {
  uploadForm.value.file = event.target.files?.[0] ?? null;
}

function submitUpload() {
  const routeName = editingId.value ? route('dokumen.update', editingId.value) : route('dokumen.store');
  const payload = {
    document_type: uploadForm.value.document_type,
    judul: uploadForm.value.judul,
    tahun: uploadForm.value.tahun,
    file: uploadForm.value.file,
  };

  if (editingId.value) {
    payload._method = 'put';
  }

  router.post(routeName, payload, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      closeModal();
    },
  });
}

function hapusDokumen(id) {
  router.delete(route('dokumen.destroy', id), {
    preserveScroll: true,
  });
}

function formatJenisDokumen(value) {
  return String(value || '').toUpperCase();
}

function formatTahunLabel(documentType, tahun) {
  if (documentType === 'renstra') {
    return '2026 - 2030';
  }

  return String(tahun || '-');
}

// Semua ikon diganti inline SVG di template agar selalu tampil
const dokumenMenu = [
  { label: 'IKU', route: '/data-dasar/dokumen/iku', iconBg: 'bg-blue-500' },
  { label: 'IKK', route: '/data-dasar/bank-data/ikk', iconBg: 'bg-blue-400' },
  { label: 'RENSTRA', route: '/data-dasar/dokumen/renstra', iconBg: 'bg-yellow-500' },
  { label: 'RENJA', route: '/data-dasar/dokumen/renja', iconBg: 'bg-yellow-400' },
  { label: 'DPA', route: '/data-dasar/dokumen/dpa', iconBg: 'bg-yellow-600' },
];
// Note: RENSTRA sudah menggunakan route baru /data-dasar/dokumen/renstra
</script>

<style scoped>
@reference "../../../css/app.css";
</style>
