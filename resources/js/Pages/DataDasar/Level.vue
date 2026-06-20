<template>
  <AppLayout
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Bank Data', href: route('data-dasar.bank-data') },
      { label: pageTitle, href: route('data-dasar.bank-data.level', { level }) }
    ]"
    :right-info="peraturanLabel"
  >
    <section class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between gap-3">
        <h3 class="text-base font-semibold text-gray-700">Daftar {{ pageTitle }}</h3>
        <div class="flex w-full max-w-xl items-center gap-2">
          <input
            v-model="search"
            type="text"
            placeholder="Cari kode, uraian, deskripsi, relasi..."
            class="input-base"
          />
          <button @click="openAdd" class="shrink-0 rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white hover:bg-emerald-800">+ Tambah</button>
        </div>
      </div>

      <div class="overflow-hidden rounded-lg border border-gray-200">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr v-if="isIndikatorLevel">
              <th class="px-4 py-3 text-center font-semibold text-gray-600 w-12">No</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Uraian</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Satuan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Keterangan</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Aksi</th>
            </tr>
            <tr v-else>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Kode</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Uraian</th>
              <!-- <th class="px-4 py-3 text-left font-semibold text-gray-600">Deskripsi</th> -->
              <!-- <th v-if="showPaguColumn" class="px-4 py-3 text-left font-semibold text-gray-600">Pagu</th> -->
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="filteredRows.length === 0">
              <td :colspan="emptyColspan" class="px-4 py-8 text-center text-gray-400">Data tidak ditemukan.</td>
            </tr>
            <tr v-for="(item, index) in filteredRows" :key="item.id" class="hover:bg-gray-50">
              <template v-if="isIndikatorLevel">
                <td class="px-4 py-3 text-center text-gray-500">{{ index + 1 }}</td>
                <td class="px-4 py-3 text-gray-800">{{ item.uraian }}</td>
                <td class="px-4 py-3 text-gray-700">{{ item.satuan }}</td>
                <td class="px-4 py-3 text-gray-600">{{ item.keterangan ?? '-' }}</td>
              </template>
              <template v-else>
                <td class="px-4 py-3 text-gray-700 font-mono">{{ item.kode }}</td>
                <td class="px-4 py-3 text-gray-800">{{ item.uraian }}</td>
              </template>
              <td class="px-4 py-3 text-center space-x-2">
                <button @click="openEdit(item)" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                <button @click="confirmDelete(item)" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
                <button
                  v-if="level === 'program'"
                  :class="item.is_prioritas ? 'btn btn-warning btn-sm rounded-lg' : 'btn btn-outline-primary btn-sm rounded-lg'"
                  @click="togglePrioritas(item)"
                >
                  {{ item.is_prioritas ? 'Prioritas' : 'Jadikan Prioritas' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Modal :show="showModal" :title="editing ? `Edit ${pageTitle}` : `Tambah ${pageTitle}`" @close="closeModal">
      <form @submit.prevent="submit">
        <template v-if="isIndikatorLevel">
          <InputField label="Uraian" :error="form.errors.uraian" required>
            <textarea v-model="form.uraian" rows="3" class="input-base" />
          </InputField>

          <InputField label="Satuan" :error="form.errors.satuan" required>
            <input v-model="form.satuan" type="text" class="input-base" />
          </InputField>

          <InputField label="Keterangan" :error="form.errors.keterangan">
            <textarea v-model="form.keterangan" rows="3" class="input-base" />
          </InputField>
        </template>

        <template v-else>
          <InputField label="Kode" :error="form.errors.kode" required>
            <input v-model="form.kode" type="text" class="input-base" />
          </InputField>

          <InputField label="Uraian" :error="form.errors.uraian" required>
            <textarea v-model="form.uraian" rows="3" class="input-base" />
          </InputField>

          <InputField v-if="['visi', 'misi', 'tujuan', 'sasaran', 'strategi', 'arah-kebijakan'].includes(props.level)" label="Deskripsi" :error="form.errors.deskripsi" required>
            <textarea v-model="form.deskripsi" rows="4" class="input-base" />
          </InputField>

          <template v-if="props.level === 'visi'">
            <div class="grid grid-cols-2 gap-4">
              <InputField label="Tahun Awal" :error="form.errors.tahun_awal" required>
                <input v-model.number="form.tahun_awal" type="number" class="input-base" />
              </InputField>
              <InputField label="Tahun Akhir" :error="form.errors.tahun_akhir" required>
                <input v-model.number="form.tahun_akhir" type="number" class="input-base" />
              </InputField>
            </div>
          </template>
          <!-- <InputField v-if="requiresPagu" label="Pagu" :error="form.errors.pagu" required>
            <input v-model="form.pagu" type="number" min="0" class="input-base" />
          </InputField> -->
        </template>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="closeModal" class="rounded-lg border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Batal</button>
          <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white hover:bg-emerald-800 disabled:opacity-50">
            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </Modal>

    <Modal :show="showDelete" title="Konfirmasi Hapus" @close="showDelete = false">
      <p class="mb-4 text-sm text-gray-600">Yakin ingin menghapus data ini?</p>
      <div class="flex justify-end gap-2">
        <button @click="showDelete = false" class="rounded-lg border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Batal</button>
        <button @click="destroy" class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700">Hapus</button>
      </div>
    </Modal>
  </AppLayout>
</template>

<script setup>
function togglePrioritas(item) {
  router.post(
    route('data-dasar.program.toggle-prioritas', { program: item.id }),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {},
    }
  );
}
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputField from '@/Components/InputField.vue';
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
  level: String,
  rows: Array,
  parents: Array,
  activePeraturan: Object,
});

const labels = {
  iku: 'IKU',
  ikk: 'IKK',
  visi: 'Visi',
  misi: 'Misi',
  tujuan: 'Tujuan',
  sasaran: 'Sasaran',
  strategi: 'Strategi',
  'arah-kebijakan': 'Arah Kebijakan',
  program: 'Program',
  'program-aksi': 'Program Aksi',
  'program-prioritas': 'Program Prioritas',
  kegiatan: 'Kegiatan',
  'sub-kegiatan': 'Sub Kegiatan',
};

const pageTitle = computed(() => labels[props.level] ?? 'Data');
const isIndikatorLevel = computed(() => ['iku', 'ikk'].includes(props.level));
const usesActivePeraturan = computed(() => ['program', 'program-aksi', 'program-prioritas', 'kegiatan', 'sub-kegiatan'].includes(props.level));
const peraturanLabel = computed(() => {
  if (!usesActivePeraturan.value) return '';

  if (!props.activePeraturan?.kode) {
    return 'Peraturan aktif : Belum diatur (atur di Pengaturan Kepmen)';
  }

  return `Peraturan ( ${props.activePeraturan.kode} - ${props.activePeraturan.nama} )`;
});
const requiresPagu = computed(() => ['kegiatan', 'sub-kegiatan'].includes(props.level));
const showPaguColumn = computed(() => requiresPagu.value);
const emptyColspan = computed(() => {
  if (isIndikatorLevel.value) return 4;
  return showPaguColumn.value ? 5 : 4;
});

const showModal = ref(false);
const showDelete = ref(false);
const editing = ref(false);
const selected = ref(null);
const search = ref('');
const codeCollator = new Intl.Collator('id-ID', { numeric: true, sensitivity: 'base' });

const form = useForm({
  kode: '',
  uraian: '',
  deskripsi: '',
  // pagu: '',
  satuan: '',
  keterangan: '',
  tahun_awal: new Date().getFullYear(),
  tahun_akhir: new Date().getFullYear() + 5,
});

const filteredRows = computed(() => {
  const keyword = search.value.trim().toLowerCase();
  const rows = !keyword
    ? [...props.rows]
    : props.rows.filter((item) => {
    const haystack = [
      item.kode,
      item.uraian,
      // item.deskripsi,
      // item.pagu,
      item.satuan,
      item.keterangan,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return haystack.includes(keyword);
  });

  return rows.sort((a, b) => {
    const kodeA = (a.kode ?? '').toString();
    const kodeB = (b.kode ?? '').toString();

    if (kodeA || kodeB) {
      const byCode = codeCollator.compare(kodeA, kodeB);
      if (byCode !== 0) return byCode;
    }

    return codeCollator.compare((a.uraian ?? '').toString(), (b.uraian ?? '').toString());
  });
});

function openAdd() {
  editing.value = false;
  selected.value = null;
  form.reset();
  showModal.value = true;
}

function openEdit(item) {
  editing.value = true;
  selected.value = item;
  form.kode = item.kode ?? '';
  form.uraian = item.uraian;
  form.deskripsi = item.deskripsi ?? '';
  // form.pagu = item.pagu ?? '';
  form.satuan = item.satuan ?? '';
  form.keterangan = item.keterangan ?? '';
  form.tahun_awal = item.tahun_awal ?? new Date().getFullYear();
  form.tahun_akhir = item.tahun_akhir ?? new Date().getFullYear() + 5;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  form.clearErrors();
}

function submit() {
  if (isIndikatorLevel.value) {
    const payload = {
      uraian: form.uraian,
      satuan: form.satuan,
      keterangan: form.keterangan,
    };

    if (editing.value && selected.value) {
      form.transform(() => payload).put(route('data-dasar.bank-data.level.update', { level: props.level, id: selected.value.id }), {
        onSuccess: () => closeModal(),
        preserveScroll: true,
      });
    } else {
      form.transform(() => payload).post(route('data-dasar.bank-data.level.store', { level: props.level }), {
        onSuccess: () => closeModal(),
        preserveScroll: true,
      });
    }

    return;
  }

  // Special handling for Visi: use dedicated routes
  if (props.level === 'visi') {
    const payload = {
      kode: form.kode,
      uraian: form.uraian,
      deskripsi: form.deskripsi,
      tahun_awal: form.tahun_awal,
      tahun_akhir: form.tahun_akhir,
      document_type: 'rpjmd',
    };

    if (editing.value && selected.value) {
      form.transform(() => payload).put(route('data-dasar.visi.update', { visi: selected.value.id }), {
        onSuccess: () => closeModal(),
        preserveScroll: true,
      });
    } else {
      form.transform(() => payload).post(route('data-dasar.visi.store'), {
        onSuccess: () => closeModal(),
        preserveScroll: true,
      });
    }

    return;
  }

  const payload = {
    kode: form.kode,
    uraian: form.uraian,
    deskripsi: ['visi', 'misi', 'tujuan', 'sasaran', 'strategi', 'arah-kebijakan'].includes(props.level) ? form.deskripsi : undefined,
    // pagu: requiresPagu.value ? form.pagu : null,
  };

  if (editing.value && selected.value) {
    form.transform(() => payload).put(route('data-dasar.bank-data.level.update', { level: props.level, id: selected.value.id }), {
      onSuccess: () => closeModal(),
      preserveScroll: true,
    });
  } else {
    form.transform(() => payload).post(route('data-dasar.bank-data.level.store', { level: props.level }), {
      onSuccess: () => closeModal(),
      preserveScroll: true,
    });
  }
}

function confirmDelete(item) {
  selected.value = item;
  showDelete.value = true;
}

function destroy() {
  if (!selected.value) return;
  router.delete(route('data-dasar.bank-data.level.destroy', { level: props.level, id: selected.value.id }), {
    onSuccess: () => { showDelete.value = false; },
    preserveScroll: true,
  });
}

function formatCurrency(value) {
  return 'Rp ' + Number(value).toLocaleString('id-ID');
}
</script>

<style scoped>
@reference "../../../css/app.css";

.input-base {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500;
}
</style>
