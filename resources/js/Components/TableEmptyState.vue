<template>
  <tr>
    <td :colspan="colspan" class="text-center py-6 text-gray-400">
      Tidak ada data yang ditemukan.<br>
      <button v-if="showReset" class="btn btn-link" @click="$emit('reset')">Reset Filter</button>
    </td>
  </tr>
</template>
<script setup>
const props = defineProps({
  colspan: { type: Number, required: true },
  showReset: { type: Boolean, default: false },
});
</script>
