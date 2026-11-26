<template>
  <div>
    <PageHeader eyebrow="Overview" title="Dashboard" subtitle="A live snapshot of your known mice, receivers and confirmed relationships.">
      <template #actions>
        <AppButton variant="secondary" :icon="RefreshCwIcon" :loading="loading" @click="load">Refresh</AppButton>
        <AppButton :icon="LinkIcon" @click="router.push('/matching')">Matching Center</AppButton>
      </template>
    </PageHeader>

    <div v-if="error" class="card">
      <EmptyState
        :icon="TriangleAlertIcon"
        title="Could not load dashboard"
        :description="error"
      >
        <template #action><AppButton :icon="RefreshCwIcon" @click="load">Try again</AppButton></template>
      </EmptyState>
    </div>

    <template v-else-if="loading">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="n in 4" :key="n" class="card h-[88px] animate-pulse p-4"><div class="skeleton h-full w-full"></div></div>
      </div>
      <div class="mt-6 card"><LoadingState :rows="4" /></div>
    </template>

    <template v-else>
      <!-- Headline stats -->
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Registered mice" :value="stats.total_mice" :icon="MouseIcon" tone="brand" to="/mice" hint="in database" />
        <StatCard label="Registered dongles" :value="stats.total_dongles" :icon="UsbIcon" tone="violet" to="/dongles" hint="in database" />
        <StatCard label="Confirmed matches" :value="stats.matched_pairs" :icon="BadgeCheckIcon" tone="emerald" to="/matches" hint="physically tested" />
        <StatCard label="Suspected pairs" :value="stats.suspected_pairs" :icon="HelpCircleIcon" tone="amber" to="/matches" hint="needs testing" />
      </div>

      <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <StatCard label="Unmatched mice" :value="stats.unmatched_mice" :icon="MouseIcon" tone="slate" to="/mice" />
        <StatCard label="Unmatched dongles" :value="stats.unmatched_dongles" :icon="UsbIcon" tone="slate" to="/dongles" />
        <StatCard label="Unknown devices" :value="stats.unknown_devices" :icon="CircleHelpIcon" tone="violet" to="/matches" />
      </div>

      <!-- Shortcuts -->
      <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <router-link to="/matching" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 to-indigo-700 p-6 text-white shadow-lg shadow-brand-600/20 transition hover:-translate-y-0.5 hover:shadow-xl">
          <div class="absolute -right-8 -top-10 h-40 w-40 rounded-full bg-white/10 transition group-hover:scale-125"></div>
          <div class="relative flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15"><LinkIcon :size="22" /></div>
            <div>
              <p class="text-base font-bold">Matching Center</p>
              <p class="mt-1 max-w-sm text-xs leading-relaxed text-white/75">Review a mouse and a dongle side by side, perform the physical test, then record the result.</p>
              <span class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold">Open workspace <ArrowRightIcon :size="14" /></span>
            </div>
          </div>
        </router-link>

        <router-link to="/usb-detective" class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md">
          <div class="flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><RadarIcon :size="22" /></div>
            <div>
              <p class="text-base font-bold text-slate-900">USB Detective</p>
              <p class="mt-1 max-w-sm text-xs leading-relaxed text-slate-500">Scan connected hardware, decode VID/PID, research an unknown receiver and find official software.</p>
              <span class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600">Start investigating <ArrowRightIcon :size="14" /></span>
            </div>
          </div>
        </router-link>
      </div>

      <!-- Status overview + recent activity -->
      <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card p-5 xl:col-span-1">
          <h2 class="text-sm font-bold text-slate-900">Relationship status</h2>
          <p class="mt-0.5 text-xs text-slate-500">How recorded relationships are distributed</p>
          <div class="mt-5 space-y-4">
            <div v-for="bar in statusBars" :key="bar.key">
              <div class="mb-1.5 flex items-center justify-between text-xs">
                <span class="font-medium text-slate-600">{{ bar.label }}</span>
                <span class="font-semibold text-slate-800">{{ bar.value }} <span class="font-normal text-slate-400">({{ bar.percent }}%)</span></span>
              </div>
              <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full transition-all duration-500" :class="bar.color" :style="{ width: `${bar.percent}%` }"></div>
              </div>
            </div>
          </div>
          <div v-if="statusTotal === 0" class="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-500">No relationships recorded yet.</div>
        </section>

        <section class="card overflow-hidden xl:col-span-2">
          <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
              <h2 class="text-sm font-bold text-slate-900">Recent activity</h2>
              <p class="mt-0.5 text-xs text-slate-500">Latest devices and recorded matches</p>
            </div>
            <AppButton size="xs" variant="ghost" @click="router.push('/matches')">View all</AppButton>
          </div>
          <div class="max-h-[340px] overflow-y-auto">
            <div v-if="!recentMice.length && !recentDongles.length && !recentMatches.length">
              <EmptyState :icon="InboxIcon" title="No activity yet" description="Add a mouse or dongle, or run a scan in USB Detective to get started." />
            </div>
            <div v-else class="divide-y divide-slate-100">
              <router-link v-for="match in recentMatches" :key="`m-${match.id}`" :to="`/matches?highlight=${match.id}`" class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><LinkIcon :size="15" /></div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-xs font-semibold text-slate-800">{{ deviceName(match.mouse) }} <span class="text-slate-400">↔</span> {{ deviceName(match.dongle) }}</p>
                  <p class="text-[11px] text-slate-400">Match recorded · {{ formatDate(match.matched_at || match.created_at) }}</p>
                </div>
                <StatusBadge :status="match.status" />
              </router-link>
              <router-link v-for="mouse in recentMice" :key="`mouse-${mouse.id}`" :to="`/mice/${mouse.id}`" class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
                  <img v-if="primaryPhotoUrl(mouse)" :src="primaryPhotoUrl(mouse)" alt="" class="h-full w-full object-cover" />
                  <MouseIcon v-else :size="15" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-xs font-semibold text-slate-800">Added mouse · {{ deviceName(mouse) }}</p>
                  <p class="font-mono text-[11px] text-slate-400">{{ mouse.mouse_id }} · {{ timeAgo(mouse.created_at) }}</p>
                </div>
                <StatusBadge :status="mouse.device_match_status || mouse.match?.status || 'unmatched'" />
              </router-link>
              <router-link v-for="dongle in recentDongles" :key="`dongle-${dongle.id}`" :to="`/dongles/${dongle.id}`" class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
                  <img v-if="primaryPhotoUrl(dongle)" :src="primaryPhotoUrl(dongle)" alt="" class="h-full w-full object-cover" />
                  <UsbIcon v-else :size="15" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-xs font-semibold text-slate-800">Added dongle · {{ deviceName(dongle, 'Unknown receiver') }}</p>
                  <p class="font-mono text-[11px] text-slate-400">{{ dongle.dongle_id }} · {{ timeAgo(dongle.created_at) }}</p>
                </div>
                <StatusBadge :status="dongle.device_match_status || dongle.match?.status || 'unmatched'" />
              </router-link>
            </div>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import {
  MouseIcon,
  UsbIcon,
  LinkIcon,
  BadgeCheckIcon,
  HelpCircleIcon,
  CircleHelpIcon,
  RefreshCwIcon,
  RadarIcon,
  ArrowRightIcon,
  InboxIcon,
  TriangleAlertIcon,
} from 'lucide-vue-next'
import PageHeader from '../components/PageHeader.vue'
import AppButton from '../components/AppButton.vue'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import LoadingState from '../components/LoadingState.vue'
import { deviceName, formatDate, timeAgo } from '../composables/useFormat'
import { primaryPhotoUrl } from '../composables/useDevicePhotos'

const router = useRouter()

const loading = ref(true)
const error = ref('')
const stats = ref({})
const recentMice = ref([])
const recentDongles = ref([])
const recentMatches = ref([])
const statusOverview = ref({})

const statusBars = computed(() => {
  const definitions = [
    { key: 'matched', label: 'Matched', color: 'bg-emerald-500' },
    { key: 'suspected', label: 'Suspected', color: 'bg-amber-500' },
    { key: 'unmatched', label: 'Unmatched', color: 'bg-slate-400' },
    { key: 'unknown', label: 'Unknown', color: 'bg-violet-500' },
  ]
  return definitions.map((item) => {
    const value = Number(statusOverview.value?.[item.key] || 0)
    return {
      ...item,
      value,
      percent: statusTotal.value ? Math.round((value / statusTotal.value) * 100) : 0,
    }
  })
})

const statusTotal = computed(() =>
  Object.values(statusOverview.value || {}).reduce((sum, value) => sum + Number(value || 0), 0),
)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.get('/dashboard')
    stats.value = data.stats || {}
    recentMice.value = Array.isArray(data.recent_mice) ? data.recent_mice : []
    recentDongles.value = Array.isArray(data.recent_dongles) ? data.recent_dongles : []
    recentMatches.value = Array.isArray(data.recent_matches) ? data.recent_matches : []
    statusOverview.value = data.status_overview || {}
  } catch (requestError) {
    error.value = requestError.response?.data?.error || requestError.response?.data?.message || 'The dashboard could not be reached.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
