
<template>
  <AppLayout title="Bidang Urusan">
    <section class="mb-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <h1 class="text-2xl font-bold text-emerald-900">Daftar Bidang Urusan</h1>
        <div class="flex gap-2">
          <form @submit.prevent="searchBidang" class="flex gap-2">
            <input v-model="search" type="text" placeholder="Cari bidang urusan..." class="border-2 border-emerald-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400" />
            <button type="submit" class="bg-emerald-600 text-white rounded-lg px-4 py-2 font-bold hover:bg-emerald-700">Cari</button>
          </form>
          <button @click="openAdd" class="bg-emerald-600 text-white rounded-lg px-4 py-2 font-bold hover:bg-emerald-700">+ Tambah Bidang Urusan</button>
        </div>
      </div>
      <div class="overflow-x-auto border border-emerald-100 rounded-xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b border-emerald-100">
            <tr>
              <th class="px-4 py-3 text-left font-bold text-gray-700">Kode</th>
              <th class="px-4 py-3 text-left font-bold text-gray-700">NAMA_BIDANG_URUSAN</th>
              <th class="px-4 py-3 text-left font-bold text-gray-700 w-32">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="bidang in bidangUrusans.data" :key="bidang.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-2">{{ bidang.kode }}</td>
              <td class="px-4 py-2">{{ bidang.nama }}</td>
              <td class="px-4 py-2">
                <button @click="openEdit(bidang)" class="text-blue-600 hover:underline mr-2">Edit</button>
                <button @click="openDelete(bidang)" class="text-red-600 hover:underline">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-4 flex justify-center">
        <Pagination :links="bidangUrusans.links" />
      </div>
    </section>

    <!-- Modal Tambah/Edit -->
    <Modal :show="showForm" :title="formMode === 'add' ? 'Tambah Bidang Urusan' : 'Edit Bidang Urusan'" @close="closeForm">
      <form @submit.prevent="submitForm">
        <div class="mb-4">
          <label class="block mb-1 font-semibold">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.kode" type="text" class="form-input w-full border-2 border-emerald-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400" :class="{'is-invalid': errors.kode}" />
          <div v-if="errors.kode" class="text-red-600 text-sm mt-1">{{ errors.kode }}</div>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">Nama <span class="text-red-500">*</span></label>
          <input v-model="form.nama" type="text" class="form-input w-full border-2 border-emerald-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400" :class="{'is-invalid': errors.nama}" />
          <div v-if="errors.nama" class="text-red-600 text-sm mt-1">{{ errors.nama }}</div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button type="button" @click="closeForm" class="bg-gray-200 text-gray-700 rounded-lg px-4 py-2 font-semibold">Batal</button>
          <button type="submit" class="bg-emerald-600 text-white rounded-lg px-4 py-2 font-bold hover:bg-emerald-700">{{ formMode === 'add' ? 'Simpan' : 'Update' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Modal Konfirmasi Hapus -->
    <Modal :show="showDelete" title="Konfirmasi Hapus" @close="closeDelete">
      <div class="mb-4">Yakin ingin menghapus bidang urusan <b>{{ form.nama }}</b>?</div>
      <div class="flex justify-end gap-2">
        <button type="button" @click="closeDelete" class="bg-gray-200 text-gray-700 rounded-lg px-4 py-2 font-semibold">Batal</button>
        <button type="button" @click="submitDelete" class="bg-red-600 text-white rounded-lg px-4 py-2 font-bold hover:bg-red-700">Hapus</button>
      </div>
    </Modal>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import { ref, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';


const props = defineProps({
  bidangUrusans: Object,
  search: String,
});

const search = ref(props.search || '');

// Modal state
const showForm = ref(false);
const showDelete = ref(false);
const formMode = ref('add'); // 'add' | 'edit'
const form = reactive({ id: null, kode: '', nama: '' });
const errors = reactive({ kode: '', nama: '' });

function searchBidang() {
  router.get(route('bidang-urusan.index'), { search: search.value }, { preserveState: true, replace: true });
}

function openAdd() {
  formMode.value = 'add';
  form.id = null;
  form.kode = '';
  form.nama = '';
  errors.kode = '';
  errors.nama = '';
  showForm.value = true;
}

function openEdit(bidang) {
  formMode.value = 'edit';
  form.id = bidang.id;
  form.kode = bidang.kode;
  form.nama = bidang.nama;
  errors.kode = '';
  errors.nama = '';
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
}

function submitForm() {
  errors.kode = '';
  errors.nama = '';
  if (formMode.value === 'add') {
    router.post(route('bidang-urusan.store'), { kode: form.kode, nama: form.nama }, {
      preserveScroll: true,
      onError: (err) => {
        errors.kode = err.kode;
        errors.nama = err.nama;
      },
      onSuccess: () => {
        showForm.value = false;
      }
    });
  } else {
    router.put(route('bidang-urusan.update', form.id), { kode: form.kode, nama: form.nama }, {
      preserveScroll: true,
      onError: (err) => {
        errors.kode = err.kode;
        errors.nama = err.nama;
      },
      onSuccess: () => {
        showForm.value = false;
      }
    });
  }
}

function openDelete(bidang) {
  form.id = bidang.id;
  form.nama = bidang.nama;
  showDelete.value = true;
}

function closeDelete() {
  showDelete.value = false;
}

function submitDelete() {
  router.delete(route('bidang-urusan.destroy', form.id), {
    preserveScroll: true,
    onSuccess: () => {
      showDelete.value = false;
    }
  });
}
</script>
