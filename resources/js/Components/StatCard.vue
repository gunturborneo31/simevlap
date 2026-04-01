<template>
  <div class="bg-white rounded-xl shadow p-6 flex items-center gap-4">
    <div class="text-4xl">{{ icon }}</div>
    <div>
      <p class="text-sm text-gray-500">{{ title }}</p>
      <p class="text-3xl font-bold text-blue-600">{{ value }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({ title: String, value: [Number, String], icon: String });
</script>
