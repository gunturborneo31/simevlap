<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 sm:p-10"
      role="dialog"
      aria-modal="true"
      @click.self="$emit('close')"
    >
      <div class="flex max-h-full w-full max-w-4xl flex-col rounded-2xl bg-white px-7 py-5">
        <div class="flex items-center justify-between">
          <p class="text-2xl font-medium uppercase text-gray-800">{{ title }}</p>
          <button type="button" class="text-5xl font-bold leading-none text-black hover:scale-125" aria-label="Tutup" @click="$emit('close')">&times;</button>
        </div>
        <div class="mt-5 overflow-y-auto">
          <table class="r2-table">
            <thead>
              <tr>
                <th class="w-[75px]">No</th>
                <th>Nama</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in items" :key="index">
                <td>{{ index + 1 }}</td>
                <td class="text-left">{{ item }}</td>
              </tr>
              <tr v-if="!items.length">
                <td colspan="2">Tidak ada data yang ditemukan.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  items: { type: Array, default: () => [] },
});

defineEmits(['close']);
</script>
