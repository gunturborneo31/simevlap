<template>
  <AppLayout title="Pengaturan OPD">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-lg font-semibold text-gray-700">Daftar OPD</h2>
      <button @click="openAdd" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
        + Tambah OPD
      </button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Kode</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Nama OPD</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Singkatan</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Kepala OPD</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">Status</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="opds.data.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada data OPD.</td>
          </tr>
          <tr v-for="opd in opds.data" :key="opd.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-gray-700">{{ opd.kode }}</td>
            <td class="px-4 py-3 text-gray-800">{{ opd.nama }}</td>
            <td class="px-4 py-3 text-gray-600">{{ opd.singkatan ?? '-' }}</td>
            <td class="px-4 py-3 text-gray-600">{{ opd.kepala_opd ?? '-' }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="opd.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ opd.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center space-x-2">
              <button @click="openEdit(opd)" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
              <button @click="confirmDelete(opd)" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="opds.last_page > 1" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between text-sm text-gray-600">
        <span>Menampilkan {{ opds.from }}–{{ opds.to }} dari {{ opds.total }} data</span>
        <div class="flex gap-2">
          <Link v-if="opds.prev_page_url" :href="opds.prev_page_url" class="px-3 py-1 border rounded hover:bg-gray-100">‹ Prev</Link>
          <Link v-if="opds.next_page_url" :href="opds.next_page_url" class="px-3 py-1 border rounded hover:bg-gray-100">Next ›</Link>
        </div>
      </div>
    </div>

    <!-- Modal Tambah/Edit -->
    <Modal :show="showModal" :title="editing ? 'Edit OPD' : 'Tambah OPD'" @close="closeModal">
      <form @submit.prevent="submit">
        <InputField label="Kode OPD" :error="form.errors.kode" required>
          <input v-model="form.kode" type="text" class="input-base" placeholder="Contoh: 1.01" />
        </InputField>
        <InputField label="Nama OPD" :error="form.errors.nama" required>
          <input v-model="form.nama" type="text" class="input-base" />
        </InputField>
        <InputField label="Singkatan" :error="form.errors.singkatan">
          <input v-model="form.singkatan" type="text" class="input-base" />
        </InputField>
        <InputField label="Kepala OPD" :error="form.errors.kepala_opd">
          <input v-model="form.kepala_opd" type="text" class="input-base" />
        </InputField>
        <InputField label="NIP Kepala" :error="form.errors.nip_kepala">
          <input v-model="form.nip_kepala" type="text" class="input-base" />
        </InputField>
        <InputField label="Status">
          <label class="flex items-center gap-2 mt-1">
            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
            <span class="text-sm text-gray-700">Aktif</span>
          </label>
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
      <p class="text-sm text-gray-600 mb-4">Apakah Anda yakin ingin menghapus OPD <strong>{{ selected?.nama }}</strong>?</p>
      <div class="flex justify-end gap-2">
        <button @click="showDeleteModal = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
        <button @click="deleteOpd" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Hapus</button>
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

const props = defineProps({ opds: Object });

const showModal = ref(false);
const showDeleteModal = ref(false);
const editing = ref(false);
const selected = ref(null);

const form = useForm({
  kode: '',
  nama: '',
  singkatan: '',
  kepala_opd: '',
  nip_kepala: '',
  is_active: true,
});

function openAdd() {
  editing.value = false;
  form.reset();
  form.is_active = true;
  showModal.value = true;
}

function openEdit(opd) {
  editing.value = true;
  selected.value = opd;
  form.kode = opd.kode;
  form.nama = opd.nama;
  form.singkatan = opd.singkatan ?? '';
  form.kepala_opd = opd.kepala_opd ?? '';
  form.nip_kepala = opd.nip_kepala ?? '';
  form.is_active = opd.is_active;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  form.clearErrors();
}

function submit() {
  if (editing.value) {
    form.put(route('pengaturan.opd.update', selected.value.id), {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post(route('pengaturan.opd.store'), {
      onSuccess: () => closeModal(),
    });
  }
}

function confirmDelete(opd) {
  selected.value = opd;
  showDeleteModal.value = true;
}

function deleteOpd() {
  router.delete(route('pengaturan.opd.destroy', selected.value.id), {
    onSuccess: () => { showDeleteModal.value = false; },
  });
}
</script>

<style scoped>
.input-base {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500;
}
</style>
