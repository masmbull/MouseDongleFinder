<template>
  <div>
    <PageHeader eyebrow="Inventory" title="Dongle Database" subtitle="Track USB receivers, including unknown devices identified only by hardware IDs.">
      <template #actions>
        <AppButton variant="secondary" :icon="RadarIcon" @click="router.push('/usb-detective')">USB Detective</AppButton>
        <AppButton :icon="PlusIcon" @click="openCreate">Add dongle</AppButton>
      </template>
    </PageHeader>

    <section class="card overflow-hidden">
      <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center">
        <div class="w-full lg:max-w-xs">
          <SearchInput v-model="search" placeholder="Search ID, brand, VID, PID..." />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="brand" class="input w-auto min-w-36" aria-label="Filter by brand">
            <option value="">All brands</option>
            <option v-for="option in brandOptions" :key="option" :value="option">{{ option }}</option>
          </select>
          <select v-model="status" class="input w-auto min-w-36" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="matched">Matched</option>
            <option value="suspected">Suspected</option>
            <option value="unmatched">Unmatched</option>
            <option value="unknown">Unknown</option>
          </select>
          <AppButton size="sm" variant="ghost" :icon="RefreshCwIcon" :loading="loading" @click="load">Refresh</AppButton>
        </div>
      </div>

      <div v-if="error" class="p-4">
        <EmptyState :icon="TriangleAlertIcon" title="Could not load dongles" :description="error">
          <template #action><AppButton :icon="RefreshCwIcon" @click="load">Try again</AppButton></template>
        </EmptyState>
      </div>
      <LoadingState v-else-if="loading && !rows.length" :rows="6" />
      <div v-else-if="!rows.length" class="p-4">
        <EmptyState :icon="UsbIcon" title="No dongles found" :description="hasFilters ? 'Try changing your search or filters.' : 'Register your first receiver, or investigate one in USB Detective.'">
          <template v-if="hasFilters" #action><AppButton variant="secondary" @click="resetFilters">Clear filters</AppButton></template>
          <template v-else #action><AppButton :icon="PlusIcon" @click="openCreate">Add dongle</AppButton></template>
        </EmptyState>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[1040px] border-collapse">
          <thead class="border-b border-slate-100 bg-slate-50/60">
            <tr>
              <th class="table-th w-12"><span class="sr-only">Photo</span></th>
              <th class="table-th">ID</th>
              <th class="table-th">Brand / Model</th>
              <th class="table-th">USB IDs</th>
              <th class="table-th">Hardware ID / Serial</th>
              <th class="table-th">Identification</th>
              <th class="table-th">Status</th>
              <th class="table-th text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="dongle in rows"
              :key="dongle.id"
              class="cursor-pointer transition hover:bg-brand-50/40"
              tabindex="0"
              @click="goTo(dongle)"
              @keydown.enter="goTo(dongle)"
            >
              <td class="table-td">
                <div class="relative flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
                  <img v-if="primaryPhotoUrl(dongle)" :src="primaryPhotoUrl(dongle)" :alt="deviceName(dongle)" class="h-full w-full object-cover" />
                  <UsbIcon v-else :size="17" />
                  <span v-if="photoCount(dongle) > 1" class="absolute bottom-0 right-0 rounded-tl bg-slate-900/80 px-1 text-[8px] font-bold text-white">{{ photoCount(dongle) }}</span>
                </div>
              </td>
              <td class="table-td">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-semibold text-brand-700">{{ dongle.dongle_id }}</span>
                  <span v-if="dongle.is_seed" class="rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-600 ring-1 ring-amber-200">Sample</span>
                </div>
              </td>
              <td class="table-td">
                <p class="font-semibold text-slate-800">{{ deviceName(dongle, 'Unknown receiver') }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">{{ dongle.receiver_type || 'Receiver type unknown' }}</p>
              </td>
              <td class="table-td font-mono text-xs">
                <span :class="dongle.vid ? 'text-slate-700' : 'text-slate-300'">{{ dongle.vid || '----' }}</span>
                <span class="text-slate-300">:</span>
                <span :class="dongle.pid ? 'text-slate-700' : 'text-slate-300'">{{ dongle.pid || '----' }}</span>
              </td>
              <td class="table-td">
                <p class="max-w-48 truncate font-mono text-[11px] text-slate-500">{{ dongle.hardware_id || '—' }}</p>
                <p class="max-w-48 truncate font-mono text-[11px] text-slate-400">{{ dongle.serial_number || '' }}</p>
              </td>
              <td class="table-td">
                <StatusBadge v-if="dongle.identification" :status="dongle.identification.confidence || 'unknown'" />
                <span v-else class="text-xs text-slate-400">Not identified</span>
              </td>
              <td class="table-td"><StatusBadge :status="dongle.device_match_status || dongle.match?.status || 'unmatched'" /></td>
              <td class="table-td">
                <div class="flex items-center justify-end gap-1">
                  <AppButton size="icon" variant="ghost" :icon="RadarIcon" :aria-label="`Identify ${dongle.dongle_id}`" @click.stop="identify(dongle)" />
                  <AppButton size="icon" variant="ghost" :icon="EyeIcon" aria-label="View dongle" @click.stop="goTo(dongle)" />
                  <AppButton size="icon" variant="ghost" :icon="PencilIcon" aria-label="Edit dongle" @click.stop="openEdit(dongle)" />
                  <AppButton size="icon" variant="ghost" :icon="Trash2Icon" aria-label="Delete dongle" @click.stop="remove(dongle)" />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :paginator="paginator" @change="page = $event" />
    </section>

    <Modal v-model="formOpen" :title="editing ? 'Edit dongle' : 'Add a dongle'" :subtitle="editing ? 'Update the details of this receiver.' : 'Brand and model are optional — unknown receivers are allowed.'" size="lg" @close="resetForm">
      <form id="dongle-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <Field label="Brand" :id="'dongle-brand'" hint="Optional for unknown receivers">
            <input id="dongle-brand" v-model="form.brand" class="input" placeholder="e.g. Logitech" />
          </Field>
          <Field label="Model" :id="'dongle-model'">
            <input id="dongle-model" v-model="form.model" class="input" placeholder="e.g. Unifying C52B" />
          </Field>
          <Field label="Color" :id="'dongle-color'">
            <input id="dongle-color" v-model="form.color" class="input" placeholder="e.g. Black" />
          </Field>
          <Field label="Receiver type" :id="'dongle-receiver-type'">
            <input id="dongle-receiver-type" v-model="form.receiver_type" class="input" placeholder="e.g. USB nano receiver" />
          </Field>
          <Field label="VID" :id="'dongle-vid'" hint="4 hex characters, e.g. 046D">
            <input id="dongle-vid" v-model="form.vid" class="input font-mono uppercase" maxlength="4" placeholder="046D" />
          </Field>
          <Field label="PID" :id="'dongle-pid'" hint="4 hex characters, e.g. C52B">
            <input id="dongle-pid" v-model="form.pid" class="input font-mono uppercase" maxlength="4" placeholder="C52B" />
          </Field>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <Field label="Hardware ID" :id="'dongle-hardware-id'">
            <input id="dongle-hardware-id" v-model="form.hardware_id" class="input font-mono text-xs" placeholder="USB\\VID_046D&PID_C52B" />
          </Field>
          <Field label="Serial number" :id="'dongle-serial'">
            <input id="dongle-serial" v-model="form.serial_number" class="input font-mono text-xs" placeholder="If reported by hardware" />
          </Field>
        </div>
        <Field label="Physical description" :id="'dongle-description'">
          <textarea id="dongle-description" v-model="form.physical_description" rows="2" class="input resize-y" placeholder="Markings, size, logo..."></textarea>
        </Field>
        <Field label="Notes" :id="'dongle-notes'">
          <textarea id="dongle-notes" v-model="form.notes" rows="2" class="input resize-y" placeholder="Optional notes"></textarea>
        </Field>
        <PhotoUpload v-model="form.photo" :current-url="form.photo_url" label="Dongle photo" @remove="form.remove_photo = true" />
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="formOpen = false">Cancel</AppButton>
        <AppButton type="submit" form="dongle-form" :icon="SaveIcon" :loading="saving">{{ editing ? 'Save changes' : 'Add dongle' }}</AppButton>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import {
  PlusIcon,
  RefreshCwIcon,
  UsbIcon,
  RadarIcon,
  EyeIcon,
  PencilIcon,
  Trash2Icon,
  SaveIcon,
  TriangleAlertIcon,
} from 'lucide-vue-next'
import PageHeader from '../components/PageHeader.vue'
import AppButton from '../components/AppButton.vue'
import SearchInput from '../components/SearchInput.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import LoadingState from '../components/LoadingState.vue'
import Pagination from '../components/Pagination.vue'
import Modal from '../components/Modal.vue'
import Field from '../components/Field.vue'
import PhotoUpload from '../components/PhotoUpload.vue'
import { deviceName } from '../composables/useFormat'
import { photoCount, primaryPhotoUrl } from '../composables/useDevicePhotos'
import { useConfirm } from '../composables/useConfirm'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const rows = ref([])
const paginator = ref(null)
const loading = ref(true)
const error = ref('')
const saving = ref(false)

const search = ref('')
const brand = ref('')
const status = ref('')
const page = ref(1)

const formOpen = ref(false)
const editing = ref(null)
const emptyForm = () => ({
  brand: '',
  model: '',
  color: '',
  receiver_type: '',
  vid: '',
  pid: '',
  hardware_id: '',
  serial_number: '',
  physical_description: '',
  notes: '',
  photo: null,
  photo_url: null,
  remove_photo: false,
})
const form = ref(emptyForm())

const brandOptions = computed(() => {
  const brands = rows.value.map((dongle) => dongle.brand).filter(Boolean)
  if (brand.value && !brands.includes(brand.value)) brands.push(brand.value)
  return [...new Set(brands)].sort((a, b) => a.localeCompare(b))
})

const hasFilters = computed(() => Boolean(search.value || brand.value || status.value))

let debounceTimer
watch([search, brand, status], () => {
  window.clearTimeout(debounceTimer)
  debounceTimer = window.setTimeout(() => {
    page.value = 1
    load()
  }, 300)
})
watch(page, load)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.get('/dongles', {
      params: {
        search: search.value || undefined,
        brand: brand.value || undefined,
        status: status.value || undefined,
        per_page: 15,
        page: page.value,
      },
    })
    rows.value = Array.isArray(data.data) ? data.data : []
    paginator.value = data
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Unable to load the dongle database.'
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  search.value = ''
  brand.value = ''
  status.value = ''
  page.value = 1
  load()
}

function goTo(dongle) {
  router.push(`/dongles/${dongle.id}`)
}

function identify(dongle) {
  router.push({ path: '/usb-detective', query: dongle.vid ? { dongle: dongle.id } : {} })
}

function resetForm() {
  form.value = emptyForm()
  editing.value = null
}

function openCreate() {
  resetForm()
  formOpen.value = true
}

function openEdit(dongle) {
  editing.value = dongle
  form.value = {
    brand: dongle.brand || '',
    model: dongle.model || '',
    color: dongle.color || '',
    receiver_type: dongle.receiver_type || '',
    vid: dongle.vid || '',
    pid: dongle.pid || '',
    hardware_id: dongle.hardware_id || '',
    serial_number: dongle.serial_number || '',
    physical_description: dongle.physical_description || '',
    notes: dongle.notes || '',
    photo: null,
    photo_url: dongle.photo_url || null,
    remove_photo: false,
  }
  formOpen.value = true
}

async function save() {
  saving.value = true
  try {
    const payload = new FormData()
    const fields = ['brand', 'model', 'color', 'receiver_type', 'vid', 'pid', 'hardware_id', 'serial_number', 'physical_description', 'notes']
    for (const field of fields) {
      payload.append(field, form.value[field] ?? '')
    }
    if (form.value.photo) payload.append('photo', form.value.photo)
    if (form.value.remove_photo) payload.append('remove_photo', '1')
    if (editing.value) payload.append('_method', 'PUT')

    const url = editing.value ? `/dongles/${editing.value.id}` : '/dongles'
    await axios.post(url, payload)
    toast.success(editing.value ? 'Dongle updated' : 'Dongle added', deviceName(form.value, 'Unknown receiver'))
    formOpen.value = false
    resetForm()
    await load()
  } catch {
    // handled by interceptor
  } finally {
    saving.value = false
  }
}

async function remove(dongle) {
  const accepted = await confirm({
    title: 'Delete this dongle?',
    message: `${deviceName(dongle, 'This receiver')} (${dongle.dongle_id}) will be permanently removed. Any related match will also be removed.`,
    confirmText: 'Delete dongle',
    variant: 'danger',
  })
  if (!accepted) return
  try {
    await axios.delete(`/dongles/${dongle.id}`)
    toast.success('Dongle deleted', deviceName(dongle, 'Dongle'))
    await load()
  } catch {
    // handled by interceptor
  }
}

onMounted(load)
</script>
