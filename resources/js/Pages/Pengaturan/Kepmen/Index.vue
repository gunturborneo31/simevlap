<template>
  <AppLayout title="Pengaturan Kepmen">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-lg font-semibold text-gray-700">Daftar Keputusan Menteri</h2>
      <button @click="openAdd" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
        + Tambah Kepmen
      </button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Kode</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Nama</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Tahun</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Keterangan</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="kepmen.data.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada data Kepmen.</td>
          </tr>
          <tr v-for="item in kepmen.data" :key="item.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-gray-700">{{ item.kode }}</td>
            <td class="px-4 py-3 text-gray-800">{{ item.nama }}</td>
            <td class="px-4 py-3 text-gray-600">{{ item.tahun }}</td>
            <td class="px-4 py-3 text-gray-500 max-w-xs truncate">{{ item.keterangan ?? '-' }}</td>
            <td class="px-4 py-3 text-center space-x-2">
              <button @click="openEdit(item)" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
              <button @click="confirmDelete(item)" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="kepmen.last_page > 1" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between text-sm text-gray-600">
        <span>Menampilkan {{ kepmen.from }}–{{ kepmen.to }} dari {{ kepmen.total }} data</span>
        <div class="flex gap-2">
          <Link v-if="kepmen.prev_page_url" :href="kepmen.prev_page_url" class="px-3 py-1 border rounded hover:bg-gray-100">‹ Prev</Link>
          <Link v-if="kepmen.next_page_url" :href="kepmen.next_page_url" class="px-3 py-1 border rounded hover:bg-gray-100">Next ›</Link>
        </div>
      </div>
    </div>

    <!-- Modal Tambah/Edit -->
    <Modal :show="showModal" :title="editing ? 'Edit Kepmen' : 'Tambah Kepmen'" @close="closeModal">
      <form @submit.prevent="submit">
        <InputField label="Kode" :error="form.errors.kode" required>
          <input v-model="form.kode" type="text" class="input-base" placeholder="Contoh: PERMENDAGRI-90/2019" />
        </InputField>
        <InputField label="Nama / Judul" :error="form.errors.nama" required>
          <textarea v-model="form.nama" rows="3" class="input-base" />
        </InputField>
        <InputField label="Tahun" :error="form.errors.tahun" required>
          <input v-model="form.tahun" type="text" class="input-base" placeholder="Contoh: 2019" />
        </InputField>
        <InputField label="Keterangan" :error="form.errors.keterangan">
          <textarea v-model="form.keterangan" rows="2" class="input-base" />
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
      <p class="text-sm text-gray-600 mb-4">Apakah Anda yakin ingin menghapus <strong>{{ selected?.kode }}</strong>?</p>
      <div class="flex justify-end gap-2">
        <button @click="showDeleteModal = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
        <button @click="deleteItem" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Hapus</button>
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

const props = defineProps({ kepmen: Object });

const showModal = ref(false);
const showDeleteModal = ref(false);
const editing = ref(false);
const selected = ref(null);

const form = useForm({
  kode: '',
  nama: '',
  tahun: '',
  keterangan: '',
});

function openAdd() {
  editing.value = false;
  form.reset();
  showModal.value = true;
}

function openEdit(item) {
  editing.value = true;
  selected.value = item;
  form.kode = item.kode;
  form.nama = item.nama;
  form.tahun = item.tahun;
  form.keterangan = item.keterangan ?? '';
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  form.clearErrors();
}

function submit() {
  if (editing.value) {
    form.put(route('pengaturan.kepmen.update', selected.value.id), {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post(route('pengaturan.kepmen.store'), {
      onSuccess: () => closeModal(),
    });
  }
}

function confirmDelete(item) {
  selected.value = item;
  showDeleteModal.value = true;
}

function deleteItem() {
  router.delete(route('pengaturan.kepmen.destroy', selected.value.id), {
    onSuccess: () => { showDeleteModal.value = false; },
  });
}
</script>

<style scoped>
.input-base {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500;
}
</style>
