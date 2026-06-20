<template>
  <AppLayout title="Edit IKU">
    <div class="max-w-2xl mx-auto bg-white rounded-xl shadow p-6 mt-6">
      <h1 class="text-lg font-bold mb-4">Edit IKU</h1>
      <form @submit.prevent="submit">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block mb-1 font-medium">Indikator</label>
            <input v-model="form.indikator" type="text" class="input-base" required />
            <div v-if="form.errors.indikator" class="text-red-500 text-xs">{{ form.errors.indikator }}</div>
          </div>
          <div>
            <label class="block mb-1 font-medium">Satuan</label>
            <input v-model="form.satuan" type="text" class="input-base" required />
            <div v-if="form.errors.satuan" class="text-red-500 text-xs">{{ form.errors.satuan }}</div>
          </div>
          <div>
            <label class="block mb-1 font-medium">Capaian 2024</label>
            <input v-model="form.capaian_2024" type="text" class="input-base" required />
            <div v-if="form.errors.capaian_2024" class="text-red-500 text-xs">{{ form.errors.capaian_2024 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-medium">Target 2025</label>
            <input v-model="form.target_2025" type="text" class="input-base" required />
            <div v-if="form.errors.target_2025" class="text-red-500 text-xs">{{ form.errors.target_2025 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-medium">Target 2026</label>
            <input v-model="form.target_2026" type="text" class="input-base" required />
            <div v-if="form.errors.target_2026" class="text-red-500 text-xs">{{ form.errors.target_2026 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-medium">Target 2027</label>
            <input v-model="form.target_2027" type="text" class="input-base" required />
            <div v-if="form.errors.target_2027" class="text-red-500 text-xs">{{ form.errors.target_2027 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-medium">Target 2028</label>
            <input v-model="form.target_2028" type="text" class="input-base" required />
            <div v-if="form.errors.target_2028" class="text-red-500 text-xs">{{ form.errors.target_2028 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-medium">Target 2029</label>
            <input v-model="form.target_2029" type="text" class="input-base" required />
            <div v-if="form.errors.target_2029" class="text-red-500 text-xs">{{ form.errors.target_2029 }}</div>
          </div>
          <div>
            <label class="block mb-1 font-medium">Target 2030</label>
            <input v-model="form.target_2030" type="text" class="input-base" required />
            <div v-if="form.errors.target_2030" class="text-red-500 text-xs">{{ form.errors.target_2030 }}</div>
          </div>
        </div>
        <div class="flex gap-2 mt-6">
          <Link :href="route('iku.index')" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700">Kembali</Link>
          <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ iku: Object });
const form = useForm({
  indikator: props.iku.indikator,
  satuan: props.iku.satuan,
  capaian_2024: props.iku.capaian_2024,
  target_2025: props.iku.target_2025,
  target_2026: props.iku.target_2026,
  target_2027: props.iku.target_2027,
  target_2028: props.iku.target_2028,
  target_2029: props.iku.target_2029,
  target_2030: props.iku.target_2030,
});
function submit() {
  form.put(route('iku.update', props.iku.id));
}
</script>
