<template>
  <div class="flex justify-between gap-2 whitespace-nowrap">
    <span>Rp.</span>
    <span>{{ value === null || value === undefined ? '-' : formatAngka(value, 0) }}</span>
  </div>
</template>

<script setup>
import { formatAngka } from '@/utils/format';

defineProps({
  value: { type: Number, default: null },
});
</script>
