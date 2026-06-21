<template>
  <AppLayout title="Dashboard">
    <div class="mb-6">
      <h2 class="text-xl font-semibold text-gray-700">Selamat Datang, {{ user.name }}</h2>
      <p class="text-gray-500 text-sm">{{ user.opd?.nama ?? 'Pemerintah Daerah' }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
      <StatCard title="Total OPD Aktif" :value="stats.total_opd" icon="🏛️" />
      <StatCard title="Total Program" :value="stats.total_program" icon="📊" />
      <StatCard title="Total Realisasi" :value="stats.total_realisasi" icon="📈" />
    </div>

    <div v-if="isOpd" class="grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr,0.8fr]">
      <div class="bg-white rounded-xl shadow p-6">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div>
            <h3 class="text-base font-semibold text-gray-700">Upload Dokumen Perencanaan</h3>
            <p class="mt-1 text-sm text-gray-500">OPD dapat mengunggah dokumen Renstra, Renja, dan DPA milik unitnya sendiri.</p>
          </div>
          <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Khusus OPD</span>
        </div>

        <form class="grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submitUpload">
          <div>
            <label class="mb-2 block text-sm font-semibold text-gray-700">Jenis Dokumen *</label>
            <select v-model="uploadForm.document_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option value="">Pilih jenis dokumen</option>
              <option v-for="item in uploadDocumentTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
            </select>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-gray-700">Tahun *</label>
            <input v-model="uploadForm.tahun" type="number" min="2020" max="2100" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>

          <div class="md:col-span-2">
            <label class="mb-2 block text-sm font-semibold text-gray-700">Judul / Nomor Dokumen *</label>
            <input v-model="uploadForm.judul" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Contoh: Renja Dinas 2026" />
          </div>

          <div class="md:col-span-2">
            <label class="mb-2 block text-sm font-semibold text-gray-700">File Dokumen *</label>
            <input ref="fileInputRef" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-700" @change="onFileChange" />
            <p class="mt-2 text-xs text-gray-500">Format yang didukung: PDF, DOC, DOCX, XLS, XLSX. Maksimal 10 MB.</p>
          </div>

          <div class="md:col-span-2 flex flex-wrap gap-3">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">Upload Dokumen</button>
            <Link :href="route('realisasi.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50">Ke Realisasi</Link>
            <Link :href="route('resume.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50">Lihat Resume</Link>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="mb-4 text-base font-semibold text-gray-700">Dokumen Terunggah</h3>
        <div v-if="dokumenUploads.length" class="space-y-3">
          <div v-for="dokumen in dokumenUploads" :key="dokumen.id" class="rounded-xl border border-gray-200 p-4">
            <div class="mb-2 flex items-start justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-gray-800">{{ dokumen.judul }}</p>
                <p class="mt-1 text-xs uppercase tracking-wide text-emerald-700">{{ formatJenisDokumen(dokumen.document_type) }} • {{ dokumen.tahun }}</p>
              </div>
              <span class="text-xs text-gray-400">{{ dokumen.created_at }}</span>
            </div>
            <div class="flex gap-2">
              <a :href="dokumen.view_url" target="_blank" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-100">Lihat</a>
              <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100" @click="hapusDokumen(dokumen.id)">Hapus</button>
            </div>
          </div>
        </div>
        <div v-else class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
          Belum ada dokumen yang diunggah.
        </div>
      </div>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-base font-semibold text-gray-700 mb-4">Menu Utama</h3>
        <ul class="space-y-3">
          <li>
            <Link :href="route('data-dasar.index')" class="flex items-center gap-3 p-3 rounded-lg hover:bg-lime-50 transition-colors">
              <span class="text-2xl">📊</span>
              <div>
                <p class="font-medium text-gray-800">Data Dasar</p>
                <p class="text-xs text-gray-500">Kelola data visi, program, kegiatan, dan sub kegiatan</p>
              </div>
            </Link>
          </li>
          <li>
            <Link :href="'/data-dasar/dokumen'" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors">
              <span class="text-2xl">📄</span>
              <div>
                <p class="font-medium text-gray-800">Dokumen</p>
                <p class="text-xs text-gray-500">Upload dan kelola dokumen perencanaan</p>
              </div>
            </Link>
          </li>
          <li>
            <Link :href="route('realisasi.index')" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors">
              <span class="text-2xl">📈</span>
              <div>
                <p class="font-medium text-gray-800">Realisasi</p>
                <p class="text-xs text-gray-500">Input capaian realisasi fisik dan keuangan</p>
              </div>
            </Link>
          </li>
          <li>
            <Link :href="route('resume.index')" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors">
              <span class="text-2xl">📋</span>
              <div>
                <p class="font-medium text-gray-800">Resume</p>
                <p class="text-xs text-gray-500">Lihat ringkasan monitoring evaluasi laporan</p>
              </div>
            </Link>
          </li>
        </ul>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-base font-semibold text-gray-700 mb-4">Informasi Sistem</h3>
        <dl class="space-y-3 text-sm">
          <div class="flex justify-between py-2 border-b border-gray-100">
            <dt class="text-gray-500">Nama Pengguna</dt>
            <dd class="font-medium text-gray-800">{{ user.name }}</dd>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100">
            <dt class="text-gray-500">Email</dt>
            <dd class="font-medium text-gray-800">{{ user.email }}</dd>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100">
            <dt class="text-gray-500">OPD</dt>
            <dd class="font-medium text-gray-800">{{ user.opd?.nama ?? 'Pemerintah Daerah' }}</dd>
          </div>
          <div class="flex justify-between py-2">
            <dt class="text-gray-500">Versi Sistem</dt>
            <dd class="font-medium text-blue-600">SIMEVLAP 2.0</dd>
          </div>
        </dl>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  stats: Object,
  user: Object,
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
const fileInputRef = ref(null);
const uploadForm = ref({
  document_type: '',
  judul: '',
  tahun: new Date().getFullYear(),
  file: null,
});

function onFileChange(event) {
  uploadForm.value.file = event.target.files?.[0] ?? null;
}

function submitUpload() {
  router.post(route('dashboard.dokumen.store'), {
    document_type: uploadForm.value.document_type,
    judul: uploadForm.value.judul,
    tahun: uploadForm.value.tahun,
    file: uploadForm.value.file,
  }, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      uploadForm.value = {
        document_type: '',
        judul: '',
        tahun: new Date().getFullYear(),
        file: null,
      };
      if (fileInputRef.value) {
        fileInputRef.value.value = '';
      }
    },
  });
}

function hapusDokumen(id) {
  router.delete(route('dashboard.dokumen.destroy', id), {
    preserveScroll: true,
  });
}

function formatJenisDokumen(value) {
  return String(value || '').toUpperCase();
}
</script>
