<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#00694f] via-[#009e60] to-[#38b449]">
    <div class="w-full max-w-4xl bg-white rounded-3xl shadow-2xl flex overflow-hidden">
      <!-- Kiri: Ilustrasi/teks motivasi -->
      <div class="hidden md:flex flex-col justify-center items-center w-1/2 bg-[#003d2c] relative p-12">
        <div class="absolute inset-0 z-0" style="background: radial-gradient(ellipse at 60% 80%, #38b449 0%, #003d2c 70%); filter: blur(8px); opacity:0.7;"></div>
        <div class="relative z-10 text-left w-full">
          <h2 class="text-3xl font-bold text-white mb-4 drop-shadow">SIMEVLAP 2.0<br><span class='text-green-200 text-lg font-medium'>Sistem Monitoring Evaluasi Laporan</span></h2>
        </div>
      </div>
      <!-- Kanan: Form login -->
      <div class="w-full md:w-1/2 bg-white p-12 flex flex-col justify-center">
        <div class="flex flex-col items-center mb-8">
          <div class="bg-green-100 rounded-full p-3 mb-2">
            <svg xmlns='http://www.w3.org/2000/svg' class='h-10 w-10 text-[#38b449]' fill='none' viewBox='0 0 24 24' stroke='currentColor'><circle cx='12' cy='12' r='10' stroke-width='2' /><circle cx='12' cy='10' r='3' /><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804' /></svg>
          </div>
          <h1 class="text-3xl font-extrabold text-[#00694f] tracking-tight">SIMEVLAP 2.0</h1>
          <p class="text-gray-500 text-base mt-1 font-medium">Masuk ke sistem monitoring evaluasi laporan</p>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
          <p v-if="form.errors.email" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 border border-red-200">{{ form.errors.email }}</p>
          <div>
            <label class="block text-sm font-semibold text-[#00694f] mb-1">Username / Email</label>
            <input v-model="form.email" type="text" class="w-full border border-[#38b449] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#38b449] bg-white placeholder:text-[#38b449]" placeholder="Masukkan username atau email" required />
          </div>
          <div>
            <label class="block text-sm font-semibold text-[#00694f] mb-1">Password</label>
            <input v-model="form.password" type="password" class="w-full border border-[#38b449] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#38b449] bg-white placeholder:text-[#38b449]" placeholder="••••••••" required />
            <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
          </div>
          <label class="flex items-center gap-2 text-sm text-[#00694f] cursor-pointer select-none">
            <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-[#38b449] text-[#00694f] focus:ring-[#38b449]" />
            Ingat saya
          </label>
          <button type="submit" :disabled="form.processing" class="w-full bg-[#38b449] text-white py-3 rounded-xl font-bold shadow hover:bg-[#2e9e3e] hover:scale-[1.03] transition-all duration-200 disabled:opacity-50 text-lg">
            <span v-if="form.processing">Memproses...</span>
            <span v-else>Masuk</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });

function submit() {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
}
</script>
