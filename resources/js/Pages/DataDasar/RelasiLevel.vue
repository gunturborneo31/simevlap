<template>
  <AppLayout
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Relasi', href: route('data-dasar.relasi') },
      { label: pageTitle, href: route('data-dasar.relasi.level', { level }) }
    ]"
    :right-info="peraturanLabel"
  >
    <section class="rounded-2xl border border-teal-100 bg-white p-5 shadow-sm">
      <div class="mb-5 grid grid-cols-1 gap-3 lg:grid-cols-3">
        <div class="rounded-xl border border-teal-100 bg-teal-50/60 p-3">
          <p class="text-xs uppercase tracking-wide text-teal-700">Total Data</p>
          <p class="mt-1 text-2xl font-semibold text-teal-900">{{ filteredRows.length }}</p>
        </div>
        <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
          <p class="text-xs uppercase tracking-wide text-emerald-700">Sudah Terhubung</p>
          <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ linkedCount }}</p>
        </div>
        <div class="rounded-xl border border-amber-100 bg-amber-50/60 p-3">
          <p class="text-xs uppercase tracking-wide text-amber-700">Belum Terhubung</p>
          <p class="mt-1 text-2xl font-semibold text-amber-900">{{ unlinkedCount }}</p>
        </div>
      </div>

      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <h3 class="text-base font-semibold text-gray-700">Relasi {{ pageTitle }}</h3>
          <p class="mt-0.5 text-xs text-gray-400">Baris atas adalah konekting {{ parentLabel }}, bagian bawah menampilkan data {{ pageTitle }} yang terelasi.</p>
        </div>
        <div class="flex w-full max-w-xl items-center justify-end gap-2">
          <button
            type="button"
            @click="statusFilter = 'all'"
            :class="statusFilter === 'all' ? 'bg-slate-700 text-white border-slate-700' : 'border-slate-200 text-slate-600'"
            class="rounded-lg border px-3 py-2 text-xs font-medium"
          >
            Semua
          </button>
          <button
            type="button"
            @click="statusFilter = 'linked'"
            :class="statusFilter === 'linked' ? 'bg-emerald-700 text-white border-emerald-700' : 'border-emerald-200 text-emerald-700'"
            class="rounded-lg border px-3 py-2 text-xs font-medium"
          >
            Sudah Terhubung
          </button>
          <button
            type="button"
            @click="statusFilter = 'unlinked'"
            :class="statusFilter === 'unlinked' ? 'bg-amber-600 text-white border-amber-600' : 'border-amber-200 text-amber-700'"
            class="rounded-lg border px-3 py-2 text-xs font-medium"
          >
            Belum Terhubung
          </button>
          <input
            v-model="search"
            type="text"
            placeholder="Cari kode, uraian, atau relasi..."
            class="input-base w-full max-w-xs"
          />
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200">
        <table class="w-full text-sm">
          <thead class="border-b border-gray-200 bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Level</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Kode</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Uraian</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-if="groupedParents.length">
              <template v-for="group in groupedParents" :key="`parent-${group.id}`">
                <tr class="bg-slate-100/80">
                  <td class="px-4 py-3 align-top text-xs font-semibold uppercase tracking-wide text-slate-600">Konekting Atas</td>
                  <td class="px-4 py-3 text-xs font-mono text-slate-700">{{ group.kode || '-' }}</td>
                  <td class="px-4 py-3 text-slate-800">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="font-semibold">{{ group.uraian || group.label }}</span>
                      <span class="rounded-full border border-teal-200 bg-teal-50 px-2 py-0.5 text-xs text-teal-700">{{ group.children.length }} relasi</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <button
                      type="button"
                      @click="openParentEdit(group)"
                      class="rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                    >
                      Atur dari Atas
                    </button>
                  </td>
                </tr>

                <tr
                  v-for="child in group.children"
                  :key="`child-${group.id}-${child.id}`"
                  class="bg-white hover:bg-gray-50"
                >
                  <td class="px-4 py-3 text-xs text-gray-500">Turunan</td>
                  <td class="px-4 py-3 font-mono text-gray-700">{{ child.kode }}</td>
                  <td class="px-4 py-3 text-gray-800">{{ child.uraian }}</td>
                  <td class="px-4 py-3 text-center">
                    <button
                      type="button"
                      @click="openChildEdit(child)"
                      class="rounded-md border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700 hover:bg-teal-100"
                    >
                      Ubah dari Bawah
                    </button>
                  </td>
                </tr>
              </template>
            </template>

            <tr v-if="unlinkedRows.length" class="bg-amber-50/60">
              <td colspan="4" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-amber-800">
                Data Belum Terhubung ({{ unlinkedRows.length }})
              </td>
            </tr>
            <tr
              v-for="item in unlinkedRows"
              :key="`unlinked-${item.id}`"
              class="bg-white hover:bg-amber-50/40"
            >
              <td class="px-4 py-3 text-xs text-amber-700">Belum Terhubung</td>
              <td class="px-4 py-3 font-mono text-gray-700">{{ item.kode }}</td>
              <td class="px-4 py-3 text-gray-800">{{ item.uraian }}</td>
              <td class="px-4 py-3 text-center">
                <button
                  type="button"
                  @click="openChildEdit(item)"
                  class="rounded-md border border-amber-300 bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 hover:bg-amber-200"
                >
                  Atur Relasi
                </button>
              </td>
            </tr>

            <tr v-if="!groupedParents.length && !unlinkedRows.length">
              <td colspan="4" class="px-4 py-8 text-center text-gray-400">Data tidak ditemukan.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Modal :show="showParentModal" :title="`Atur dari Konekting ${parentLabel}`" @close="closeParentModal">
      <form @submit.prevent="submitParent">
        <div class="mb-3">
          <p class="text-sm text-gray-500 mb-1">Konekting atas:</p>
          <p class="text-sm font-medium text-gray-800">{{ selectedParent?.label }}</p>
        </div>

        <InputField :label="`${pageTitle} yang terelasi`" :error="parentForm.errors.child_ids">
          <div class="mb-2 flex items-center justify-between gap-2">
            <p class="text-xs text-gray-500">Pilih data {{ pageTitle }} yang ingin dikoneksikan.</p>
            <div class="space-x-2">
              <button type="button" @click="selectAllChildren" class="rounded-md border border-indigo-200 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Pilih Semua</button>
              <button type="button" @click="clearAllChildren" class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">Kosongkan</button>
            </div>
          </div>
          <div class="mb-2 max-h-56 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3">
            <label v-for="row in rowsForSelection" :key="`select-child-${row.id}`" class="flex cursor-pointer items-start gap-2 rounded-md border border-transparent p-2 hover:border-indigo-100 hover:bg-indigo-50/60">
              <input
                :value="row.id"
                v-model="parentForm.child_ids"
                type="checkbox"
                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
              <span class="text-sm text-gray-700">{{ row.kode }} - {{ row.uraian }}</span>
            </label>
          </div>
        </InputField>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="closeParentModal" class="rounded-lg border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Batal</button>
          <button type="submit" :disabled="parentForm.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-50">
            {{ parentForm.processing ? 'Menyimpan...' : 'Simpan dari Atas' }}
          </button>
        </div>
      </form>
    </Modal>

    <Modal :show="showChildModal" :title="`Atur Relasi ${pageTitle}`" @close="closeChildModal">
      <form @submit.prevent="submit">
        <div class="mb-3">
          <p class="text-sm text-gray-500 mb-1">Data:</p>
          <p class="text-sm font-medium text-gray-800">{{ selectedChild?.kode }} — {{ selectedChild?.uraian }}</p>
        </div>

        <InputField :label="`${parentLabel} (boleh lebih dari satu)`" :error="childForm.errors.parent_ids">
          <div class="mb-2 flex items-center justify-between gap-2">
            <p class="text-xs text-gray-500">Centang satu atau beberapa relasi yang sesuai.</p>
            <div class="space-x-2">
              <button type="button" @click="selectAllParents" class="rounded-md border border-teal-200 px-2.5 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50">Pilih Semua</button>
              <button type="button" @click="clearAllParents" class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">Kosongkan</button>
            </div>
          </div>
          <div class="mb-2 max-h-52 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3">
            <label v-for="p in parents" :key="p.id" class="flex cursor-pointer items-start gap-2 rounded-md border border-transparent p-2 hover:border-teal-100 hover:bg-teal-50/60">
              <input
                :value="p.id"
                v-model="childForm.parent_ids"
                type="checkbox"
                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500"
              />
              <span class="text-sm text-gray-700">{{ p.label }}</span>
            </label>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <span v-if="selectedLabels.length === 0" class="text-xs italic text-gray-400">Belum ada relasi dipilih</span>
            <span
              v-for="label in selectedLabels"
              :key="`selected-${label}`"
              class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs text-emerald-800"
            >
              {{ label }}
            </span>
          </div>
        </InputField>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="closeChildModal" class="rounded-lg border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Batal</button>
          <button type="submit" :disabled="childForm.processing" class="rounded-lg bg-teal-600 px-4 py-2 text-sm text-white hover:bg-teal-700 disabled:opacity-50">
            {{ childForm.processing ? 'Menyimpan...' : 'Simpan dari Bawah' }}
          </button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputField from '@/Components/InputField.vue';
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  level: String,
  rows: Array,
  parents: Array,
  activePeraturan: Object,
});

const labels = {
  misi:            { title: 'Misi',           parent: 'Visi' },
  tujuan:          { title: 'Tujuan',         parent: 'Misi' },
  sasaran:         { title: 'Sasaran',        parent: 'Tujuan' },
  strategi:        { title: 'Strategi',       parent: 'Sasaran' },
  'arah-kebijakan':{ title: 'Arah Kebijakan', parent: 'Strategi' },
  urusan:          { title: 'Urusan',         parent: '-' },
  'bidang-urusan': { title: 'Bidang Urusan', parent: 'Urusan' },
  program:         { title: 'Program',        parent: 'Bidang Urusan' },
  kegiatan:        { title: 'Kegiatan',       parent: 'Program' },
  'sub-kegiatan':  { title: 'Sub Kegiatan',   parent: 'Kegiatan' },
};

const pageTitle = computed(() => labels[props.level]?.title ?? 'Data');
const parentLabel = computed(() => labels[props.level]?.parent ?? 'Parent');

const peraturanLabel = computed(() => {
  if (!props.activePeraturan?.kode) return '';
  return `Peraturan ( ${props.activePeraturan.kode} - ${props.activePeraturan.nama} )`;
});

const showChildModal = ref(false);
const showParentModal = ref(false);
const selectedChild = ref(null);
const selectedParent = ref(null);
const search = ref('');
const statusFilter = ref('all');
const codeCollator = new Intl.Collator('id-ID', { numeric: true, sensitivity: 'base' });

const childForm = useForm({
  parent_ids: [],
});

const parentForm = useForm({
  child_ids: [],
});

const parentLabelMap = computed(() => {
  return props.parents.reduce((map, parent) => {
    map[parent.id] = parent.label;
    return map;
  }, {});
});

const selectedLabels = computed(() => {
  return (childForm.parent_ids ?? [])
    .map((id) => parentLabelMap.value[id])
    .filter(Boolean);
});

const linkedCount = computed(() => props.rows.filter((row) => (row.parent_ids?.length ?? 0) > 0).length);
const unlinkedCount = computed(() => props.rows.length - linkedCount.value);

const filteredRows = computed(() => {
  const keyword = search.value.trim().toLowerCase();
  const rows = props.rows.filter((item) => {
    const linked = (item.parent_ids?.length ?? 0) > 0;
    const passStatus =
      statusFilter.value === 'all' ||
      (statusFilter.value === 'linked' && linked) ||
      (statusFilter.value === 'unlinked' && !linked);

    if (!passStatus) return false;

    if (!keyword) return true;

    const haystack = [item.kode, item.uraian, ...(item.parent_labels ?? [])]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return haystack.includes(keyword);
  });

  return rows.sort((a, b) => {
    const kodeA = (a.kode ?? '').toString();
    const kodeB = (b.kode ?? '').toString();
    const byCode = codeCollator.compare(kodeA, kodeB);
    if (byCode !== 0) return byCode;

    return codeCollator.compare((a.uraian ?? '').toString(), (b.uraian ?? '').toString());
  });
});

const groupedParents = computed(() => {
  return [...props.parents]
    .sort((a, b) => codeCollator.compare((a.label ?? '').toString(), (b.label ?? '').toString()))
    .map((parent) => {
      const [kodePart, ...uraianParts] = (parent.label ?? '').split(' - ');
      const parsedKode = uraianParts.length > 0 ? kodePart : '';
      const parsedUraian = uraianParts.length > 0 ? uraianParts.join(' - ') : (parent.label ?? '');
      const children = filteredRows.value
        .filter((row) => (row.parent_ids ?? []).includes(parent.id))
        .sort((a, b) => codeCollator.compare((a.kode ?? '').toString(), (b.kode ?? '').toString()));
      return {
        id: parent.id,
        label: parent.label,
        kode: parent.kode ?? parsedKode,
        uraian: parent.uraian ?? parsedUraian,
        children,
      };
    })
    .filter((group) => {
      if (statusFilter.value === 'unlinked') return false;
      return group.children.length > 0;
    });
});

const unlinkedRows = computed(() => {
  return filteredRows.value.filter((row) => (row.parent_ids?.length ?? 0) === 0);
});

const rowsForSelection = computed(() => {
  return [...props.rows].sort((a, b) => codeCollator.compare((a.kode ?? '').toString(), (b.kode ?? '').toString()));
});

function openChildEdit(item) {
  selectedChild.value = item;
  childForm.parent_ids = [...(item.parent_ids ?? [])];
  showChildModal.value = true;
}

function closeChildModal() {
  showChildModal.value = false;
  selectedChild.value = null;
  childForm.parent_ids = [];
  childForm.clearErrors();
}

function submit() {
  if (!selectedChild.value) return;

  childForm.put(route('data-dasar.relasi.level.update', { level: props.level, id: selectedChild.value.id }), {
    onSuccess: () => closeChildModal(),
    preserveScroll: true,
  });
}

function openParentEdit(parent) {
  selectedParent.value = parent;
  parentForm.child_ids = props.rows
    .filter((row) => (row.parent_ids ?? []).includes(parent.id))
    .map((row) => row.id);
  showParentModal.value = true;
}

function closeParentModal() {
  showParentModal.value = false;
  selectedParent.value = null;
  parentForm.child_ids = [];
  parentForm.clearErrors();
}

function submitParent() {
  if (!selectedParent.value) return;

  parentForm.put(route('data-dasar.relasi.level.parent.update', { level: props.level, parentId: selectedParent.value.id }), {
    onSuccess: () => closeParentModal(),
    preserveScroll: true,
  });
}

function selectAllParents() {
  childForm.parent_ids = [...props.parents]
    .sort((a, b) => codeCollator.compare((a.label ?? '').toString(), (b.label ?? '').toString()))
    .map((parent) => parent.id);
}

function clearAllParents() {
  childForm.parent_ids = [];
}

function selectAllChildren() {
  parentForm.child_ids = rowsForSelection.value.map((row) => row.id);
}

function clearAllChildren() {
  parentForm.child_ids = [];
}
</script>

<style scoped>
@reference "../../../css/app.css";

.input-base {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500;
}
</style>
