<template>
  <div>
    <div class="mb-5 flex flex-wrap items-center gap-3">
      <AppButton variant="secondary" :icon="ArrowLeftIcon" @click="router.push('/mice')">Back to mice</AppButton>
      <span class="text-slate-300">/</span>
      <span class="font-mono text-xs text-slate-400">{{ route.params.id }}</span>
    </div>

    <LoadingState v-if="loading" variant="detail" :rows="6" />
    <div v-else-if="error" class="card">
      <EmptyState :icon="TriangleAlertIcon" title="Mouse not found" :description="error">
        <template #action><AppButton :icon="ArrowLeftIcon" @click="router.push('/mice')">Back to database</AppButton></template>
      </EmptyState>
    </div>

    <template v-else-if="mouse">
      <PageHeader :eyebrow="mouse.mouse_id || 'Mouse profile'" :title="deviceName(mouse, 'Unnamed mouse')" :subtitle="mouse.physical_description || 'No physical description recorded.'">
        <template #actions>
          <AppButton variant="secondary" :icon="PencilIcon" @click="openEdit">Edit</AppButton>
          <AppButton variant="danger-soft" :icon="Trash2Icon" @click="remove">Delete</AppButton>
          <AppButton :icon="LinkIcon" @click="router.push('/matching')">Match this mouse</AppButton>
        </template>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <StatusBadge :status="matchStatus" />
          <span v-if="mouse.is_seed" class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200 ring-inset">Sample data</span>
        </div>
      </PageHeader>

      <div class="grid gap-6 lg:grid-cols-3">
        <section class="card overflow-hidden">
          <div class="flex aspect-square items-center justify-center overflow-hidden bg-slate-100 text-slate-300">
            <img v-if="mouse.photo_url" :src="mouse.photo_url" :alt="deviceName(mouse)" class="h-full w-full object-cover" />
            <MouseIcon v-else :size="72" />
          </div>
          <div class="border-t border-slate-100 px-5 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Created</p>
            <p class="mt-1 text-sm text-slate-700">{{ formatDateTime(mouse.created_at) }}</p>
            <p class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Last updated</p>
            <p class="mt-1 text-sm text-slate-700">{{ formatDateTime(mouse.updated_at) }}</p>
          </div>
        </section>

        <section class="card p-6 lg:col-span-2">
          <h2 class="mb-5 text-sm font-bold text-slate-900">Mouse details</h2>
          <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
            <div v-for="item in details" :key="item.label" class="min-w-0">
              <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</dt>
              <dd class="mt-1 break-words text-sm" :class="item.mono ? 'font-mono text-xs text-slate-600' : 'text-slate-800'">{{ item.value || '—' }}</dd>
            </div>
          </dl>
          <div v-if="mouse.notes" class="mt-6 rounded-xl bg-slate-50 p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Notes</p>
            <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ mouse.notes }}</p>
          </div>
        </section>
      </div>

      <section class="card mt-6 overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
          <h2 class="text-sm font-bold text-slate-900">Current match</h2>
          <p class="mt-0.5 text-xs text-slate-500">Relationships are only recorded after a physical test.</p>
        </div>
        <div v-if="mouse.match" class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 text-slate-400">
              <img v-if="mouse.match.dongle?.photo_url" :src="mouse.match.dongle.photo_url" alt="" class="h-full w-full object-cover" />
              <UsbIcon v-else :size="22" />
            </div>
            <div>
              <p class="text-sm font-bold text-slate-800">{{ deviceName(mouse.match.dongle, 'Unknown receiver') }}</p>
              <p class="font-mono text-[11px] text-slate-400">{{ mouse.match.dongle?.dongle_id }}</p>
              <p v-if="mouse.match.notes" class="mt-1 text-xs text-slate-500">{{ mouse.match.notes }}</p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <StatusBadge :status="mouse.match.status" />
            <AppButton size="sm" variant="secondary" @click="router.push(`/dongles/${mouse.match.dongle_id}`)">View dongle</AppButton>
            <AppButton size="sm" variant="secondary" @click="router.push('/matches')">Manage match</AppButton>
          </div>
        </div>
        <div v-else class="p-6">
          <EmptyState :icon="LinkIcon" title="No match recorded" description="This mouse has not been paired with a receiver yet. Open the Matching Center to test a candidate.">
            <template #action><AppButton :icon="LinkIcon" @click="router.push('/matching')">Open Matching Center</AppButton></template>
          </EmptyState>
        </div>
      </section>

      <ReferenceGallery :device="mouse" />
    </template>

    <Modal v-model="formOpen" title="Edit mouse" subtitle="Update the details of this mouse." size="lg" @close="resetForm">
      <form id="mouse-detail-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <Field label="Brand" :id="'detail-brand'" required><input id="detail-brand" v-model="form.brand" class="input" required /></Field>
          <Field label="Model" :id="'detail-model'"><input id="detail-model" v-model="form.model" class="input" /></Field>
          <Field label="Color" :id="'detail-color'"><input id="detail-color" v-model="form.color" class="input" /></Field>
          <Field label="Connectivity type" :id="'detail-connectivity'"><input id="detail-connectivity" v-model="form.connectivity_type" class="input" /></Field>
        </div>
        <Field label="Physical description" :id="'detail-description'"><textarea id="detail-description" v-model="form.physical_description" rows="3" class="input resize-y"></textarea></Field>
        <Field label="Notes" :id="'detail-notes'"><textarea id="detail-notes" v-model="form.notes" rows="2" class="input resize-y"></textarea></Field>
        <PhotoUpload v-model="form.photo" :current-url="form.photo_url" label="Mouse photo" @remove="form.remove_photo = true" />
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="formOpen = false">Cancel</AppButton>
        <AppButton type="submit" form="mouse-detail-form" :icon="SaveIcon" :loading="saving">Save changes</AppButton>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import {
  ArrowLeftIcon,
  PencilIcon,
  Trash2Icon,
  LinkIcon,
  MouseIcon,
  UsbIcon,
  SaveIcon,
  TriangleAlertIcon,
} from 'lucide-vue-next'
import PageHeader from '../components/PageHeader.vue'
import AppButton from '../components/AppButton.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import LoadingState from '../components/LoadingState.vue'
import Modal from '../components/Modal.vue'
import Field from '../components/Field.vue'
import PhotoUpload from '../components/PhotoUpload.vue'
import ReferenceGallery from '../components/ReferenceGallery.vue'
import { deviceName, formatDateTime } from '../composables/useFormat'
import { useConfirm } from '../composables/useConfirm'
import { useToast } from '../composables/useToast'

const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const mouse = ref(null)
const loading = ref(true)
const error = ref('')
const saving = ref(false)
const formOpen = ref(false)
const form = ref({})

const matchStatus = computed(() => mouse.value?.device_match_status || mouse.value?.match?.status || 'unmatched')

const details = computed(() => [
  { label: 'Mouse ID', value: mouse.value?.mouse_id, mono: true },
  { label: 'Brand', value: mouse.value?.brand },
  { label: 'Model', value: mouse.value?.model },
  { label: 'Color', value: mouse.value?.color },
  { label: 'Connectivity', value: mouse.value?.connectivity_type },
  { label: 'Physical description', value: mouse.value?.physical_description },
])

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.get(`/mice/${route.params.id}`)
    mouse.value = data
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'This mouse could not be loaded.'
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.value = {}
}

function openEdit() {
  form.value = {
    brand: mouse.value.brand || '',
    model: mouse.value.model || '',
    color: mouse.value.color || '',
    connectivity_type: mouse.value.connectivity_type || '',
    physical_description: mouse.value.physical_description || '',
    notes: mouse.value.notes || '',
    photo: null,
    photo_url: mouse.value.photo_url || null,
    remove_photo: false,
  }
  formOpen.value = true
}

async function save() {
  saving.value = true
  try {
    const payload = new FormData()
    for (const field of ['brand', 'model', 'color', 'connectivity_type', 'physical_description', 'notes']) {
      payload.append(field, form.value[field] ?? '')
    }
    if (form.value.photo) payload.append('photo', form.value.photo)
    if (form.value.remove_photo) payload.append('remove_photo', '1')
    payload.append('_method', 'PUT')
    const { data } = await axios.post(`/mice/${route.params.id}`, payload)
    mouse.value = data
    toast.success('Mouse updated', deviceName(data))
    formOpen.value = false
    resetForm()
  } catch {
    // handled by interceptor
  } finally {
    saving.value = false
  }
}

async function remove() {
  const accepted = await confirm({
    title: 'Delete this mouse?',
    message: `${deviceName(mouse.value)} (${mouse.value.mouse_id}) will be permanently removed.`,
    confirmText: 'Delete mouse',
    variant: 'danger',
  })
  if (!accepted) return
  try {
    await axios.delete(`/mice/${route.params.id}`)
    toast.success('Mouse deleted', deviceName(mouse.value))
    router.push('/mice')
  } catch {
    // handled by interceptor
  }
}

onMounted(load)
</script>
