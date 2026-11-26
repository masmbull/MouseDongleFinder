<template>
  <div>
    <PageHeader eyebrow="Inventory" title="Mouse Database" subtitle="Register and maintain the wireless mice you are tracking.">
      <template #actions>
        <AppButton :icon="PlusIcon" @click="openCreate">Add mouse</AppButton>
      </template>
    </PageHeader>

    <section class="card overflow-hidden">
      <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center">
        <div class="w-full lg:max-w-xs">
          <SearchInput v-model="search" placeholder="Search ID, brand, model, description..." />
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
        <EmptyState :icon="TriangleAlertIcon" title="Could not load mice" :description="error">
          <template #action><AppButton :icon="RefreshCwIcon" @click="load">Try again</AppButton></template>
        </EmptyState>
      </div>
      <LoadingState v-else-if="loading && !rows.length" :rows="6" />
      <div v-else-if="!rows.length" class="p-4">
        <EmptyState :icon="MouseIcon" title="No mice found" :description="hasFilters ? 'Try changing your search or filters.' : 'Add your first mouse to start building the database.'">
          <template v-if="hasFilters" #action><AppButton variant="secondary" @click="resetFilters">Clear filters</AppButton></template>
          <template v-else #action><AppButton :icon="PlusIcon" @click="openCreate">Add mouse</AppButton></template>
        </EmptyState>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[860px] border-collapse">
          <thead class="border-b border-slate-100 bg-slate-50/60">
            <tr>
              <th class="table-th w-12"><span class="sr-only">Photo</span></th>
              <th class="table-th">ID</th>
              <th class="table-th">Brand / Model</th>
              <th class="table-th">Color</th>
              <th class="table-th">Connectivity</th>
              <th class="table-th">Status</th>
              <th class="table-th">Added</th>
              <th class="table-th text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="mouse in rows"
              :key="mouse.id"
              class="cursor-pointer transition hover:bg-brand-50/40"
              tabindex="0"
              @click="goTo(mouse)"
              @keydown.enter="goTo(mouse)"
            >
              <td class="table-td">
                <div class="relative flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
                  <img v-if="primaryPhotoUrl(mouse)" :src="primaryPhotoUrl(mouse)" :alt="deviceName(mouse)" class="h-full w-full object-cover" />
                  <MouseIcon v-else :size="17" />
                  <span v-if="photoCount(mouse) > 1" class="absolute bottom-0 right-0 rounded-tl bg-slate-900/80 px-1 text-[8px] font-bold text-white">{{ photoCount(mouse) }}</span>
                </div>
              </td>
              <td class="table-td">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-semibold text-brand-700">{{ mouse.mouse_id }}</span>
                  <span v-if="mouse.is_seed" class="rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-600 ring-1 ring-amber-200">Sample</span>
                </div>
              </td>
              <td class="table-td">
                <p class="font-semibold text-slate-800">{{ deviceName(mouse, 'Unnamed mouse') }}</p>
                <p v-if="mouse.physical_description" class="mt-0.5 max-w-52 truncate text-[11px] text-slate-400">{{ mouse.physical_description }}</p>
              </td>
              <td class="table-td"><span class="text-slate-600">{{ mouse.color || '—' }}</span></td>
              <td class="table-td"><span class="text-slate-600">{{ mouse.connectivity_type || '—' }}</span></td>
              <td class="table-td"><StatusBadge :status="mouse.device_match_status || mouse.match?.status || 'unmatched'" /></td>
              <td class="table-td text-xs text-slate-500">{{ formatDate(mouse.created_at) }}</td>
              <td class="table-td">
                <div class="flex items-center justify-end gap-1">
                  <AppButton size="icon" variant="ghost" :icon="EyeIcon" aria-label="View mouse" @click.stop="goTo(mouse)" />
                  <AppButton size="icon" variant="ghost" :icon="PencilIcon" aria-label="Edit mouse" @click.stop="openEdit(mouse)" />
                  <AppButton size="icon" variant="ghost" :icon="Trash2Icon" aria-label="Delete mouse" @click.stop="remove(mouse)" />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :paginator="paginator" @change="page = $event" />
    </section>

    <Modal v-model="formOpen" :title="editing ? 'Edit mouse' : 'Add a mouse'" :subtitle="editing ? 'Update the details of this mouse.' : 'Record a new wireless mouse in the database.'" size="lg" @close="resetForm">
      <form id="mouse-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <Field label="Brand" :id="'mouse-brand'" required>
            <input id="mouse-brand" v-model="form.brand" class="input" placeholder="e.g. Logitech" required />
          </Field>
          <Field label="Model" :id="'mouse-model'">
            <input id="mouse-model" v-model="form.model" class="input" placeholder="e.g. M185" />
          </Field>
          <Field label="Color" :id="'mouse-color'">
            <input id="mouse-color" v-model="form.color" class="input" placeholder="e.g. Black" />
          </Field>
          <Field label="Connectivity type" :id="'mouse-connectivity'" hint="Wireless receiver, Bluetooth, etc.">
            <input id="mouse-connectivity" v-model="form.connectivity_type" class="input" placeholder="e.g. Wireless USB receiver" />
          </Field>
        </div>
        <Field label="Physical description" :id="'mouse-description'" hint="Markings, stickers, wear — anything that helps identify it.">
          <textarea id="mouse-description" v-model="form.physical_description" rows="3" class="input resize-y" placeholder="Describe how this mouse looks..."></textarea>
        </Field>
        <Field label="Notes" :id="'mouse-notes'">
          <textarea id="mouse-notes" v-model="form.notes" rows="2" class="input resize-y" placeholder="Optional notes"></textarea>
        </Field>
        <PhotoUpload v-model="form.photo" :current-url="form.photo_url" label="Mouse photo" @remove="form.remove_photo = true" />
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="formOpen = false">Cancel</AppButton>
        <AppButton type="submit" form="mouse-form" :icon="SaveIcon" :loading="saving">{{ editing ? 'Save changes' : 'Add mouse' }}</AppButton>
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
  MouseIcon,
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
import { deviceName, formatDate } from '../composables/useFormat'
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
  connectivity_type: '',
  physical_description: '',
  notes: '',
  photo: null,
  photo_url: null,
  remove_photo: false,
})
const form = ref(emptyForm())

const brandOptions = computed(() => {
  const brands = rows.value.map((mouse) => mouse.brand).filter(Boolean)
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
    const { data } = await axios.get('/mice', {
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
    error.value = requestError.response?.data?.message || 'Unable to load the mouse database.'
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

function goTo(mouse) {
  router.push(`/mice/${mouse.id}`)
}

function resetForm() {
  form.value = emptyForm()
  editing.value = null
}

function openCreate() {
  resetForm()
  formOpen.value = true
}

function openEdit(mouse) {
  editing.value = mouse
  form.value = {
    brand: mouse.brand || '',
    model: mouse.model || '',
    color: mouse.color || '',
    connectivity_type: mouse.connectivity_type || '',
    physical_description: mouse.physical_description || '',
    notes: mouse.notes || '',
    photo: null,
    photo_url: mouse.photo_url || null,
    remove_photo: false,
  }
  formOpen.value = true
}

async function save() {
  if (!form.value.brand?.trim()) {
    toast.warning('Brand is required', 'Please enter a brand before saving.')
    return
  }
  saving.value = true
  try {
    const payload = new FormData()
    const fields = ['brand', 'model', 'color', 'connectivity_type', 'physical_description', 'notes']
    for (const field of fields) {
      payload.append(field, form.value[field] ?? '')
    }
    if (form.value.photo) payload.append('photo', form.value.photo)
    if (form.value.remove_photo) payload.append('remove_photo', '1')
    if (editing.value) payload.append('_method', 'PUT')

    const url = editing.value ? `/mice/${editing.value.id}` : '/mice'
    await axios.post(url, payload)
    toast.success(editing.value ? 'Mouse updated' : 'Mouse added', deviceName(form.value, 'Mouse'))
    formOpen.value = false
    resetForm()
    await load()
  } catch {
    // The axios interceptor already shows a readable toast.
  } finally {
    saving.value = false
  }
}

async function remove(mouse) {
  const accepted = await confirm({
    title: 'Delete this mouse?',
    message: `${deviceName(mouse, 'This mouse')} (${mouse.mouse_id}) will be permanently removed. Any related match will also be removed.`,
    confirmText: 'Delete mouse',
    variant: 'danger',
  })
  if (!accepted) return
  try {
    await axios.delete(`/mice/${mouse.id}`)
    toast.success('Mouse deleted', deviceName(mouse, 'Mouse'))
    await load()
  } catch {
    // handled by interceptor
  }
}

onMounted(load)
</script>
