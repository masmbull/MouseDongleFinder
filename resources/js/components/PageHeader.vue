<template>
  <div class="mb-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div class="min-w-0">
        <p v-if="eyebrow" class="mb-1 text-[11px] font-bold uppercase tracking-[0.14em] text-brand-600">{{ eyebrow }}</p>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-500">{{ subtitle }}</p>
        <slot />
      </div>
      <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  eyebrow: { type: String, default: '' },
})
</script>
