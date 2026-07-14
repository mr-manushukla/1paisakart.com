<script setup>
// Native <details> = accessible, crawlable, zero-JS accordion (research: prefer over tabs).
defineProps({
  description: { type: String, default: '' },
  brand: { type: String, default: '' },
  specs: { type: Array, default: () => [] }, // [{ label, value }]
})
</script>

<template>
  <div class="space-y-3">
    <details open class="card overflow-hidden">
      <summary class="cursor-pointer list-none px-5 py-4 font-semibold marker:hidden">Description</summary>
      <div class="border-t border-slate-100 px-5 py-4 text-slate-600">{{ description || 'No description provided.' }}</div>
    </details>

    <details v-if="brand || specs.length" open class="card overflow-hidden">
      <summary class="cursor-pointer list-none px-5 py-4 font-semibold marker:hidden">Product information</summary>
      <table class="w-full border-t border-slate-100 text-sm">
        <tbody>
          <tr v-if="brand" class="border-b border-slate-50">
            <th class="w-40 bg-slate-50/60 px-5 py-2.5 text-left font-medium text-slate-500">Brand</th>
            <td class="px-5 py-2.5 text-slate-700">{{ brand }}</td>
          </tr>
          <tr v-for="s in specs" :key="s.label" class="border-b border-slate-50 last:border-0">
            <th class="w-40 bg-slate-50/60 px-5 py-2.5 text-left font-medium text-slate-500">{{ s.label }}</th>
            <td class="px-5 py-2.5 text-slate-700">{{ s.value }}</td>
          </tr>
        </tbody>
      </table>
    </details>
  </div>
</template>
