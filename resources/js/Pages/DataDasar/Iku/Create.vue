<template>
  <AppLayout title="Tambah IKU">
    <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">
      <h1 class="text-xl font-bold text-emerald-900 mb-6">Tambah IKU</h1>
      <form @submit.prevent="submit">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block mb-1 font-semibold text-gray-700">Indikator <span class="text-red-500">*</span></label>
            <input v-model="form.indikator" type="text" class="w-full px-4 py-2 rounded-lg border-2 border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none transition" required />
            <div v-if="form.errors.indikator" class="text-red-500 text-xs mt-1">{{ form.errors.indikator }}</div>
          </div>
          <div>
            <label class="block mb-1 font-semibold text-gray-700">Satuan <span class="text-red-500">*</span></label>
            <input v-model="form.satuan" type="text" class="w-full px-4 py-2 rounded-lg border-2 border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none transition" required />
            <div v-if="form.errors.satuan" class="text-red-500 text-xs mt-1">{{ form.errors.satuan }}</div>
          </div>
          <div>
            <label class="block mb-1 font-semibold text-gray-700">Capaian 2024 <span class="text-red-500">*</span></label>
            <input v-model="form.capaian_2024" type="text" class="w-full px-4 py-2 rounded-lg border-2 border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none transition" required />
            <div v-if="form.errors.capaian_2024" class="text-red-500 text-xs mt-1">{{ form.errors.capaian_2024 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-semibold text-gray-700">Target 2025 <span class="text-red-500">*</span></label>
            <input v-model="form.target_2025" type="text" class="w-full px-4 py-2 rounded-lg border-2 border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none transition" required />
            <div v-if="form.errors.target_2025" class="text-red-500 text-xs mt-1">{{ form.errors.target_2025 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-semibold text-gray-700">Target 2026 <span class="text-red-500">*</span></label>
            <input v-model="form.target_2026" type="text" class="w-full px-4 py-2 rounded-lg border-2 border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none transition" required />
            <div v-if="form.errors.target_2026" class="text-red-500 text-xs mt-1">{{ form.errors.target_2026 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-semibold text-gray-700">Target 2027 <span class="text-red-500">*</span></label>
            <input v-model="form.target_2027" type="text" class="w-full px-4 py-2 rounded-lg border-2 border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none transition" required />
            <div v-if="form.errors.target_2027" class="text-red-500 text-xs mt-1">{{ form.errors.target_2027 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-semibold text-gray-700">Target 2028 <span class="text-red-500">*</span></label>
            <input v-model="form.target_2028" type="text" class="w-full px-4 py-2 rounded-lg border-2 border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none transition" required />
            <div v-if="form.errors.target_2028" class="text-red-500 text-xs mt-1">{{ form.errors.target_2028 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-semibold text-gray-700">Target 2029 <span class="text-red-500">*</span></label>
            <input v-model="form.target_2029" type="text" class="w-full px-4 py-2 rounded-lg border-2 border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none transition" required />
            <div v-if="form.errors.target_2029" class="text-red-500 text-xs mt-1">{{ form.errors.target_2029 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-semibold text-gray-700">Target 2030 <span class="text-red-500">*</span></label>
            <input v-model="form.target_2030" type="text" class="w-full px-4 py-2 rounded-lg border-2 border-emerald-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none transition" required />
            <div v-if="form.errors.target_2030" class="text-red-500 text-xs mt-1">{{ form.errors.target_2030 }}</div>
          </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 mt-8 justify-end">
          <Link :href="route('iku.index')" class="px-6 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200 transition text-center">Kembali</Link>
          <button type="submit" class="px-6 py-2 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition text-center">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
const form = useForm({
  indikator: '',
  satuan: '',
  capaian_2024: '',
  target_2025: '',
  target_2026: '',
  target_2027: '',
  target_2028: '',
  target_2029: '',
  target_2030: '',
});
function submit() {
  form.post(route('iku.store'));
}
</script>
