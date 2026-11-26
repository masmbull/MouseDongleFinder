<template>
  <section v-if="photos.length" class="card mt-6 overflow-hidden">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-sm font-bold text-slate-900">Reference photos ({{ photos.length }})</h2>
        <p class="mt-0.5 text-xs leading-relaxed text-slate-500">
          Real product images collected from public sources. Compare them with the device you are holding — markings and underside labels are usually the most useful.
        </p>
      </div>
      <span class="shrink-0 rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500">Public reference · not user upload</span>
    </div>

    <div class="grid gap-5 p-6 md:grid-cols-[minmax(0,2fr)_minmax(180px,1fr)]">
      <div>
        <button type="button" class="group block w-full overflow-hidden rounded-2xl border border-slate-200 bg-slate-50" :aria-label="`Enlarge ${photoTypeLabel(selected)}`" @click="lightbox = true">
          <img :src="selected.url" :alt="selected.caption || photoTypeLabel(selected)" class="aspect-[4/3] w-full bg-white object-contain p-2 transition group-hover:scale-[1.01]" />
        </button>
        <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-800">{{ photoTypeLabel(selected) }}</p>
            <p v-if="selected.caption" class="mt-0.5 text-xs leading-relaxed text-slate-500">{{ selected.caption }}</p>
          </div>
          <a v-if="selected.source_url" :href="selected.source_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[11px] font-semibold text-brand-700 hover:underline">
            <ExternalLinkIcon :size="13" /> {{ selected.source_name || 'View source' }}
          </a>
        </div>
      </div>

      <div>
        <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">All angles</p>
        <div class="grid grid-cols-3 gap-2 md:grid-cols-2">
          <button
            v-for="(photo, index) in photos"
            :key="photo.id || index"
            type="button"
            class="overflow-hidden rounded-xl border-2 transition"
            :class="photo.url === selected.url ? 'border-brand-500 ring-2 ring-brand-100' : 'border-slate-200 hover:border-brand-300'"
            :aria-label="`Show ${photoTypeLabel(photo)}`"
            @click="selected = photo"
          >
            <img :src="photo.url" :alt="photo.caption || photoTypeLabel(photo)" class="aspect-square w-full bg-white object-contain p-1" />
          </button>
        </div>
        <p class="mt-3 text-[11px] leading-relaxed text-slate-400">Click an image to enlarge it. Source links open the original page.</p>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="lightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/85 p-4" role="dialog" aria-modal="true" aria-label="Reference photo viewer" @click.self="lightbox = false">
        <div class="max-h-full w-full max-w-5xl">
          <div class="mb-3 flex items-center justify-between gap-3 text-white">
            <div class="min-w-0">
              <p class="text-sm font-bold">{{ photoTypeLabel(selected) }}</p>
              <p v-if="selected.caption" class="truncate text-xs text-slate-300">{{ selected.caption }}</p>
            </div>
            <button type="button" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold hover:bg-white/20" @click="lightbox = false">Close</button>
          </div>
          <img :src="selected.url" :alt="selected.caption || photoTypeLabel(selected)" class="mx-auto max-h-[80vh] w-auto max-w-full rounded-xl bg-white object-contain" />
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { ExternalLinkIcon } from 'lucide-vue-next'
import { photoTypeLabel, referencePhotos } from '../composables/useDevicePhotos'

const props = defineProps({
  device: { type: Object, default: null },
})

const photos = computed(() => referencePhotos(props.device))
const selected = ref(null)
const lightbox = ref(false)

watch(photos, (items) => {
  selected.value = items[0] || null
}, { immediate: true })

watch(lightbox, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
})
</script>
