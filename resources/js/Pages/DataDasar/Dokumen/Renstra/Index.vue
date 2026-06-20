<template>
  <AppLayout
    title="Rencana Strategis (RENSTRA)"
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Dokumen', href: route('dokumen.index') },
      { label: 'RENSTRA', href: route('renstra.index') }
    ]"
  >
    <div ref="fullscreenContainerRef" class="bg-white rounded-2xl shadow-md p-6" :class="isFullscreen ? 'fixed inset-0 z-[9999] overflow-auto rounded-none' : ''">
      <!-- Header & Filter -->
      <div class="flex flex-col md:justify-between gap-4 mb-4">
        <div class="w-fit">
          <h1 class="text-lg font-bold text-emerald-900 mb-2 md:mb-0">Rincian Rencana Strategis (RENSTRA)</h1>
        </div>
        <div class="w-full md:w-auto flex">
          <div class="flex w-full gap-3 items-center">
            <Multiselect
              v-model="selectedOpd"
              :options="opdOptionsGrouped"
              :searchable="true"
              :clearable="true"
              label="nama"
              track-by="id"
              placeholder="Cari/Pilih SKPD"
              class="w-full flex-7"
            >
              <template #option="{ option }">
                <div class="flex items-center justify-between gap-2">
                  <span class="truncate">
                    <span v-if="String(option.optionType || '').startsWith('sub-')" class="text-emerald-700 font-semibold">↳</span>
                    {{ option.nama }}
                  </span>
                  <span class="text-[10px] px-2 py-0.5 rounded-full border"
                    :class="String(option.optionType || '').startsWith('sub-')
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-slate-50 text-slate-600 border-slate-200'">
                    {{ option.badgeLabel || (String(option.optionType || '').startsWith('sub-') ? 'SUB UNIT' : 'SKPD') }}
                  </span>
                </div>
              </template>
              <template #singleLabel="{ option }">
                <div class="flex items-center gap-2">
                  <span>{{ option.nama }}</span>
                  <span class="text-[10px] px-2 py-0.5 rounded-full border"
                    :class="String(option.optionType || '').startsWith('sub-')
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-slate-50 text-slate-600 border-slate-200'">
                    {{ option.badgeLabel || (String(option.optionType || '').startsWith('sub-') ? 'SUB UNIT' : 'SKPD') }}
                  </span>
                </div>
              </template>
            </Multiselect>
            <button
              @click="openSelectMasterModal('program', null)"
              class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition whitespace-nowrap shrink-0"
            >+ Program</button>
            <button
              @click="savePerubahan"
              :disabled="!hasAnyChanges"
              class="px-4 py-2 rounded-lg bg-lime-800 text-white font-bold hover:bg-lime-900 transition whitespace-nowrap shrink-0"
              :class="hasAnyChanges ? 'btn-save-blink' : 'opacity-50 cursor-not-allowed'"
              title="Simpan perubahan pagu dan target indikator"
            >Simpan</button>
          </div>
        </div>
      </div>

      <!-- Pesan jika OPD dipilih tapi tidak ada program -->
      <div v-if="selectedOpd && !masterProgramList.length" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <p class="text-sm text-amber-800">
          <span class="font-bold">Tidak ada program</span> yang terhubung dengan OPD ini.
          Klik <strong>+ Program</strong> untuk menambahkan program baru.
        </p>
      </div>

      <!-- Tabel Rincian RENSTRA -->
      <div class="mb-2 flex flex-wrap items-center gap-2">
        <div class="flex items-center gap-1 flex-1 min-w-[220px]">
          <div class="relative flex items-center w-full max-w-sm">
            <span class="absolute left-2 text-gray-400 pointer-events-none">🔍</span>
            <input
              ref="findInputRef"
              v-model="findQuery"
              @keydown.enter.prevent="stepMatch(1)"
              @keydown.shift.enter.prevent="stepMatch(-1)"
              @keydown.escape="clearFind"
              type="text"
              placeholder="Cari dalam tabel… (Enter ↓ / Shift+Enter ↑)"
              class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-gray-300 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-400"
            />
            <button v-if="findQuery" @click="clearFind" class="absolute right-2 text-gray-400 hover:text-gray-600 text-sm leading-none">✕</button>
          </div>
          <span v-if="findQuery" class="text-xs text-gray-500 whitespace-nowrap">
            <template v-if="matchKeys.length">{{ currentMatchIndex + 1 }} / {{ matchKeys.length }}</template>
            <template v-else>Tidak ditemukan</template>
          </span>
          <button @click="stepMatch(-1)" :disabled="!matchKeys.length" class="px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-40" title="Hasil sebelumnya">↑</button>
          <button @click="stepMatch(1)"  :disabled="!matchKeys.length" class="px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-40" title="Hasil berikutnya">↓</button>
        </div>
        <div class="flex items-center gap-1">
          <span class="text-xs text-gray-500">Ukuran teks</span>
          <button @click="decreaseTableFont" class="px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">A-</button>
          <button @click="increaseTableFont" class="px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">A+</button>
          <button @click="toggleFullscreen" class="px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-emerald-50 hover:border-emerald-400 transition" :title="isFullscreen ? 'Keluar Layar Penuh (Esc)' : 'Layar Penuh'">
            <span v-if="!isFullscreen">&#x26F6;</span>
            <span v-else>&#x2715; Keluar</span>
          </button>
        </div>
      </div>

      <div ref="tableWrapRef" class="overflow-x-auto overflow-y-auto rounded-xl border border-emerald-100" :style="tableWrapStyle">
        <table class="min-w-[2400px] bg-white" :style="tableFontStyle">
          <thead class="bg-gray-100 border-b-2 border-emerald-200 sticky top-0 z-20">
            <tr class="divide-x divide-gray-300">
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Sub Unit</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Urusan</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Bidang Urusan</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide sticky-col-kode-header">Kode</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide sticky-col-uraian-header">Program / Kegiatan</th>
              <!-- Pagu per tahun -->
              <th v-for="y in tahunList" :key="`pagu-${y}`" class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide whitespace-nowrap">Pagu {{ y }}</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Aksi Uraian</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Aksi Indikator</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Indikator</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Sifat Indikator</th>
              <!-- Target per tahun -->
              <th v-for="y in tahunList" :key="`target-${y}`" class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide whitespace-nowrap">Target {{ y }}</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Satuan Indikator</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Aksi Data Indikator</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-300">
            <template v-for="row in renderRows(data)" :key="row.key">
              <tr
                :data-find-key="row.key"
                :class="[
                  row.bg,
                  'hover:opacity-90 transition',
                  matchKeys[currentMatchIndex] === row.key ? 'outline outline-2 outline-offset-[-2px] outline-emerald-500' : ''
                ]"
              >
                <template v-for="col in row.cols">
                  <td
                    v-if="!col.skip"
                    :rowspan="col.rowspan || undefined"
                    :class="[col.class, ['indikator_action','indikator_item_action'].includes(col.type) ? 'align-top' : '']"
                  >
                    <div v-if="col.type === 'komponen_action'" class="flex flex-col items-center justify-center gap-1">
                      <div class="flex items-center justify-center gap-1">
                        <button @click="openForm(row.komponen)" class="inline-block px-2 py-1 rounded-lg bg-blue-100 text-blue-700 font-medium hover:bg-blue-200 transition" title="Ubah Uraian">✏️</button>
                        <button @click="confirmDelete(row.komponen)" class="inline-block px-2 py-1 rounded-lg bg-red-100 text-red-700 font-medium hover:bg-red-200 transition" title="Hapus Uraian">🗑️</button>
                      </div>
                      <button v-if="row.komponen?.jenis === 'program'" @click="openSelectMasterModal('kegiatan', row.komponen)" class="inline-block px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700 font-medium hover:bg-emerald-200 transition">+ Kegiatan</button>
                    </div>
                    <div v-else-if="col.type === 'indikator_item_action'" class="flex items-center justify-center gap-1">
                      <button v-if="col.indikatorId" @click="openEditIndikatorPrompt(col)" class="inline-block px-2 py-1 rounded-lg bg-blue-100 text-blue-700 font-medium hover:bg-blue-200 transition" title="Ubah Indikator">✏️</button>
                      <button v-if="col.indikatorId" @click="confirmDeleteIndikator(col)" class="inline-block px-2 py-1 rounded-lg bg-red-100 text-red-700 font-medium hover:bg-red-200 transition" title="Hapus Indikator">🗑️</button>
                      <span v-if="!col.indikatorId" class="text-gray-400">-</span>
                    </div>
                    <button v-else-if="col.type === 'indikator_action'" @click="openAddIndikatorModal(row.komponen)" class="inline-block px-3 py-1 rounded-lg bg-indigo-100 text-indigo-700 font-medium hover:bg-indigo-200 transition">+ Indikator</button>
                    <input
                      v-else-if="col.type === 'pagu_tahun_input'"
                      type="text"
                      inputmode="numeric"
                      pattern="[0-9]*"
                      :value="getPaguTahunDisplay(col.komponentId, col.tahun, col.rawValue)"
                      @input="e => onPaguTahunInput(col.komponentId, col.tahun, e.target.value)"
                      @keydown="onPaguKeydown"
                      @paste.prevent="e => onPaguTahunPaste(e, col.komponentId, col.tahun)"
                      class="w-full text-right bg-white border border-emerald-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-emerald-400 font-semibold"
                      placeholder="0"
                    />
                    <span v-else-if="col.type === 'pagu_computed_tahun'" class="font-semibold tabular-nums">
                      {{ formatRupiah(getEffectivePaguTahun(col.komponen, col.tahun)) }}
                    </span>
                    <input
                      v-else-if="col.type === 'target_tahun_input'"
                      type="text"
                      :value="getTargetTahunDisplay(col.indikatorId, col.tahun, col.rawValue)"
                      @input="e => onTargetTahunInput(col.indikatorId, col.tahun, e.target.value)"
                      class="w-full bg-white border border-indigo-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                      placeholder="Target"
                    />
                    <span v-else v-html="highlightText(col.value)"></span>
                  </td>
                </template>
              </tr>
            </template>
            <tr v-if="!data || data.length === 0">
              <td :colspan="tableColspan" class="text-center py-8 text-gray-400">
                Belum ada data. Pilih SKPD dan tambahkan program untuk memulai.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Modal Form Komponen -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm bg-black/30">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-3 md:p-4 relative border border-emerald-100">
          <h2 class="text-xl font-bold mb-2 text-emerald-800">{{ editing ? 'Edit' : 'Tambah' }} Program / Komponen Rencana Strategis</h2>
          <form @submit.prevent="submitForm" class="space-y-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-3">
              <div v-if="editingRow" class="md:col-span-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                Posisi data: <span class="font-semibold">{{ editingJenisLabel }}</span>
              </div>
              <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700">SKPD</label>
                <input type="text" class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm" :value="selectedOpdLabel" readonly />
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-semibold mb-2 text-gray-700">Pilih data dari database</label>
                <select v-model="form.master_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                  <option value="">Pilih data</option>
                  <option
                    v-for="item in editingRow ? editingMasterOptions : masterProgramList"
                    :key="`${editingRow ? editingRow.jenis : 'program'}-${item.id}`"
                    :value="String(item.id)"
                    :disabled="item.is_added"
                  >
                    {{ item.kode }} - {{ item.nama }}{{ item.is_added ? ' (sudah ditambahkan)' : '' }}
                  </option>
                </select>
              </div>
              <div class="md:col-span-2" v-if="editingRow">
                <label class="block text-sm font-semibold mb-2 text-gray-700">Uraian terpilih</label>
                <input type="text" class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm" :value="selectedEditMaster?.nama || ''" readonly />
              </div>
              <div v-if="!editingRow">
                <label class="block text-sm font-semibold mb-2 text-gray-700">Bidang</label>
                <input type="text" class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm" :value="selectedProgram?.bidang || ''" readonly />
              </div>
            </div>
            <div class="flex justify-end gap-2 mt-2">
              <button type="button" @click="closeModal" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold">Batal</button>
              <button type="submit" class="px-3 py-1.5 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-semibold shadow">Simpan</button>
            </div>
          </form>
          <button @click="closeModal" class="absolute top-3 right-4 text-gray-400 hover:text-gray-700 text-2xl">×</button>
        </div>
      </div>

      <!-- Modal Tambah Indikator -->
      <div v-if="showAddIndikator" class="fixed inset-0 z-[60] flex items-center justify-center backdrop-blur-sm bg-black/30">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl p-4 relative border border-indigo-100">
          <h2 class="text-lg font-bold mb-3 text-indigo-800">Tambah Indikator</h2>
          <form @submit.prevent="submitAddIndikator" class="space-y-3">
            <div>
              <label class="block text-sm font-semibold mb-1 text-gray-700">Nama indikator</label>
              <input v-model="addIndikatorForm.nama_indikator" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required />
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 text-gray-700">Sifat indikator</label>
              <select v-model="addIndikatorForm.sifat_indikator" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                <option value="positif">Positif</option>
                <option value="negatif">Negatif</option>
                <option value="akumulatif">Akumulatif</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 text-gray-700">Satuan</label>
              <input v-model="addIndikatorForm.satuan" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required />
            </div>
            <div class="flex justify-end gap-2 pt-1">
              <button type="button" @click="closeAddIndikatorModal" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold">Batal</button>
              <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold shadow">Tambah</button>
            </div>
          </form>
          <button @click="closeAddIndikatorModal" class="absolute top-2.5 right-3 text-gray-400 hover:text-gray-700 text-2xl">×</button>
        </div>
      </div>

      <!-- Modal Pilih Master -->
      <div v-if="showSelectMaster" class="fixed inset-0 z-[70] flex items-center justify-center backdrop-blur-sm bg-black/30">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-4 relative border border-emerald-100">
          <h2 class="text-lg font-bold mb-2 text-emerald-800">{{ selectMasterTitle }}</h2>
          <p v-if="selectMasterParentLabel" class="text-xs text-gray-500 mb-3">Parent: {{ selectMasterParentLabel }}</p>
          <form @submit.prevent="submitSelectMaster" class="space-y-3">
            <div>
              <label class="block text-sm font-semibold mb-1 text-gray-700">Pilih data</label>
              <select v-model="selectMasterForm.master_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                <option value="">Pilih data</option>
                <option v-for="item in selectableMasterOptions" :key="`${selectMasterForm.master_type}-${item.id}`" :value="String(item.id)" :disabled="item.is_added">
                  {{ item.kode }} - {{ item.nama }}{{ item.is_added ? ' (sudah ditambahkan)' : '' }}
                </option>
              </select>
              <p v-if="!selectableMasterOptions.length" class="text-xs text-amber-600 mt-1">Tidak ada data turunan yang tersedia untuk konteks ini.</p>
              <p v-else-if="selectableMasterOptions.every(item => item.is_added)" class="text-xs text-amber-600 mt-1">Semua data untuk unit ini sudah ditambahkan.</p>
            </div>
            <div class="flex justify-end gap-2 pt-1">
              <button type="button" @click="closeSelectMasterModal" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold">Batal</button>
              <button type="submit" :disabled="!selectMasterForm.master_id" class="px-3 py-1.5 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-semibold shadow disabled:opacity-50 disabled:cursor-not-allowed">Tambahkan</button>
            </div>
          </form>
          <button @click="closeSelectMasterModal" class="absolute top-2.5 right-3 text-gray-400 hover:text-gray-700 text-2xl">×</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, reactive, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps({
  data: Array,
  opds: Array,
  tahunList: Array,
  masterProgramList: { type: Array, default: () => [] },
  masterReferensi: { type: Object, default: () => ({ program: [], kegiatan: [] }) },
});

// ── State ─────────────────────────────────────────────────────
const showModal       = ref(false);
const editing         = ref(false);
const editingRow      = ref(null);
const form            = ref({ master_type: 'program', master_id: '', jenis: 'program', parent_id: null, kode: '', kode_program: '' });
const errors          = ref({});
const showAddIndikator = ref(false);
const addIndikatorForm = reactive({ komponen_id: null, nama_indikator: '', sifat_indikator: 'positif', target_indikator: '', satuan: '' });
const selectedOpd     = ref(null);
const tableFontSize   = ref(12);
const showSelectMaster = ref(false);
const selectMasterForm = reactive({ master_type: 'program', parent_id: null, parent_kode: '', master_id: '' });

// Per-tahun edited state — key: `<komponenId>_<tahun>`
const editedPaguTahunan   = reactive({});
const editedTargetTahunan = reactive({});

const hasPaguChanges   = computed(() => Object.keys(editedPaguTahunan).length > 0);
const hasTargetChanges = computed(() => Object.keys(editedTargetTahunan).length > 0);
const hasAnyChanges    = computed(() => hasPaguChanges.value || hasTargetChanges.value);

// ── Fullscreen ────────────────────────────────────────────────
const fullscreenContainerRef = ref(null);
const isFullscreen = ref(false);
const tableWrapRef = ref(null);

const tableWrapStyle = computed(() => isFullscreen.value
  ? { maxHeight: 'calc(100vh - 180px)' }
  : { maxHeight: '70vh' }
);

function toggleFullscreen() {
  const el = fullscreenContainerRef.value;
  if (!el) return;
  if (!document.fullscreenElement) { el.requestFullscreen().catch(() => {}); }
  else { document.exitFullscreen().catch(() => {}); }
}

function onFullscreenChange() { isFullscreen.value = !!document.fullscreenElement; }

onMounted(() => {
  document.addEventListener('fullscreenchange', onFullscreenChange);
  const opdId = new URL(window.location.href).searchParams.get('opd_id');
  console.log(`[Index] Component mounted with props.data=${props.data?.length ?? 0} items, query opd_id=${opdId}`);
  if (opdId) {
    const found = props.opds?.find(o => String(o.id) === String(opdId));
    console.log(`[Index] Found OPD for id=${opdId}:`, found);
    if (found) selectedOpd.value = found;
  } else if (props.opds?.length) {
    selectedOpd.value = props.opds[0];
  }
});

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', onFullscreenChange);
});
// ─────────────────────────────────────────────────────────────

const tableColspan = computed(() => {
  // 5 kolom tetap + 6 pagu + aksi uraian + aksi ind + indikator + sifat + 6 target + satuan + aksi data ind
  return 5 + props.tahunList.length + 2 + 1 + 1 + props.tahunList.length + 1 + 1;
});

const tableFontStyle = computed(() => ({ fontSize: `${tableFontSize.value}px` }));

// ── Pagu per tahun ────────────────────────────────────────────
function paguTahunKey(komponenId, tahun) { return `${komponenId}_${tahun}`; }

function getPaguTahunDisplay(komponenId, tahun, rawValue) {
  const key = paguTahunKey(komponenId, tahun);
  const source = editedPaguTahunan[key] !== undefined
    ? String(editedPaguTahunan[key])
    : onlyDigits(rawValue);
  if (!source) return '';
  return formatRupiah(parseInt(source) || 0);
}

function onPaguTahunInput(komponenId, tahun, value) {
  editedPaguTahunan[paguTahunKey(komponenId, tahun)] = onlyDigits(value);
}

function onPaguTahunPaste(event, komponenId, tahun) {
  const pasted = event.clipboardData?.getData('text') ?? '';
  editedPaguTahunan[paguTahunKey(komponenId, tahun)] = onlyDigits(pasted);
}

function getEffectivePaguTahun(komponen, tahun) {
  if (!komponen) return 0;
  const tahunStr = String(tahun);

  if (komponen.jenis === 'kegiatan') {
    const key = paguTahunKey(komponen.id, tahun);
    if (editedPaguTahunan[key] !== undefined) return parseInt(editedPaguTahunan[key]) || 0;
    return parseInt(komponen.pagu_tahunan?.[tahunStr] ?? 0) || 0;
  }

  const children = Array.isArray(komponen.children) ? komponen.children : [];
  if (komponen.jenis === 'program') {
    const totalKegiatan = children
      .filter(c => c.jenis === 'kegiatan')
      .reduce((s, c) => s + getEffectivePaguTahun(c, tahun), 0);

    // Fallback ke pagu program jika kegiatan belum memiliki nilai.
    if (totalKegiatan > 0) return totalKegiatan;
    return parseInt(komponen.pagu_tahunan?.[tahunStr] ?? komponen.pagu ?? 0) || 0;
  }
  return parseInt(komponen.pagu_tahunan?.[tahunStr] ?? 0) || 0;
}

// ── Target per tahun ─────────────────────────────────────────
function targetTahunKey(indikatorId, tahun) { return `${indikatorId}_${tahun}`; }

function getTargetTahunDisplay(indikatorId, tahun, rawValue) {
  if (!indikatorId) return '';
  const key = targetTahunKey(indikatorId, tahun);
  if (editedTargetTahunan[key] !== undefined) return editedTargetTahunan[key];
  return String(rawValue ?? '');
}

function onTargetTahunInput(indikatorId, tahun, value) {
  if (!indikatorId) return;
  editedTargetTahunan[targetTahunKey(indikatorId, tahun)] = value;
}

// ── Save ─────────────────────────────────────────────────────
function savePerubahan() {
  if (!hasAnyChanges.value) return;

  // Pagu: group by komponenId → {tahun: value}
  const payloadPagu = {};
  Object.keys(editedPaguTahunan).forEach(k => {
    const [id, tahun] = k.split('_');
    if (!payloadPagu[id]) payloadPagu[id] = {};
    payloadPagu[id][tahun] = parseInt(editedPaguTahunan[k]) || 0;
  });

  // Target: group by indikatorId → {tahun: value}
  const payloadTarget = {};
  Object.keys(editedTargetTahunan).forEach(k => {
    const [id, tahun] = k.split('_');
    if (!payloadTarget[id]) payloadTarget[id] = {};
    payloadTarget[id][tahun] = String(editedTargetTahunan[k] ?? '').trim();
  });

  router.post(route('renstra.bulk-save'), {
    pagu_tahunan: payloadPagu,
    indikator_target_tahunan: payloadTarget,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      Object.keys(editedPaguTahunan).forEach(k => delete editedPaguTahunan[k]);
      Object.keys(editedTargetTahunan).forEach(k => delete editedTargetTahunan[k]);
    },
  });
}

// ── Helpers ───────────────────────────────────────────────────
function onlyDigits(value) { return String(value ?? '').replace(/[^0-9]/g, ''); }

function onPaguKeydown(event) {
  const allowed = ['Backspace','Delete','Tab','ArrowLeft','ArrowRight','Home','End'];
  if (allowed.includes(event.key)) return;
  if ((event.ctrlKey || event.metaKey) && ['a','c','v','x'].includes(event.key.toLowerCase())) return;
  if (!/^[0-9]$/.test(event.key)) event.preventDefault();
}

function formatRupiah(value) {
  const n = Number(value ?? 0);
  return Number.isNaN(n) ? '0' : n.toLocaleString('id-ID');
}

function formatSifatIndikator(value) {
  const m = { positif: 'Positif', negatif: 'Negatif', akumulatif: 'Akumulatif' };
  return m[normalizeSifatIndikator(value)] ?? '-';
}

function normalizeSifatIndikator(value) {
  const raw = String(value ?? '').trim().toLowerCase();
  if (raw === 'maximize') return 'positif';
  if (raw === 'minimize') return 'negatif';
  if (raw === 'stabilize') return 'akumulatif';
  return ['positif','negatif','akumulatif'].includes(raw) ? raw : '';
}

function decreaseTableFont() { tableFontSize.value = Math.max(10, tableFontSize.value - 1); }
function increaseTableFont()  { tableFontSize.value = Math.min(18, tableFontSize.value + 1); }

function getRowBg(jenis) {
  if (jenis === 'program') return 'bg-gray-400';
  if (jenis === 'kegiatan') return 'bg-yellow-200';
  return 'bg-white';
}

// ── Find in table ─────────────────────────────────────────────
const findQuery          = ref('');
const currentMatchIndex  = ref(0);
const findInputRef       = ref(null);

const matchKeys = computed(() => {
  const q = findQuery.value.trim().toLowerCase();
  if (!q) return [];
  const seen = new Set();
  const keys = [];
  for (const row of renderRows(props.data)) {
    if (seen.has(row.key)) continue;
    if (row.searchText.includes(q)) { seen.add(row.key); keys.push(row.key); }
  }
  return keys;
});

watch(matchKeys, () => { currentMatchIndex.value = 0; scrollToCurrentMatch(); });
watch(findQuery, () => { currentMatchIndex.value = 0; });

function stepMatch(dir) {
  if (!matchKeys.value.length) return;
  const total = matchKeys.value.length;
  currentMatchIndex.value = ((currentMatchIndex.value + dir) % total + total) % total;
  scrollToCurrentMatch();
}

async function scrollToCurrentMatch() {
  if (!matchKeys.value.length) return;
  await nextTick();
  const key = matchKeys.value[currentMatchIndex.value];
  const row = tableWrapRef.value?.querySelector(`tr[data-find-key="${key}"]`);
  row?.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

function clearFind() { findQuery.value = ''; currentMatchIndex.value = 0; }

function highlightText(value) {
  const q = findQuery.value.trim();
  if (!q || value == null) return escapeHtml(String(value ?? ''));
  return String(value).replace(new RegExp(`(${escapeRegex(q)})`, 'gi'), '<mark class="bg-yellow-300 text-gray-900 rounded px-0.5">$1</mark>');
}

function escapeHtml(str) { return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escapeRegex(str) { return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

// ── OPD grouped options ───────────────────────────────────────
const opdOptionsGrouped = computed(() => {
  const all = Array.isArray(props.opds) ? props.opds : [];
  const childMap = {
    '4.01.0.00.0.00.14.0000': { prefix: '4.01', optionType: 'sub-sekda', badgeLabel: 'SUB BAGIAN' },
    '1.02.2.14.0.00.02.0000': { prefix: '1.02.2.14.0.00.02.', optionType: 'sub-dinkes', badgeLabel: 'SUB UNIT' },
  };
  const usedChildCodes = new Set();
  const result = [];

  all.slice().sort((a,b)=>String(a.nama||'').localeCompare(String(b.nama||''))).forEach(parent => {
    const parentCode = String(parent.kode || '');
    const config = childMap[parentCode];
    result.push({ ...parent, optionType: 'main', badgeLabel: 'SKPD' });
    if (!config) return;
    all.filter(o => String(o.kode||'').startsWith(config.prefix) && String(o.kode||'') !== parentCode)
       .sort((a,b)=>String(a.nama||'').localeCompare(String(b.nama||'')))
       .map(o => ({ ...o, optionType: config.optionType, badgeLabel: config.badgeLabel }))
       .forEach(child => { usedChildCodes.add(String(child.kode||'')); result.push(child); });
  });

  return result.filter((item, idx, arr) => {
    const code = String(item.kode || '');
    if (String(item.optionType||'').startsWith('sub-')) {
      return arr.findIndex(x=>String(x.kode||'')===code && String(x.optionType||'')===String(item.optionType||''))===idx;
    }
    if (usedChildCodes.has(code) && item.optionType === 'main') return false;
    return arr.findIndex(x=>String(x.kode||'')===code && String(x.optionType||'')===String(item.optionType||''))===idx;
  });
});

// ── Master program / computed ─────────────────────────────────
const masterProgramList = computed(() => props.masterProgramList || []);
const selectedOpdLabel  = computed(() => selectedOpd.value?.nama ?? '');
const selectedProgram   = computed(() => masterProgramList.value.find(p => p.id === form.value.program_id) || null);

// ── Existing code sets ────────────────────────────────────────
const existingMasterCodeSets = computed(() => {
  const bucket = { program: new Set(), kegiatan: new Set() };
  function visit(items) {
    if (!Array.isArray(items)) return;
    items.forEach(item => {
      const jenis = String(item?.jenis||'');
      const kode  = String(item?.kode||'').trim();
      if (bucket[jenis] && kode) bucket[jenis].add(kode);
      if (Array.isArray(item?.children)) visit(item.children);
    });
  }
  visit(props.data || []);
  return bucket;
});

const selectableMasterOptions = computed(() => {
  const type       = selectMasterForm.master_type;
  const parentKode = String(selectMasterForm.parent_kode || '');
  const opdId      = Number(selectedOpd.value?.id || 0);
  const source     = Array.isArray(props.masterReferensi?.[type]) ? props.masterReferensi[type] : [];
  const existing   = existingMasterCodeSets.value[type] || new Set();

  return source
    .filter(item => {
      if (Number(item.opd_id||0) !== opdId) return false;
      if (!parentKode) return true;
      return String(item.kode||'').startsWith(parentKode + '.');
    })
    .map(item => ({ ...item, is_added: existing.has(String(item.kode||'')) }))
    .sort((a,b) => String(a.kode||'').localeCompare(String(b.kode||'')));
});

const selectMasterTitle       = computed(() => {
  if (selectMasterForm.master_type === 'program') return 'Tambah Program dari Referensi';
  return 'Tambah Kegiatan (Turunan Program)';
});
const selectMasterParentLabel = computed(() => selectMasterForm.parent_kode || '');

// ── Edit form ─────────────────────────────────────────────────
function findKomponenById(items, id) {
  if (!Array.isArray(items) || !id) return null;
  for (const item of items) {
    if (Number(item?.id) === Number(id)) return item;
    const found = findKomponenById(item?.children || [], id);
    if (found) return found;
  }
  return null;
}

const editingParentKode = computed(() => {
  if (!editingRow.value?.parent_id) return '';
  return findKomponenById(props.data || [], editingRow.value.parent_id)?.kode || '';
});

const editingMasterOptions = computed(() => {
  if (!editingRow.value?.jenis) return [];
  const type       = editingRow.value.jenis;
  const opdId      = Number(selectedOpd.value?.id || 0);
  const parentKode = type === 'program' ? '' : String(editingParentKode.value || '');
  const currentKode = String(editingRow.value?.kode || '');
  const source     = Array.isArray(props.masterReferensi?.[type]) ? props.masterReferensi[type] : [];
  const existing   = existingMasterCodeSets.value[type] || new Set();

  return source
    .filter(item => {
      if (Number(item.opd_id||0) !== opdId) return false;
      if (!parentKode) return true;
      return String(item.kode||'').startsWith(parentKode + '.');
    })
    .map(item => ({
      ...item,
      is_added: existing.has(String(item.kode||'')) && String(item.kode||'') !== currentKode,
      is_current: String(item.kode||'') === currentKode,
    }))
    .sort((a,b) => String(a.kode||'').localeCompare(String(b.kode||'')));
});

const selectedEditMaster = computed(() => editingMasterOptions.value.find(i => String(i.id) === String(form.value.master_id)) || null);

const editingJenisLabel = computed(() => {
  const m = { program: 'Program', kegiatan: 'Kegiatan' };
  return m[editingRow.value?.jenis] ?? editingRow.value?.jenis ?? '';
});

function openForm(komponen = null) {
  editing.value    = !!komponen;
  editingRow.value = komponen;
  const currentMaster = komponen
    ? (Array.isArray(props.masterReferensi?.[komponen.jenis]) ? props.masterReferensi[komponen.jenis] : [])
        .find(i => String(i.kode||'') === String(komponen.kode||''))
    : null;
  form.value = {
    master_type: komponen?.jenis ?? 'program',
    master_id: currentMaster?.id ? String(currentMaster.id) : '',
    id: komponen?.id ?? null,
    jenis: komponen?.jenis ?? 'program',
    parent_id: komponen?.parent_id ?? null,
    kode: komponen?.kode ?? '',
    kode_program: komponen?.kode_program ?? '',
  };
  errors.value   = {};
  showModal.value = true;
}

function closeModal() { showModal.value = false; editingRow.value = null; }

async function submitForm() {
  errors.value = {};
  const payload = { ...form.value, opd_id: selectedOpd.value?.id };

  if (editingRow.value) {
    payload.parent_id   = editingRow.value.parent_id ?? null;
    payload.master_type = editingRow.value.jenis;
    if (!payload.master_id) { window.alert('Pilih data dari database terlebih dahulu.'); return; }
  }

  try {
    if (editing.value && form.value.id) {
      await router.put(route('renstra.update', form.value.id), payload, { onSuccess: () => closeModal(), onError: e => { errors.value = e; } });
    } else {
      await router.post(route('renstra.store'), payload, { onSuccess: () => closeModal(), onError: e => { errors.value = e; } });
    }
  } catch (_) {}
}

function confirmDelete(komponen) {
  if (!komponen?.id) return;
  if (confirm('Yakin ingin menghapus data ini beserta turunannya?')) {
    router.delete(route('renstra.destroy', komponen.id), { preserveScroll: true });
  }
}

// ── Add indikator ─────────────────────────────────────────────
function openAddIndikatorModal(komponen) {
  addIndikatorForm.komponen_id     = komponen?.id ?? null;
  addIndikatorForm.nama_indikator  = '';
  addIndikatorForm.sifat_indikator = 'positif';
  addIndikatorForm.target_indikator = '';
  addIndikatorForm.satuan          = '';
  showAddIndikator.value           = true;
}

function closeAddIndikatorModal() { showAddIndikator.value = false; }

function submitAddIndikator() {
  const id = addIndikatorForm.komponen_id;
  if (!id) return;
  const payload = {
    nama_indikator:   addIndikatorForm.nama_indikator.trim(),
    sifat_indikator:  addIndikatorForm.sifat_indikator,
    target_indikator: null,
    satuan:           addIndikatorForm.satuan.trim(),
  };
  if (!payload.nama_indikator || !payload.satuan) { window.alert('Nama indikator dan satuan wajib diisi.'); return; }
  router.post(route('renstra.indikator.store', id), payload, { preserveScroll: true, onSuccess: () => closeAddIndikatorModal() });
}

function openSelectMasterModal(type, parentKomponen = null) {
  selectMasterForm.master_type = type;
  selectMasterForm.parent_id   = parentKomponen?.id ?? null;
  selectMasterForm.parent_kode = parentKomponen?.kode ?? '';
  selectMasterForm.master_id   = '';
  showSelectMaster.value       = true;
}

function closeSelectMasterModal() { showSelectMaster.value = false; }

function submitSelectMaster() {
  if (!selectedOpd.value?.id) { window.alert('Pilih SKPD terlebih dahulu.'); return; }
  if (!selectMasterForm.master_id) return;
  router.post(route('renstra.attach-master'), {
    opd_id:      selectedOpd.value.id,
    parent_id:   selectMasterForm.parent_id || undefined,
    master_type: selectMasterForm.master_type,
    master_id:   Number(selectMasterForm.master_id),
  }, { preserveScroll: true, onSuccess: () => closeSelectMasterModal() });
}

function openEditIndikatorPrompt(col) {
  if (!col?.indikatorId) return;
  const nama = window.prompt('Nama indikator:', col.namaIndikator || '');
  if (!nama) return;
  const sifatInput = window.prompt('Sifat indikator (positif/negatif/akumulatif):', normalizeSifatIndikator(col.sifatRaw) || 'positif');
  if (!sifatInput) return;
  const sifat = normalizeSifatIndikator(sifatInput);
  if (!sifat) { window.alert('Sifat indikator harus positif, negatif, atau akumulatif.'); return; }
  const satuan = window.prompt('Satuan indikator:', col.satuanIndikator || '');
  if (!satuan) return;
  router.put(route('renstra.indikator.update', col.indikatorId), { nama_indikator: nama, sifat_indikator: sifat, target_indikator: null, satuan }, { preserveScroll: true });
}

function confirmDeleteIndikator(col) {
  if (!col?.indikatorId) return;
  if (confirm('Yakin ingin menghapus indikator ini?')) {
    router.delete(route('renstra.indikator.destroy', col.indikatorId), { preserveScroll: true });
  }
}

// ── Watch OPD → reload ────────────────────────────────────────
watch(selectedOpd, opd => {
  console.log(`[Index] selectedOpd changed to:`, opd);
  router.get(route('renstra.index'), { opd_id: opd?.id || undefined }, { preserveState: true, preserveScroll: true, replace: true });
});

// ── Row rendering ─────────────────────────────────────────────
function buildNoticeRow({ key, jenis, message }) {
  const dashes = Array(tableColspan.value).fill({ value: '-', class: 'px-3 py-2 text-gray-500 border-b border-r border-gray-300' });
  dashes[4] = { value: message, class: 'px-3 py-2 font-semibold text-amber-700 border-b border-r border-gray-300' };
  return { key, isFirstRow: false, komponen: null, bg: getRowBg(jenis), rowspan: 1, searchText: message.toLowerCase(), cols: dashes };
}

function renderRows(data, parentKey = '') {
  const rows = [];
  if (!data) {
    console.log(`[renderRows] Data empty at level parentKey="${parentKey}"`);
    return rows;
  }
  
  console.log(`[renderRows] Processing ${data.length} items at level parentKey="${parentKey}"`);

  data.forEach(komponen => {
    console.log(`[renderRows] Item: kode=${komponen.kode} | jenis=${komponen.jenis} | has children=${!!komponen.children && Array.isArray(komponen.children)}`);
    
    const indikatorList = komponen.indikator.length
      ? komponen.indikator
      : [{ nama_indikator: '', sifat_indikator: '', target_indikator: '', target_tahunan: null, satuan: '' }];

    indikatorList.forEach((ind, i) => {
      const key = `${parentKey}${komponen.id}-${i}`;

      rows.push({
        key,
        isFirstRow: i === 0,
        komponen,
        bg: getRowBg(komponen.jenis),
        rowspan: indikatorList.length,
        searchText: [
          komponen.kode, komponen.nama_komponen, komponen.sub_unit, komponen.urusan, komponen.bidang_urusan,
          ind.nama_indikator, ind.satuan,
        ].map(v => String(v ?? '')).join(' ').toLowerCase(),
        cols: [
          { value: komponen.sub_unit, rowspan: indikatorList.length, skip: i !== 0, class: 'px-3 py-2 align-top border-b border-r border-gray-300' },
          { value: komponen.urusan, rowspan: indikatorList.length, skip: i !== 0, class: 'px-3 py-2 align-top border-b border-r border-gray-300' },
          { value: komponen.bidang_urusan, rowspan: indikatorList.length, skip: i !== 0, class: 'px-3 py-2 align-top border-b border-r border-gray-300' },
          { value: komponen.kode, rowspan: indikatorList.length, skip: i !== 0, class: 'px-3 py-2 align-top font-semibold border-b border-r border-gray-300 sticky-col-kode-body' },
          { value: komponen.nama_komponen, rowspan: indikatorList.length, skip: i !== 0, class: 'px-3 py-2 align-top font-bold border-b border-r border-gray-300 sticky-col-uraian-body' },
          // Pagu per tahun
          ...props.tahunList.map(tahun => komponen.jenis === 'kegiatan'
            ? { type: 'pagu_tahun_input', komponentId: komponen.id, tahun, rawValue: String(komponen.pagu_tahunan?.[tahun] ?? 0), rowspan: indikatorList.length, skip: i !== 0, class: 'px-2 py-1 align-top border-b border-r border-gray-300 min-w-[120px]' }
            : { type: 'pagu_computed_tahun', komponen, tahun, rowspan: indikatorList.length, skip: i !== 0, class: 'px-3 py-2 align-top text-right whitespace-nowrap border-b border-r border-gray-300' }
          ),
          { type: 'komponen_action', rowspan: indikatorList.length, skip: i !== 0, class: 'px-2 py-1 text-center align-top border-b border-r border-gray-300 min-w-[92px]' },
          { type: 'indikator_action', rowspan: indikatorList.length, skip: i !== 0, class: 'px-2 py-1 text-center align-top border-b border-r border-gray-300 min-w-[110px]' },
          { value: ind.nama_indikator || '-', class: 'px-3 py-2 align-top text-left border-b border-r border-gray-300' },
          { value: formatSifatIndikator(ind.sifat_indikator), class: 'px-3 py-2 align-top text-left border-b border-r border-gray-300' },
          // Target per tahun
          ...props.tahunList.map(tahun => ind.id
            ? { type: 'target_tahun_input', indikatorId: ind.id, tahun, rawValue: ind.target_tahunan?.[tahun] ?? '', class: 'px-2 py-1 border-b border-r border-gray-300 min-w-[120px]' }
            : { value: '-', class: 'px-3 py-2 border-b border-r border-gray-300' }
          ),
          { value: ind.satuan, class: 'px-3 py-2 align-top border-b border-r border-gray-300' },
          { type: 'indikator_item_action', indikatorId: ind.id ?? null, namaIndikator: ind.nama_indikator ?? '', sifatRaw: ind.sifat_indikator ?? '', satuanIndikator: ind.satuan ?? '', class: 'px-2 py-1 text-center border-b border-r border-gray-300 min-w-[92px]' },
        ],
      });
    });

    const children = Array.isArray(komponen.children) ? komponen.children : [];

    if (komponen.jenis === 'program') {
      const keg = children.filter(c => c.jenis === 'kegiatan');
      console.log(`[renderRows] Program ${komponen.kode}: filtered kegiatan=${keg.length} from ${children.length} children`);
      rows.push(...(keg.length ? renderRows(keg, `${parentKey}${komponen.id}-`) : [buildNoticeRow({ key: `${parentKey}${komponen.id}-missing-kegiatan`, jenis: 'kegiatan', message: 'Program ini belum memiliki kegiatan.' })]));
      return;
    }

    if (komponen.jenis === 'kegiatan') {
      console.log(`[renderRows] Kegiatan ${komponen.kode}: returning (no sub-rendering)`);
      return;
    }

    if (children.length) {
      console.log(`[renderRows] ${komponen.kode}: rendering ${children.length} children`);
      rows.push(...renderRows(children, `${parentKey}${komponen.id}-`));
    }
  });

  console.log(`[renderRows] Returning ${rows.length} rows from level parentKey="${parentKey}"`);
  return rows;
}
</script>

<style scoped>
@reference "../../../../../css/app.css";
.btn-save-blink { animation: blink 1.2s ease-in-out infinite; }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.55; } }
</style>
