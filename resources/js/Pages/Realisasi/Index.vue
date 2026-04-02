<template>
  <AppLayout title="Realisasi">
    <!-- Filter Bar -->
    <div class="flex flex-wrap items-end gap-4 mb-6">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Jenis Dokumen</label>
        <select v-model="filters.document_type" @change="applyFilter" class="input-filter">
          <option value="rpjmd">RPJMD</option>
          <option value="renstra">Renstra</option>
          <option value="renja">Renja</option>
          <option value="dpa">DPA</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Tahun</label>
        <input v-model="filters.tahun" type="number" @change="applyFilter" class="input-filter w-28" />
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Triwulan</label>
        <select v-model="filters.triwulan" @change="applyFilter" class="input-filter">
          <option value="1">Triwulan I</option>
          <option value="2">Triwulan II</option>
          <option value="3">Triwulan III</option>
          <option value="4">Triwulan IV</option>
        </select>
      </div>
    </div>

    <!-- Program List -->
    <div class="space-y-4">
      <div v-if="program.length === 0" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Belum ada data program untuk filter ini.
      </div>

      <div v-for="prog in program" :key="prog.id" class="bg-white rounded-xl shadow">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <div>
            <p class="text-xs font-mono text-gray-500">{{ prog.kode_rek }}</p>
            <p class="font-semibold text-gray-800">{{ prog.nama_rincian }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Kepmen: {{ prog.kepmen?.kode ?? '-' }}</p>
          </div>
          <div class="text-right">
            <p class="text-xs text-gray-500">Pagu</p>
            <p class="font-semibold text-gray-700">{{ formatCurrency(prog.pagu) }}</p>
          </div>
        </div>

        <div class="px-5 py-4">
          <div v-if="prog.realisasi && prog.realisasi.length > 0">
            <div v-for="real in prog.realisasi" :key="real.id" class="flex items-center justify-between text-sm mb-2">
              <div class="flex items-center gap-6">
                <div>
                  <span class="text-xs text-gray-500">Realisasi Fisik</span>
                  <div class="flex items-center gap-2 mt-0.5">
                    <div class="w-24 bg-gray-200 rounded-full h-2">
                      <div class="bg-blue-500 h-2 rounded-full" :style="{ width: Math.min(real.realisasi_fisik, 100) + '%' }"></div>
                    </div>
                    <span class="text-xs font-medium text-blue-600">{{ real.realisasi_fisik }}%</span>
                  </div>
                </div>
                <div>
                  <span class="text-xs text-gray-500">Realisasi Keuangan</span>
                  <p class="font-medium text-gray-700 text-xs mt-0.5">{{ formatCurrency(real.realisasi_keuangan) }}</p>
                </div>
                <div v-if="real.catatan">
                  <span class="text-xs text-gray-500">Catatan</span>
                  <p class="text-xs text-gray-600 mt-0.5">{{ real.catatan }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <button @click="openEdit(prog, real)" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                <button @click="confirmDelete(real)" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-gray-400 mb-2">Belum ada realisasi untuk triwulan ini.</div>
          <button @click="openAdd(prog)" class="mt-2 text-xs text-blue-600 hover:text-blue-800 font-medium border border-blue-300 rounded px-3 py-1 hover:bg-blue-50">
            + Input Realisasi
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Input/Edit Realisasi -->
    <Modal :show="showModal" :title="editing ? 'Edit Realisasi' : 'Input Realisasi'" @close="closeModal">
      <div v-if="selectedProgram" class="mb-4 p-3 bg-blue-50 rounded-lg text-sm">
        <p class="font-medium text-blue-800">{{ selectedProgram.nama_rincian }}</p>
        <p class="text-blue-600 text-xs mt-0.5">{{ filters.document_type.toUpperCase() }} — Triwulan {{ filters.triwulan }} Tahun {{ filters.tahun }}</p>
      </div>
      <form @submit.prevent="submit">
        <InputField label="Realisasi Fisik (%)" :error="form.errors.realisasi_fisik" required>
          <input v-model="form.realisasi_fisik" type="number" step="0.01" min="0" max="100" class="input-base" />
        </InputField>
        <InputField label="Realisasi Keuangan (Rp)" :error="form.errors.realisasi_keuangan">
          <input v-model="form.realisasi_keuangan" type="number" min="0" class="input-base" />
        </InputField>
        <InputField label="Sisa Anggaran (Rp)" :error="form.errors.sisa_anggaran">
          <input v-model="form.sisa_anggaran" type="number" min="0" class="input-base" />
        </InputField>
        <InputField label="Catatan" :error="form.errors.catatan">
          <textarea v-model="form.catatan" rows="2" class="input-base" />
        </InputField>
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" @click="closeModal" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </Modal>

    <!-- Modal Konfirmasi Hapus -->
    <Modal :show="showDeleteModal" title="Konfirmasi Hapus" @close="showDeleteModal = false">
      <p class="text-sm text-gray-600 mb-4">Apakah Anda yakin ingin menghapus data realisasi ini?</p>
      <div class="flex justify-end gap-2">
        <button @click="showDeleteModal = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
        <button @click="deleteReal" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Hapus</button>
      </div>
    </Modal>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputField from '@/Components/InputField.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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
.input-base {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500;
}
.input-filter {
  @apply border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500;
}
</style>
