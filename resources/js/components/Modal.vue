<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-[90] flex items-end justify-center bg-slate-900/40 p-0 backdrop-blur-sm sm:items-center sm:p-4"
        @click.self="closeOnBackdrop && close()"
      >
        <Transition name="pop" appear>
          <div
            class="flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-2xl sm:rounded-2xl"
            :class="sizeClasses"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
          >
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
              <div class="min-w-0">
                <h2 class="text-base font-bold text-slate-900">{{ title }}</h2>
                <p v-if="subtitle" class="mt-0.5 text-xs text-slate-500">{{ subtitle }}</p>
              </div>
              <button type="button" aria-label="Close dialog" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" @click="close">
                <XIcon :size="18" />
              </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
              <slot />
            </div>

            <div v-if="$slots.footer" class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4">
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, watch } from 'vue'
import { XIcon } from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  size: { type: String, default: 'md' },
  closeOnBackdrop: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'close'])

const sizes = {
  sm: 'sm:max-w-md',
  md: 'sm:max-w-xl',
  lg: 'sm:max-w-3xl',
  xl: 'sm:max-w-5xl',
}
const sizeClasses = computed(() => sizes[props.size] || sizes.md)

function close() {
  emit('update:modelValue', false)
  emit('close')
}

watch(
  () => props.modelValue,
  (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
  },
  { immediate: true }
)
</script>
