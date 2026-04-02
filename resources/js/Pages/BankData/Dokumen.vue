<template>
  <AppLayout title="Dokumen">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-lg font-semibold text-gray-700">Dokumen Perencanaan</h2>
      <button @click="showModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
        + Upload Dokumen
      </button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Judul</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Jenis Dokumen</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">OPD</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Tahun</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Diunggah Oleh</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="dokumen.data.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada dokumen yang diunggah.</td>
          </tr>
          <tr v-for="doc in dokumen.data" :key="doc.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-800">{{ doc.judul }}</td>
            <td class="px-4 py-3">
              <span class="inline-block bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded uppercase font-medium">{{ doc.document_type }}</span>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ doc.opd?.singkatan ?? 'Pemda' }}</td>
            <td class="px-4 py-3 text-gray-600">{{ doc.tahun }}</td>
            <td class="px-4 py-3 text-gray-600">{{ doc.uploaded_by?.name ?? '-' }}</td>
            <td class="px-4 py-3 text-center space-x-2">
              <a :href="`/storage/${doc.file_path}`" target="_blank" class="text-green-600 hover:text-green-800 text-xs font-medium">Lihat</a>
              <button @click="confirmDelete(doc)" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="dokumen.last_page > 1" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between text-sm text-gray-600">
        <span>Menampilkan {{ dokumen.from }}–{{ dokumen.to }} dari {{ dokumen.total }} data</span>
        <div class="flex gap-2">
          <Link v-if="dokumen.prev_page_url" :href="dokumen.prev_page_url" class="px-3 py-1 border rounded hover:bg-gray-100">‹ Prev</Link>
          <Link v-if="dokumen.next_page_url" :href="dokumen.next_page_url" class="px-3 py-1 border rounded hover:bg-gray-100">Next ›</Link>
        </div>
      </div>
    </div>

    <!-- Modal Upload -->
    <Modal :show="showModal" title="Upload Dokumen" @close="closeModal">
      <form @submit.prevent="submit" enctype="multipart/form-data">
        <InputField label="Judul Dokumen" :error="form.errors.judul" required>
          <input v-model="form.judul" type="text" class="input-base" />
        </InputField>
        <InputField label="Jenis Dokumen" :error="form.errors.document_type" required>
          <select v-model="form.document_type" class="input-base">
            <option value="">-- Pilih Jenis --</option>
            <option value="rpjmd">RPJMD</option>
            <option value="renstra">Renstra</option>
            <option value="renja">Renja</option>
            <option value="dpa">DPA</option>
          </select>
        </InputField>
        <InputField label="OPD" :error="form.errors.opd_id">
          <select v-model="form.opd_id" class="input-base">
            <option value="">-- Semua OPD / Pemda --</option>
            <option v-for="opd in opds" :key="opd.id" :value="opd.id">{{ opd.singkatan ?? opd.nama }}</option>
          </select>
        </InputField>
        <InputField label="Tahun" :error="form.errors.tahun" required>
          <input v-model="form.tahun" type="number" class="input-base" :min="2000" :max="2100" />
        </InputField>
        <InputField label="File PDF (maks. 10 MB)" :error="form.errors.file" required>
          <input type="file" accept=".pdf" class="input-base" @change="handleFile" />
        </InputField>
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" @click="closeModal" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ form.processing ? 'Mengunggah...' : 'Upload' }}
          </button>
        </div>
      </form>
    </Modal>

    <!-- Modal Konfirmasi Hapus -->
    <Modal :show="showDeleteModal" title="Konfirmasi Hapus" @close="showDeleteModal = false">
      <p class="text-sm text-gray-600 mb-4">Apakah Anda yakin ingin menghapus dokumen <strong>{{ selected?.judul }}</strong>?</p>
      <div class="flex justify-end gap-2">
        <button @click="showDeleteModal = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
        <button @click="deleteDoc" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Hapus</button>
      </div>
    </Modal>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputField from '@/Components/InputField.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  dokumen: Object,
  opds: Array,
});

const showModal = ref(false);
const showDeleteModal = ref(false);
const selected = ref(null);

const form = useForm({
  judul: '',
  document_type: '',
  opd_id: '',
  tahun: new Date().getFullYear(),
  file: null,
});

function handleFile(e) {
  form.file = e.target.files[0];
}

function closeModal() {
  showModal.value = false;
  form.reset();
  form.clearErrors();
}

function submit() {
  form.post(route('dokumen.store'), {
    forceFormData: true,
    onSuccess: () => closeModal(),
  });
}

function confirmDelete(doc) {
  selected.value = doc;
  showDeleteModal.value = true;
}

function deleteDoc() {
  router.delete(route('dokumen.destroy', selected.value.id), {
    onSuccess: () => { showDeleteModal.value = false; },
  });
}
</script>

<style scoped>
.input-base {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500;
}
</style>
