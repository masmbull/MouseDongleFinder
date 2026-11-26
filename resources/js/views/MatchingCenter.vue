<template>
  <div>
    <PageHeader eyebrow="Main workspace" title="Matching Center" subtitle="Pick a mouse and a receiver, test them together, then record what actually happened.">
      <template #actions>
        <AppButton variant="secondary" :icon="RefreshCwIcon" :loading="loading" @click="loadAll">Refresh inventory</AppButton>
        <AppButton variant="secondary" :icon="LayersIcon" @click="router.push('/matches')">All matches</AppButton>
      </template>
    </PageHeader>

    <!-- Workflow stepper -->
    <section class="card mb-6 p-4 sm:p-5">
      <ol class="flex flex-wrap items-center gap-y-3">
        <li v-for="(step, index) in steps" :key="step.label" class="flex items-center gap-2" :class="index < steps.length - 1 ? 'mr-2 sm:mr-4' : ''">
          <span
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold transition"
            :class="index + 1 < activeStep || (activeStep === steps.length + 1 && lastResult)
              ? 'bg-emerald-500 text-white'
              : index + 1 === activeStep
                ? 'bg-brand-600 text-white shadow-sm shadow-brand-600/25'
                : 'bg-slate-100 text-slate-400'"
          >
            <CheckIcon v-if="index + 1 < activeStep || (activeStep === steps.length + 1 && lastResult)" :size="14" />
            {{ index + 1 }}
          </span>
          <span class="text-xs font-semibold" :class="index + 1 === activeStep ? 'text-brand-700' : 'text-slate-500'">{{ step.label }}</span>
          <ChevronRightIcon v-if="index < steps.length - 1" :size="15" class="ml-1 hidden text-slate-300 sm:block" />
        </li>
      </ol>

      <div class="mt-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-3">
        <TriangleAlertIcon :size="18" class="mt-0.5 shrink-0 text-amber-600" />
        <p class="text-xs leading-relaxed text-amber-800">
          <span class="font-bold">Physical testing is required.</span>
          A shared brand, model name or radio frequency is not evidence of a pair. VID/PID identifies a device family, not a unique receiver. Only test the mouse with the dongle in hand before recording a match.
        </p>
      </div>
    </section>

    <!-- Duplicate / conflict -->
    <div v-if="existingMatch" class="card mb-6 border-rose-200 bg-rose-50/60 p-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600"><LinkIcon :size="18" /></div>
          <div>
            <p class="text-sm font-bold text-rose-800">An active match already exists</p>
            <p class="mt-0.5 text-xs text-rose-700">
              {{ deviceName(existingMatch.mouse, 'A mouse') }} is already linked to {{ deviceName(existingMatch.dongle, 'a dongle') }} ({{ existingMatch.status }}).
              Remove or review that relationship before creating another.
            </p>
          </div>
        </div>
        <div class="flex shrink-0 gap-2">
          <AppButton size="sm" variant="secondary" @click="existingMatch = null">Dismiss</AppButton>
          <AppButton size="sm" :icon="ArrowUpRightIcon" @click="router.push(`/matches?highlight=${existingMatch.id}`)">View existing match</AppButton>
        </div>
      </div>
    </div>

    <!-- Device panels -->
    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Mice -->
      <section class="card flex flex-col overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><MouseIcon :size="17" /></div>
              <div>
                <h2 class="text-sm font-bold text-slate-900">Step 1 · Choose a mouse</h2>
                <p class="text-[11px] text-slate-400">{{ mice.length }} available</p>
              </div>
            </div>
            <AppButton size="xs" variant="ghost" :icon="PlusIcon" @click="router.push('/mice')">Add</AppButton>
          </div>
          <div class="mt-3"><SearchInput v-model="mouseSearch" placeholder="Search mice..." /></div>
        </div>

        <div class="min-h-56 flex-1 space-y-2 overflow-y-auto p-4" style="max-height: 360px">
          <LoadingState v-if="loading && !mice.length" :rows="4" />
          <div v-else-if="!mice.length" class="py-8">
            <EmptyState :icon="MouseIcon" title="No mice available" description="Register a mouse first to start matching." />
          </div>
          <DeviceCard
            v-for="mouse in mice"
            v-else
            :key="mouse.id"
            :device="mouse"
            type="mouse"
            :selected="selectedMouse?.id === mouse.id"
            @click="selectMouse(mouse)"
          />
        </div>

        <div class="border-t border-slate-100 bg-slate-50/60 p-4">
          <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Selected mouse</p>
          <div v-if="selectedMouse" class="flex items-center gap-3 rounded-xl border border-brand-200 bg-white p-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
              <img v-if="primaryPhotoUrl(selectedMouse)" :src="primaryPhotoUrl(selectedMouse)" alt="" class="h-full w-full object-cover" />
              <MouseIcon v-else :size="20" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-bold text-slate-800">{{ deviceName(selectedMouse, 'Unnamed mouse') }}</p>
              <p class="font-mono text-[11px] text-slate-400">{{ selectedMouse.mouse_id }}</p>
              <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ selectedMouse.physical_description || selectedMouse.connectivity_type || 'No description' }}</p>
            </div>
            <StatusBadge :status="selectedMouse.device_match_status || selectedMouse.match?.status || 'unmatched'" />
          </div>
          <div v-if="selectedMouse && referencePhotos(selectedMouse).length" class="mt-2 flex gap-1.5 overflow-x-auto">
            <img v-for="(photo, index) in referencePhotos(selectedMouse)" :key="photo.id || index" :src="photo.url" :alt="photo.caption || photoTypeLabel(photo)" :title="photo.caption || photoTypeLabel(photo)" class="h-9 w-9 shrink-0 rounded-md border border-slate-200 bg-white object-contain p-0.5" />
          </div>
          <div v-else class="flex h-20 items-center justify-center rounded-xl border border-dashed border-slate-200 text-xs text-slate-400">Nothing selected yet</div>
        </div>
      </section>

      <!-- Dongles -->
      <section class="card flex flex-col overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><UsbIcon :size="17" /></div>
              <div>
                <h2 class="text-sm font-bold text-slate-900">Step 2 · Choose a dongle</h2>
                <p class="text-[11px] text-slate-400">{{ dongles.length }} available</p>
              </div>
            </div>
            <AppButton size="xs" variant="secondary" :icon="RadarIcon" @click="router.push('/usb-detective')">Investigate</AppButton>
          </div>
          <div class="mt-3"><SearchInput v-model="dongleSearch" placeholder="Search dongles by ID, brand or VID/PID..." /></div>
        </div>

        <div class="min-h-56 flex-1 space-y-2 overflow-y-auto p-4" style="max-height: 360px">
          <LoadingState v-if="loading && !dongles.length" :rows="4" />
          <div v-else-if="!dongles.length" class="py-8">
            <EmptyState :icon="UsbIcon" title="No dongles available" description="Register a receiver or investigate one in USB Detective first." />
          </div>
          <DeviceCard
            v-for="dongle in dongles"
            v-else
            :key="dongle.id"
            :device="dongle"
            type="dongle"
            :selected="selectedDongle?.id === dongle.id"
            @click="selectDongle(dongle)"
          />
        </div>

        <div class="border-t border-slate-100 bg-slate-50/60 p-4">
          <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Selected dongle</p>
          <div v-if="selectedDongle" class="flex items-center gap-3 rounded-xl border border-brand-200 bg-white p-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
              <img v-if="primaryPhotoUrl(selectedDongle)" :src="primaryPhotoUrl(selectedDongle)" alt="" class="h-full w-full object-cover" />
              <UsbIcon v-else :size="20" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-bold text-slate-800">{{ deviceName(selectedDongle, 'Unknown receiver') }}</p>
              <p class="font-mono text-[11px] text-slate-400">{{ selectedDongle.dongle_id }} · {{ selectedDongle.vid || '----' }}:{{ selectedDongle.pid || '----' }}</p>
              <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ selectedDongle.physical_description || 'No description' }}</p>
            </div>
            <StatusBadge :status="selectedDongle.device_match_status || selectedDongle.match?.status || 'unmatched'" />
          </div>
          <div v-if="selectedDongle && referencePhotos(selectedDongle).length" class="mt-2 flex gap-1.5 overflow-x-auto">
            <img v-for="(photo, index) in referencePhotos(selectedDongle)" :key="photo.id || index" :src="photo.url" :alt="photo.caption || photoTypeLabel(photo)" :title="photo.caption || photoTypeLabel(photo)" class="h-9 w-9 shrink-0 rounded-md border border-slate-200 bg-white object-contain p-0.5" />
          </div>
          <div v-else class="flex h-20 items-center justify-center rounded-xl border border-dashed border-slate-200 text-xs text-slate-400">Nothing selected yet</div>
        </div>
      </section>
    </div>

    <!-- Review / record result -->
    <section class="card mt-6 overflow-hidden">
      <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="text-sm font-bold text-slate-900">Step 3–5 · Review, test and record</h2>
        <p class="mt-0.5 text-xs text-slate-500">The result you choose is stored exactly as selected.</p>
      </div>

      <div v-if="!selectedMouse || !selectedDongle" class="p-6">
        <EmptyState :icon="LinkIcon" title="Select one mouse and one dongle" description="Choose a device from each panel above to unlock the pairing controls." />
      </div>

      <div v-else class="p-6">
        <div class="grid gap-4 lg:grid-cols-3">
          <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Mouse</p>
            <p class="mt-1 text-sm font-bold text-slate-800">{{ deviceName(selectedMouse, 'Unnamed mouse') }}</p>
            <p class="font-mono text-[11px] text-slate-400">{{ selectedMouse.mouse_id }}</p>
          </div>
          <div class="flex items-center justify-center"><div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-400"><LinkIcon :size="17" /></div></div>
          <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Dongle</p>
            <p class="mt-1 text-sm font-bold text-slate-800">{{ deviceName(selectedDongle, 'Unknown receiver') }}</p>
            <p class="font-mono text-[11px] text-slate-400">{{ selectedDongle.dongle_id }} · {{ selectedDongle.vid || '----' }}:{{ selectedDongle.pid || '----' }}</p>
          </div>
        </div>

        <div v-if="pairedMatch" class="mt-4 flex flex-col gap-3 rounded-xl border border-brand-200 bg-brand-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <BadgeCheckIcon :size="20" class="shrink-0 text-brand-600" />
            <div>
              <p class="text-sm font-semibold text-brand-800">This pair already has a recorded relationship</p>
              <p class="text-xs text-brand-700">Changing the status updates that record. Removing it releases both devices.</p>
            </div>
          </div>
          <StatusBadge :status="pairedMatch.status" />
        </div>

        <div class="mt-5 space-y-4">
          <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-brand-200 hover:bg-slate-50">
            <input v-model="testConfirmed" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 accent-brand-600" />
            <span class="text-xs leading-relaxed text-slate-600">
              <span class="font-semibold text-slate-800">I physically tested this mouse with this dongle.</span>
              The device responded correctly when paired. This is required before recording a <span class="font-semibold">confirmed match</span>.
            </span>
          </label>

          <Field label="Result notes" id="match-notes" hint="Optional — what did you observe during the test?">
            <textarea id="match-notes" v-model="matchNotes" rows="2" class="input resize-y" placeholder="e.g. Paired successfully after pressing the connect button"></textarea>
          </Field>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-5">
          <AppButton :icon="BadgeCheckIcon" :loading="acting === 'matched'" :disabled="acting !== null" @click="record('matched')">Confirm Match</AppButton>
          <AppButton variant="secondary" :icon="HelpCircleIcon" :loading="acting === 'suspected'" :disabled="acting !== null" @click="record('suspected')">Mark as Suspected</AppButton>
          <AppButton variant="secondary" :icon="UnlinkIcon" :loading="acting === 'unmatched'" :disabled="acting !== null" @click="record('unmatched')">Mark as Unmatched</AppButton>
          <AppButton v-if="pairedMatch" variant="danger-soft" :icon="Trash2Icon" :loading="acting === 'remove'" :disabled="acting !== null" @click="record('remove')">Remove Match</AppButton>
          <span v-if="lastResult" class="ml-auto inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
            <CheckIcon :size="15" /> Last result: {{ statusLabel(lastResult) }}
          </span>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import {
  RefreshCwIcon,
  LayersIcon,
  MouseIcon,
  UsbIcon,
  LinkIcon,
  PlusIcon,
  RadarIcon,
  ArrowUpRightIcon,
  TriangleAlertIcon,
  CheckIcon,
  ChevronRightIcon,
  BadgeCheckIcon,
  HelpCircleIcon,
  UnlinkIcon,
  Trash2Icon,
} from 'lucide-vue-next'
import PageHeader from '../components/PageHeader.vue'
import AppButton from '../components/AppButton.vue'
import SearchInput from '../components/SearchInput.vue'
import DeviceCard from '../components/DeviceCard.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import LoadingState from '../components/LoadingState.vue'
import Field from '../components/Field.vue'
import { deviceName, statusLabel } from '../composables/useFormat'
import { photoTypeLabel, primaryPhotoUrl, referencePhotos } from '../composables/useDevicePhotos'
import { useConfirm } from '../composables/useConfirm'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const mice = ref([])
const dongles = ref([])
const loading = ref(true)
const acting = ref(null)
const mouseSearch = ref('')
const dongleSearch = ref('')
const selectedMouse = ref(null)
const selectedDongle = ref(null)
const testConfirmed = ref(false)
const matchNotes = ref('')
const lastResult = ref('')
const existingMatch = ref(null)

const steps = [
  { label: 'Select' },
  { label: 'Review' },
  { label: 'Physical test' },
  { label: 'Record result' },
  { label: 'Confirm' },
]

const activeStep = computed(() => {
  if (!selectedMouse.value || !selectedDongle.value) return 1
  if (!testConfirmed.value && !lastResult.value) return 3
  if (!lastResult.value) return 4
  return 5
})

const pairedMatch = computed(() => {
  const mouse = selectedMouse.value
  const dongle = selectedDongle.value
  if (!mouse || !dongle) return null
  const candidate = mouse.match || dongle.match
  if (!candidate || candidate.mouse_id !== mouse.id || candidate.dongle_id !== dongle.id) return null
  return candidate
})

watch(mouseSearch, () => debouncedLoad('mouse'))
watch(dongleSearch, () => debouncedLoad('dongle'))

let debounceTimer
function debouncedLoad() {
  window.clearTimeout(debounceTimer)
  debounceTimer = window.setTimeout(loadAll, 300)
}

function preserveSelection(list, selected) {
  if (!selected) return null
  return list.find((item) => item.id === selected.id) || selected
}

async function loadAll() {
  loading.value = true
  try {
    const [miceResponse, donglesResponse] = await Promise.all([
      axios.get('/mice', { params: { search: mouseSearch.value || undefined, per_page: 50 } }),
      axios.get('/dongles', { params: { search: dongleSearch.value || undefined, per_page: 50 } }),
    ])
    const nextMice = Array.isArray(miceResponse.data.data) ? miceResponse.data.data : []
    const nextDongles = Array.isArray(donglesResponse.data.data) ? donglesResponse.data.data : []
    mice.value = nextMice
    dongles.value = nextDongles
    selectedMouse.value = preserveSelection(nextMice, selectedMouse.value)
    selectedDongle.value = preserveSelection(nextDongles, selectedDongle.value)
  } catch {
    // handled by interceptor
  } finally {
    loading.value = false
  }
}

function selectMouse(mouse) {
  if (selectedMouse.value?.id === mouse.id) {
    selectedMouse.value = null
    return
  }
  selectedMouse.value = mouse
  testConfirmed.value = false
  lastResult.value = ''
  matchNotes.value = pairedMatch.value?.notes || ''
  existingMatch.value = null
}

function selectDongle(dongle) {
  if (selectedDongle.value?.id === dongle.id) {
    selectedDongle.value = null
    return
  }
  selectedDongle.value = dongle
  testConfirmed.value = false
  lastResult.value = ''
  matchNotes.value = pairedMatch.value?.notes || ''
  existingMatch.value = null
}

const dialogCopy = {
  matched: {
    title: 'Record a confirmed match?',
    message: 'Only continue if you physically tested this mouse with this dongle and it worked. This marks the relationship as confirmed.',
    confirmText: 'Record match',
    variant: 'primary',
  },
  suspected: {
    title: 'Mark as suspected?',
    message: 'This records a possible relationship that still needs a physical test. It is not treated as confirmed.',
    confirmText: 'Mark suspected',
    variant: 'primary',
  },
  unmatched: {
    title: 'Mark as unmatched?',
    message: 'This records that the pair did not work together. It is different from removing the relationship entirely.',
    confirmText: 'Mark unmatched',
    variant: 'primary',
  },
  remove: {
    title: 'Remove this match?',
    message: 'The recorded relationship will be deleted and both devices will become unmatched. This cannot be undone.',
    confirmText: 'Remove match',
    variant: 'danger',
  },
}

async function record(status) {
  if (!selectedMouse.value || !selectedDongle.value) return
  if (status === 'matched' && !testConfirmed.value) {
    toast.warning('Physical test required', 'Confirm that you tested this pair before recording a confirmed match.')
    return
  }

  const accepted = await confirm(dialogCopy[status])
  if (!accepted) return

  acting.value = status
  existingMatch.value = null
  try {
    if (status === 'remove') {
      if (!pairedMatch.value) {
        toast.info('Nothing to remove', 'This pair has no recorded relationship.')
        return
      }
      await axios.delete(`/matches/${pairedMatch.value.id}`)
      toast.success('Match removed', 'Both devices are now unmatched.')
    } else if (pairedMatch.value) {
      await axios.post(`/matches/${pairedMatch.value.id}`, {
        _method: 'PUT',
        status,
        notes: matchNotes.value || null,
      })
      toast.success('Match updated', `Status set to ${statusLabel(status).toLowerCase()}.`)
    } else {
      await axios.post('/matches', {
        mouse_id: selectedMouse.value.id,
        dongle_id: selectedDongle.value.id,
        status,
        notes: matchNotes.value || null,
      })
      toast.success('Result recorded', `Status set to ${statusLabel(status).toLowerCase()}.`)
    }
    lastResult.value = status === 'remove' ? 'unmatched' : status
    if (status === 'matched' || status === 'remove') testConfirmed.value = false
    await loadAll()
  } catch (requestError) {
    if (requestError.response?.status === 422 && requestError.response.data?.existing_match) {
      existingMatch.value = requestError.response.data.existing_match
      toast.warning('Duplicate active match', requestError.response.data.error || 'This device already has an active match.')
    }
    // All other failures are surfaced by the axios interceptor.
  } finally {
    acting.value = null
  }
}

onMounted(loadAll)
</script>
