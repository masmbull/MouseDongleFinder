<template>
  <div class="flex min-h-[60vh] items-center justify-center">
    <div class="card w-full max-w-lg p-8 text-center sm:p-12">
      <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
        <CompassIcon :size="32" />
      </div>
      <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">404</p>
      <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">This page went missing</h1>
      <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-500">
        The page you were looking for does not exist, or the link is out of date. Your devices are safe — head back to the dashboard.
      </p>
      <div class="mt-7 flex flex-wrap items-center justify-center gap-2">
        <AppButton :icon="HomeIcon" @click="router.push('/dashboard')">Back to dashboard</AppButton>
        <AppButton variant="secondary" :icon="RadarIcon" @click="router.push('/usb-detective')">USB Detective</AppButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { CompassIcon, HomeIcon, RadarIcon } from 'lucide-vue-next'
import AppButton from '../components/AppButton.vue'

const router = useRouter()
</script>
