<template>
  <div>
    <label v-if="label" :for="id" class="label">
      {{ label }}<span v-if="required" class="ml-0.5 text-rose-500">*</span>
    </label>
    <slot />
    <p v-if="error" class="mt-1 text-[11px] font-medium text-rose-600">{{ error }}</p>
    <p v-else-if="hint" class="mt-1 text-[11px] leading-relaxed text-slate-400">{{ hint }}</p>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, default: null },
  id: { type: String, default: undefined },
  required: { type: Boolean, default: false },
  hint: { type: String, default: null },
  error: { type: String, default: null },
})
</script>
