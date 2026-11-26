<template>
  <div class="rounded-xl border border-slate-200 bg-white p-3">
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="flex min-w-0 items-start gap-2.5">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><UsbIcon :size="16" /></div>
        <div class="min-w-0">
          <p class="truncate text-xs font-bold text-slate-800">{{ device.name || 'Unknown device' }}</p>
          <p class="mt-0.5 font-mono text-[11px] text-slate-500">{{ vidPid }}</p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-1.5">
        <span v-if="device.class" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ device.class }}</span>
        <span v-if="device.status" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ device.status }}</span>
        <slot name="badge" />
      </div>
    </div>
    <p v-if="device.location" class="mt-2 truncate text-[11px] text-slate-400" :title="device.location">Location: {{ device.location }}</p>
    <p v-if="hardwareIdsText" class="mt-1 truncate font-mono text-[10px] text-slate-400" :title="hardwareIdsText">{{ hardwareIdsText }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { UsbIcon } from 'lucide-vue-next'

const props = defineProps({
  device: { type: Object, required: true },
})

const vidPid = computed(() => `${props.device.vid || '----'}:${props.device.pid || '----'}`)
const hardwareIdsText = computed(() => Array.isArray(props.device.hardware_ids) ? props.device.hardware_ids.join(' · ') : '')
</script>
