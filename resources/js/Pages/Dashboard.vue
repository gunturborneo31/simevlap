
<template>
  <div class="min-h-screen bg-gradient-to-br from-emerald-900 via-emerald-700 to-lime-600 flex flex-col">
    <header class="w-full py-8 px-4 md:px-0 flex flex-col items-center text-center">
      <h1 class="text-5xl md:text-6xl font-extrabold text-white drop-shadow mb-2">SIMEVLAP 2.0</h1>
      <p class="text-xl md:text-2xl text-lime-200 mb-2 font-medium">Sistem Monitoring Evaluasi Laporan</p>
      <p class="text-lime-100 mb-6 max-w-2xl">Platform terintegrasi untuk monitoring, evaluasi, dan pelaporan pembangunan daerah secara digital, cepat, dan transparan.</p>
      <div class="flex flex-wrap justify-center gap-4 mb-4">
        <div class="bg-white/90 rounded-xl shadow-lg px-8 py-6 flex flex-col items-center min-w-[220px]">
          <div class="text-4xl mb-2">🏛️</div>
          <div class="text-2xl font-bold text-emerald-700">{{ stats.total_opd }}</div>
          <div class="text-sm text-gray-700">OPD Aktif</div>
        </div>
        <div class="bg-white/90 rounded-xl shadow-lg px-8 py-6 flex flex-col items-center min-w-[220px]">
          <div class="text-4xl mb-2">📊</div>
          <div class="text-2xl font-bold text-emerald-700">{{ stats.total_program }}</div>
          <div class="text-sm text-gray-700">Program</div>
        </div>
        <div class="bg-white/90 rounded-xl shadow-lg px-8 py-6 flex flex-col items-center min-w-[220px]">
          <div class="text-4xl mb-2">📈</div>
          <div class="text-2xl font-bold text-emerald-700">{{ stats.total_realisasi }}</div>
          <div class="text-sm text-gray-700">Realisasi</div>
        </div>
      </div>
      <div class="flex flex-col md:flex-row justify-center gap-4 mt-4">
        <div class="bg-emerald-800/80 rounded-2xl shadow-lg p-6 text-left text-white max-w-md w-full">
          <h2 class="text-xl font-bold mb-2">Rangkuman Sistem</h2>
          <ul class="list-disc pl-5 space-y-1 text-lime-100">
            <li>Monitoring realisasi fisik & keuangan seluruh OPD</li>
            <li>Evaluasi program, kegiatan, dan sub kegiatan</li>
            <li>Upload & manajemen dokumen perencanaan</li>
            <li>Resume laporan dan analitik capaian</li>
            <li>Integrasi data antar bidang & OPD</li>
          </ul>
        </div>
        <div class="bg-white/90 rounded-2xl shadow-lg p-6 text-emerald-900 max-w-md w-full flex flex-col gap-2">
          <h2 class="text-xl font-bold mb-2">Akses Cepat</h2>
          <Link :href="route('data-dasar.index')" class="block py-2 px-4 rounded-lg bg-emerald-700 text-white font-semibold hover:bg-emerald-800 transition">Data Dasar</Link>
          <Link :href="'/data-dasar/dokumen'" class="block py-2 px-4 rounded-lg bg-emerald-700 text-white font-semibold hover:bg-emerald-800 transition">Dokumen</Link>
          <Link :href="route('realisasi.index')" class="block py-2 px-4 rounded-lg bg-emerald-700 text-white font-semibold hover:bg-emerald-800 transition">Realisasi</Link>
          <Link :href="route('resume.index')" class="block py-2 px-4 rounded-lg bg-emerald-700 text-white font-semibold hover:bg-emerald-800 transition">Resume</Link>
        </div>
      </div>
    </header>
    <footer class="mt-auto py-6 text-center text-lime-100 text-sm opacity-80">
      &copy; {{ new Date().getFullYear() }} SIMEVLAP 2.0 — Monitoring & Evaluasi Pembangunan Daerah
    </footer>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({
  stats: Object,
  user: Object,
});
</script>
