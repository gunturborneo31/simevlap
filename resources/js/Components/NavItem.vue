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

const props = defineProps({ href: String, icon: String, alsoActive: { type: Array, default: () => [] } });

const page = usePage();
const isActive = computed(() => {
  try {
    // Cocokkan per segmen path agar /resume2 tidak ikut mengaktifkan menu /resume.
    const matches = (path) => page.url === path || (page.url.startsWith(path) && /^[/?#]/.test(page.url.slice(path.length)));
    return [new URL(props.href, window.location.origin).pathname, ...props.alsoActive].some(matches);
  } catch {
    return false;
  }
});
</script>
