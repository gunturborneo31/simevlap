<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-900 to-blue-700 flex items-center justify-center">
    <div class="text-center text-white">
      <h1 class="text-5xl font-bold mb-4">SIMEVLAP 2.0</h1>
      <p class="text-xl text-blue-200 mb-2">Sistem Monitoring Evaluasi Laporan</p>
      <p class="text-blue-300 mb-8">Monitoring dan Evaluasi Laporan Perencanaan Pembangunan Daerah</p>
      <Link :href="route('login')" class="inline-block bg-white text-blue-900 font-semibold px-8 py-3 rounded-full hover:bg-blue-50 transition-colors">
        Masuk ke Sistem
      </Link>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>
