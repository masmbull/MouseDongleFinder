<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="state.open" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm" @click.self="cancel">
        <Transition name="pop" appear>
          <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" role="alertdialog" aria-modal="true" :aria-labelledby="'confirm-title'" :aria-describedby="'confirm-message'">
            <div class="p-6">
              <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="variantStyle.icon">
                  <component :is="variantStyle.iconComponent" :size="21" />
                </div>
                <div class="min-w-0 flex-1">
                  <h2 id="confirm-title" class="text-base font-bold text-slate-900">{{ state.title }}</h2>
                  <p id="confirm-message" class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-slate-500">{{ state.message }}</p>
                </div>
              </div>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4">
              <AppButton variant="secondary" @click="cancel">{{ state.cancelText }}</AppButton>
              <AppButton :variant="state.variant === 'danger' ? 'danger' : 'primary'" @click="accept">{{ state.confirmText }}</AppButton>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { TriangleAlertIcon, HelpCircleIcon } from 'lucide-vue-next'
import { useConfirm } from '../composables/useConfirm'
import AppButton from './AppButton.vue'

const { state, accept, cancel } = useConfirm()

const variantStyle = computed(() =>
  state.variant === 'danger'
    ? { icon: 'bg-rose-50 text-rose-600', iconComponent: TriangleAlertIcon }
    : { icon: 'bg-brand-50 text-brand-600', iconComponent: HelpCircleIcon }
)
</script>
