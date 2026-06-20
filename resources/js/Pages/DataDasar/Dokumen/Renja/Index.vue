<template>
  <AppLayout
    title="Rencana Kerja (RENJA)"
    :breadcrumbs="[
      { label: 'Data Dasar', href: route('data-dasar.index') },
      { label: 'Dokumen', href: route('dokumen.index') },
      { label: 'RENJA', href: route('renja.index') }
    ]"
  >
    <div ref="fullscreenContainerRef" class="bg-white rounded-2xl shadow-md p-6" :class="isFullscreen ? 'fixed inset-0 z-[9999] overflow-auto rounded-none' : ''">
      <!-- Header & Filter -->
      <div class="flex flex-col md:justify-between gap-4 mb-4">
        <div class="w-fit">
          <h1 class="text-lg font-bold text-emerald-900 mb-2 md:mb-0">Rincian Rencana Kerja (RENJA)</h1>
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
                  <span
                    class="text-[10px] px-2 py-0.5 rounded-full border"
                    :class="String(option.optionType || '').startsWith('sub-')
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-slate-50 text-slate-600 border-slate-200'"
                  >
                    {{ option.badgeLabel || (String(option.optionType || '').startsWith('sub-') ? 'SUB UNIT' : 'SKPD') }}
                  </span>
                </div>
              </template>
              <template #singleLabel="{ option }">
                <div class="flex items-center gap-2">
                  <span>{{ option.nama }}</span>
                  <span
                    class="text-[10px] px-2 py-0.5 rounded-full border"
                    :class="String(option.optionType || '').startsWith('sub-')
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-slate-50 text-slate-600 border-slate-200'"
                  >
                    {{ option.badgeLabel || (String(option.optionType || '').startsWith('sub-') ? 'SUB UNIT' : 'SKPD') }}
                  </span>
                </div>
              </template>
            </Multiselect>
            <Multiselect
              v-model="selectedTahun"
              :options="tahunList"
              :searchable="true"
              :clearable="true"
              placeholder="Tahun"
              class="flex-1 mr-2"
            />
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
          <span class="font-bold">Tidak ada program</span> yang terhubung dengan OPD ini untuk tahun {{ selectedTahun || 'yang dipilih' }}.
          Klik <strong>+ Program</strong> untuk menambahkan program baru.
        </p>
      </div>

      <!-- Tabel Rincian RENJA -->
      <div class="mb-2 flex flex-wrap items-center gap-2">
        <!-- Find-in-table (Ctrl+F) -->
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
          <button @click="stepMatch(-1)" :disabled="!matchKeys.length" class="px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-40" title="Hasil sebelumnya (Shift+Enter)">↑</button>
          <button @click="stepMatch(1)"  :disabled="!matchKeys.length" class="px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-40" title="Hasil berikutnya (Enter)">↓</button>
        </div>
        <!-- Font size controls + Fullscreen -->
        <div class="flex items-center gap-1">
          <span class="text-xs text-gray-500">Ukuran teks</span>
          <button @click="decreaseTableFont" class="px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-gray-50" title="Perkecil teks">A-</button>
          <button @click="increaseTableFont" class="px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-gray-50" title="Perbesar teks">A+</button>
          <button
            @click="toggleFullscreen"
            class="px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-emerald-50 hover:border-emerald-400 transition"
            :title="isFullscreen ? 'Keluar Layar Penuh (Esc)' : 'Layar Penuh'"
          >
            <span v-if="!isFullscreen">&#x26F6;</span>
            <span v-else>&#x2715; Keluar</span>
          </button>
        </div>
      </div>

      <div ref="tableWrapRef" class="overflow-x-auto overflow-y-auto rounded-xl border border-emerald-100" :style="tableWrapStyle">
        <table class="min-w-[1600px] bg-white" :style="tableFontStyle">
          <thead class="bg-gray-100 border-b-2 border-emerald-200 sticky top-0 z-20">
            <tr class="divide-x divide-gray-300">
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Sub Unit</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Urusan</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Bidang Urusan</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide sticky-col-kode-header">Kode</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide sticky-col-uraian-header">Program / Kegiatan</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Pagu</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Aksi Uraian</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Aksi Indikator</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Indikator</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Sifat Indikator</th>
              <th class="px-4 py-3 text-gray-700 font-bold uppercase text-center tracking-wide">Target Indikator</th>
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
                    :class="[
                      col.class,
                      [
                        'indikator_action',
                        'indikator_nama',
                        'indikator_sifat',
                        'indikator_target_input',
                        'indikator_satuan',
                        'indikator_item_action'
                      ].includes(col.type) ? 'align-top' : ''
                    ]"
                  >
                    <div v-if="col.type === 'komponen_action'" class="flex flex-col items-center justify-center gap-1">
                      <div class="flex  items-center justify-center gap-1">
                        <button @click="openForm(row.komponen)" class="inline-block px-2 py-1 rounded-lg bg-blue-100 text-blue-700 font-medium hover:bg-blue-200 transition" title="Ubah Uraian">
                        ✏️
                      </button>
                      <button @click="confirmDelete(row.komponen)" class="inline-block px-2 py-1 rounded-lg bg-red-100 text-red-700 font-medium hover:bg-red-200 transition" title="Hapus Uraian">
                        🗑️
                      </button>
                      </div>
                      <button
                        v-if="row.komponen?.jenis === 'program'"
                        @click="openSelectMasterModal('kegiatan', row.komponen)"
                        class="inline-block px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700 font-medium hover:bg-emerald-200 transition"
                        title="Tambah Kegiatan"
                      >
                        + Kegiatan
                      </button>
                      <button
                        v-if="row.komponen?.jenis === 'kegiatan'"
                        @click="openSelectMasterModal('sub_kegiatan', row.komponen)"
                        class="inline-block px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700 font-medium hover:bg-emerald-200 transition"
                        title="Tambah Sub Kegiatan"
                      >
                        + Sub Kegiatan
                      </button>
                    </div>
                    <div v-else-if="col.type === 'indikator_item_action'" class="flex items-center justify-center gap-1">
                      <button
                        v-if="col.indikatorId"
                        @click="openEditIndikatorPrompt(col)"
                        class="inline-block px-2 py-1 rounded-lg bg-blue-100 text-blue-700 font-medium hover:bg-blue-200 transition"
                        title="Ubah Indikator"
                      >
                        ✏️
                      </button>
                      <button
                        v-if="col.indikatorId"
                        @click="confirmDeleteIndikator(col)"
                        class="inline-block px-2 py-1 rounded-lg bg-red-100 text-red-700 font-medium hover:bg-red-200 transition"
                        title="Hapus Indikator"
                      >
                        🗑️
                      </button>
                      <span v-if="!col.indikatorId" class="text-gray-400">-</span>
                    </div>
                    <button
                      v-else-if="col.type === 'indikator_action'"
                      @click="openAddIndikatorModal(row.komponen)"
                      class="inline-block px-3 py-1 rounded-lg bg-indigo-100 text-indigo-700 font-medium hover:bg-indigo-200 transition"
                      title="Tambah indikator"
                    >
                      + Indikator
                    </button>
                    <input
                      v-else-if="col.type === 'pagu_input'"
                      type="text"
                      inputmode="numeric"
                      pattern="[0-9]*"
                      :value="getPaguInputDisplay(col.komponentId, col.rawValue)"
                      @input="e => onPaguInput(col.komponentId, e.target.value)"
                      @keydown="onPaguKeydown"
                      @paste.prevent="onPaguPaste($event, col.komponentId)"
                      @blur="e => { if (editedPagu[col.komponentId] === '') delete editedPagu[col.komponentId]; }"
                      class="w-full text-right bg-white border border-emerald-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-emerald-400 font-semibold"
                      placeholder="0"
                    />
                    <span v-else-if="col.type === 'pagu_computed'" class="font-semibold tabular-nums">
                      {{ formatRupiah(getEffectivePagu(col.komponen)) }}
                    </span>
                    <input
                      v-else-if="col.type === 'indikator_target_input'"
                      type="text"
                      :value="getTargetInputDisplay(col.indikatorId, col.rawValue)"
                      @input="e => onTargetInput(col.indikatorId, e.target.value)"
                      @blur="e => { if (editedTargetIndikator[col.indikatorId] === '') delete editedTargetIndikator[col.indikatorId]; }"
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
          <h2 class="text-xl font-bold mb-2 text-emerald-800">{{ editing ? 'Edit' : 'Tambah' }} Program / Komponen Rencana Kerja</h2>
          <form @submit.prevent="submitForm" class="space-y-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-3">
              <div v-if="editingRow" class="md:col-span-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                Posisi data: <span class="font-semibold">{{ editingJenisLabel }}</span>
                <span v-if="editingRow.parent_id" class="text-emerald-700">(turunan)</span>
              </div>
              <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700">SKPD</label>
                <input type="text" class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm" :value="selectedOpdLabel" readonly />
              </div>
              <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700">Tahun</label>
                <input type="text" class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm" :value="selectedTahun" readonly />
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
                <p v-if="editingRow && !editingMasterOptions.length" class="text-xs text-amber-600 mt-1">
                  Tidak ada data yang bisa dipilih untuk posisi ini.
                </p>
                <p v-else-if="editingRow && editingMasterOptions.every(item => item.is_added)" class="text-xs text-amber-600 mt-1">
                  Semua data untuk posisi ini sudah ditambahkan.
                </p>
                <p v-else-if="!editingRow && !masterProgramList.length" class="text-xs text-amber-600 mt-1">
                  Pilih SKPD dan tahun terlebih dahulu untuk melihat daftar program yang tersedia.
                </p>
              </div>
              <div class="md:col-span-2" v-if="editingRow">
                <label class="block text-sm font-semibold mb-2 text-gray-700">Uraian terpilih</label>
                <input
                  type="text"
                  class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm"
                  :value="selectedEditMaster?.nama || ''"
                  readonly
                />
              </div>
              <div v-if="!editingRow">
                <label class="block text-sm font-semibold mb-2 text-gray-700">Bidang</label>
                <input type="text" class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm" :value="selectedProgram?.bidang || ''" readonly />
              </div>
            </div>
            <div v-if="!editingRow" class="mt-2">
              <label class="block text-sm font-bold mb-1 text-gray-700">Indikator</label>
              <div v-if="selectedProgram && selectedProgram.indikator && selectedProgram.indikator.length">
                <div v-for="(indikator, idx) in selectedProgram.indikator" :key="idx" class="flex gap-1 mb-0.5">
                  <input :value="indikator.nama_indikator" class="flex-1 rounded-lg border border-gray-200 px-2 py-1.5 text-sm bg-gray-100" readonly />
                  <input :value="indikator.satuan" class="w-24 rounded-lg border border-gray-200 px-2 py-1.5 text-sm bg-gray-100" readonly />
                </div>
              </div>
              <div v-else class="text-gray-400 text-xs italic">Pilih program untuk melihat indikator</div>
            </div>
            <div v-else class="mt-2 text-xs text-gray-500 italic">
              Data diubah dengan memilih master dari database. Posisi level tetap mengikuti data asalnya.
            </div>
            <div class="flex justify-end gap-2 mt-2">
              <button type="button" @click="closeModal" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold">Batal</button>
              <button type="submit" class="px-3 py-1.5 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-semibold shadow">Simpan</button>
            </div>
          </form>
          <button @click="closeModal" class="absolute top-3 right-4 text-gray-400 hover:text-gray-700 text-2xl">×</button>
        </div>
      </div>

      <div v-if="showAddIndikator" class="fixed inset-0 z-[60] flex items-center justify-center backdrop-blur-sm bg-black/30">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl p-4 relative border border-indigo-100">
          <h2 class="text-lg font-bold mb-3 text-indigo-800">Tambah Indikator</h2>
          <form @submit.prevent="submitAddIndikator" class="space-y-3">
            <div>
              <label class="block text-sm font-semibold mb-1 text-gray-700">Nama indikator</label>
              <input
                v-model="addIndikatorForm.nama_indikator"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                placeholder="Masukkan nama indikator"
                autocomplete="off"
                required
              />
              <div v-if="namaIndikatorSuggestions.length" class="mt-1 border border-gray-200 rounded-lg max-h-36 overflow-auto bg-white">
                <button
                  v-for="(nama, idx) in namaIndikatorSuggestions"
                  :key="`nama-${idx}`"
                  type="button"
                  @click="applyNamaSuggestion(nama)"
                  class="block w-full text-left px-3 py-1.5 text-sm hover:bg-indigo-50"
                >
                  {{ nama }}
                </button>
              </div>
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
              <label class="block text-sm font-semibold mb-1 text-gray-700">Target indikator</label>
              <input
                v-model="addIndikatorForm.target_indikator"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                placeholder="Masukkan target"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-semibold mb-1 text-gray-700">Satuan</label>
              <input
                v-model="addIndikatorForm.satuan"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                placeholder="Masukkan satuan"
                autocomplete="off"
                required
              />
              <div v-if="satuanIndikatorSuggestions.length" class="mt-1 border border-gray-200 rounded-lg max-h-36 overflow-auto bg-white">
                <button
                  v-for="(satuan, idx) in satuanIndikatorSuggestions"
                  :key="`satuan-${idx}`"
                  type="button"
                  @click="applySatuanSuggestion(satuan)"
                  class="block w-full text-left px-3 py-1.5 text-sm hover:bg-indigo-50"
                >
                  {{ satuan }}
                </button>
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-1">
              <button type="button" @click="closeAddIndikatorModal" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold">Batal</button>
              <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold shadow">Tambah</button>
            </div>
          </form>
          <button @click="closeAddIndikatorModal" class="absolute top-2.5 right-3 text-gray-400 hover:text-gray-700 text-2xl">×</button>
        </div>
      </div>

      <div v-if="showSelectMaster" class="fixed inset-0 z-[70] flex items-center justify-center backdrop-blur-sm bg-black/30">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-4 relative border border-emerald-100">
          <h2 class="text-lg font-bold mb-2 text-emerald-800">{{ selectMasterTitle }}</h2>
          <p v-if="selectMasterParentLabel" class="text-xs text-gray-500 mb-3">Parent: {{ selectMasterParentLabel }}</p>

          <form @submit.prevent="submitSelectMaster" class="space-y-3">
            <div>
              <label class="block text-sm font-semibold mb-1 text-gray-700">Pilih data</label>
              <select v-model="selectMasterForm.master_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                <option value="">Pilih data</option>
                <option
                  v-for="item in selectableMasterOptions"
                  :key="`${selectMasterForm.master_type}-${item.id}`"
                  :value="String(item.id)"
                  :disabled="item.is_added"
                >
                  {{ item.kode }} - {{ item.nama }}{{ item.is_added ? ' (sudah ditambahkan)' : '' }}
                </option>
              </select>
              <p v-if="!selectableMasterOptions.length" class="text-xs text-amber-600 mt-1">
                Tidak ada data turunan yang tersedia untuk konteks ini.
              </p>
              <p v-else-if="selectableMasterOptions.every(item => item.is_added)" class="text-xs text-amber-600 mt-1">
                Semua data untuk unit ini sudah ditambahkan.
              </p>
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
  masterProgramList: {
    type: Array,
    default: () => [],
  },
  masterReferensi: {
    type: Object,
    default: () => ({ program: [], kegiatan: [], sub_kegiatan: [] }),
  },
});

const showModal = ref(false);
const editing = ref(false);
const editingRow = ref(null);
const form = ref({
  program_id: '',
  master_type: 'program',
  master_id: '',
  nama_komponen: '',
  jenis: 'program',
  parent_id: null,
  kode: '',
  kode_program: '',
});
const errors = ref({});

const showAddIndikator = ref(false);
const addIndikatorForm = reactive({
  komponen_id: null,
  nama_indikator: '',
  sifat_indikator: 'positif',
  target_indikator: '',
  satuan: '',
});

const selectedOpd = ref(null);
const selectedTahun = ref('');
const tableFontSize = ref(12);

const tableColspan = computed(() => {
  return 13; // Total columns for RENJA (no realisasi)
});

const showSelectMaster = ref(false);
const selectMasterForm = reactive({
  master_type: 'program',
  parent_id: null,
  parent_kode: '',
  master_id: '',
});

// ── Fullscreen ────────────────────────────────────────────────
const fullscreenContainerRef = ref(null);
const isFullscreen = ref(false);

const tableWrapStyle = computed(() =>
  isFullscreen.value
    ? { maxHeight: 'calc(100vh - 180px)' }
    : { maxHeight: '70vh' }
);

function toggleFullscreen() {
  const el = fullscreenContainerRef.value;
  if (!el) return;
  if (!document.fullscreenElement) {
    el.requestFullscreen().catch(() => {});
  } else {
    document.exitFullscreen().catch(() => {});
  }
}

function onFullscreenChange() {
  isFullscreen.value = !!document.fullscreenElement;
}

onMounted(() => {
  document.addEventListener('fullscreenchange', onFullscreenChange);
});

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', onFullscreenChange);
});
// ─────────────────────────────────────────────────────────────

// ── Pagu inline edit ──────────────────────────────────────────
const editedPagu = reactive({});

const editedTargetIndikator = reactive({});

const hasPaguChanges = computed(() => Object.keys(editedPagu).length > 0);
const hasTargetChanges = computed(() => Object.keys(editedTargetIndikator).length > 0);
const hasAnyChanges = computed(() => hasPaguChanges.value || hasTargetChanges.value);

function getEffectivePagu(komponen) {
  if (!komponen) return 0;
  if (komponen.jenis === 'sub_kegiatan') {
    const edited = editedPagu[komponen.id];
    return edited !== undefined ? (parseInt(edited) || 0) : (Number(komponen.pagu) || 0);
  }
  const children = Array.isArray(komponen.children) ? komponen.children : [];
  if (komponen.jenis === 'kegiatan') {
    return children
      .filter(c => c.jenis === 'sub_kegiatan')
      .reduce((sum, c) => sum + getEffectivePagu(c), 0);
  }
  if (komponen.jenis === 'program') {
    return children
      .filter(c => c.jenis === 'kegiatan')
      .reduce((sum, c) => sum + getEffectivePagu(c), 0);
  }
  return Number(komponen.pagu) || 0;
}

function savePerubahan() {
  if (!hasAnyChanges.value) return;

  const payloadPagu = {};
  Object.keys(editedPagu).forEach(k => { payloadPagu[k] = parseInt(editedPagu[k]) || 0; });

  const payloadTarget = {};
  Object.keys(editedTargetIndikator).forEach(k => { payloadTarget[k] = String(editedTargetIndikator[k] ?? '').trim(); });

  router.post(route('renja.bulk-save'), {
    pagu: payloadPagu,
    indikator_target: payloadTarget,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      Object.keys(editedPagu).forEach(k => delete editedPagu[k]);
      Object.keys(editedTargetIndikator).forEach(k => delete editedTargetIndikator[k]);
    },
  });
}

function onlyDigits(value) {
  return String(value ?? '').replace(/[^0-9]/g, '');
}

function getPaguInputDisplay(komponentId, rawValue) {
  const source = editedPagu[komponentId] !== undefined
    ? String(editedPagu[komponentId])
    : onlyDigits(rawValue);

  if (!source) return '';

  return formatRupiah(parseInt(source) || 0);
}

function onPaguInput(komponentId, inputValue) {
  editedPagu[komponentId] = onlyDigits(inputValue);
}

function onPaguKeydown(event) {
  const allowedKeys = [
    'Backspace',
    'Delete',
    'Tab',
    'ArrowLeft',
    'ArrowRight',
    'Home',
    'End',
  ];

  if (allowedKeys.includes(event.key)) return;

  if ((event.ctrlKey || event.metaKey) && ['a', 'c', 'v', 'x'].includes(event.key.toLowerCase())) {
    return;
  }

  if (!/^[0-9]$/.test(event.key)) {
    event.preventDefault();
  }
}

function onPaguPaste(event, komponentId) {
  const pasted = event.clipboardData?.getData('text') ?? '';
  editedPagu[komponentId] = onlyDigits(pasted);
}

function getTargetInputDisplay(indikatorId, rawValue) {
  if (!indikatorId) return '';
  if (editedTargetIndikator[indikatorId] !== undefined) {
    return editedTargetIndikator[indikatorId];
  }
  return String(rawValue ?? '');
}

function onTargetInput(indikatorId, value) {
  if (!indikatorId) return;
  editedTargetIndikator[indikatorId] = value;
}

function collectIndikatorReferences(items, bucket = []) {
  if (!Array.isArray(items)) return bucket;

  items.forEach((item) => {
    const indikatorList = Array.isArray(item?.indikator) ? item.indikator : [];
    indikatorList.forEach((ind) => {
      const nama = String(ind?.nama_indikator ?? '').trim();
      const satuan = String(ind?.satuan ?? '').trim();
      const sifat = String(ind?.sifat_indikator ?? '').trim();
      const target = String(ind?.target_indikator ?? '').trim();

      if (!nama || nama === '-') return;

      bucket.push({
        nama_indikator: nama,
        satuan,
        sifat_indikator: normalizeSifatIndikator(sifat) || 'akumulatif',
        target_indikator: target,
      });
    });

    if (Array.isArray(item?.children) && item.children.length) {
      collectIndikatorReferences(item.children, bucket);
    }
  });

  return bucket;
}

const indikatorReferencePool = computed(() => {
  const refs = collectIndikatorReferences(props.data || []);
  const map = new Map();
  refs.forEach((item) => {
    const key = [item.nama_indikator, item.satuan, item.sifat_indikator, item.target_indikator].join('|');
    if (!map.has(key)) map.set(key, item);
  });
  return Array.from(map.values());
});

const namaIndikatorSuggestions = computed(() => {
  const keyword = addIndikatorForm.nama_indikator.trim().toLowerCase();
  if (!keyword) return [];

  const names = indikatorReferencePool.value
    .map((item) => item.nama_indikator)
    .filter((name) => name.toLowerCase().includes(keyword));

  return Array.from(new Set(names)).slice(0, 8);
});

const satuanIndikatorSuggestions = computed(() => {
  const keyword = addIndikatorForm.satuan.trim().toLowerCase();
  if (!keyword) return [];

  const units = indikatorReferencePool.value
    .map((item) => item.satuan)
    .filter((unit) => unit && unit.toLowerCase().includes(keyword));

  return Array.from(new Set(units)).slice(0, 8);
});

const selectMasterTitle = computed(() => {
  if (selectMasterForm.master_type === 'program') return 'Tambah Program dari Referensi';
  if (selectMasterForm.master_type === 'kegiatan') return 'Tambah Kegiatan (Turunan Program)';
  return 'Tambah Sub Kegiatan (Turunan Kegiatan)';
});

const selectMasterParentLabel = computed(() => {
  if (!selectMasterForm.parent_kode) return '';
  return selectMasterForm.parent_kode;
});

const selectableMasterOptions = computed(() => {
  const type = selectMasterForm.master_type;
  const parentKode = String(selectMasterForm.parent_kode || '');
  const selectedOpdId = Number(selectedOpd.value?.id || 0);

  const source = Array.isArray(props.masterReferensi?.[type]) ? props.masterReferensi[type] : [];
  const existingCodes = existingMasterCodeSets.value[type] || new Set();

  return source
    .filter((item) => {
      const sameOpd = Number(item.opd_id || 0) === selectedOpdId;
      if (!sameOpd) return false;

      if (!parentKode) return true;
      return String(item.kode || '').startsWith(parentKode + '.');
    })
    .map((item) => ({
      ...item,
      is_added: existingCodes.has(String(item.kode || '')),
    }))
    .sort((a, b) => String(a.kode || '').localeCompare(String(b.kode || '')));
});

const existingMasterCodeSets = computed(() => {
  const bucket = {
    program: new Set(),
    kegiatan: new Set(),
    sub_kegiatan: new Set(),
  };

  function visit(items) {
    if (!Array.isArray(items)) return;

    items.forEach((item) => {
      const jenis = String(item?.jenis || '');
      const kode = String(item?.kode || '').trim();

      if (bucket[jenis] && kode) {
        bucket[jenis].add(kode);
      }

      if (Array.isArray(item?.children) && item.children.length) {
        visit(item.children);
      }
    });
  }

  visit(props.data || []);

  return bucket;
});

function applyNamaSuggestion(nama) {
  addIndikatorForm.nama_indikator = nama;

  const matched = indikatorReferencePool.value.find((item) => item.nama_indikator === nama);
  if (matched) {
    if (!addIndikatorForm.satuan && matched.satuan) {
      addIndikatorForm.satuan = matched.satuan;
    }
    if (!addIndikatorForm.target_indikator && matched.target_indikator) {
      addIndikatorForm.target_indikator = matched.target_indikator;
    }
    const sifat = normalizeSifatIndikator(matched.sifat_indikator);
    if (sifat) {
      addIndikatorForm.sifat_indikator = sifat;
    }
  }
}

function applySatuanSuggestion(satuan) {
  addIndikatorForm.satuan = satuan;
}

function openAddIndikatorModal(komponen) {
  addIndikatorForm.komponen_id = komponen?.id ?? null;
  addIndikatorForm.nama_indikator = '';
  addIndikatorForm.sifat_indikator = 'positif';
  addIndikatorForm.target_indikator = '';
  addIndikatorForm.satuan = '';
  showAddIndikator.value = true;
}

function openSelectMasterModal(type, parentKomponen = null) {
  selectMasterForm.master_type = type;
  selectMasterForm.parent_id = parentKomponen?.id ?? null;
  selectMasterForm.parent_kode = parentKomponen?.kode ?? '';
  selectMasterForm.master_id = '';
  showSelectMaster.value = true;
}

function closeSelectMasterModal() {
  showSelectMaster.value = false;
}

function submitSelectMaster() {
  if (!selectedOpd.value?.id) {
    window.alert('Pilih SKPD terlebih dahulu.');
    return;
  }

  if (!selectMasterForm.master_id) return;

  router.post(route('renja.attach-master'), {
    opd_id: selectedOpd.value.id,
    tahun: selectedTahun.value || undefined,
    parent_id: selectMasterForm.parent_id || undefined,
    master_type: selectMasterForm.master_type,
    master_id: Number(selectMasterForm.master_id),
  }, {
    preserveScroll: true,
    onSuccess: () => closeSelectMasterModal(),
  });
}

function closeAddIndikatorModal() {
  showAddIndikator.value = false;
}

function submitAddIndikator() {
  const komponenId = addIndikatorForm.komponen_id;
  if (!komponenId) return;

  const payload = {
    nama_indikator: addIndikatorForm.nama_indikator.trim(),
    sifat_indikator: addIndikatorForm.sifat_indikator,
    target_indikator: addIndikatorForm.target_indikator.trim(),
    satuan: addIndikatorForm.satuan.trim(),
  };

  if (!payload.nama_indikator || !payload.target_indikator || !payload.satuan) {
    window.alert('Nama indikator, target indikator, dan satuan wajib diisi.');
    return;
  }

  router.post(route('renja.indikator.store', komponenId), payload, {
    preserveScroll: true,
    onSuccess: () => closeAddIndikatorModal(),
  });
}
// ─────────────────────────────────────────────────────────────

const findQuery = ref('');
const currentMatchIndex = ref(0);
const findInputRef = ref(null);
const tableWrapRef = ref(null);

const matchKeys = computed(() => {
  const q = findQuery.value.trim().toLowerCase();
  if (!q) return [];
  const rows = renderRows(props.data);
  const seen = new Set();
  const keys = [];
  for (const row of rows) {
    if (seen.has(row.key)) continue;
    if (row.searchText.includes(q)) {
      seen.add(row.key);
      keys.push(row.key);
    }
  }
  return keys;
});

watch(matchKeys, () => {
  currentMatchIndex.value = 0;
  scrollToCurrentMatch();
});

watch(findQuery, () => {
  currentMatchIndex.value = 0;
});

function stepMatch(direction) {
  if (!matchKeys.value.length) return;
  const total = matchKeys.value.length;
  currentMatchIndex.value = ((currentMatchIndex.value + direction) % total + total) % total;
  scrollToCurrentMatch();
}

async function scrollToCurrentMatch() {
  if (!matchKeys.value.length) return;
  await nextTick();
  const key = matchKeys.value[currentMatchIndex.value];
  const wrap = tableWrapRef.value;
  if (!wrap) return;
  const row = wrap.querySelector(`tr[data-find-key="${key}"]`);
  if (row) {
    row.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }
}

function clearFind() {
  findQuery.value = '';
  currentMatchIndex.value = 0;
}

function highlightText(value) {
  const q = findQuery.value.trim();
  if (!q || value === null || value === undefined) return escapeHtml(String(value ?? ''));
  const str = String(value);
  const regex = new RegExp(`(${escapeRegex(q)})`, 'gi');
  return str.replace(regex, '<mark class="bg-yellow-300 text-gray-900 rounded px-0.5">$1</mark>');
}

function escapeHtml(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function escapeRegex(str) {
  return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
// ────────────────────────────────────────────────────────────────

const tableFontStyle = computed(() => ({
  fontSize: `${tableFontSize.value}px`,
}));

const opdOptionsGrouped = computed(() => {
  const all = Array.isArray(props.opds) ? props.opds : [];

  const sekdaParentCode = '4.01.0.00.0.00.14.0000';
  const dinkesParentCode = '1.02.2.14.0.00.02.0000';

  const childMap = {
    [sekdaParentCode]: {
      prefix: '4.01',
      optionType: 'sub-sekda',
      badgeLabel: 'SUB BAGIAN',
    },
    [dinkesParentCode]: {
      prefix: '1.02.2.14.0.00.02.',
      optionType: 'sub-dinkes',
      badgeLabel: 'SUB UNIT',
    },
  };

  const parents = all
    .slice()
    .sort((a, b) => String(a.nama || '').localeCompare(String(b.nama || '')));

  const usedChildCodes = new Set();
  const result = [];

  parents.forEach((parent) => {
    const parentCode = String(parent.kode || '');
    const config = childMap[parentCode];

    result.push({ ...parent, optionType: 'main', badgeLabel: 'SKPD' });

    if (!config) return;

    const children = all
      .filter(opd => {
        const kode = String(opd.kode || '');
        return kode.startsWith(config.prefix) && kode !== parentCode;
      })
      .sort((a, b) => String(a.nama || '').localeCompare(String(b.nama || '')))
      .map(opd => ({
        ...opd,
        optionType: config.optionType,
        badgeLabel: config.badgeLabel,
      }));

    children.forEach((child) => {
      usedChildCodes.add(String(child.kode || ''));
      result.push(child);
    });
  });

  return result.filter((item, idx, arr) => {
    const code = String(item.kode || '');
    // Hindari duplikasi child jika parent lain kebetulan ter-loop lebih dulu.
    if (String(item.optionType || '').startsWith('sub-')) {
      return arr.findIndex(x => String(x.kode || '') === code && String(x.optionType || '') === String(item.optionType || '')) === idx;
    }

    // Jika parent adalah child dari parent terkelola, tampilkan hanya sebagai child.
    if (usedChildCodes.has(code) && item.optionType === 'main') {
      return false;
    }

    return arr.findIndex(x => String(x.kode || '') === code && String(x.optionType || '') === String(item.optionType || '')) === idx;
  });
});

function getQueryParam(name) {
  const url = new URL(window.location.href);
  return url.searchParams.get(name);
}

onMounted(() => {
  const opdId = getQueryParam('opd_id');
  const tahunQ = getQueryParam('tahun');

  if (opdId) {
    const found = props.opds.find(o => o.id == opdId);
    if (found) selectedOpd.value = found;
  } else if (props.opds && props.opds.length > 0) {
    selectedOpd.value = props.opds[0];
  }

  if (tahunQ && props.tahunList.includes(Number(tahunQ))) {
    selectedTahun.value = Number(tahunQ);
  } else {
    const nowYear = new Date().getFullYear();
    if (props.tahunList && props.tahunList.length > 0) {
      selectedTahun.value = props.tahunList.includes(nowYear) ? nowYear : props.tahunList[0];
    }
  }
});

// masterProgramList diterima dari server (sudah difilter berdasarkan OPD & tahun)
const masterProgramList = computed(() => props.masterProgramList || []);

const selectedOpdLabel = computed(() => selectedOpd.value?.nama ?? '');

const selectedProgram = computed(() =>
  masterProgramList.value.find(p => p.id === form.value.program_id) || null
);

function findKomponenById(items, targetId) {
  if (!Array.isArray(items) || !targetId) return null;

  for (const item of items) {
    if (Number(item?.id) === Number(targetId)) {
      return item;
    }

    if (Array.isArray(item?.children) && item.children.length) {
      const found = findKomponenById(item.children, targetId);
      if (found) return found;
    }
  }

  return null;
}

const editingParentKode = computed(() => {
  if (!editingRow.value?.parent_id) return '';
  return findKomponenById(props.data || [], editingRow.value.parent_id)?.kode || '';
});

const editingMasterOptions = computed(() => {
  if (!editingRow.value?.jenis) return [];

  const type = editingRow.value.jenis;
  const selectedOpdId = Number(selectedOpd.value?.id || 0);
  const parentKode = type === 'program' ? '' : String(editingParentKode.value || '');
  const currentKode = String(editingRow.value?.kode || '');
  const source = Array.isArray(props.masterReferensi?.[type]) ? props.masterReferensi[type] : [];
  const existingCodes = existingMasterCodeSets.value[type] || new Set();

  return source
    .filter((item) => {
      const sameOpd = Number(item.opd_id || 0) === selectedOpdId;
      if (!sameOpd) return false;

      if (!parentKode) return true;
      return String(item.kode || '').startsWith(parentKode + '.');
    })
    .map((item) => ({
      ...item,
      is_added: existingCodes.has(String(item.kode || '')) && String(item.kode || '') !== currentKode,
      is_current: String(item.kode || '') === currentKode,
    }))
    .sort((a, b) => String(a.kode || '').localeCompare(String(b.kode || '')));
});

const selectedEditMaster = computed(() => {
  if (!editingRow.value) return null;
  return editingMasterOptions.value.find((item) => String(item.id) === String(form.value.master_id)) || null;
});

const editingJenisLabel = computed(() => {
  if (!editingRow.value?.jenis) return '';
  if (editingRow.value.jenis === 'program') return 'Program';
  if (editingRow.value.jenis === 'kegiatan') return 'Kegiatan';
  if (editingRow.value.jenis === 'sub_kegiatan') return 'Sub Kegiatan';
  return editingRow.value.jenis;
});

function openForm(komponen = null) {
  editing.value = !!komponen;
  editingRow.value = komponen;
  const currentMaster = komponen
    ? (Array.isArray(props.masterReferensi?.[komponen.jenis]) ? props.masterReferensi[komponen.jenis] : [])
        .find(item => String(item.kode || '') === String(komponen.kode || ''))
    : null;
  form.value = {
    program_id: komponen?.program_id ?? '',
    master_type: komponen?.jenis ?? 'program',
    master_id: currentMaster?.id ? String(currentMaster.id) : '',
    id: komponen?.id ?? null,
    nama_komponen: komponen?.nama_komponen ?? '',
    jenis: komponen?.jenis ?? 'program',
    parent_id: komponen?.parent_id ?? null,
    kode: komponen?.kode ?? '',
    kode_program: komponen?.kode_program ?? '',
  };
  errors.value = {};
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingRow.value = null;
}

async function submitForm() {
  errors.value = {};
  const payload = { ...form.value };
  payload.opd_id = selectedOpd.value?.id;
  payload.tahun = selectedTahun.value;

  if (editingRow.value) {
    payload.parent_id = editingRow.value.parent_id ?? null;
    payload.master_type = editingRow.value.jenis;

    if (!payload.master_id) {
      window.alert('Pilih data dari database terlebih dahulu.');
      return;
    }
  }

  try {
    if (editing.value && form.value.id) {
      await router.put(route('renja.update', form.value.id), payload, {
        onSuccess: () => closeModal(),
        onError: (e) => { errors.value = e; },
      });
    } else {
      await router.post(route('renja.store'), payload, {
        onSuccess: () => closeModal(),
        onError: (e) => { errors.value = e; },
      });
    }
  } catch (e) {}
}

function onProgramChange() {
  // Bidang dan indikator otomatis dari selectedProgram (computed)
}

// Fetch data ulang jika filter berubah
watch([selectedOpd, selectedTahun], ([opd, tahun]) => {
  router.get(route('renja.index'), {
    opd_id: opd?.id || undefined,
    tahun: tahun || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
});

function getRowBg(jenis) {
  if (jenis === 'program') return 'bg-gray-400';
  if (jenis === 'kegiatan') return 'bg-yellow-200';
  if (jenis === 'sub_kegiatan') return 'bg-white';
  return 'bg-white';
}

function formatRupiah(value) {
  const amount = Number(value ?? 0);
  if (Number.isNaN(amount)) return '0';
  return amount.toLocaleString('id-ID');
}

function formatSifatIndikator(value) {
  const normalized = normalizeSifatIndikator(value);
  if (normalized === 'positif') return 'Positif';
  if (normalized === 'negatif') return 'Negatif';
  if (normalized === 'akumulatif') return 'Akumulatif';
  return '-';
}

function normalizeSifatIndikator(value) {
  const raw = String(value ?? '').trim().toLowerCase();

  if (raw === 'maximize') return 'positif';
  if (raw === 'minimize') return 'negatif';
  if (raw === 'stabilize') return 'akumulatif';

  if (['positif', 'negatif', 'akumulatif'].includes(raw)) {
    return raw;
  }

  return '';
}

function decreaseTableFont() {
  tableFontSize.value = Math.max(10, tableFontSize.value - 1);
}

function increaseTableFont() {
  tableFontSize.value = Math.min(18, tableFontSize.value + 1);
}

function openEditIndikatorPrompt(col) {
  if (!col?.indikatorId) return;

  const namaIndikator = window.prompt('Nama indikator:', col.namaIndikator || '');
  if (!namaIndikator) return;

  const sifatInput = window.prompt(
    'Sifat indikator (positif/negatif/akumulatif):',
    normalizeSifatIndikator(col.sifatRaw) || 'positif'
  );
  if (!sifatInput) return;

  const sifatIndikator = normalizeSifatIndikator(sifatInput);
  if (!sifatIndikator) {
    window.alert('Sifat indikator harus positif, negatif, atau akumulatif.');
    return;
  }

  const targetIndikator = window.prompt('Target indikator:', col.targetIndikator || '');
  if (!targetIndikator) return;

  const satuanIndikator = window.prompt('Satuan indikator:', col.satuanIndikator || '');
  if (!satuanIndikator) return;

  router.put(route('renja.indikator.update', col.indikatorId), {
    nama_indikator: namaIndikator,
    sifat_indikator: sifatIndikator,
    target_indikator: targetIndikator,
    satuan: satuanIndikator,
  }, {
    preserveScroll: true,
  });
}

function confirmDeleteIndikator(col) {
  if (!col?.indikatorId) return;
  if (confirm('Yakin ingin menghapus indikator ini?')) {
    router.delete(route('renja.indikator.destroy', col.indikatorId), {
      preserveScroll: true,
    });
  }
}

function buildNoticeRow({ key, jenis, message }) {
  const cols = [
    { value: '-', class: 'px-3 py-2 text-gray-500 border-b border-r border-gray-300' },
    { value: '-', class: 'px-3 py-2 text-gray-500 border-b border-r border-gray-300' },
    { value: '-', class: 'px-3 py-2 text-gray-500 border-b border-r border-gray-300' },
    { value: '-', class: 'px-3 py-2 text-gray-500 border-b border-r border-gray-300' },
    { value: message, class: 'px-3 py-2 font-semibold text-amber-700 border-b border-r border-gray-300' },
    { value: '-', class: 'px-3 py-2 text-right text-gray-500 border-b border-r border-gray-300' },
    { value: '-', class: 'px-2 py-1 text-center text-gray-500 border-b border-r border-gray-300' },
    { value: '-', class: 'px-2 py-1 text-center text-gray-500 border-b border-r border-gray-300' },
    { value: '-', class: 'px-3 py-2 text-gray-500 border-b border-r border-gray-300' },
    { value: '-', class: 'px-3 py-2 text-gray-500 border-b border-r border-gray-300' },
    { value: '-', class: 'px-3 py-2 text-gray-500 border-b border-r border-gray-300' },
    { value: '-', class: 'px-3 py-2 text-gray-500 border-b border-r border-gray-300' },
    { value: '-', class: 'px-2 py-1 text-center text-gray-500 border-b border-r border-gray-300' },
  ];

  return {
    key,
    isFirstRow: false,
    komponen: null,
    bg: getRowBg(jenis),
    rowspan: 1,
    searchText: message.toLowerCase(),
    cols,
  };
}

function renderRows(data, parentKey = '') {
  const rows = [];
  if (!data) return rows;
  data.forEach((komponen) => {

    const indikator = komponen.indikator.length ? komponen.indikator : [{ nama_indikator: '', sifat_indikator: '', target_indikator: '', satuan: '' }];
    indikator.forEach((ind, i) => {
      const key = `${parentKey}${komponen.id}-${i}`;
      rows.push({
        key,
        isFirstRow: i === 0,
        komponen,
        bg: getRowBg(komponen.jenis),
        rowspan: indikator.length,
        searchText: [
          komponen.kode,
          komponen.nama_komponen,
          komponen.sub_unit,
          komponen.urusan,
          komponen.bidang_urusan,
          ind.nama_indikator,
          ind.target_indikator,
          ind.satuan,
        ].map(v => String(v ?? '')).join(' ').toLowerCase(),
        cols: [
          { value: komponen.sub_unit, rowspan: indikator.length, skip: i !== 0, class: 'px-3 py-2 align-top border-b border-r border-gray-300' },
          { value: komponen.urusan, rowspan: indikator.length, skip: i !== 0, class: 'px-3 py-2 align-top border-b border-r border-gray-300' },
          { value: komponen.bidang_urusan, rowspan: indikator.length, skip: i !== 0, class: 'px-3 py-2 align-top border-b border-r border-gray-300' },
          { value: komponen.kode, rowspan: indikator.length, skip: i !== 0, class: 'px-3 py-2 align-top font-semibold border-b border-r border-gray-300 sticky-col-kode-body' },
          { value: komponen.nama_komponen, rowspan: indikator.length, skip: i !== 0, class: 'px-3 py-2 align-top font-bold border-b border-r border-gray-300 sticky-col-uraian-body' },
          // Pagu: editable untuk sub_kegiatan, computed (akumulasi) untuk kegiatan & program
          (komponen.jenis === 'sub_kegiatan')
            ? { type: 'pagu_input', komponentId: komponen.id, rawValue: String(komponen.pagu ?? 0), rowspan: indikator.length, skip: i !== 0, class: 'px-2 py-1 align-top border-b border-r border-gray-300 min-w-[120px]' }
            : { type: 'pagu_computed', komponen, rowspan: indikator.length, skip: i !== 0, class: 'px-3 py-2 align-top text-right whitespace-nowrap border-b border-r border-gray-300' },
          { type: 'komponen_action', rowspan: indikator.length, skip: i !== 0, class: 'px-2 py-1 text-center align-top border-b border-r border-gray-300 min-w-[92px]' },
          { type: 'indikator_action', rowspan: indikator.length, skip: i !== 0, class: 'px-2 py-1 text-center align-top border-b border-r border-gray-300 min-w-[110px]' },
          { value: ind.nama_indikator || '-', class: 'px-3 py-2 align-top text-left border-b border-r border-gray-300' },
          { value: formatSifatIndikator(ind.sifat_indikator), class: 'px-3 py-2 align-top text-left border-b border-r border-gray-300' },
          (ind.id)
            ? { type: 'indikator_target_input', indikatorId: ind.id, rawValue: ind.target_indikator ?? '', class: 'px-2 py-1 border-b border-r border-gray-300 min-w-[140px]' }
            : { value: ind.target_indikator || '-', class: 'px-3 py-2 border-b border-r border-gray-300' },
          { value: ind.satuan, class: 'px-3 py-2 align-top border-b border-r border-gray-300' },
          {
            type: 'indikator_item_action',
            indikatorId: ind.id ?? null,
            namaIndikator: ind.nama_indikator ?? '',
            sifatRaw: ind.sifat_indikator ?? '',
            targetIndikator: ind.target_indikator ?? '',
            satuanIndikator: ind.satuan ?? '',
            class: 'px-2 py-1 text-center border-b border-r border-gray-300 min-w-[92px]'
          },
        ]
      });
    });

    const children = Array.isArray(komponen.children) ? komponen.children : [];

    if (komponen.jenis === 'program') {
      const kegiatanRows = children.filter((child) => child.jenis === 'kegiatan');

      if (kegiatanRows.length) {
        rows.push(...renderRows(kegiatanRows, `${parentKey}${komponen.id}-`));
      } else {
        rows.push(buildNoticeRow({
          key: `${parentKey}${komponen.id}-missing-kegiatan`,
          jenis: 'kegiatan',
          message: 'Program ini belum memiliki kegiatan.',
        }));
      }
      return;
    }

    if (komponen.jenis === 'kegiatan') {
      const subKegiatanRows = children.filter((child) => child.jenis === 'sub_kegiatan');

      if (subKegiatanRows.length) {
        rows.push(...renderRows(subKegiatanRows, `${parentKey}${komponen.id}-`));
      } else {
        rows.push(buildNoticeRow({
          key: `${parentKey}${komponen.id}-missing-sub-kegiatan`,
          jenis: 'sub_kegiatan',
          message: 'Kegiatan ini belum memiliki sub kegiatan.',
        }));
      }
      return;
    }

    if (children.length) {
      rows.push(...renderRows(children, `${parentKey}${komponen.id}-`));
    }
  });
  return rows;
}

function confirmDelete(komponen) {
  if (confirm('Yakin ingin menghapus? Semua child akan ikut terhapus!')) {
    router.delete(route('renja.destroy', komponen.id));
  }
}
</script>

<style scoped>
.sticky-col-kode-header {
  position: sticky;
  left: 0;
  top: 0;
  z-index: 45;
  min-width: 160px;
  background: #f3f4f6;
  box-shadow: 1px 0 0 #d1d5db;
}

.sticky-col-uraian-header {
  position: sticky;
  left: 160px;
  top: 0;
  z-index: 45;
  min-width: 280px;
  background: #f3f4f6;
  box-shadow: 1px 0 0 #d1d5db;
}

.sticky-col-kode-body {
  position: sticky;
  left: 0;
  top: auto;
  z-index: 15;
  min-width: 160px;
  background-color: inherit;
  box-shadow: 1px 0 0 #d1d5db;
}

.sticky-col-uraian-body {
  position: sticky;
  left: 160px;
  top: auto;
  z-index: 15;
  min-width: 280px;
  background-color: inherit;
  box-shadow: 1px 0 0 #d1d5db;
}

.btn-save-blink {
  animation: saveBlink 1.8s ease-in-out infinite;
}

@keyframes saveBlink {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(132, 204, 22, 0.05);
    transform: translateY(0);
  }
  50% {
    box-shadow: 0 0 0 4px rgba(132, 204, 22, 0.22);
    transform: translateY(-1px);
  }
}
</style>
