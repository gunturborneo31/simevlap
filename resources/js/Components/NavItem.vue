<template>
  <Link
    :href="href"
    class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors"
    :class="isActive ? 'bg-blue-800 text-white' : 'text-blue-200 hover:bg-blue-800 hover:text-white'"
  >
    <span class="mr-3">{{ icon }}</span>
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
