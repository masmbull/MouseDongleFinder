<template>
  <div>
    <div class="mb-5 flex flex-wrap items-center gap-3">
      <AppButton variant="secondary" :icon="ArrowLeftIcon" @click="router.push('/dongles')">Back to dongles</AppButton>
      <span class="text-slate-300">/</span>
      <span class="font-mono text-xs text-slate-400">{{ route.params.id }}</span>
    </div>

    <LoadingState v-if="loading" variant="detail" :rows="6" />
    <div v-else-if="error" class="card">
      <EmptyState :icon="TriangleAlertIcon" title="Dongle not found" :description="error">
        <template #action><AppButton :icon="ArrowLeftIcon" @click="router.push('/dongles')">Back to database</AppButton></template>
      </EmptyState>
    </div>

    <template v-else-if="dongle">
      <PageHeader :eyebrow="dongle.dongle_id || 'Dongle profile'" :title="deviceName(dongle, 'Unknown receiver')" :subtitle="dongle.physical_description || 'No physical description recorded.'">
        <template #actions>
          <AppButton variant="secondary" :icon="RadarIcon" @click="router.push({ path: '/usb-detective', query: { dongle: dongle.id } })">Software &amp; Downloads</AppButton>
          <AppButton variant="secondary" :icon="PencilIcon" @click="openEdit">Edit</AppButton>
          <AppButton variant="danger-soft" :icon="Trash2Icon" @click="remove">Delete</AppButton>
        </template>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <StatusBadge :status="matchStatus" />
          <span v-if="dongle.is_seed" class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200 ring-inset">Sample data</span>
          <span v-if="dongle.identification" class="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 ring-1 ring-brand-200 ring-inset">Identified</span>
        </div>
      </PageHeader>

      <div class="grid gap-6 lg:grid-cols-3">
        <section class="card overflow-hidden">
          <div class="flex aspect-square items-center justify-center overflow-hidden bg-slate-100 text-slate-300">
            <img v-if="dongle.photo_url" :src="dongle.photo_url" :alt="deviceName(dongle, 'Dongle')" class="h-full w-full object-cover" />
            <UsbIcon v-else :size="72" />
          </div>
          <div class="border-t border-slate-100 px-5 py-4">
            <div class="flex items-center justify-between">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">USB identifier</p>
              <AppButton size="xs" variant="ghost" :icon="CopyIcon" @click="copyUsbIds">Copy</AppButton>
            </div>
            <p class="mt-1 font-mono text-lg font-bold tracking-tight text-slate-800">{{ usbIds }}</p>
            <p class="mt-4 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Registered</p>
            <p class="mt-1 text-sm text-slate-700">{{ formatDateTime(dongle.created_at) }}</p>
          </div>
        </section>

        <section class="card p-6 lg:col-span-2">
          <h2 class="mb-5 text-sm font-bold text-slate-900">Device details</h2>
          <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
            <div v-for="item in details" :key="item.label" class="min-w-0">
              <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</dt>
              <dd class="mt-1 break-all text-sm text-slate-800" :class="item.mono ? 'font-mono text-xs text-slate-600' : ''">{{ item.value || '—' }}</dd>
            </div>
          </dl>
          <div v-if="dongle.notes" class="mt-6 rounded-xl bg-slate-50 p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Notes</p>
            <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ dongle.notes }}</p>
          </div>
        </section>
      </div>

      <!-- Identification panel -->
      <section class="card mt-6 overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-sm font-bold text-slate-900">Identification</h2>
            <p class="mt-0.5 text-xs text-slate-500">Evidence-based findings — never inferred from brand or frequency alone.</p>
          </div>
          <AppButton size="sm" variant="secondary" :icon="RadarIcon" @click="router.push({ path: '/usb-detective', query: { dongle: dongle.id } })">Open in USB Detective</AppButton>
        </div>

        <div v-if="!dongle.identification" class="p-6">
          <EmptyState :icon="FileSearchIcon" title="Not identified yet" description="Analyze the hardware IDs in USB Detective to gather evidence about this receiver.">
            <template #action><AppButton :icon="RadarIcon" @click="router.push({ path: '/usb-detective', query: { dongle: dongle.id } })">Identify device</AppButton></template>
          </EmptyState>
        </div>
        <div v-else class="p-6">
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Probable manufacturer</p>
              <p class="mt-1 text-sm font-bold text-slate-800">{{ identification.probable_manufacturer || 'Unknown' }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Probable model / family</p>
              <p class="mt-1 text-sm font-bold text-slate-800">{{ identification.probable_model || identification.receiver_family || 'Unknown' }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Technology / frequency</p>
              <p class="mt-1 text-sm font-bold text-slate-800">{{ identification.wireless_technology || 'Unknown' }}</p>
              <p v-if="identification.frequency" class="text-xs text-slate-500">{{ identification.frequency }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Confidence</p>
              <div class="mt-1"><StatusBadge :status="identification.confidence || 'unknown'" /></div>
            </div>
          </div>

          <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div>
              <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Evidence</h3>
              <ul v-if="evidence.length" class="space-y-2">
                <li v-for="(item, index) in evidence" :key="index" class="rounded-xl border border-slate-100 bg-white p-3">
                  <p class="text-xs font-semibold text-slate-700">{{ typeof item === 'string' ? item : item.detail || item.type || 'Evidence' }}</p>
                  <p v-if="typeof item === 'object' && item.source" class="mt-1 text-[11px] text-slate-400">Source: {{ item.source }}</p>
                </li>
              </ul>
              <p v-else class="text-xs text-slate-400">No evidence recorded.</p>
            </div>
            <div>
              <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Remaining unknowns</h3>
              <ul v-if="unknowns.length" class="space-y-2">
                <li v-for="(item, index) in unknowns" :key="index" class="flex items-start gap-2 text-xs text-slate-600">
                  <HelpCircleIcon :size="14" class="mt-0.5 shrink-0 text-amber-500" />
                  <span>{{ typeof item === 'string' ? item : item.detail || JSON.stringify(item) }}</span>
                </li>
              </ul>
              <p v-else class="text-xs text-slate-400">No unknowns were recorded for this identification.</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Downloads -->
      <section class="card mt-6 overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <div>
            <h2 class="text-sm font-bold text-slate-900">Software &amp; downloads</h2>
            <p class="mt-0.5 text-xs text-slate-500">Files are never executed automatically.</p>
          </div>
          <AppButton size="sm" variant="secondary" :icon="DownloadIcon" @click="router.push({ path: '/usb-detective', query: { dongle: dongle.id } })">Find software</AppButton>
        </div>
        <div v-if="!downloads.length" class="p-6">
          <EmptyState :icon="DownloadIcon" title="No downloads yet" description="Find official software in USB Detective and download it here to keep a record." />
        </div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="download in downloads" :key="download.id" class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <p class="truncate text-sm font-semibold text-slate-800">{{ download.filename }}</p>
                <StatusBadge :status="download.source_type === 'official' ? 'matched' : download.source_type || 'unknown'" :label="download.source_type || 'unknown'" />
              </div>
              <p class="mt-1 truncate font-mono text-[11px] text-slate-400">{{ download.source_url }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-1">
              <AppButton size="xs" variant="ghost" :icon="ShieldCheckIcon" :loading="busyDownload === download.id" @click="verifyDownload(download)">Verify</AppButton>
              <AppButton size="xs" variant="ghost" :icon="FolderOpenIcon" :loading="busyDownload === download.id" @click="openDownload(download)">Open folder</AppButton>
              <AppButton size="xs" variant="ghost" :icon="Trash2Icon" @click="removeDownload(download)">Delete</AppButton>
            </div>
          </div>
        </div>
      </section>

      <section class="card mt-6 overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
          <h2 class="text-sm font-bold text-slate-900">Matched mice ({{ (dongle.matches || []).length }})</h2>
          <p class="mt-0.5 text-xs text-slate-500">Every relationship recorded for this receiver, newest first. Only one can be active.</p>
        </div>
        <div v-if="!(dongle.matches || []).length" class="p-6">
          <EmptyState :icon="LinkIcon" title="No matched mouse yet" description="Test this receiver with a mouse and record the result in Matching Center." />
        </div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="match in dongle.matches" :key="match.id" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
                <img v-if="match.mouse?.photo_url" :src="match.mouse.photo_url" alt="" class="h-full w-full object-cover" />
                <MouseIcon v-else :size="18" />
              </div>
              <div>
                <p class="text-sm font-bold text-slate-800">{{ deviceName(match.mouse, 'Unknown mouse') }}</p>
                <p class="font-mono text-[11px] text-slate-400">{{ match.mouse?.mouse_id }} · {{ formatDateTime(match.matched_at || match.created_at) }}</p>
                <p v-if="match.notes" class="mt-1 text-xs text-slate-500">{{ match.notes }}</p>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <StatusBadge :status="match.status" />
              <AppButton size="xs" variant="secondary" @click="router.push(`/matches?highlight=${match.id}`)">View match</AppButton>
            </div>
          </div>
        </div>
      </section>

      <ReferenceGallery :device="dongle" />
    </template>

    <Modal v-model="formOpen" title="Edit dongle" subtitle="Update the details of this receiver." size="lg" @close="resetForm">
      <form id="dongle-detail-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <Field label="Brand" :id="'detail-dongle-brand'"><input id="detail-dongle-brand" v-model="form.brand" class="input" /></Field>
          <Field label="Model" :id="'detail-dongle-model'"><input id="detail-dongle-model" v-model="form.model" class="input" /></Field>
          <Field label="Color" :id="'detail-dongle-color'"><input id="detail-dongle-color" v-model="form.color" class="input" /></Field>
          <Field label="Receiver type" :id="'detail-dongle-type'"><input id="detail-dongle-type" v-model="form.receiver_type" class="input" /></Field>
          <Field label="VID" :id="'detail-dongle-vid'"><input id="detail-dongle-vid" v-model="form.vid" class="input font-mono uppercase" maxlength="4" /></Field>
          <Field label="PID" :id="'detail-dongle-pid'"><input id="detail-dongle-pid" v-model="form.pid" class="input font-mono uppercase" maxlength="4" /></Field>
          <Field label="Hardware ID" :id="'detail-dongle-hardware'"><input id="detail-dongle-hardware" v-model="form.hardware_id" class="input font-mono text-xs" /></Field>
          <Field label="Serial number" :id="'detail-dongle-serial'"><input id="detail-dongle-serial" v-model="form.serial_number" class="input font-mono text-xs" /></Field>
        </div>
        <Field label="Physical description" :id="'detail-dongle-description'"><textarea id="detail-dongle-description" v-model="form.physical_description" rows="2" class="input resize-y"></textarea></Field>
        <Field label="Notes" :id="'detail-dongle-notes'"><textarea id="detail-dongle-notes" v-model="form.notes" rows="2" class="input resize-y"></textarea></Field>
        <PhotoUpload v-model="form.photo" :current-url="form.photo_url" label="Dongle photo" @remove="form.remove_photo = true" />
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="formOpen = false">Cancel</AppButton>
        <AppButton type="submit" form="dongle-detail-form" :icon="SaveIcon" :loading="saving">Save changes</AppButton>
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
  RadarIcon,
  UsbIcon,
  SaveIcon,
  CopyIcon,
  FileSearchIcon,
  HelpCircleIcon,
  DownloadIcon,
  ShieldCheckIcon,
  FolderOpenIcon,
  TriangleAlertIcon,
  LinkIcon,
  MouseIcon,
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
import { deviceName, formatDateTime, safeJson } from '../composables/useFormat'
import { useConfirm } from '../composables/useConfirm'
import { useToast } from '../composables/useToast'

const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const dongle = ref(null)
const downloads = ref([])
const loading = ref(true)
const error = ref('')
const saving = ref(false)
const busyDownload = ref(null)
const formOpen = ref(false)
const form = ref({})

const matchStatus = computed(() => dongle.value?.device_match_status || dongle.value?.match?.status || 'unmatched')
const identification = computed(() => dongle.value?.identification || {})
const evidence = computed(() => safeJson(identification.value.evidence))
const unknowns = computed(() => safeJson(identification.value.unknowns))
const usbIds = computed(() => `${dongle.value?.vid || '----'}:${dongle.value?.pid || '----'}`)

const details = computed(() => [
  { label: 'Dongle ID', value: dongle.value?.dongle_id, mono: true },
  { label: 'Brand', value: dongle.value?.brand },
  { label: 'Model', value: dongle.value?.model },
  { label: 'Color', value: dongle.value?.color },
  { label: 'Receiver type', value: dongle.value?.receiver_type },
  { label: 'VID', value: dongle.value?.vid, mono: true },
  { label: 'PID', value: dongle.value?.pid, mono: true },
  { label: 'Hardware ID', value: dongle.value?.hardware_id, mono: true },
  { label: 'Serial number', value: dongle.value?.serial_number, mono: true },
  { label: 'Physical description', value: dongle.value?.physical_description },
])

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.get(`/dongles/${route.params.id}`)
    dongle.value = data
    try {
      const response = await axios.get('/downloads', { params: { dongle_id: route.params.id, per_page: 20 } })
      downloads.value = Array.isArray(response.data.data) ? response.data.data : []
    } catch {
      downloads.value = []
    }
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'This dongle could not be loaded.'
  } finally {
    loading.value = false
  }
}

async function copyUsbIds() {
  try {
    await navigator.clipboard.writeText(usbIds.value)
    toast.success('Copied', usbIds.value)
  } catch {
    toast.warning('Clipboard unavailable', `Select and copy manually: ${usbIds.value}`)
  }
}

function resetForm() {
  form.value = {}
}

function openEdit() {
  form.value = {
    brand: dongle.value.brand || '',
    model: dongle.value.model || '',
    color: dongle.value.color || '',
    receiver_type: dongle.value.receiver_type || '',
    vid: dongle.value.vid || '',
    pid: dongle.value.pid || '',
    hardware_id: dongle.value.hardware_id || '',
    serial_number: dongle.value.serial_number || '',
    physical_description: dongle.value.physical_description || '',
    notes: dongle.value.notes || '',
    photo: null,
    photo_url: dongle.value.photo_url || null,
    remove_photo: false,
  }
  formOpen.value = true
}

async function save() {
  saving.value = true
  try {
    const payload = new FormData()
    for (const field of ['brand', 'model', 'color', 'receiver_type', 'vid', 'pid', 'hardware_id', 'serial_number', 'physical_description', 'notes']) {
      payload.append(field, form.value[field] ?? '')
    }
    if (form.value.photo) payload.append('photo', form.value.photo)
    if (form.value.remove_photo) payload.append('remove_photo', '1')
    payload.append('_method', 'PUT')
    const { data } = await axios.post(`/dongles/${route.params.id}`, payload)
    dongle.value = { ...dongle.value, ...data }
    toast.success('Dongle updated', deviceName(data, 'Unknown receiver'))
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
    title: 'Delete this dongle?',
    message: `${deviceName(dongle.value, 'This receiver')} (${dongle.value.dongle_id}) will be permanently removed.`,
    confirmText: 'Delete dongle',
    variant: 'danger',
  })
  if (!accepted) return
  try {
    await axios.delete(`/dongles/${route.params.id}`)
    toast.success('Dongle deleted', deviceName(dongle.value, 'Dongle'))
    router.push('/dongles')
  } catch {
    // handled by interceptor
  }
}

async function verifyDownload(download) {
  busyDownload.value = download.id
  try {
    const { data } = await axios.get(`/downloads/${download.id}/verify`)
    if (data.verified) {
      toast.success('Hash verified', 'The stored SHA-256 hash matches the file.')
    } else {
      toast.warning('Verification failed', data.error || 'Stored and computed hashes do not match.')
    }
  } catch {
    // handled by interceptor
  } finally {
    busyDownload.value = null
  }
}

async function openDownload(download) {
  busyDownload.value = download.id
  try {
    const { data } = await axios.post(`/downloads/${download.id}/open`)
    if (data.opened) {
      toast.success('Folder opened', data.path || 'The file location was opened in Explorer.')
    } else {
      toast.warning('Could not open folder', data.error || 'No file path is available.')
    }
  } catch {
    // handled by interceptor
  } finally {
    busyDownload.value = null
  }
}

async function removeDownload(download) {
  const accepted = await confirm({
    title: 'Delete download record?',
    message: `The record for ${download.filename} will be removed from the database.`,
    confirmText: 'Delete record',
    variant: 'danger',
  })
  if (!accepted) return
  try {
    await axios.delete(`/downloads/${download.id}`)
    downloads.value = downloads.value.filter((item) => item.id !== download.id)
    toast.success('Download record deleted')
  } catch {
    // handled by interceptor
  }
}

onMounted(load)
</script>
