<template>
  <AppLayout title="Bank Data">
    <!-- Filter & Tabs -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div class="flex gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="activeTab === tab.key ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'"
          class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        >
          {{ tab.label }}
        </button>
      </div>
      <div class="flex items-center gap-2">
        <label class="text-xs font-medium text-gray-600">Jenis Dokumen:</label>
        <select v-model="selectedDocType" @change="changeDocType" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="rpjmd">RPJMD</option>
          <option value="renstra">Renstra</option>
          <option value="renja">Renja</option>
          <option value="dpa">DPA</option>
        </select>
      </div>
    </div>

    <!-- TAB: Visi & Hierarki -->
    <div v-show="activeTab === 'visi'">
      <div class="flex justify-between items-center mb-4">
        <h3 class="font-semibold text-gray-700">Visi & Hierarki Perencanaan</h3>
        <button @click="openVisiModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
          + Tambah Visi
        </button>
      </div>

      <div v-if="visi.length === 0" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Belum ada data visi untuk jenis dokumen ini.
      </div>

      <div v-for="v in visi" :key="v.id" class="bg-white rounded-xl shadow mb-4">
        <div class="flex items-start justify-between p-4 border-b border-gray-100">
          <div>
            <span class="text-xs font-mono text-gray-500 bg-gray-100 px-2 py-0.5 rounded mr-2">{{ v.kode }}</span>
            <span class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded">{{ v.tahun_awal }}–{{ v.tahun_akhir }}</span>
            <p class="mt-1 font-semibold text-gray-800">{{ v.uraian }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0 ml-4">
            <button @click="openVisiModal(v)" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
            <button @click="confirmDeleteVisi(v)" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
          </div>
        </div>

        <!-- Misi -->
        <div v-if="v.misi && v.misi.length" class="p-4">
          <div v-for="misi in v.misi" :key="misi.id" class="pl-4 border-l-2 border-blue-200 mb-3">
            <p class="text-sm font-medium text-gray-700">
              <span class="text-xs text-gray-400 mr-1">Misi {{ misi.kode ?? '' }}</span>{{ misi.uraian }}
            </p>
            <!-- Tujuan -->
            <div v-if="misi.tujuan && misi.tujuan.length" class="mt-2 pl-4 space-y-2">
              <div v-for="tujuan in misi.tujuan" :key="tujuan.id" class="border-l-2 border-green-200 pl-3">
                <p class="text-xs font-medium text-gray-600">Tujuan: {{ tujuan.uraian }}</p>
                <!-- Sasaran -->
                <div v-if="tujuan.sasaran && tujuan.sasaran.length" class="mt-1 pl-3 space-y-1">
                  <div v-for="sasaran in tujuan.sasaran" :key="sasaran.id" class="border-l-2 border-yellow-200 pl-2">
                    <p class="text-xs text-gray-500">Sasaran: {{ sasaran.uraian }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="px-4 pb-4 text-xs text-gray-400">Belum ada misi terdaftar.</div>
      </div>
    </div>

    <!-- TAB: Program & Kegiatan -->
    <div v-show="activeTab === 'program'">
      <div class="flex justify-between items-center mb-4">
        <h3 class="font-semibold text-gray-700">Program, Kegiatan & Sub Kegiatan</h3>
        <button @click="openProgramModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
          + Tambah Program
        </button>
      </div>

      <div v-if="program.length === 0" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Belum ada data program untuk jenis dokumen ini.
      </div>

      <div v-for="prog in program" :key="prog.id" class="bg-white rounded-xl shadow mb-4">
        <!-- Program Header -->
        <div class="flex items-start justify-between p-4 bg-blue-50 border-b border-blue-100 rounded-t-xl">
          <div>
            <span class="text-xs font-mono text-blue-600 bg-blue-100 px-2 py-0.5 rounded mr-2">{{ prog.kode_rek }}</span>
            <span class="text-xs text-gray-500">{{ prog.opd?.singkatan ?? 'Pemda' }} | {{ prog.kepmen?.kode ?? '-' }}</span>
            <p class="mt-1 font-semibold text-gray-800">{{ prog.nama_rincian }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Pagu: {{ formatCurrency(prog.pagu) }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0 ml-4">
            <button @click="openKegiatanModal(prog)" class="text-green-600 hover:text-green-800 text-xs font-medium border border-green-300 rounded px-2 py-1">+ Kegiatan</button>
            <button @click="openProgramModal(prog)" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
            <button @click="confirmDeleteProgram(prog)" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
          </div>
        </div>

        <!-- Kegiatan List -->
        <div v-if="prog.kegiatan && prog.kegiatan.length" class="divide-y divide-gray-100">
          <div v-for="keg in prog.kegiatan" :key="keg.id" class="p-4 pl-8">
            <div class="flex items-start justify-between">
              <div>
                <span class="text-xs font-mono text-gray-500 bg-gray-100 px-2 py-0.5 rounded mr-2">{{ keg.kode_rek }}</span>
                <p class="mt-1 text-sm font-medium text-gray-700">{{ keg.nama_rincian }}</p>
                <p class="text-xs text-gray-500">Pagu: {{ formatCurrency(keg.pagu) }}</p>
              </div>
              <div class="flex items-center gap-2 shrink-0 ml-4">
                <button @click="openSubKegiatanModal(keg)" class="text-green-600 hover:text-green-800 text-xs font-medium border border-green-300 rounded px-2 py-1">+ Sub</button>
                <button @click="openKegiatanModal(prog, keg)" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                <button @click="confirmDeleteKegiatan(keg)" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
              </div>
            </div>

            <!-- Sub Kegiatan -->
            <div v-if="keg.sub_kegiatan && keg.sub_kegiatan.length" class="mt-2 pl-4 border-l-2 border-gray-200 space-y-1">
              <div v-for="sub in keg.sub_kegiatan" :key="sub.id" class="flex items-center justify-between py-1">
                <div>
                  <span class="text-xs font-mono text-gray-400 mr-1">{{ sub.kode_rek }}</span>
                  <span class="text-xs text-gray-600">{{ sub.nama_rincian }}</span>
                  <span class="text-xs text-gray-400 ml-2">({{ formatCurrency(sub.pagu) }})</span>
                </div>
                <div class="flex items-center gap-2">
                  <button @click="openSubKegiatanModal(keg, sub)" class="text-blue-600 hover:text-blue-800 text-xs">Edit</button>
                  <button @click="confirmDeleteSubKegiatan(sub)" class="text-red-600 hover:text-red-800 text-xs">Hapus</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="px-4 pb-4 pt-2 text-xs text-gray-400">Belum ada kegiatan terdaftar.</div>
      </div>
    </div>

    <!-- MODAL: Visi -->
    <Modal :show="showVisiModal" :title="editingVisi ? 'Edit Visi' : 'Tambah Visi'" @close="closeVisiModal">
      <form @submit.prevent="submitVisi">
        <InputField label="Kode" :error="visiForm.errors.kode" required>
          <input v-model="visiForm.kode" type="text" class="input-base" />
        </InputField>
        <InputField label="Uraian Visi" :error="visiForm.errors.uraian" required>
          <textarea v-model="visiForm.uraian" rows="3" class="input-base" />
        </InputField>
        <div class="grid grid-cols-2 gap-3">
          <InputField label="Tahun Awal" :error="visiForm.errors.tahun_awal" required>
            <input v-model="visiForm.tahun_awal" type="number" class="input-base" />
          </InputField>
          <InputField label="Tahun Akhir" :error="visiForm.errors.tahun_akhir" required>
            <input v-model="visiForm.tahun_akhir" type="number" class="input-base" />
          </InputField>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" @click="closeVisiModal" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
          <button type="submit" :disabled="visiForm.processing" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ visiForm.processing ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </Modal>

    <!-- MODAL: Program -->
    <Modal :show="showProgramModal" :title="editingProgram ? 'Edit Program' : 'Tambah Program'" @close="closeProgramModal">
      <form @submit.prevent="submitProgram">
        <InputField label="Kepmen" :error="programForm.errors.kepmen_id" required>
          <select v-model="programForm.kepmen_id" class="input-base">
            <option value="">-- Pilih Kepmen --</option>
            <option v-for="k in kepmen" :key="k.id" :value="k.id">{{ k.kode }}</option>
          </select>
        </InputField>
        <InputField label="OPD" :error="programForm.errors.opd_id">
          <select v-model="programForm.opd_id" class="input-base">
            <option value="">-- Pemda --</option>
            <option v-for="o in opds" :key="o.id" :value="o.id">{{ o.singkatan ?? o.nama }}</option>
          </select>
        </InputField>
        <InputField label="Kode Rekening" :error="programForm.errors.kode_rek" required>
          <input v-model="programForm.kode_rek" type="text" class="input-base" />
        </InputField>
        <InputField label="Nama Program" :error="programForm.errors.nama_rincian" required>
          <textarea v-model="programForm.nama_rincian" rows="2" class="input-base" />
        </InputField>
        <InputField label="Pagu (Rp)" :error="programForm.errors.pagu" required>
          <input v-model="programForm.pagu" type="number" min="0" class="input-base" />
        </InputField>
        <div class="grid grid-cols-2 gap-3">
          <InputField label="Tahun Awal" :error="programForm.errors.tahun_awal">
            <input v-model="programForm.tahun_awal" type="number" class="input-base" />
          </InputField>
          <InputField label="Tahun Akhir" :error="programForm.errors.tahun_akhir">
            <input v-model="programForm.tahun_akhir" type="number" class="input-base" />
          </InputField>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" @click="closeProgramModal" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
          <button type="submit" :disabled="programForm.processing" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ programForm.processing ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </Modal>

    <!-- MODAL: Kegiatan -->
    <Modal :show="showKegiatanModal" :title="editingKegiatan ? 'Edit Kegiatan' : 'Tambah Kegiatan'" @close="closeKegiatanModal">
      <form @submit.prevent="submitKegiatan">
        <InputField label="Kepmen" :error="kegiatanForm.errors.kepmen_id" required>
          <select v-model="kegiatanForm.kepmen_id" class="input-base">
            <option value="">-- Pilih Kepmen --</option>
            <option v-for="k in kepmen" :key="k.id" :value="k.id">{{ k.kode }}</option>
          </select>
        </InputField>
        <InputField label="Kode Rekening" :error="kegiatanForm.errors.kode_rek" required>
          <input v-model="kegiatanForm.kode_rek" type="text" class="input-base" />
        </InputField>
        <InputField label="Nama Kegiatan" :error="kegiatanForm.errors.nama_rincian" required>
          <textarea v-model="kegiatanForm.nama_rincian" rows="2" class="input-base" />
        </InputField>
        <InputField label="Pagu (Rp)" :error="kegiatanForm.errors.pagu" required>
          <input v-model="kegiatanForm.pagu" type="number" min="0" class="input-base" />
        </InputField>
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" @click="closeKegiatanModal" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
          <button type="submit" :disabled="kegiatanForm.processing" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ kegiatanForm.processing ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </Modal>

    <!-- MODAL: Sub Kegiatan -->
    <Modal :show="showSubKegiatanModal" :title="editingSubKegiatan ? 'Edit Sub Kegiatan' : 'Tambah Sub Kegiatan'" @close="closeSubKegiatanModal">
      <form @submit.prevent="submitSubKegiatan">
        <InputField label="Kepmen" :error="subKegiatanForm.errors.kepmen_id" required>
          <select v-model="subKegiatanForm.kepmen_id" class="input-base">
            <option value="">-- Pilih Kepmen --</option>
            <option v-for="k in kepmen" :key="k.id" :value="k.id">{{ k.kode }}</option>
          </select>
        </InputField>
        <InputField label="Kode Rekening" :error="subKegiatanForm.errors.kode_rek" required>
          <input v-model="subKegiatanForm.kode_rek" type="text" class="input-base" />
        </InputField>
        <InputField label="Nama Sub Kegiatan" :error="subKegiatanForm.errors.nama_rincian" required>
          <textarea v-model="subKegiatanForm.nama_rincian" rows="2" class="input-base" />
        </InputField>
        <InputField label="Pagu (Rp)" :error="subKegiatanForm.errors.pagu" required>
          <input v-model="subKegiatanForm.pagu" type="number" min="0" class="input-base" />
        </InputField>
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" @click="closeSubKegiatanModal" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
          <button type="submit" :disabled="subKegiatanForm.processing" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ subKegiatanForm.processing ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </Modal>

    <!-- MODAL: Konfirmasi Hapus -->
    <Modal :show="showDeleteModal" title="Konfirmasi Hapus" @close="showDeleteModal = false">
      <p class="text-sm text-gray-600 mb-4">Apakah Anda yakin ingin menghapus data ini? Tindakan tidak dapat dibatalkan.</p>
      <div class="flex justify-end gap-2">
        <button @click="showDeleteModal = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">Batal</button>
        <button @click="executeDelete" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Hapus</button>
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
  visi: Array,
  program: Array,
  kepmen: Array,
  opds: Array,
  documentType: String,
});

const tabs = [
  { key: 'visi', label: '🏛️ Visi & Hierarki' },
  { key: 'program', label: '📊 Program & Kegiatan' },
];
const activeTab = ref('visi');
const selectedDocType = ref(props.documentType);

function changeDocType() {
  router.get(route('bank-data.index'), { document_type: selectedDocType.value }, { preserveState: false });
}

function formatCurrency(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}

const currentYear = new Date().getFullYear();

// ============ VISI ============
const showVisiModal = ref(false);
const editingVisi = ref(false);
const selectedVisi = ref(null);
const visiForm = useForm({
  document_type: props.documentType,
  kode: '',
  uraian: '',
  tahun_awal: currentYear,
  tahun_akhir: currentYear + 5,
});

function openVisiModal(v = null) {
  editingVisi.value = !!v;
  selectedVisi.value = v;
  if (v) {
    visiForm.kode = v.kode;
    visiForm.uraian = v.uraian;
    visiForm.tahun_awal = v.tahun_awal;
    visiForm.tahun_akhir = v.tahun_akhir;
  } else {
    visiForm.reset();
    visiForm.document_type = selectedDocType.value;
  }
  showVisiModal.value = true;
}

function closeVisiModal() {
  showVisiModal.value = false;
  visiForm.clearErrors();
}

function submitVisi() {
  if (editingVisi.value) {
    visiForm.put(route('bank-data.visi.update', selectedVisi.value.id), { onSuccess: () => closeVisiModal() });
  } else {
    visiForm.post(route('bank-data.visi.store'), { onSuccess: () => closeVisiModal() });
  }
}

// ============ PROGRAM ============
const showProgramModal = ref(false);
const editingProgram = ref(false);
const selectedProgram = ref(null);
const programForm = useForm({
  document_type: props.documentType,
  kepmen_id: '',
  opd_id: '',
  kode_rek: '',
  nama_rincian: '',
  pagu: '',
  tahun_awal: '',
  tahun_akhir: '',
});

function openProgramModal(prog = null) {
  editingProgram.value = !!prog;
  selectedProgram.value = prog;
  if (prog) {
    programForm.kepmen_id = prog.kepmen_id ?? '';
    programForm.opd_id = prog.opd_id ?? '';
    programForm.kode_rek = prog.kode_rek;
    programForm.nama_rincian = prog.nama_rincian;
    programForm.pagu = prog.pagu;
    programForm.tahun_awal = prog.tahun_awal ?? '';
    programForm.tahun_akhir = prog.tahun_akhir ?? '';
  } else {
    programForm.reset();
    programForm.document_type = selectedDocType.value;
  }
  showProgramModal.value = true;
}

function closeProgramModal() {
  showProgramModal.value = false;
  programForm.clearErrors();
}

function submitProgram() {
  if (editingProgram.value) {
    programForm.put(route('bank-data.program.update', selectedProgram.value.id), { onSuccess: () => closeProgramModal() });
  } else {
    programForm.post(route('bank-data.program.store'), { onSuccess: () => closeProgramModal() });
  }
}

// ============ KEGIATAN ============
const showKegiatanModal = ref(false);
const editingKegiatan = ref(false);
const selectedKegiatan = ref(null);
const parentProgram = ref(null);
const kegiatanForm = useForm({
  program_id: '',
  document_type: props.documentType,
  kepmen_id: '',
  kode_rek: '',
  nama_rincian: '',
  pagu: '',
});

function openKegiatanModal(prog, keg = null) {
  editingKegiatan.value = !!keg;
  parentProgram.value = prog;
  selectedKegiatan.value = keg;
  if (keg) {
    kegiatanForm.kepmen_id = keg.kepmen_id ?? '';
    kegiatanForm.kode_rek = keg.kode_rek;
    kegiatanForm.nama_rincian = keg.nama_rincian;
    kegiatanForm.pagu = keg.pagu;
  } else {
    kegiatanForm.reset();
    kegiatanForm.program_id = prog.id;
    kegiatanForm.document_type = selectedDocType.value;
  }
  showKegiatanModal.value = true;
}

function closeKegiatanModal() {
  showKegiatanModal.value = false;
  kegiatanForm.clearErrors();
}

function submitKegiatan() {
  if (editingKegiatan.value) {
    kegiatanForm.put(route('bank-data.kegiatan.update', selectedKegiatan.value.id), { onSuccess: () => closeKegiatanModal() });
  } else {
    kegiatanForm.post(route('bank-data.kegiatan.store'), { onSuccess: () => closeKegiatanModal() });
  }
}

// ============ SUB KEGIATAN ============
const showSubKegiatanModal = ref(false);
const editingSubKegiatan = ref(false);
const selectedSubKegiatan = ref(null);
const parentKegiatan = ref(null);
const subKegiatanForm = useForm({
  kegiatan_id: '',
  document_type: props.documentType,
  kepmen_id: '',
  kode_rek: '',
  nama_rincian: '',
  pagu: '',
});

function openSubKegiatanModal(keg, sub = null) {
  editingSubKegiatan.value = !!sub;
  parentKegiatan.value = keg;
  selectedSubKegiatan.value = sub;
  if (sub) {
    subKegiatanForm.kepmen_id = sub.kepmen_id ?? '';
    subKegiatanForm.kode_rek = sub.kode_rek;
    subKegiatanForm.nama_rincian = sub.nama_rincian;
    subKegiatanForm.pagu = sub.pagu;
  } else {
    subKegiatanForm.reset();
    subKegiatanForm.kegiatan_id = keg.id;
    subKegiatanForm.document_type = selectedDocType.value;
  }
  showSubKegiatanModal.value = true;
}

function closeSubKegiatanModal() {
  showSubKegiatanModal.value = false;
  subKegiatanForm.clearErrors();
}

function submitSubKegiatan() {
  if (editingSubKegiatan.value) {
    subKegiatanForm.put(route('bank-data.sub-kegiatan.update', selectedSubKegiatan.value.id), { onSuccess: () => closeSubKegiatanModal() });
  } else {
    subKegiatanForm.post(route('bank-data.sub-kegiatan.store'), { onSuccess: () => closeSubKegiatanModal() });
  }
}

// ============ DELETE ============
const showDeleteModal = ref(false);
const deleteCallback = ref(null);

function confirmDeleteVisi(v) {
  deleteCallback.value = () => router.delete(route('bank-data.visi.destroy', v.id), { onSuccess: () => { showDeleteModal.value = false; } });
  showDeleteModal.value = true;
}

function confirmDeleteProgram(prog) {
  deleteCallback.value = () => router.delete(route('bank-data.program.destroy', prog.id), { onSuccess: () => { showDeleteModal.value = false; } });
  showDeleteModal.value = true;
}

function confirmDeleteKegiatan(keg) {
  deleteCallback.value = () => router.delete(route('bank-data.kegiatan.destroy', keg.id), { onSuccess: () => { showDeleteModal.value = false; } });
  showDeleteModal.value = true;
}

function confirmDeleteSubKegiatan(sub) {
  deleteCallback.value = () => router.delete(route('bank-data.sub-kegiatan.destroy', sub.id), { onSuccess: () => { showDeleteModal.value = false; } });
  showDeleteModal.value = true;
}

function executeDelete() {
  if (deleteCallback.value) deleteCallback.value();
}
</script>

<style scoped>
.input-base {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500;
}
</style>
