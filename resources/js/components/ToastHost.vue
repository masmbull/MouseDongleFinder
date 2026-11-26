<template>
  <Teleport to="body">
    <div class="pointer-events-none fixed inset-x-4 bottom-4 z-[100] flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:top-6 sm:bottom-auto sm:items-end" aria-live="polite">
      <TransitionGroup name="list" tag="div" class="relative flex w-full flex-col gap-2 sm:w-96">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto flex w-full items-start gap-3 rounded-xl border bg-white/95 p-3.5 shadow-lg shadow-slate-900/5 backdrop-blur"
          :class="tone[toast.type]?.border || 'border-slate-200'"
          role="status"
        >
          <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg" :class="tone[toast.type]?.icon || 'bg-slate-100 text-slate-500'">
            <component :is="tone[toast.type]?.iconComponent || InfoIcon" :size="16" />
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-slate-800">{{ toast.title }}</p>
            <p v-if="toast.message" class="mt-0.5 break-words text-xs leading-relaxed text-slate-500">{{ toast.message }}</p>
          </div>
          <button type="button" aria-label="Dismiss notification" class="rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" @click="dismiss(toast.id)">
            <XIcon :size="15" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { CheckIcon, InfoIcon, XIcon, TriangleAlertIcon, XCircleIcon } from 'lucide-vue-next'
import { useToast } from '../composables/useToast'

const { toasts, dismiss } = useToast()

const tone = {
  success: { border: 'border-emerald-200', icon: 'bg-emerald-50 text-emerald-600', iconComponent: CheckIcon },
  error: { border: 'border-rose-200', icon: 'bg-rose-50 text-rose-600', iconComponent: XCircleIcon },
  warning: { border: 'border-amber-200', icon: 'bg-amber-50 text-amber-600', iconComponent: TriangleAlertIcon },
  info: { border: 'border-brand-200', icon: 'bg-brand-50 text-brand-600', iconComponent: InfoIcon },
}
</script>
