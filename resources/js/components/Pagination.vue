<template>
  <div v-if="paginator && paginator.last_page > 1" class="flex flex-col items-center gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:justify-between">
    <p class="text-xs text-slate-500">
      Showing
      <span class="font-semibold text-slate-700">{{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }}</span>
      of <span class="font-semibold text-slate-700">{{ paginator.total ?? 0 }}</span>
    </p>
    <nav class="flex items-center gap-1" aria-label="Pagination">
      <button
        type="button"
        :disabled="paginator.current_page <= 1"
        aria-label="Previous page"
        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-brand-600 disabled:cursor-not-allowed disabled:opacity-40"
        @click="go(paginator.current_page - 1)"
      >
        <ChevronLeftIcon :size="16" />
      </button>

      <template v-for="(page, index) in pages" :key="`${page}-${index}`">
        <span v-if="page === '…'" class="px-1 text-xs text-slate-400">…</span>
        <button
          v-else
          type="button"
          :aria-label="`Go to page ${page}`"
          :aria-current="page === paginator.current_page ? 'page' : undefined"
          class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-semibold transition"
          :class="page === paginator.current_page
            ? 'bg-brand-600 text-white shadow-sm'
            : 'text-slate-600 hover:bg-slate-100'"
          @click="go(page)"
        >
          {{ page }}
        </button>
      </template>

      <button
        type="button"
        :disabled="paginator.current_page >= paginator.last_page"
        aria-label="Next page"
        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-brand-600 disabled:cursor-not-allowed disabled:opacity-40"
        @click="go(paginator.current_page + 1)"
      >
        <ChevronRightIcon :size="16" />
      </button>
    </nav>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-vue-next'

const props = defineProps({
  paginator: { type: Object, default: null },
})

const emit = defineEmits(['change'])

const pages = computed(() => {
  const current = props.paginator?.current_page || 1
  const last = props.paginator?.last_page || 1
  if (last <= 7) {
    return Array.from({ length: last }, (_, i) => i + 1)
  }
  const result = [1]
  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)
  if (start > 2) result.push('…')
  for (let page = start; page <= end; page++) result.push(page)
  if (end < last - 1) result.push('…')
  result.push(last)
  return result
})

function go(page) {
  if (page < 1 || page > (props.paginator?.last_page || 1)) return
  emit('change', page)
}
</script>
