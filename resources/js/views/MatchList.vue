<template>
  <div>
    <PageHeader eyebrow="Tools" title="Match List" subtitle="Every recorded mouse-to-receiver relationship, with its verification status.">
      <template #actions>
        <AppButton :icon="LinkIcon" @click="router.push('/matching')">Open Matching Center</AppButton>
      </template>
    </PageHeader>

    <section class="card overflow-hidden">
      <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center">
        <div class="w-full lg:max-w-sm"><SearchInput v-model="search" placeholder="Search mouse, dongle or ID..." /></div>
        <div class="flex flex-wrap items-center gap-2">
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
        <EmptyState :icon="TriangleAlertIcon" title="Could not load matches" :description="error">
          <template #action><AppButton :icon="RefreshCwIcon" @click="load">Try again</AppButton></template>
        </EmptyState>
      </div>
      <LoadingState v-else-if="loading && !rows.length" :rows="6" />
      <div v-else-if="!rows.length" class="p-4">
        <EmptyState :icon="LinkIcon" title="No matches recorded" :description="hasFilters ? 'Try changing your search or filters.' : 'Record your first relationship in the Matching Center.'">
          <template #action><AppButton :icon="LinkIcon" @click="router.push('/matching')">Open Matching Center</AppButton></template>
        </EmptyState>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[900px] border-collapse">
          <thead class="border-b border-slate-100 bg-slate-50/60">
            <tr>
              <th class="table-th">Mouse</th>
              <th class="table-th">Dongle</th>
              <th class="table-th">Status</th>
              <th class="table-th">Match date</th>
              <th class="table-th">Notes</th>
              <th class="table-th text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="match in rows"
              :key="match.id"
              class="transition"
              :class="route.query.highlight === String(match.id) ? 'bg-brand-50/60' : 'hover:bg-slate-50'"
            >
              <td class="table-td">
                <router-link :to="`/mice/${match.mouse_id}`" class="flex items-center gap-3 group">
                  <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
                    <img v-if="primaryPhotoUrl(match.mouse)" :src="primaryPhotoUrl(match.mouse)" alt="" class="h-full w-full object-cover" />
                    <MouseIcon v-else :size="17" />
                  </div>
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800 group-hover:text-brand-700">{{ deviceName(match.mouse, 'Unknown mouse') }}</p>
                    <p class="font-mono text-[11px] text-slate-400">{{ match.mouse?.mouse_id }}</p>
                  </div>
                </router-link>
              </td>
              <td class="table-td">
                <router-link :to="`/dongles/${match.dongle_id}`" class="flex items-center gap-3 group">
                  <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
                    <img v-if="primaryPhotoUrl(match.dongle)" :src="primaryPhotoUrl(match.dongle)" alt="" class="h-full w-full object-cover" />
                    <UsbIcon v-else :size="17" />
                  </div>
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800 group-hover:text-brand-700">{{ deviceName(match.dongle, 'Unknown receiver') }}</p>
                    <p class="font-mono text-[11px] text-slate-400">{{ match.dongle?.dongle_id }}</p>
                  </div>
                </router-link>
              </td>
              <td class="table-td"><StatusBadge :status="match.status" /></td>
              <td class="table-td text-xs text-slate-500">{{ formatDate(match.matched_at || match.created_at) }}</td>
              <td class="table-td">
                <p class="max-w-52 truncate text-xs text-slate-500" :title="match.notes || ''">{{ match.notes || '—' }}</p>
              </td>
              <td class="table-td">
                <div class="flex items-center justify-end gap-1">
                  <AppButton size="icon" variant="ghost" :icon="EyeIcon" aria-label="View match" @click="viewMatch(match)" />
                  <AppButton size="icon" variant="ghost" :icon="PencilIcon" aria-label="Edit notes" @click="openEdit(match)" />
                  <AppButton size="icon" variant="ghost" :icon="Trash2Icon" aria-label="Remove match" @click="remove(match)" />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :paginator="paginator" @change="page = $event" />
    </section>

    <!-- Detail -->
    <Modal v-model="detailOpen" title="Match details" size="md">
      <div v-if="detailMatch" class="space-y-5">
        <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-4">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Status</p>
            <div class="mt-1"><StatusBadge :status="detailMatch.status" /></div>
          </div>
          <div class="text-right">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Match date</p>
            <p class="mt-1 text-sm text-slate-700">{{ formatDateTime(detailMatch.matched_at || detailMatch.created_at) }}</p>
          </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <router-link :to="`/mice/${detailMatch.mouse_id}`" class="rounded-xl border border-slate-200 p-4 transition hover:border-brand-200">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Mouse</p>
            <p class="mt-1 text-sm font-bold text-slate-800">{{ deviceName(detailMatch.mouse, 'Unknown mouse') }}</p>
            <p class="font-mono text-[11px] text-slate-400">{{ detailMatch.mouse?.mouse_id }}</p>
          </router-link>
          <router-link :to="`/dongles/${detailMatch.dongle_id}`" class="rounded-xl border border-slate-200 p-4 transition hover:border-brand-200">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Dongle</p>
            <p class="mt-1 text-sm font-bold text-slate-800">{{ deviceName(detailMatch.dongle, 'Unknown receiver') }}</p>
            <p class="font-mono text-[11px] text-slate-400">{{ detailMatch.dongle?.dongle_id }}</p>
          </router-link>
        </div>
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Notes</p>
          <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ detailMatch.notes || 'No notes recorded.' }}</p>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="detailOpen = false">Close</AppButton>
        <AppButton :icon="PencilIcon" @click="openEdit(detailMatch); detailOpen = false">Edit notes</AppButton>
      </template>
    </Modal>

    <!-- Edit notes / status -->
    <Modal v-model="editOpen" title="Edit match" subtitle="Update the status or notes for this relationship." size="md" @close="resetEdit">
      <form id="match-edit-form" class="space-y-4" @submit.prevent="save">
        <Field label="Status" id="edit-status">
          <select id="edit-status" v-model="editForm.status" class="input">
            <option value="matched">Matched — physically verified</option>
            <option value="suspected">Suspected — needs testing</option>
            <option value="unmatched">Unmatched — tested, did not work</option>
            <option value="unknown">Unknown</option>
          </select>
        </Field>
        <Field label="Notes" id="edit-notes">
          <textarea id="edit-notes" v-model="editForm.notes" rows="4" class="input resize-y" placeholder="Observations from the physical test"></textarea>
        </Field>
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="editOpen = false">Cancel</AppButton>
        <AppButton type="submit" form="match-edit-form" :icon="SaveIcon" :loading="saving">Save changes</AppButton>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import {
  LinkIcon,
  RefreshCwIcon,
  MouseIcon,
  UsbIcon,
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
import { deviceName, formatDate, formatDateTime } from '../composables/useFormat'
import { primaryPhotoUrl } from '../composables/useDevicePhotos'
import { useConfirm } from '../composables/useConfirm'
import { useToast } from '../composables/useToast'

const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const rows = ref([])
const paginator = ref(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const search = ref('')
const status = ref('')
const page = ref(1)

const detailOpen = ref(false)
const detailMatch = ref(null)
const editOpen = ref(false)
const editingMatch = ref(null)
const editForm = ref({ status: 'suspected', notes: '' })

const hasFilters = computed(() => Boolean(search.value || status.value))

let debounceTimer
watch([search, status], () => {
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
    const { data } = await axios.get('/matches', {
      params: {
        search: search.value || undefined,
        status: status.value || undefined,
        per_page: 15,
        page: page.value,
      },
    })
    rows.value = Array.isArray(data.data) ? data.data : []
    paginator.value = data
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Unable to load matches.'
  } finally {
    loading.value = false
  }
}

function viewMatch(match) {
  detailMatch.value = match
  detailOpen.value = true
}

function resetEdit() {
  editingMatch.value = null
  editForm.value = { status: 'suspected', notes: '' }
}

function openEdit(match) {
  if (!match) return
  editingMatch.value = match
  editForm.value = { status: match.status || 'suspected', notes: match.notes || '' }
  editOpen.value = true
}

async function save() {
  if (!editingMatch.value) return
  saving.value = true
  try {
    await axios.post(`/matches/${editingMatch.value.id}`, {
      _method: 'PUT',
      status: editForm.value.status,
      notes: editForm.value.notes || null,
    })
    toast.success('Match updated', 'The relationship was updated.')
    editOpen.value = false
    resetEdit()
    await load()
  } catch {
    // handled by interceptor
  } finally {
    saving.value = false
  }
}

async function remove(match) {
  const accepted = await confirm({
    title: 'Remove this match?',
    message: `${deviceName(match.mouse, 'This mouse')} ↔ ${deviceName(match.dongle, 'this dongle')} will no longer be linked. Both devices become unmatched.`,
    confirmText: 'Remove match',
    variant: 'danger',
  })
  if (!accepted) return
  try {
    await axios.delete(`/matches/${match.id}`)
    toast.success('Match removed')
    await load()
  } catch {
    // handled by interceptor
  }
}

onMounted(load)
</script>
