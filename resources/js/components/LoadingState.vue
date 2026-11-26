<template>
  <div v-if="variant === 'cards'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <div v-for="n in rows" :key="n" class="card p-5">
      <div class="skeleton h-4 w-1/3"></div>
      <div class="skeleton mt-4 h-8 w-2/3"></div>
      <div class="skeleton mt-4 h-3 w-full"></div>
      <div class="skeleton mt-2 h-3 w-4/5"></div>
    </div>
  </div>
  <div v-else-if="variant === 'detail'" class="space-y-4">
    <div class="skeleton h-5 w-40"></div>
    <div class="grid gap-4 lg:grid-cols-3">
      <div class="skeleton h-64 rounded-2xl lg:col-span-1"></div>
      <div class="card space-y-3 p-6 lg:col-span-2">
        <div v-for="n in 6" :key="n" class="skeleton h-4" :class="n % 2 ? 'w-3/4' : 'w-full'"></div>
      </div>
    </div>
  </div>
  <div v-else class="divide-y divide-slate-100" role="status" :aria-label="label">
    <div v-for="n in rows" :key="n" class="flex items-center gap-4 px-4 py-4">
      <div class="skeleton h-10 w-10 shrink-0 rounded-xl"></div>
      <div class="flex-1 space-y-2">
        <div class="skeleton h-3.5 w-1/3"></div>
        <div class="skeleton h-3 w-1/2"></div>
      </div>
      <div class="skeleton hidden h-6 w-20 rounded-full sm:block"></div>
      <div class="skeleton hidden h-3 w-16 md:block"></div>
    </div>
    <span class="sr-only">{{ label }}</span>
  </div>
</template>

<script setup>
defineProps({
  rows: { type: Number, default: 5 },
  variant: { type: String, default: 'table' },
  label: { type: String, default: 'Loading data' },
})
</script>
