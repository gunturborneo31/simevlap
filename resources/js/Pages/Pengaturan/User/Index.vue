<template>
  <AppLayout title="Pengaturan User">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-lg font-semibold text-gray-700">Daftar Pengguna</h2>
      <button @click="openAdd" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
        + Tambah User
      </button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Nama</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Email</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">OPD</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Role</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">Status</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="users.data.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada data pengguna.</td>
          </tr>
          <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-800">{{ user.name }}</td>
            <td class="px-4 py-3 text-gray-600">{{ user.email }}</td>
            <td class="px-4 py-3 text-gray-600">{{ user.opd?.singkatan ?? 'Pemda' }}</td>
            <td class="px-4 py-3">
              <span v-for="role in user.roles" :key="role.id" class="inline-block bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full mr-1">{{ role.name }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="user.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center space-x-2">
              <button @click="openEdit(user)" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
              <button @click="confirmDelete(user)" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="users.last_page > 1" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between text-sm text-gray-600">
        <span>Menampilkan {{ users.from }}–{{ users.to }} dari {{ users.total }} data</span>
        <div class="flex gap-2">
          <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="px-3 py-1 border rounded hover:bg-gray-100">‹ Prev</Link>
          <Link v-if="users.next_page_url" :href="users.next_page_url" class="px-3 py-1 border rounded hover:bg-gray-100">Next ›</Link>
        </div>
      </div>
    </div>

    <!-- Modal Tambah/Edit -->
    <Modal :show="showModal" :title="editing ? 'Edit User' : 'Tambah User'" @close="closeModal">
      <form @submit.prevent="submit">
        <InputField label="Nama Lengkap" :error="form.errors.name" required>
          <input v-model="form.name" type="text" class="input-base" />
        </InputField>
        <InputField label="Email" :error="form.errors.email" required>
          <input v-model="form.email" type="email" class="input-base" />
        </InputField>
        <InputField :label="editing ? 'Password (kosongkan jika tidak diubah)' : 'Password'" :error="form.errors.password" :required="!editing">
          <input v-model="form.password" type="password" class="input-base" placeholder="Min. 8 karakter" />
        </InputField>
        <InputField label="OPD" :error="form.errors.opd_id">
          <select v-model="form.opd_id" class="input-base">
            <option value="">-- Superadmin / Pemda --</option>
            <option v-for="opd in opds" :key="opd.id" :value="opd.id">{{ opd.singkatan ?? opd.nama }}</option>
          </select>
        </InputField>
        <InputField label="Role" :error="form.errors.role" required>
          <select v-model="form.role" class="input-base">
            <option value="">-- Pilih Role --</option>
            <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
          </select>
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
      <p class="text-sm text-gray-600 mb-4">Apakah Anda yakin ingin menghapus pengguna <strong>{{ selected?.name }}</strong>?</p>
      <div class="flex justify-end gap-2">
        <button @click="showDeleteModal = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
        <button @click="deleteUser" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Hapus</button>
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
  users: Object,
  opds: Array,
  roles: Array,
});

const showModal = ref(false);
const showDeleteModal = ref(false);
const editing = ref(false);
const selected = ref(null);

const form = useForm({
  name: '',
  email: '',
  password: '',
  opd_id: '',
  role: '',
  is_active: true,
});

function openAdd() {
  editing.value = false;
  form.reset();
  form.is_active = true;
  showModal.value = true;
}

function openEdit(user) {
  editing.value = true;
  selected.value = user;
  form.name = user.name;
  form.email = user.email;
  form.password = '';
  form.opd_id = user.opd_id ?? '';
  form.role = user.roles?.[0]?.name ?? '';
  form.is_active = user.is_active;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  form.clearErrors();
}

function submit() {
  if (editing.value) {
    form.put(route('pengaturan.user.update', selected.value.id), {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post(route('pengaturan.user.store'), {
      onSuccess: () => closeModal(),
    });
  }
}

function confirmDelete(user) {
  selected.value = user;
  showDeleteModal.value = true;
}

function deleteUser() {
  router.delete(route('pengaturan.user.destroy', selected.value.id), {
    onSuccess: () => { showDeleteModal.value = false; },
  });
}
</script>

<style scoped>
.input-base {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500;
}
</style>
