<template>
  <div>
    <span v-if="label" class="label">{{ label }}</span>
    <div class="flex items-center gap-4">
      <div class="relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 text-slate-300">
        <img v-if="preview" :src="preview" alt="Photo preview" class="h-full w-full object-cover" />
        <ImageIcon v-else :size="26" />
      </div>
      <div class="min-w-0 flex-1 space-y-2">
        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" :aria-label="label || 'Choose photo'" @change="onChange" />
        <div class="flex flex-wrap gap-2">
          <AppButton type="button" size="sm" variant="secondary" :icon="UploadIcon" @click="fileInput?.click()">
            {{ modelValue ? 'Replace photo' : currentUrl ? 'Replace photo' : 'Choose photo' }}
          </AppButton>
          <AppButton v-if="modelValue || currentUrl" type="button" size="sm" variant="danger-soft" :icon="Trash2Icon" @click="removePhoto">
            Remove
          </AppButton>
        </div>
        <p class="text-[11px] leading-relaxed text-slate-400">{{ modelValue ? modelValue.name : 'JPG, PNG, GIF or WebP · up to 5 MB' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ImageIcon, UploadIcon, Trash2Icon } from 'lucide-vue-next'
import AppButton from './AppButton.vue'

const props = defineProps({
  modelValue: { type: [Object, File, null], default: null },
  currentUrl: { type: String, default: null },
  label: { type: String, default: 'Photo' },
})

const emit = defineEmits(['update:modelValue', 'remove'])

const fileInput = ref(null)
const objectUrl = ref(null)

const preview = computed(() => objectUrl.value || props.currentUrl || null)

watch(
  () => props.modelValue,
  (file) => {
    if (objectUrl.value) {
      URL.revokeObjectURL(objectUrl.value)
      objectUrl.value = null
    }
    if (file && typeof URL.createObjectURL === 'function') {
      objectUrl.value = URL.createObjectURL(file)
    }
  },
  { immediate: true }
)

function onChange(event) {
  const file = event.target.files?.[0] || null
  emit('update:modelValue', file)
}

function removePhoto() {
  emit('update:modelValue', null)
  emit('remove')
  if (fileInput.value) fileInput.value.value = ''
}

onBeforeUnmount(() => {
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
})
</script>
