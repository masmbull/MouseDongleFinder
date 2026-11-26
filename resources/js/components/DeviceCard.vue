<template>
  <button
    type="button"
    class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-all duration-150"
    :class="selected
      ? 'border-brand-300 bg-brand-50/70 shadow-sm ring-2 ring-brand-500/10'
      : 'border-slate-200 bg-white hover:border-brand-200 hover:bg-slate-50'"
    @click="$emit('click', device)"
  >
    <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
      <img v-if="primaryPhotoUrl(device)" :src="primaryPhotoUrl(device)" :alt="name" class="h-full w-full object-cover" />
      <component :is="type === 'mouse' ? MouseIcon : UsbIcon" v-else :size="20" />
    </div>
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-2">
        <p class="truncate text-sm font-semibold text-slate-800">{{ name }}</p>
        <span v-if="device?.is_seed" class="shrink-0 rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-600 ring-1 ring-amber-200">Sample</span>
      </div>
      <p class="mt-0.5 truncate font-mono text-[11px] text-slate-400">{{ identifier }}</p>
      <p v-if="device?.physical_description" class="mt-0.5 truncate text-[11px] text-slate-500">{{ device.physical_description }}</p>
      <p v-else-if="type === 'dongle' && (device?.vid || device?.pid)" class="mt-0.5 truncate text-[11px] text-slate-500">
        USB {{ device.vid || '----' }}:{{ device.pid || '----' }}
      </p>
      <StatusBadge v-if="showStatus" :status="status" class="mt-1.5" />
    </div>
    <ChevronRightIcon v-if="selected" :size="16" class="shrink-0 text-brand-500" />
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { MouseIcon, UsbIcon, ChevronRightIcon } from 'lucide-vue-next'
import { deviceName } from '../composables/useFormat'
import { primaryPhotoUrl } from '../composables/useDevicePhotos'
import StatusBadge from './StatusBadge.vue'

const props = defineProps({
  device: { type: Object, default: null },
  type: { type: String, default: 'mouse' },
  selected: { type: Boolean, default: false },
  showStatus: { type: Boolean, default: true },
})

defineEmits(['click'])

const name = computed(() => deviceName(props.device))
const identifier = computed(() => props.device?.mouse_id || props.device?.dongle_id || 'No ID')
const status = computed(() => props.device?.device_match_status || props.device?.match?.status || 'unmatched')
</script>
