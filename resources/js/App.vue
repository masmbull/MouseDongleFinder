<template>
  <div class="min-h-screen bg-slate-50 lg:flex">
    <!-- Mobile top bar -->
    <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur lg:hidden">
      <button
        type="button"
        aria-label="Open navigation menu"
        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50 hover:text-brand-600"
        @click="mobileOpen = true"
      >
        <MenuIcon :size="20" />
      </button>
      <div class="flex min-w-0 items-center gap-2">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white shadow-sm">
          <MousePointer2Icon :size="17" />
        </div>
        <span class="truncate text-sm font-bold tracking-tight text-slate-800">MouseDongleFinder</span>
      </div>
      <span class="ml-auto truncate text-xs font-medium text-slate-400">{{ pageTitle }}</span>
    </header>

    <!-- Sidebar (desktop) -->
    <aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex">
      <div class="flex h-20 items-center gap-3 border-b border-slate-100 px-6">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow-md shadow-brand-600/20">
          <MousePointer2Icon :size="20" />
        </div>
        <div class="min-w-0">
          <p class="truncate text-sm font-bold tracking-tight text-slate-900">MouseDongleFinder</p>
          <p class="truncate text-[11px] text-slate-400">Wireless device lab</p>
        </div>
      </div>

      <nav class="flex-1 space-y-7 overflow-y-auto px-4 py-6" aria-label="Main navigation">
        <div>
          <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Workspace</p>
          <ul class="space-y-1">
            <li v-for="item in primaryNav" :key="item.path">
              <router-link
                :to="item.path"
                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150"
                :class="isActive(item.path)
                  ? 'bg-brand-50 text-brand-700 shadow-sm ring-1 ring-brand-100'
                  : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                :aria-current="isActive(item.path) ? 'page' : undefined"
              >
                <component :is="item.icon" :size="18" :class="isActive(item.path) ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600'" />
                <span class="truncate">{{ item.label }}</span>
                <span v-if="isActive(item.path)" class="ml-auto h-1.5 w-1.5 rounded-full bg-brand-500"></span>
              </router-link>
            </li>
          </ul>
        </div>

        <div>
          <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Tools</p>
          <ul class="space-y-1">
            <li v-for="item in toolNav" :key="item.path">
              <router-link
                :to="item.path"
                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150"
                :class="isActive(item.path)
                  ? 'bg-brand-50 text-brand-700 shadow-sm ring-1 ring-brand-100'
                  : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                :aria-current="isActive(item.path) ? 'page' : undefined"
              >
                <component :is="item.icon" :size="18" :class="isActive(item.path) ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600'" />
                <span class="truncate">{{ item.label }}</span>
                <span v-if="isActive(item.path)" class="ml-auto h-1.5 w-1.5 rounded-full bg-brand-500"></span>
              </router-link>
            </li>
          </ul>
        </div>
      </nav>

      <div class="border-t border-slate-100 px-6 py-4">
        <div class="flex items-center justify-between text-[11px] text-slate-400">
          <div>
            <p class="font-semibold text-slate-500">Version 1.0</p>
            <p>Laravel &amp; Vue</p>
          </div>
          <span class="rounded-md bg-slate-100 px-2 py-1 font-medium text-slate-500">Local</span>
        </div>
      </div>
    </aside>

    <!-- Mobile drawer -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden" @click="mobileOpen = false"></div>
      </Transition>
      <Transition name="slide">
        <aside v-if="mobileOpen" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-white shadow-2xl lg:hidden" aria-label="Mobile navigation">
          <div class="flex h-16 items-center gap-3 border-b border-slate-100 px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white">
              <MousePointer2Icon :size="18" />
            </div>
            <span class="text-sm font-bold text-slate-900">MouseDongleFinder</span>
            <button type="button" aria-label="Close navigation menu" class="ml-auto rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="mobileOpen = false">
              <XIcon :size="18" />
            </button>
          </div>
          <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-5">
            <div>
              <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Workspace</p>
              <ul class="space-y-1">
                <li v-for="item in primaryNav" :key="item.path">
                  <router-link
                    :to="item.path"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                    :class="isActive(item.path) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="mobileOpen = false"
                  >
                    <component :is="item.icon" :size="18" />
                    {{ item.label }}
                  </router-link>
                </li>
              </ul>
            </div>
            <div>
              <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Tools</p>
              <ul class="space-y-1">
                <li v-for="item in toolNav" :key="item.path">
                  <router-link
                    :to="item.path"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                    :class="isActive(item.path) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="mobileOpen = false"
                  >
                    <component :is="item.icon" :size="18" />
                    {{ item.label }}
                  </router-link>
                </li>
              </ul>
            </div>
          </nav>
          <div class="border-t border-slate-100 px-5 py-4 text-[11px] text-slate-400">Version 1.0 · Laravel &amp; Vue</div>
        </aside>
      </Transition>
    </Teleport>

    <!-- Main content -->
    <main class="min-w-0 flex-1">
      <div class="mx-auto max-w-[1600px] px-4 py-5 sm:px-6 lg:px-8 lg:py-8">
        <router-view v-slot="{ Component }">
          <Transition name="fade" mode="out-in">
            <component :is="Component" :key="route.fullPath" />
          </Transition>
        </router-view>
      </div>
    </main>

    <ToastHost />
    <ConfirmDialog />
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  HomeIcon,
  MouseIcon,
  UsbIcon,
  LinkIcon,
  LayersIcon,
  RadarIcon,
  FlaskConicalIcon,
  MenuIcon,
  XIcon,
  MousePointer2Icon,
} from 'lucide-vue-next'
import ToastHost from './components/ToastHost.vue'
import ConfirmDialog from './components/ConfirmDialog.vue'

const route = useRoute()
const mobileOpen = ref(false)

const primaryNav = [
  { path: '/dashboard', label: 'Dashboard', icon: HomeIcon },
  { path: '/mice', label: 'Mouse Database', icon: MouseIcon },
  { path: '/dongles', label: 'Dongle Database', icon: UsbIcon },
  { path: '/matching', label: 'Matching Center', icon: LinkIcon },
]

const toolNav = [
  { path: '/matches', label: 'Match List', icon: LayersIcon },
  { path: '/usb-detective', label: 'USB Detective', icon: RadarIcon },
  { path: '/dongle-lab', label: 'Dongle Lab', icon: FlaskConicalIcon },
]

const pageTitle = computed(() => route.meta.title || 'Dashboard')

const isActive = (path) => route.path === path || route.path.startsWith(`${path}/`)

watch(() => route.fullPath, () => {
  mobileOpen.value = false
})
</script>
