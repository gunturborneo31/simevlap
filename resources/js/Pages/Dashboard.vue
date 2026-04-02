<template>
  <AppLayout title="Dashboard">
    <div class="mb-6">
      <h2 class="text-xl font-semibold text-gray-700">Selamat Datang, {{ user.name }}</h2>
      <p class="text-gray-500 text-sm">{{ user.opd?.nama ?? 'Pemerintah Daerah' }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
      <StatCard title="Total OPD Aktif" :value="stats.total_opd" icon="🏛️" />
      <StatCard title="Total Program" :value="stats.total_program" icon="📊" />
      <StatCard title="Total Realisasi" :value="stats.total_realisasi" icon="📈" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-base font-semibold text-gray-700 mb-4">Menu Utama</h3>
        <ul class="space-y-3">
          <li>
            <Link :href="route('bank-data.index')" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors">
              <span class="text-2xl">📊</span>
              <div>
                <p class="font-medium text-gray-800">Bank Data</p>
                <p class="text-xs text-gray-500">Kelola data visi, program, kegiatan, dan sub kegiatan</p>
              </div>
            </Link>
          </li>
          <li>
            <Link :href="route('dokumen.index')" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors">
              <span class="text-2xl">📄</span>
              <div>
                <p class="font-medium text-gray-800">Dokumen</p>
                <p class="text-xs text-gray-500">Upload dan kelola dokumen perencanaan</p>
              </div>
            </Link>
          </li>
          <li>
            <Link :href="route('realisasi.index')" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors">
              <span class="text-2xl">📈</span>
              <div>
                <p class="font-medium text-gray-800">Realisasi</p>
                <p class="text-xs text-gray-500">Input capaian realisasi fisik dan keuangan</p>
              </div>
            </Link>
          </li>
          <li>
            <Link :href="route('resume.index')" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors">
              <span class="text-2xl">📋</span>
              <div>
                <p class="font-medium text-gray-800">Resume</p>
                <p class="text-xs text-gray-500">Lihat ringkasan monitoring evaluasi laporan</p>
              </div>
            </Link>
          </li>
        </ul>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-base font-semibold text-gray-700 mb-4">Informasi Sistem</h3>
        <dl class="space-y-3 text-sm">
          <div class="flex justify-between py-2 border-b border-gray-100">
            <dt class="text-gray-500">Nama Pengguna</dt>
            <dd class="font-medium text-gray-800">{{ user.name }}</dd>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100">
            <dt class="text-gray-500">Email</dt>
            <dd class="font-medium text-gray-800">{{ user.email }}</dd>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100">
            <dt class="text-gray-500">OPD</dt>
            <dd class="font-medium text-gray-800">{{ user.opd?.nama ?? 'Pemerintah Daerah' }}</dd>
          </div>
          <div class="flex justify-between py-2">
            <dt class="text-gray-500">Versi Sistem</dt>
            <dd class="font-medium text-blue-600">SIMEVLAP 2.0</dd>
          </div>
        </dl>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
  stats: Object,
  user: Object,
});
</script>
