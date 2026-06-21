<template>
  <div class="min-h-screen bg-slate-50">
    <header class="sticky top-0 z-40 border-b border-emerald-900/60 bg-gradient-to-r from-[#064E3B] via-[#0B5F49] to-[#0E6B52] shadow-xl shadow-emerald-950/20">
      <div class="mx-auto flex h-16 max-w-[1400px] items-center gap-3 px-3 sm:px-6">
        <span class="shrink-0 text-lg font-bold tracking-tight text-white">SIMEVLAP 2.0</span>

        <nav class="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto whitespace-nowrap pr-2">
          <NavItem v-if="canAccessDashboard" :href="route('dashboard')">Dashboard</NavItem>
          <NavItem v-if="canAccessDataDasar" :href="route('data-dasar.index')">Data Dasar</NavItem>
          <NavItem v-if="canAccessDokumen" :href="route('dokumen.index')">Dokumen</NavItem>
          <NavItem v-if="canAccessRealisasi" :href="route('realisasi.index')">Realisasi</NavItem>
          <NavItem v-if="isVerifikator" :href="route('verifikator.index')">Verifikator</NavItem>
          <NavItem :href="route('resume.index')">Resume</NavItem>
        </nav>

        <template v-if="canAccessSettings">
          <div class="relative shrink-0 group" ref="settingsDropdownRef">
            <button
              class="inline-flex items-center gap-1 rounded-md border border-[#C7EA46]/80 bg-emerald-950/20 px-3 py-2 text-sm font-medium text-emerald-50 transition-colors hover:border-[#C7EA46] hover:bg-emerald-900/50"
            >
              ⚙ Pengaturan
              <span class="text-xs">∨</span>
            </button>
            <div class="absolute right-0 top-11 hidden w-44 rounded-lg border border-[#C7EA46]/40 bg-white p-1 shadow-lg group-hover:block">
              <Link :href="route('pengaturan.opd.index')" class="block rounded-md px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-lime-50">OPD</Link>
              <Link :href="route('pengaturan.user.index')" class="block rounded-md px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-lime-50">User</Link>
              <Link :href="route('pengaturan.kepmen.index')" class="block rounded-md px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-lime-50">Kepmen</Link>
            </div>
          </div>
        </template>

        <div class="shrink-0 text-right">
          <p class="text-sm font-semibold text-emerald-50">{{ $page.props.auth?.user?.name }}</p>
          <p class="text-xs text-emerald-100/80">{{ $page.props.auth?.user?.opd?.singkatan ?? 'Pemda' }}</p>
        </div>
        <button @click="logout" class="shrink-0 rounded-md border border-[#C7EA46] bg-[#C7EA46] px-3 py-1.5 text-xs font-semibold text-[#234123] transition-colors hover:bg-[#D4F06A]">
          Logout
        </button>
      </div>
    </header>

    <div class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6">
      <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 v-if="breadcrumbs.length" class="text-lg font-semibold text-gray-800">
            <template v-for="(crumb, idx) in breadcrumbs" :key="`${crumb.label}-${idx}`">
              <Link
                :href="crumb.href"
                class="transition-colors hover:text-emerald-700"
              >
                {{ crumb.label }}
              </Link>
              <span v-if="idx < breadcrumbs.length - 1" class="px-1.5 text-slate-400">/</span>
            </template>
          </h1>
          <h1 v-else class="text-lg font-semibold text-gray-800">{{ title }}</h1>
          <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
        </div>
        <p v-if="rightInfo" class="text-right text-sm font-medium text-emerald-900 sm:max-w-[55%]">
          {{ rightInfo }}
        </p>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-4 rounded border border-green-300 bg-green-100 p-3 text-sm text-green-800">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="mb-4 rounded border border-red-300 bg-red-100 p-3 text-sm text-red-800">
        {{ $page.props.flash.error }}
      </div>

      <main>
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import NavItem from '@/Components/NavItem.vue';

defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  rightInfo: { type: String, default: '' },
  breadcrumbs: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const userRoles = computed(() => page.props.auth?.user?.roles ?? []);
const isPimpinan = computed(() => userRoles.value.includes('pimpinan'));
const isVerifikator = computed(() => userRoles.value.includes('verifikator'));
const isOpd = computed(() => userRoles.value.includes('opd'));
const canAccessDashboard = computed(() => !isPimpinan.value);
const canAccessDataDasar = computed(() => !isPimpinan.value && !isOpd.value);
const canAccessDokumen = computed(() => !isPimpinan.value);
const canAccessRealisasi = computed(() => !isPimpinan.value);
const canAccessSettings = computed(() => {
  return userRoles.value.includes('superadmin') || userRoles.value.includes('admin');
});

function logout() {
  router.post('/logout');
}
</script>
