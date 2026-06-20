<template>
  <Link
    :href="href"
    class="flex items-center rounded-md border px-3 py-2 text-sm font-medium transition-colors"
    :class="isActive ? 'border-white/50 bg-white text-emerald-900 shadow-sm' : 'border-white/10 bg-emerald-950/20 text-white/95 hover:border-white/25 hover:bg-emerald-950/30 hover:text-white'"
  >
    <slot />
  </Link>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({ href: String, icon: String });

const page = usePage();
const isActive = computed(() => {
  try {
    return page.url.startsWith(new URL(props.href, window.location.origin).pathname);
  } catch {
    return false;
  }
});
</script>
