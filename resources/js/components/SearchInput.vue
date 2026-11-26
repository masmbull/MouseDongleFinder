<template>
  <div class="relative">
    <SearchIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" :size="16" />
    <input
      :id="id"
      :value="modelValue"
      type="search"
      :placeholder="placeholder"
      :aria-label="ariaLabel || placeholder"
      class="input pl-9 pr-9"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <button
      v-if="modelValue"
      type="button"
      aria-label="Clear search"
      class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
      @click="$emit('update:modelValue', '')"
    >
      <XIcon :size="14" />
    </button>
  </div>
</template>

<script setup>
import { SearchIcon, XIcon } from 'lucide-vue-next'

defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search...' },
  ariaLabel: { type: String, default: null },
  id: { type: String, default: undefined },
})

defineEmits(['update:modelValue'])
</script>
