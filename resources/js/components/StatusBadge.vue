<template>
  <span
    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
    :class="tone.badge"
  >
    <span class="h-1.5 w-1.5 rounded-full" :class="tone.dot"></span>
    {{ labelText }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { statusLabel } from '../composables/useFormat'

const props = defineProps({
  status: { type: String, default: 'unknown' },
  label: { type: String, default: null },
})

const tones = {
  matched: { badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
  suspected: { badge: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' },
  unmatched: { badge: 'bg-slate-100 text-slate-600 ring-slate-200', dot: 'bg-slate-400' },
  unknown: { badge: 'bg-violet-50 text-violet-700 ring-violet-200', dot: 'bg-violet-500' },
  high: { badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
  medium: { badge: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' },
  low: { badge: 'bg-rose-50 text-rose-700 ring-rose-200', dot: 'bg-rose-500' },
  active: { badge: 'bg-brand-50 text-brand-700 ring-brand-200', dot: 'bg-brand-500' },
  completed: { badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
  available: { badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
  unavailable: { badge: 'bg-slate-100 text-slate-500 ring-slate-200', dot: 'bg-slate-400' },
  false: { badge: 'bg-slate-100 text-slate-500 ring-slate-200', dot: 'bg-slate-400' },
  true: { badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
}

const tone = computed(() => tones[props.status] || tones.unknown)
const labelText = computed(() => props.label || statusLabel(props.status))
</script>
