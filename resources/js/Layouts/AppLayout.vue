<template>
  <div class="min-h-screen bg-gray-50">
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-blue-900 flex flex-col">
      <div class="flex items-center h-16 px-4 bg-blue-950">
        <span class="text-white font-bold text-xl">SIMEVLAP 2.0</span>
      </div>
      <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <NavItem :href="route('dashboard')" icon="🏠">Dashboard</NavItem>
        <div class="pt-2">
          <p class="px-3 text-xs font-semibold text-blue-300 uppercase tracking-wider mb-1">Data Dasar</p>
          <NavItem :href="route('bank-data.index')" icon="📊">Bank Data</NavItem>
          <NavItem :href="route('dokumen.index')" icon="📄">Dokumen</NavItem>
        </div>
        <NavItem :href="route('realisasi.index')" icon="📈">Realisasi</NavItem>
        <NavItem :href="route('resume.index')" icon="📋">Resume</NavItem>
        <div v-if="isSuperadmin" class="pt-2">
          <p class="px-3 text-xs font-semibold text-blue-300 uppercase tracking-wider mb-1">Pengaturan</p>
          <NavItem :href="route('pengaturan.opd.index')" icon="🏛️">OPD</NavItem>
          <NavItem :href="route('pengaturan.user.index')" icon="👥">User</NavItem>
          <NavItem :href="route('pengaturan.kepmen.index')" icon="📜">Kepmen</NavItem>
        </div>
      </nav>
      <div class="p-4 border-t border-blue-800">
        <p class="text-white text-sm font-medium">{{ $page.props.auth?.user?.name }}</p>
        <p class="text-blue-300 text-xs">{{ $page.props.auth?.user?.opd?.singkatan ?? 'Superadmin' }}</p>
        <button @click="logout" class="mt-2 text-xs text-blue-300 hover:text-white underline">Logout</button>
      </div>
    </div>
    <div class="pl-64">
      <div class="h-16 bg-white border-b border-gray-200 flex items-center px-6 shadow-sm">
        <h1 class="text-lg font-semibold text-gray-800">{{ title }}</h1>
      </div>
      <div v-if="$page.props.flash?.success" class="mx-6 mt-4 p-3 bg-green-100 border border-green-300 rounded text-green-800 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="mx-6 mt-4 p-3 bg-red-100 border border-red-300 rounded text-red-800 text-sm">
        {{ $page.props.flash.error }}
      </div>
      <main class="p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import NavItem from '@/Components/NavItem.vue';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const isSuperadmin = computed(() => page.props.auth?.user?.roles?.includes('superadmin'));

function logout() {
  router.post(route('logout'));
}
</script>
