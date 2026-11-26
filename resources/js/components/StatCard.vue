<template>
  <component :is="to ? 'router-link' : 'div'" :to="to" class="card group flex items-center gap-4 p-4 transition-all duration-200" :class="to ? 'hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md' : ''">
    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="toneStyle.icon">
      <component :is="icon" :size="20" />
    </div>
    <div class="min-w-0 flex-1">
      <p class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ label }}</p>
      <div class="mt-0.5 flex items-baseline gap-2">
        <p class="text-2xl font-bold tracking-tight text-slate-900">{{ value ?? 0 }}</p>
        <span v-if="hint" class="truncate text-[11px] text-slate-400">{{ hint }}</span>
      </div>
    </div>
    <ArrowUpRightIcon v-if="to" :size="15" class="shrink-0 text-slate-300 transition group-hover:text-brand-500" />
  </component>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowUpRightIcon } from 'lucide-vue-next'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: [Object, Function], default: null },
  tone: { type: String, default: 'brand' },
  hint: { type: String, default: '' },
  to: { type: [String, Object], default: null },
})

const tones = {
  brand: 'bg-brand-50 text-brand-600',
  emerald: 'bg-emerald-50 text-emerald-600',
  amber: 'bg-amber-50 text-amber-600',
  violet: 'bg-violet-50 text-violet-600',
  rose: 'bg-rose-50 text-rose-600',
  slate: 'bg-slate-100 text-slate-500',
}
const toneStyle = computed(() => tones[props.tone] || tones.brand)
</script>
