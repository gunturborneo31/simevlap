<template>
  <nav v-if="links && links.length > 1" aria-label="Pagination" class="mt-6">
    <ul class="flex justify-center gap-1">
      <li v-for="(link, i) in links" :key="i">
        <button
          v-if="link.url"
          :disabled="!link.url"
          @click.prevent="$emit('navigate', link.url)"
          v-html="link.label"
          :class="[
            'min-w-[36px] px-3 py-1 rounded-lg border text-sm font-semibold transition',
            link.active
              ? 'bg-emerald-600 text-white border-emerald-600 cursor-default'
              : 'bg-white text-emerald-700 border-emerald-200 hover:bg-emerald-50 hover:border-emerald-400',
            !link.url ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' : ''
          ]"
        />
        <span
          v-else
          v-html="link.label"
          class="min-w-[36px] px-3 py-1 rounded-lg border text-sm font-semibold bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
        />
      </li>
    </ul>
  </nav>
</template>

<script setup>
const props = defineProps({
  links: Array,
});
</script>
