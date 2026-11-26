<template>
  <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
      <component :is="icon" :size="26" />
    </div>
    <h3 class="text-sm font-bold text-slate-800">{{ title }}</h3>
    <p v-if="description" class="mt-1.5 max-w-sm text-xs leading-relaxed text-slate-500">{{ description }}</p>
    <div v-if="$slots.action" class="mt-5"><slot name="action" /></div>
  </div>
</template>

<script setup>
import { InboxIcon } from 'lucide-vue-next'

defineProps({
  title: { type: String, default: 'Nothing here yet' },
  description: { type: String, default: '' },
  icon: { type: [Object, Function], default: InboxIcon },
})
</script>
