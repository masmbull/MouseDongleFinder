<template>
  <div>
    <PageHeader
      eyebrow="Tools"
      title="Dongle Lab"
      subtitle="Plug all the loose dongles into a laptop or hub at once and scan them. Then switch ON one wireless mouse at a time, observe which new mouse device appears, choose the mouse and the exact dongle, and save the match."
    >
      <template #actions>
        <AppButton variant="secondary" :icon="RefreshCwIcon" :loading="refreshingSession" @click="refreshSession(true)">Refresh session</AppButton>
      </template>
    </PageHeader>

    <!-- Workflow stepper -->
    <section class="card mb-6 p-4 sm:p-5">
      <ol class="flex flex-wrap items-center gap-y-3">
        <li
          v-for="(step, index) in steps"
          :key="step.label"
          class="flex items-center gap-2"
          :class="index < steps.length - 1 ? 'mr-2 sm:mr-4' : ''"
        >
          <span
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold transition"
            :class="index + 1 < activeStep
              ? 'bg-emerald-500 text-white'
              : index + 1 === activeStep
                ? 'bg-brand-600 text-white shadow-sm shadow-brand-600/25'
                : 'bg-slate-100 text-slate-400'"
          >
            <CheckIcon v-if="index + 1 < activeStep" :size="14" />
            {{ index + 1 }}
          </span>
          <span class="text-xs font-semibold" :class="index + 1 === activeStep ? 'text-brand-700' : 'text-slate-500'">{{ step.label }}</span>
          <ChevronRightIcon v-if="index < steps.length - 1" :size="15" class="ml-1 hidden text-slate-300 sm:block" />
        </li>
      </ol>

      <div class="mt-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-3">
        <TriangleAlertIcon :size="18" class="mt-0.5 shrink-0 text-amber-600" />
        <p class="text-xs leading-relaxed text-amber-800">
          <span class="font-bold">Nothing is matched automatically.</span>
          Identical dongles share the same VID/PID and cannot be told apart electronically. Note the USB port for each label, then choose the dongle yourself after observing the mouse.
        </p>
      </div>
    </section>

    <!-- Step 1: scan all dongles -->
    <section class="card mb-6 overflow-hidden">
      <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><UsbIcon :size="18" /></div>
          <div>
            <h2 class="text-sm font-bold text-slate-900">Step 1 · Colok semua dongle</h2>
            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500">
              Plug every loose dongle into the computer or a USB hub at once. Each one gets a temporary label (A, B, C …) and its USB port, so you can tell them apart later.
            </p>
          </div>
        </div>
        <div class="flex flex-col items-start gap-2 sm:items-end">
          <div class="flex flex-wrap items-center gap-2">
            <AppButton :icon="ScanLineIcon" :loading="scanning" :disabled="autoDiscovering" @click="scanAll">Scan all plugged dongles</AppButton>
            <AppButton :icon="RadarIcon" :loading="autoDiscovering" :disabled="scanning" @click="runAutoDiscover()">Auto-discover pairs</AppButton>
          </div>
          <p class="max-w-md text-[11px] leading-relaxed text-slate-500 sm:text-right">
            Plug the dongles, switch ON one mouse, then click — the app reads the real HID connection and registers what it finds.
          </p>
        </div>
      </div>

      <div v-if="scanning" class="p-5">
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50/60 p-4">
          <Loader2Icon :size="18" class="mt-0.5 shrink-0 animate-spin text-brand-600" />
          <div>
            <p class="text-sm font-bold text-brand-800">Scanning all dongles…</p>
            <p class="mt-0.5 text-xs text-brand-700">Reading USB ports and hardware IDs. This takes about 10 seconds — keep every dongle plugged in.</p>
          </div>
        </div>
        <LoadingState variant="cards" :rows="3" label="Scanning dongles" />
      </div>

      <div v-else-if="!dongles.length" class="p-2">
        <EmptyState
          :icon="FlaskConicalIcon"
          :title="session ? 'No dongles scanned yet' : 'No Dongle Lab session yet'"
          :description="session
            ? 'Plug all the loose dongles into the computer or hub, then click Scan all plugged dongles.'
            : 'Plug all your loose wireless dongles into a laptop or hub at once. Dongle Lab labels each one (A, B, C …), then lets you switch on one mouse at a time to find its dongle. No match is created automatically.'"
        >
          <template #action>
            <div class="flex flex-wrap justify-center gap-2">
              <AppButton :icon="ScanLineIcon" :loading="scanning" @click="scanAll">Scan all plugged dongles</AppButton>
              <AppButton v-if="!session" variant="secondary" :icon="RefreshCwIcon" @click="loadLatestSession(true)">Look for an existing session</AppButton>
            </div>
          </template>
        </EmptyState>
      </div>

      <div v-else class="p-5">
        <div class="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3">
          <InfoIcon :size="16" class="mt-0.5 shrink-0 text-amber-600" />
          <p class="text-xs leading-relaxed text-amber-800">
            Identical dongles cannot be told apart by electronics. Use the <span class="font-bold">USB port / location</span> on each card to tell them apart when you observe a mouse.
          </p>
        </div>
        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-bold text-emerald-900">Plugged dongles → recorded matched mice</h3>
            <span class="rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200">From your database, not a guess</span>
          </div>
          <div class="mt-3 divide-y divide-emerald-100">
            <div v-for="dongle in dongles" :key="`summary-${dongle.key || vidPid(dongle)}`" class="flex flex-col gap-2 py-2.5 sm:flex-row sm:items-start sm:justify-between">
              <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-700 text-xs font-black text-white">{{ dongle.key || '?' }}</span>
                <div>
                  <p class="text-xs font-bold text-slate-800">{{ dongle.name || 'Unknown receiver' }}</p>
                  <p class="font-mono text-[11px] text-slate-500">{{ vidPid(dongle) }} · {{ dongle.port || 'port unknown' }}</p>
                </div>
              </div>
              <div class="sm:max-w-[62%] sm:text-right">
                <div v-if="asArray(dongle.matched_mice).length" class="flex flex-wrap gap-1.5 sm:justify-end">
                  <span v-for="match in dongle.matched_mice" :key="match.match_id" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2 py-1 text-[11px] text-slate-700 ring-1 ring-emerald-200">
                    <span class="font-semibold">{{ mouseName(match) }}</span>
                    <StatusBadge :status="match.status" />
                    <span v-if="match.match_source === 'same_vid_pid_family'" class="text-[9px] font-bold uppercase text-amber-600" title="Same VID/PID family — identical receivers cannot be distinguished">family clue</span>
                  </span>
                </div>
                <span v-else class="text-[11px] text-slate-500">No recorded match yet</span>
              </div>
            </div>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="dongle in dongles" :key="dongle.key || vidPid(dongle)" class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-lg font-black text-white">
                {{ dongle.key || '?' }}
              </div>
              <div class="flex flex-wrap items-center justify-end gap-1.5">
                <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-[11px] font-semibold text-slate-700">{{ vidPid(dongle) }}</span>
                <StatusBadge :status="dongle.confidence || 'unknown'" />
              </div>
            </div>
            <p class="mt-3 text-sm font-bold text-slate-800">{{ dongle.name || dongle.brand_guess || 'Unknown receiver' }}</p>
            <dl class="mt-3 space-y-2 text-xs">
              <div class="flex items-start justify-between gap-3">
                <dt class="shrink-0 text-slate-400">Brand guess</dt>
                <dd class="text-right font-medium text-slate-700">{{ dongle.brand_guess || 'Not identified' }}</dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="shrink-0 text-slate-400">Receiver family</dt>
                <dd class="text-right font-medium text-slate-700">{{ dongle.receiver_family || 'Unknown' }}</dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="shrink-0 text-slate-400">USB port / location</dt>
                <dd class="max-w-[65%] break-words text-right font-medium text-slate-700">{{ dongle.port || dongle.location || 'Not reported' }}</dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="shrink-0 text-slate-400">Registration</dt>
                <dd class="text-right font-medium" :class="dongle.dongle_id ? 'text-emerald-700' : 'text-slate-500'">
                  <template v-if="dongle.dongle_id">Registered dongle #{{ dongle.dongle_id }}</template>
                  <template v-else>Not registered</template>
                </dd>
              </div>
            </dl>
            <p v-if="dongle.note" class="mt-3 rounded-lg bg-slate-50 p-2.5 text-[11px] leading-relaxed text-slate-600">{{ dongle.note }}</p>

            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Matched mice ({{ asArray(dongle.matched_mice).length }})</p>
              <ul v-if="asArray(dongle.matched_mice).length" class="mt-2 space-y-2">
                <li v-for="match in dongle.matched_mice" :key="match.match_id" class="rounded-lg bg-white p-2 ring-1 ring-slate-200">
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs font-bold text-slate-800">{{ mouseName(match) }}</span>
                    <StatusBadge :status="match.status" />
                  </div>
                  <p v-if="match.mouse_code" class="mt-0.5 font-mono text-[10px] text-slate-400">{{ match.mouse_code }}</p>
                  <p v-if="match.notes" class="mt-1 text-[11px] leading-relaxed text-slate-600">{{ match.notes }}</p>
                  <p v-if="match.match_source === 'same_vid_pid_family'" class="mt-1 text-[10px] font-semibold text-amber-600">Same VID:PID family — not proven to be this physical dongle.</p>
                </li>
              </ul>
              <p v-else class="mt-1.5 text-[11px] text-slate-500">No mouse has been matched with this dongle yet.</p>
            </div>

            <div class="mt-3 border-t border-slate-100 pt-3">
              <button
                type="button"
                class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-700 transition hover:underline"
                :aria-expanded="Boolean(labelUi[dongle.key])"
                @click="labelUi[dongle.key] = !labelUi[dongle.key]"
              >
                <PlusIcon :size="12" /> {{ dongle.brand_source === 'manual' ? 'Edit brand label' : 'Set brand / label manually' }}
              </button>
              <p v-if="dongle.brand_source === 'manual'" class="mt-1 text-[10px] font-semibold text-emerald-700">Brand label provided by you — not detected from VID/PID.</p>
              <form v-if="labelUi[dongle.key]" class="mt-2 space-y-2" @submit.prevent="labelDongle(dongle)">
                <div class="grid grid-cols-2 gap-2">
                  <input v-model="labelForm[dongle.key].brand" class="input" placeholder="Brand, e.g. Mikuso" :aria-label="`Brand for dongle ${dongle.key}`" />
                  <input v-model="labelForm[dongle.key].model" class="input" placeholder="Model (optional)" :aria-label="`Model for dongle ${dongle.key}`" />
                </div>
                <AppButton type="submit" size="xs" variant="secondary" :icon="SaveIcon" :loading="labelingKey === dongle.key">Save label</AppButton>
              </form>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- Auto-discover results -->
    <section v-if="autoResult || autoDiscovering" class="card mb-6 overflow-hidden">
      <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><RadarIcon :size="18" /></div>
          <div>
            <h2 class="text-sm font-bold text-slate-900">Auto-discovered pairs</h2>
            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-600">
              {{ autoResult?.message || 'Reading connected mice and identifying them…' }}
            </p>
            <p v-if="autoResult?.scanned_at" class="mt-1 text-[11px] text-slate-400">Scanned {{ formatDateTime(autoResult.scanned_at) }}</p>
          </div>
        </div>
        <AppButton variant="secondary" :icon="RefreshCwIcon" :loading="autoDiscovering" @click="runAutoDiscover()">Refresh</AppButton>
      </div>

      <div v-if="autoDiscovering" class="p-5">
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50/60 p-4">
          <Loader2Icon :size="18" class="mt-0.5 shrink-0 animate-spin text-brand-600" />
          <div>
            <p class="text-sm font-bold text-brand-800">Reading connected mice and identifying them…</p>
            <p class="mt-0.5 text-xs text-brand-700">Checking the real HID connection and registering what is found. This may take about 10 seconds.</p>
          </div>
        </div>
        <LoadingState variant="cards" :rows="3" label="Identifying devices" />
      </div>

      <div v-else-if="autoResult?.error" class="p-2">
        <EmptyState
          :icon="TriangleAlertIcon"
          title="Auto-discover could not complete"
          :description="String(autoResult.error)"
        >
          <template #action>
            <AppButton :icon="RadarIcon" @click="runAutoDiscover()">Try again</AppButton>
          </template>
        </EmptyState>
      </div>

      <div v-else-if="!discoveredItems.length && !unattributedDevices.length" class="p-2">
        <EmptyState
          :icon="RadarIcon"
          title="Nothing was discovered"
          description="Windows did not report a connected mouse/dongle pair. Plug in the dongles, switch ON one mouse, then run Auto-discover again. Nothing was created or guessed."
        >
          <template #action>
            <AppButton :icon="RadarIcon" @click="runAutoDiscover()">Run again</AppButton>
          </template>
        </EmptyState>
      </div>

      <div v-else class="space-y-4 p-5">
        <article v-for="(discovered, index) in discoveredItems" :key="autoKey(discovered, index)" class="rounded-2xl border border-slate-200 bg-white p-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white"><UsbIcon :size="18" /></div>
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <p class="text-sm font-bold text-slate-800">Dongle <span class="font-mono">{{ vidPid(discovered.dongle) }}</span></p>
                  <StatusBadge v-if="discovered.dongle?.auto_created" status="true" label="Auto-created" />
                </div>
                <p class="mt-0.5 text-[11px] text-slate-500">{{ discovered.dongle?.port || 'USB port not reported' }}</p>
                <p v-if="discovered.dongle?.name" class="mt-0.5 text-[11px] text-slate-500">{{ discovered.dongle.name }}</p>
              </div>
            </div>
            <button
              type="button"
              class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1.5 text-[11px] font-semibold text-brand-700 transition hover:bg-brand-50"
              :aria-expanded="Boolean(autoUi[autoKey(discovered, index)]?.evidenceOpen)"
              @click="toggleAutoField(autoKey(discovered, index), 'evidenceOpen')"
            >
              Evidence ({{ safeJson(discovered.evidence).length }})
              <ChevronDownIcon :size="14" :class="autoUi[autoKey(discovered, index)]?.evidenceOpen ? 'rotate-180' : ''" class="transition-transform" />
            </button>
          </div>

          <div v-if="autoUi[autoKey(discovered, index)]?.evidenceOpen" class="mt-3 space-y-2 rounded-lg bg-slate-50 p-3">
            <div v-for="(item, itemIndex) in safeJson(discovered.evidence)" :key="itemIndex" class="rounded-lg border border-slate-100 bg-white p-2.5">
              <p class="text-[11px] font-bold text-brand-700">{{ item.type || 'Evidence' }}</p>
              <p class="mt-0.5 text-xs text-slate-700">{{ item.detail || 'No detail reported.' }}</p>
              <p v-if="item.source" class="mt-1 text-[11px] text-slate-400">Source: {{ item.source }}</p>
            </div>
            <p v-if="!safeJson(discovered.evidence).length" class="text-xs text-slate-400">No evidence was reported for this device.</p>
          </div>

          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Connected mouse device</p>
              <p class="mt-1 text-xs font-semibold text-slate-800">{{ discovered.device?.name || 'Unnamed device' }}</p>
              <p class="mt-0.5 font-mono text-[10px] text-slate-400">{{ vidPid(discovered.device) }} · {{ discovered.device?.instance_id || 'instance unknown' }}</p>
              <p v-if="discovered.device?.location" class="mt-1 text-[11px] text-slate-500">Location: {{ discovered.device.location }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Identified as</p>
              <div class="mt-1 flex flex-wrap items-center gap-2">
                <p class="text-xs font-semibold text-slate-800">{{ deviceName(discovered.identity, 'Not identified') }}</p>
                <StatusBadge :status="discovered.identity?.confidence || 'unknown'" />
              </div>
              <p v-if="discovered.identity?.source" class="mt-1 text-[11px] text-slate-500">Source: {{ discovered.identity.source }}</p>
            </div>
          </div>

          <div v-if="discovered.mouse" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Registered mouse</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                  <p class="text-sm font-bold text-slate-800">{{ deviceName(discovered.mouse, 'Mouse') }}</p>
                  <span class="font-mono text-[11px] text-slate-500">{{ discovered.mouse.mouse_id }}</span>
                  <StatusBadge v-if="discovered.mouse.auto_created" status="true" label="Auto-created" />
                </div>
              </div>
              <router-link v-if="discovered.mouse.id" :to="`/mice/${discovered.mouse.id}`" class="inline-flex shrink-0 items-center gap-1 text-[11px] font-bold text-brand-700 transition hover:underline">
                Open mouse <ExternalLinkIcon :size="12" />
              </router-link>
            </div>
            <div v-if="discovered.match" class="mt-3 flex flex-wrap items-center gap-2 border-t border-emerald-200 pt-3">
              <span class="text-[11px] font-semibold text-emerald-800">Recorded match:</span>
              <StatusBadge :status="discovered.match.status || 'unknown'" />
              <router-link v-if="discovered.match.id" :to="`/matches?highlight=${discovered.match.id}`" class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-700 transition hover:underline">
                View match <ExternalLinkIcon :size="12" />
              </router-link>
              <p v-if="discovered.match.notes" class="w-full text-[11px] leading-relaxed text-slate-600">{{ discovered.match.notes }}</p>
            </div>
          </div>

          <div v-if="discovered.needs_user_review" class="mt-4 flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <TriangleAlertIcon :size="18" class="mt-0.5 shrink-0 text-amber-600" />
            <div class="min-w-0">
              <p class="text-xs font-bold leading-relaxed text-amber-900">Needs verification — Windows did not provide enough evidence; register or confirm manually.</p>
              <div class="mt-3 flex flex-wrap items-center gap-2">
                <AppButton size="sm" variant="secondary" :icon="RadarIcon" @click="openMatchingCenter">Open Matching Center</AppButton>
                <AppButton v-if="!discovered.mouse" size="sm" variant="secondary" :icon="PlusIcon" @click="toggleAutoField(autoKey(discovered, index), 'registerOpen')">
                  Register mouse
                </AppButton>
              </div>
              <form
                v-if="!discovered.mouse && autoUi[autoKey(discovered, index)]?.registerOpen"
                class="mt-3 space-y-3 rounded-lg border border-amber-200 bg-white p-3"
                @submit.prevent="registerDiscoveredMouse(discovered, autoKey(discovered, index))"
              >
                <p class="text-[11px] font-semibold text-slate-600">Add the brand and model you can see on the mouse. You can edit the details later.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                  <Field :id="`auto-brand-${index}`" label="Brand" required>
                    <input :id="`auto-brand-${index}`" v-model="autoUi[autoKey(discovered, index)].brand" type="text" class="input" placeholder="e.g. Logitech" required />
                  </Field>
                  <Field :id="`auto-model-${index}`" label="Model" required>
                    <input :id="`auto-model-${index}`" v-model="autoUi[autoKey(discovered, index)].model" type="text" class="input" placeholder="e.g. M185" required />
                  </Field>
                </div>
                <AppButton type="submit" size="sm" :icon="PlusIcon" :loading="autoUi[autoKey(discovered, index)]?.registering">Register mouse</AppButton>
              </form>
            </div>
          </div>

          <div v-if="!discovered.match && discovered.mouse && discovered.dongle?.dongle_id" class="mt-4 border-t border-slate-100 pt-4">
            <div v-if="autoUi[autoKey(discovered, index)]?.existingMatch" class="mb-3 flex flex-col gap-2 rounded-xl border border-rose-200 bg-rose-50 p-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <p class="text-xs font-bold text-rose-800">An active match already exists</p>
                <p class="mt-0.5 text-[11px] leading-relaxed text-rose-700">
                  {{ deviceName(autoUi[autoKey(discovered, index)].existingMatch.mouse, 'A mouse') }} is already linked to {{ deviceName(autoUi[autoKey(discovered, index)].existingMatch.dongle, 'a dongle') }} ({{ autoUi[autoKey(discovered, index)].existingMatch.status }}).
                </p>
              </div>
              <div class="flex shrink-0 gap-2">
                <AppButton size="xs" variant="ghost" @click="dismissAutoConflict(autoKey(discovered, index))">Dismiss</AppButton>
                <AppButton size="xs" variant="secondary" @click="viewExistingMatch(autoUi[autoKey(discovered, index)].existingMatch)">View match</AppButton>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <AppButton size="sm" variant="secondary" :icon="SaveIcon" :loading="autoUi[autoKey(discovered, index)]?.saving" @click="saveDiscoveredMatch(discovered, autoKey(discovered, index))">
                Save as suspected
              </AppButton>
              <span class="text-[11px] text-slate-400">Records an unverified relationship — a physical test is still required.</span>
            </div>
          </div>
          <div v-else-if="!discovered.match && discovered.mouse && !discovered.dongle?.dongle_id" class="mt-4 border-t border-slate-100 pt-4 text-[11px] text-amber-700">
            This receiver is not registered yet, so no match can be saved. Register the dongle first.
          </div>
        </article>

        <div v-if="unattributedDevices.length" class="rounded-2xl border border-slate-200 p-4">
          <button
            type="button"
            class="flex w-full items-center gap-2 text-left"
            :aria-expanded="autoUnattributedOpen"
            @click="autoUnattributedOpen = !autoUnattributedOpen"
          >
            <ChevronDownIcon :size="16" :class="autoUnattributedOpen ? 'rotate-180' : ''" class="shrink-0 text-slate-400 transition-transform" />
            <span class="text-xs font-bold text-slate-700">Devices not tied to a dongle ({{ unattributedDevices.length }})</span>
          </button>
          <p class="mt-1.5 text-[11px] leading-relaxed text-slate-500">Windows reported these devices, but did not tie them to a dongle. They cannot be attributed to a pair — nothing was inferred from them.</p>
          <div v-if="autoUnattributedOpen" class="mt-3 space-y-2">
            <LiveDeviceRow v-for="(device, index) in unattributedDevices" :key="device.instance_id || index" :device="device" />
          </div>
        </div>
      </div>
    </section>

    <!-- Step 2: observe one mouse at a time -->
    <section v-if="session && dongles.length" class="card mb-6 overflow-hidden">
      <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><PowerIcon :size="18" /></div>
          <div>
            <h2 class="text-sm font-bold text-slate-900">Step 2 · Nyalakan satu mouse</h2>
            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500">
              Leave all other mice OFF. The observation lists only the new HID/mouse device that appeared and the dongle(s) that could own it — nothing is guessed or saved automatically.
            </p>
          </div>
        </div>
        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
          Observation #{{ latestObservation?.observation_count ?? (observations.length + (latestObservation ? 0 : 1)) }}
        </span>
      </div>

      <div class="flex items-start gap-2 border-b border-amber-100 bg-amber-50/60 px-5 py-3">
        <TriangleAlertIcon :size="16" class="mt-0.5 shrink-0 text-amber-600" />
        <p class="text-xs font-bold text-amber-800">Turn ON exactly ONE mouse now, then click Observe selected dongle.</p>
      </div>

      <div class="p-5">
        <div class="mb-4 flex items-start gap-2 rounded-xl border border-brand-200 bg-brand-50/60 p-3">
          <InfoIcon :size="16" class="mt-0.5 shrink-0 text-brand-600" />
          <p class="text-xs leading-relaxed text-brand-800">
            <span class="font-bold">For the strongest result, unplug every other dongle</span> so only the dongle you select below is connected. If you leave several plugged in, Windows cannot tell identical dongles apart — you must choose by USB port.
          </p>
        </div>

        <div class="mb-5 grid gap-3 sm:grid-cols-3">
          <Field id="lab-observe-dongle" label="Dongle being tested" hint="Required — pick the label you want to test." required>
            <select id="lab-observe-dongle" v-model="observeDongleKey" class="input" required>
              <option :value="null">— Choose a dongle —</option>
              <option v-for="dongle in dongles" :key="dongle.key || vidPid(dongle)" :value="dongle.key">
                {{ dongle.key || '?' }} · {{ vidPid(dongle) }} · {{ dongle.port || dongle.location || 'port unknown' }}
              </option>
            </select>
          </Field>
          <Field id="lab-observe-mouse" label="Mouse being tested (optional)" hint="Only if it is already registered.">
            <select id="lab-observe-mouse" v-model="observeMouseId" class="input">
              <option :value="null">— Not specified —</option>
              <option v-for="mouse in mice" :key="mouse.id" :value="mouse.id">{{ deviceName(mouse, 'Unnamed mouse') }} · {{ mouse.mouse_id }}</option>
            </select>
          </Field>
          <Field id="lab-observe-notes" label="Observation notes (optional)">
            <input id="lab-observe-notes" v-model="observeNotes" type="text" class="input" placeholder="e.g. switched on the black mouse" />
          </Field>
        </div>

        <div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
          <AppButton
            :icon="RadarIcon"
            :loading="Boolean(observingKey)"
            :disabled="!observeDongleKey || Boolean(observingKey)"
            @click="observe(observeDongleKey)"
          >Observe selected dongle</AppButton>
          <span v-if="!observeDongleKey" class="text-[11px] text-amber-700">Choose the dongle you are testing first.</span>
          <span v-else class="text-[11px] text-slate-500">Testing dongle <span class="font-bold">{{ observeDongleKey }}</span> · {{ donglePort(observeDongleKey) }}</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <div v-for="dongle in dongles" :key="dongle.key || vidPid(dongle)" class="rounded-xl border border-slate-200 p-3">
            <div class="flex items-center gap-2">
              <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-xs font-black text-white">{{ dongle.key || '?' }}</span>
              <span class="font-mono text-[11px] font-semibold text-slate-600">{{ vidPid(dongle) }}</span>
              <span class="ml-auto truncate text-[11px] text-slate-400" :title="dongle.location">{{ dongle.port || dongle.location || 'Port unknown' }}</span>
            </div>
            <AppButton
              class="mt-3"
              size="sm"
              block
              :icon="RadarIcon"
              :loading="observingKey === dongle.key"
              :disabled="Boolean(observingKey) || !dongle.key"
              @click="observeDongleKey = dongle.key; observe(dongle.key)"
            >Test this dongle</AppButton>
          </div>
        </div>

        <div v-if="observingKey" class="mt-5 flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50/60 p-4">
          <Loader2Icon :size="18" class="mt-0.5 shrink-0 animate-spin text-brand-600" />
          <div>
            <p class="text-sm font-bold text-brand-800">Reading HID / mouse devices…</p>
            <p class="mt-0.5 text-xs text-brand-700">Checking which mouse device appeared after you switched the mouse on.</p>
          </div>
        </div>

        <template v-else-if="latestObservation">
          <div v-if="latestObservation.message" class="mt-5 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-xs leading-relaxed text-slate-600">
            <InfoIcon :size="15" class="mt-0.5 shrink-0 text-slate-400" />
            <span>{{ latestObservation.message }}</span>
          </div>

          <div class="mt-5">
            <div class="mb-2 flex items-center justify-between">
              <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Mouse devices connected now ({{ connectedDevices.length }})</h3>
              <span v-if="latestObservation.observed_at" class="text-[11px] text-slate-400">{{ formatDateTime(latestObservation.observed_at) }}</span>
            </div>
            <p class="mb-2 text-[11px] leading-relaxed text-slate-500">
              These are real devices Windows reports as connected right now. A <span class="font-bold text-emerald-700">NEW</span> badge means it appeared after the previous observation; devices without a badge were already connected when the session started.
            </p>
            <div v-if="!connectedDevices.length" class="rounded-xl border border-dashed border-slate-200">
              <EmptyState
                :icon="MouseIcon"
                title="No mouse device is connected"
                description="Switch the mouse ON, check the batteries, make sure it is paired with a dongle that is plugged in, then observe again. No device or match was invented."
              />
            </div>
            <div v-else class="space-y-2">
              <div v-for="(device, index) in connectedDevices" :key="device.instance_id || index" class="rounded-xl border border-slate-200 p-2">
                <div class="mb-1 flex items-center justify-between gap-2 px-1">
                  <span v-if="device.is_new" class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">NEW since last observation</span>
                  <span v-else class="text-[10px] text-slate-400">Already connected</span>
                  <span class="font-mono text-[10px] text-slate-400">{{ vidPid(device) }}</span>
                </div>
                <LiveDeviceRow :device="device" />
              </div>
            </div>
          </div>

          <div class="mt-6">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Dongle candidates ({{ candidates.length }})</h3>
            <p class="mt-1 text-[11px] leading-relaxed text-slate-500">Possible owners of the new device, not confirmed matches. Pick the dongle by the USB port you noted in Step 1.</p>
            <div v-if="ambiguousVidPids.size" class="mt-3 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3">
              <TriangleAlertIcon :size="16" class="mt-0.5 shrink-0 text-amber-600" />
              <p class="text-xs leading-relaxed text-amber-800">Some candidates share the same VID:PID. Identical dongles cannot be told apart electronically — you must choose by USB port.</p>
            </div>
            <div v-if="!candidateRows.length" class="mt-3 rounded-xl border border-dashed border-slate-200">
              <EmptyState :icon="UsbIcon" title="No candidates reported" description="Windows did not tie the connected mouse to any labelled dongle. Choose the dongle you are testing in Step 2 and observe again — for the strongest result, unplug the other dongles first." />
            </div>
            <div v-else class="mt-3 space-y-3">
              <div v-for="candidate in candidateRows" :key="candidate._key" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div class="flex items-start gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">{{ candidate.dongle_key || '?' }}</div>
                    <div>
                      <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-bold text-slate-800">Dongle {{ candidate.dongle_key || '?' }}</p>
                        <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-[11px] font-semibold text-slate-600">{{ vidPid(candidate) }}</span>
                        <StatusBadge :status="candidate.confidence || 'unknown'" />
                      </div>
                      <p class="mt-1 text-[11px] text-slate-500">USB port: {{ donglePort(candidate.dongle_key) }}</p>
                      <p v-if="candidate.dongle_id" class="mt-0.5 text-[11px] text-emerald-700">Registered dongle #{{ candidate.dongle_id }}</p>
                      <p v-else class="mt-0.5 text-[11px] text-amber-700">Not registered</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1.5 text-[11px] font-semibold text-brand-700 transition hover:bg-brand-50"
                    :aria-expanded="Boolean(evidenceOpen[candidate._key])"
                    @click="evidenceOpen[candidate._key] = !evidenceOpen[candidate._key]"
                  >
                    Evidence ({{ evidenceFor(candidate).length }})
                    <ChevronDownIcon :size="14" :class="evidenceOpen[candidate._key] ? 'rotate-180' : ''" class="transition-transform" />
                  </button>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-slate-600">{{ candidate.reason || 'No reason was reported for this candidate.' }}</p>
                <div v-if="evidenceOpen[candidate._key]" class="mt-3 space-y-2 rounded-lg bg-slate-50 p-3">
                  <div v-for="(item, itemIndex) in evidenceFor(candidate)" :key="itemIndex" class="rounded-lg border border-slate-100 bg-white p-2.5">
                    <p class="text-[11px] font-bold text-brand-700">{{ item.type || 'Evidence' }}</p>
                    <p class="mt-0.5 text-xs text-slate-700">{{ item.detail || 'No detail reported.' }}</p>
                    <p v-if="item.source" class="mt-1 text-[11px] text-slate-400">Source: {{ item.source }}</p>
                  </div>
                  <p v-if="!evidenceFor(candidate).length" class="text-xs text-slate-400">No evidence was reported for this candidate.</p>
                </div>
                <div v-if="isAmbiguous(candidate)" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-800">
                  Shares VID:PID with another candidate — choose this dongle only if its USB port matches the mouse you switched on.
                </div>
              </div>
            </div>
          </div>
        </template>

        <div v-else class="mt-5">
          <EmptyState :icon="RadarIcon" title="No observation yet" description="Switch ON exactly one mouse, then click Observe this dongle on the dongle you want to test." />
        </div>
      </div>
    </section>

    <!-- Step 3: save the result -->
    <section v-if="candidateRows.length" class="card mb-6 overflow-hidden">
      <div class="border-b border-slate-100 p-5">
        <div class="flex items-start gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><SaveIcon :size="18" /></div>
          <div>
            <h2 class="text-sm font-bold text-slate-900">Step 3 · Simpan hasil</h2>
            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500">
              For each candidate, choose the mouse you switched on, pick the specific dongle by USB port, then save. A saved result is never created without your action.
            </p>
          </div>
        </div>
      </div>

      <div class="space-y-4 p-5">
        <article v-for="candidate in candidateRows" :key="candidate._key" class="rounded-2xl border border-slate-200 p-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-2.5">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-sm font-black text-white">{{ candidate.dongle_key || '?' }}</div>
              <div>
                <p class="text-sm font-bold text-slate-800">Dongle {{ candidate.dongle_key || '?' }} · <span class="font-mono">{{ vidPid(candidate) }}</span></p>
                <p class="mt-0.5 text-[11px] text-slate-500">USB port: {{ donglePort(candidate.dongle_key) }}</p>
              </div>
            </div>
            <StatusBadge :status="candidateUi[candidate._key]?.status === 'matched' ? 'matched' : 'suspected'" :label="candidateUi[candidate._key]?.status === 'matched' ? 'Will confirm' : 'Will save as suspected'" />
          </div>

          <div v-if="!candidate.dongle_id" class="mt-4 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-xs font-bold text-amber-800">This receiver is not registered</p>
              <p class="mt-0.5 text-[11px] text-amber-700">VID {{ candidate.vid || '----' }} and PID {{ candidate.pid || '----' }} will be saved. Brand/model can be edited later.</p>
            </div>
            <AppButton
              size="sm"
              variant="secondary"
              :icon="PlusIcon"
              :loading="candidateUi[candidate._key]?.registering"
              @click="registerReceiver(candidate, candidate._key)"
            >Register this receiver</AppButton>
          </div>

          <div class="mt-4 space-y-4">
            <Field :id="`lab-mouse-${candidate._key}`" label="Registered mouse" hint="Choose the mouse you just switched on.">
              <select :id="`lab-mouse-${candidate._key}`" v-model="candidateUi[candidate._key].mouseId" class="input">
                <option :value="null">— Choose a mouse —</option>
                <option v-for="mouse in mice" :key="mouse.id" :value="mouse.id">{{ deviceName(mouse, 'Unnamed mouse') }} · {{ mouse.mouse_id }}</option>
              </select>
            </Field>

            <div v-if="!candidateUi[candidate._key].quickAddOpen">
              <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 transition hover:underline" @click="candidateUi[candidate._key].quickAddOpen = true">
                <PlusIcon :size="13" /> Or quickly add a new mouse
              </button>
            </div>
            <form v-else class="rounded-xl border border-slate-200 bg-slate-50/60 p-3" @submit.prevent="quickAddMouse(candidate._key)">
              <p class="mb-3 text-[11px] font-semibold text-slate-600">Add brand and model only — you can edit the details later.</p>
              <div class="grid gap-3 sm:grid-cols-2">
                <Field :id="`lab-brand-${candidate._key}`" label="Brand" required>
                  <input :id="`lab-brand-${candidate._key}`" v-model="candidateUi[candidate._key].quickBrand" type="text" class="input" placeholder="e.g. Logitech" required />
                </Field>
                <Field :id="`lab-model-${candidate._key}`" label="Model" required>
                  <input :id="`lab-model-${candidate._key}`" v-model="candidateUi[candidate._key].quickModel" type="text" class="input" placeholder="e.g. M185" required />
                </Field>
              </div>
              <div class="mt-3 flex flex-wrap items-center gap-2">
                <AppButton type="submit" size="sm" :icon="PlusIcon" :loading="candidateUi[candidate._key].addingMouse">Add mouse</AppButton>
                <AppButton type="button" size="sm" variant="ghost" @click="candidateUi[candidate._key].quickAddOpen = false">Cancel</AppButton>
              </div>
            </form>

            <div>
              <p class="label">Result</p>
              <div class="flex flex-wrap gap-2">
                <label
                  class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold transition"
                  :class="candidateUi[candidate._key].status === 'suspected' ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                >
                  <input v-model="candidateUi[candidate._key].status" type="radio" value="suspected" class="accent-amber-600" />
                  Suspected
                </label>
                <label
                  class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold transition"
                  :class="candidateUi[candidate._key].status === 'matched' ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                >
                  <input v-model="candidateUi[candidate._key].status" type="radio" value="matched" class="accent-emerald-600" />
                  Confirmed
                </label>
              </div>
              <p class="mt-1.5 text-[11px] leading-relaxed text-slate-400">Suspected = not physically verified. Confirmed = you tested this mouse with this dongle and it worked.</p>
            </div>

            <Field :id="`lab-notes-${candidate._key}`" label="Notes" hint="Prefilled from the candidate reason — edit before saving.">
              <textarea :id="`lab-notes-${candidate._key}`" v-model="candidateUi[candidate._key].notes" rows="2" class="input resize-y" placeholder="Why this dongle was chosen"></textarea>
            </Field>

            <div v-if="candidateUi[candidate._key].existingMatch" class="flex flex-col gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <p class="text-xs font-bold text-rose-800">An active match already exists</p>
                <p class="mt-0.5 text-[11px] leading-relaxed text-rose-700">
                  {{ deviceName(candidateUi[candidate._key].existingMatch.mouse, 'A mouse') }} is already linked to {{ deviceName(candidateUi[candidate._key].existingMatch.dongle, 'a dongle') }} ({{ candidateUi[candidate._key].existingMatch.status }}).
                </p>
              </div>
              <div class="flex shrink-0 gap-2">
                <AppButton size="xs" variant="ghost" @click="candidateUi[candidate._key].existingMatch = null">Dismiss</AppButton>
                <AppButton size="xs" variant="secondary" @click="viewExistingMatch(candidateUi[candidate._key].existingMatch)">View match</AppButton>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
              <AppButton
                :icon="SaveIcon"
                :loading="candidateUi[candidate._key].saving"
                :disabled="!candidateUi[candidate._key].mouseId || !candidate.dongle_id"
                @click="saveMatch(candidate, candidate._key)"
              >Save match</AppButton>
              <span v-if="!candidate.dongle_id" class="text-[11px] text-amber-700">Register this receiver before saving a match.</span>
              <span v-else-if="!candidateUi[candidate._key].mouseId" class="text-[11px] text-slate-400">Choose or add a mouse first.</span>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- Session controls -->
    <section v-if="session" class="card overflow-hidden">
      <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-sm font-bold text-slate-900">Session controls</h2>
          <p class="mt-0.5 text-xs text-slate-500">
            {{ session.name || 'Dongle Lab session' }} · {{ titleCase(session.status || 'active') }} · started {{ formatDateTime(session.started_at) }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <AppButton
            size="sm"
            variant="secondary"
            :icon="CheckCircleIcon"
            :loading="finishing"
            :disabled="session.status === 'completed'"
            @click="finishSession"
          >Finish session</AppButton>
          <AppButton size="sm" variant="danger-soft" :icon="Trash2Icon" :loading="deleting" @click="deleteSession">Delete session</AppButton>
        </div>
      </div>

      <div>
        <button
          type="button"
          class="flex w-full items-center gap-2 px-5 py-4 text-left transition hover:bg-slate-50"
          :aria-expanded="historyOpen"
          @click="historyOpen = !historyOpen"
        >
          <ChevronDownIcon :size="16" :class="historyOpen ? 'rotate-180' : ''" class="shrink-0 text-slate-400 transition-transform" />
          <span class="text-xs font-bold text-slate-700">Observation history ({{ observations.length }})</span>
        </button>
        <div v-if="historyOpen" class="border-t border-slate-100">
          <div v-if="!observations.length" class="px-5 py-4 text-xs text-slate-400">No observations recorded yet.</div>
          <div v-else class="divide-y divide-slate-100">
            <div v-for="(observation, index) in observations" :key="observation.observed_at || index" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-xs">
              <span class="font-semibold text-slate-700">{{ formatDateTime(observation.observed_at) }}</span>
              <span class="text-slate-500">Mouse: {{ observation.mouse_id ? `#${observation.mouse_id}` : 'Not specified' }}</span>
              <span class="text-slate-500">{{ newDeviceCount(observation) }} new device(s)</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import {
  CheckIcon,
  CheckCircleIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  ExternalLinkIcon,
  FlaskConicalIcon,
  InfoIcon,
  Loader2Icon,
  MouseIcon,
  PlusIcon,
  PowerIcon,
  RadarIcon,
  RefreshCwIcon,
  SaveIcon,
  ScanLineIcon,
  Trash2Icon,
  TriangleAlertIcon,
  UsbIcon,
} from 'lucide-vue-next'
import PageHeader from '../components/PageHeader.vue'
import AppButton from '../components/AppButton.vue'
import Field from '../components/Field.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import LoadingState from '../components/LoadingState.vue'
import LiveDeviceRow from '../components/LiveDeviceRow.vue'
import { deviceName, formatDateTime, safeJson, titleCase } from '../composables/useFormat'
import { useConfirm } from '../composables/useConfirm'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const session = ref(null)
const dongles = ref([])
const mice = ref([])
const scanning = ref(false)
const refreshingSession = ref(false)
const finishing = ref(false)
const deleting = ref(false)
const observingKey = ref('')
const observeDongleKey = ref(null)
const observeMouseId = ref(null)
const observeNotes = ref('')
const latestObservation = ref(null)
const observations = ref([])
const historyOpen = ref(false)
const evidenceOpen = reactive({})
const candidateUi = reactive({})
const labelUi = reactive({})
const labelForm = reactive({})
const labelingKey = ref('')
const autoDiscovering = ref(false)
const autoResult = ref(null)
const autoUnattributedOpen = ref(false)
const autoUi = reactive({})

const steps = [
  { label: 'Colok dongle' },
  { label: 'Nyalakan mouse' },
  { label: 'Simpan hasil' },
  { label: 'Selesaikan sesi' },
]

const activeStep = computed(() => {
  if (!session.value) return 1
  if (session.value.status === 'completed') return 4
  if (candidateRows.value.length) return 3
  if (dongles.value.length) return 2
  return 1
})

const discoveredItems = computed(() => asArray(autoResult.value?.discovered))
const unattributedDevices = computed(() => asArray(autoResult.value?.unattributed_devices))

const newDevices = computed(() => asArray(latestObservation.value?.new_devices))
const connectedDevices = computed(() => asArray(latestObservation.value?.connected_devices))
const candidates = computed(() => asArray(
  latestObservation.value?.flat_candidates ?? latestObservation.value?.candidates,
))

const candidateRows = computed(() => candidates.value.map((candidate, index) => {
  const key = candidateKey(candidate, index)
  return { ...candidate, _key: key, _ui: candidateUi[key] }
}))

const ambiguousVidPids = computed(() => {
  const counts = new Map()
  for (const candidate of candidates.value) {
    const id = vidPid(candidate)
    counts.set(id, (counts.get(id) || 0) + 1)
  }
  return new Set([...counts.entries()].filter(([, count]) => count > 1).map(([id]) => id))
})

function asArray(value) {
  return Array.isArray(value) ? value : []
}

function emptyCandidateUi(candidate) {
  return {
    mouseId: candidate?.mouse_id || null,
    status: 'suspected',
    notes: candidate?.reason || '',
    quickAddOpen: false,
    quickBrand: '',
    quickModel: '',
    existingMatch: null,
    saving: false,
    registering: false,
    addingMouse: false,
  }
}

function vidPid(device) {
  return `${device?.vid || '----'}:${device?.pid || '----'}`
}

function autoKey(discovered, index) {
  return `${vidPid(discovered?.dongle)}-${discovered?.device?.instance_id || index}-${index}`
}

function evidenceNotes(evidence) {
  const items = safeJson(evidence)
  if (!items.length) return 'Auto-discovered from the live HID connection; no evidence detail was reported.'
  return items.map((item) => [
    item?.type ? `[${item.type}]` : '',
    item?.detail || '',
    item?.source ? `(${item.source})` : '',
  ].filter(Boolean).join(' ')).join('; ')
}

function initializeAutoUi() {
  for (const key of Object.keys(autoUi)) delete autoUi[key]
  discoveredItems.value.forEach((discovered, index) => {
    autoUi[autoKey(discovered, index)] = {
      evidenceOpen: false,
      registerOpen: false,
      registering: false,
      saving: false,
      existingMatch: null,
      brand: discovered?.identity?.brand || '',
      model: discovered?.identity?.model || '',
    }
  })
}

function openMatchingCenter() {
  router.push('/matching')
}

function toggleAutoField(key, field) {
  if (autoUi[key]) autoUi[key][field] = !autoUi[key][field]
}

function dismissAutoConflict(key) {
  if (autoUi[key]) autoUi[key].existingMatch = null
}

function mouseName(match) {
  const name = deviceName({ brand: match?.brand, model: match?.model }, '')
  return name && name !== 'Unnamed device' ? name : (match?.mouse_code || 'Unknown mouse')
}

function candidateKey(candidate, index) {
  return `${candidate?.dongle_key || 'key'}-${vidPid(candidate)}-${index}`
}

function donglePort(dongleKey) {
  const dongle = dongles.value.find((item) => item.key === dongleKey)
  if (!dongle) return 'Not reported'
  return [dongle.port, dongle.location].filter(Boolean).join(' · ') || 'Not reported'
}

function evidenceFor(candidate) {
  return safeJson(candidate?.evidence)
}

function isAmbiguous(candidate) {
  return ambiguousVidPids.value.has(vidPid(candidate))
}

function newDeviceCount(observation) {
  if (Array.isArray(observation?.new_devices)) return observation.new_devices.length
  return observation?.new_devices_count ?? 0
}

function normalizeSessionResponse(data) {
  const detail = data?.session || data || null
  session.value = detail
  if (Array.isArray(data?.dongles)) dongles.value = data.dongles
  else if (Array.isArray(detail?.dongles)) dongles.value = detail.dongles
  if (Array.isArray(data?.observations)) observations.value = data.observations
  else if (Array.isArray(detail?.observations)) observations.value = detail.observations
  if (!latestObservation.value && observations.value.length) {
    const last = observations.value[observations.value.length - 1]
    latestObservation.value = { ...last, observation_count: observations.value.length }
  }
}

watch(latestObservation, (observation) => {
  for (const key of Object.keys(evidenceOpen)) delete evidenceOpen[key]
  for (const key of Object.keys(candidateUi)) delete candidateUi[key]
  asArray(observation?.flat_candidates ?? observation?.candidates).forEach((candidate, index) => {
    const key = candidateKey(candidate, index)
    evidenceOpen[key] = false
    candidateUi[key] = emptyCandidateUi(candidate)
  })
})

watch(dongles, (items) => {
  if (!observeDongleKey.value && items.length) {
    observeDongleKey.value = items[0]?.key || null
  }
  for (const dongle of items) {
    if (!dongle?.key) continue
    if (!labelForm[dongle.key]) {
      labelForm[dongle.key] = { brand: dongle.brand_guess === 'Unknown' ? '' : (dongle.brand_guess || ''), model: '' }
    }
  }
}, { immediate: true })

async function loadMice() {
  try {
    const { data } = await axios.get('/mice', { params: { per_page: 100 } })
    mice.value = Array.isArray(data?.data) ? data.data : asArray(data)
  } catch {
    // handled by the axios interceptor
  }
}

async function loadLatestSession(notify = false) {
  refreshingSession.value = true
  try {
    const { data } = await axios.get('/matching-sessions', { params: { per_page: 10 } })
    const rows = Array.isArray(data?.data) ? data.data : asArray(data)
    const open = rows.filter((row) => !['completed', 'cancelled', 'deleted'].includes(String(row.status || '').toLowerCase()))
    const active = open.find((row) => String(row.status || '').toLowerCase() === 'observing')
      || open[0]
      || rows[0]
    if (!active) {
      session.value = null
      return
    }
    const { data: detailData } = await axios.get(`/matching-sessions/${active.id}`)
    normalizeSessionResponse(detailData)
    if (notify) toast.info('Session loaded', active.name || `Session #${active.id}`)
  } catch {
    // handled by the axios interceptor
  } finally {
    refreshingSession.value = false
  }
}

async function scanAll() {
  scanning.value = true
  try {
    const { data } = await axios.post('/matching-sessions', {})
    normalizeSessionResponse(data)
    latestObservation.value = null
    observations.value = []
    toast.success('Scan complete', data?.message || `${dongles.value.length} dongle(s) found.`)
  } catch (requestError) {
    const message = requestError.response?.data?.error || requestError.response?.data?.message
    if (message) toast.error('Scan failed', String(message))
  } finally {
    scanning.value = false
  }
}

async function runAutoDiscover(notify = true) {
  autoDiscovering.value = true
  try {
    const { data } = await axios.post('/auto-discover')
    autoResult.value = data && typeof data === 'object' ? data : null
    initializeAutoUi()
    if (data?.error) {
      toast.warning('Auto-discover incomplete', String(data.error))
    } else if (!discoveredItems.value.length && !unattributedDevices.value.length) {
      toast.info('Auto-discover finished', data?.message || 'No connected mouse or dongle was found.')
    } else if (notify) {
      toast.success('Auto-discover complete', data?.message || `${discoveredItems.value.length} pair(s) found.`)
    }
  } catch (requestError) {
    autoResult.value = null
    const message = requestError.response?.data?.error || requestError.response?.data?.message
    if (message) toast.error('Auto-discover failed', String(message))
  } finally {
    autoDiscovering.value = false
  }
}

async function registerDiscoveredMouse(discovered, key) {
  const ui = autoUi[key]
  if (!ui) return
  const brand = (ui.brand || '').trim()
  const model = (ui.model || '').trim()
  if (!brand || !model) {
    toast.warning('Brand and model required', 'Enter both a brand and a model before registering.')
    return
  }
  ui.registering = true
  try {
    const { data } = await axios.post('/mice', { brand, model })
    if (data?.id && !mice.value.some((mouse) => mouse.id === data.id)) mice.value.unshift(data)
    toast.success('Mouse registered', deviceName(data, 'Mouse'))
    await runAutoDiscover(false)
  } catch {
    // handled by the axios interceptor
  } finally {
    ui.registering = false
  }
}

async function saveDiscoveredMatch(discovered, key) {
  const ui = autoUi[key]
  if (!ui || !discovered?.mouse?.id || !discovered?.dongle?.dongle_id) return
  const accepted = await confirm({
    title: 'Save as suspected?',
    message: `Record a possible match between ${deviceName(discovered.mouse, 'this mouse')} and dongle #${discovered.dongle.dongle_id}? A physical test is still required.`,
    confirmText: 'Save suspected',
  })
  if (!accepted) return
  ui.saving = true
  ui.existingMatch = null
  try {
    await axios.post('/matches', {
      mouse_id: discovered.mouse.id,
      dongle_id: discovered.dongle.dongle_id,
      status: 'suspected',
      notes: evidenceNotes(discovered.evidence),
    })
    toast.success('Suspected match saved', `${deviceName(discovered.mouse, 'Mouse')} · dongle #${discovered.dongle.dongle_id}`)
    await runAutoDiscover(false)
  } catch (requestError) {
    if (requestError.response?.status === 422 && requestError.response.data?.existing_match) {
      ui.existingMatch = requestError.response.data.existing_match
      toast.warning('Duplicate active match', requestError.response.data.error || 'This device already has an active match.')
    }
  } finally {
    ui.saving = false
  }
}

async function refreshSession(notify = false) {
  if (!session.value?.id) {
    await loadLatestSession(notify)
    return
  }
  refreshingSession.value = true
  try {
    const { data } = await axios.get(`/matching-sessions/${session.value.id}`)
    normalizeSessionResponse(data)
    if (notify) toast.info('Session refreshed', session.value?.name || `Session #${session.value?.id}`)
  } catch {
    // handled by the axios interceptor
  } finally {
    refreshingSession.value = false
  }
}

async function observe(dongleKey) {
  if (!session.value?.id || !dongleKey) return
  observeDongleKey.value = dongleKey
  observingKey.value = dongleKey
  const payload = { dongle_key: dongleKey || observeDongleKey.value }
  if (observeMouseId.value) payload.mouse_id = observeMouseId.value
  if (observeNotes.value.trim()) payload.notes = observeNotes.value.trim()
  try {
    const { data } = await axios.post(`/matching-sessions/${session.value.id}/observe`, payload)
    latestObservation.value = data
    observations.value.push({
      observed_at: data?.observed_at,
      mouse_id: observeMouseId.value || data?.mouse_id || null,
      new_devices: asArray(data?.new_devices),
    })
    if (data?.message) toast.info('Observation complete', data.message)
  } catch (requestError) {
    const message = requestError.response?.data?.error || requestError.response?.data?.message
    if (message) toast.error('Observation failed', String(message))
  } finally {
    observingKey.value = ''
  }
}

async function quickAddMouse(key) {
  const ui = candidateUi[key]
  if (!ui) return
  const brand = (ui.quickBrand || '').trim()
  const model = (ui.quickModel || '').trim()
  if (!brand || !model) {
    toast.warning('Brand and model required', 'Enter both a brand and a model before adding.')
    return
  }
  ui.addingMouse = true
  try {
    const { data } = await axios.post('/mice', { brand, model })
    mice.value.unshift(data)
    ui.mouseId = data?.id ?? data?.mouse_id ?? null
    ui.quickAddOpen = false
    ui.quickBrand = ''
    ui.quickModel = ''
    toast.success('Mouse registered', deviceName(data, 'Mouse'))
  } catch {
    // handled by the axios interceptor
  } finally {
    ui.addingMouse = false
  }
}

async function labelDongle(dongle) {
  if (!session.value?.id || !dongle?.key) return
  const form = labelForm[dongle.key] || {}
  if (!(form.brand || '').trim() && !(form.model || '').trim()) {
    toast.warning('Nothing to save', 'Enter a brand and/or model for this dongle.')
    return
  }
  labelingKey.value = dongle.key
  try {
    const { data } = await axios.post(`/matching-sessions/${session.value.id}/label`, {
      dongle_key: dongle.key,
      brand: (form.brand || '').trim() || null,
      model: (form.model || '').trim() || null,
    })
    normalizeSessionResponse(data)
    labelUi[dongle.key] = false
    toast.success('Label saved', `Dongle ${dongle.key} · ${form.brand || form.model}`)
  } catch {
    // handled by interceptor
  } finally {
    labelingKey.value = ''
  }
}

async function registerReceiver(candidate, key) {
  const ui = candidateUi[key]
  if (!ui) return
  ui.registering = true
  try {
    const { data } = await axios.post('/dongles', {
      vid: candidate.vid || null,
      pid: candidate.pid || null,
      model: null,
      notes: 'Registered from Dongle Lab observation. Edit brand/model if known.',
    })
    const newId = data?.id ?? data?.dongle_id ?? null
    if (newId) {
      candidate.dongle_id = newId
      const dongle = dongles.value.find((item) => item.key === candidate.dongle_key)
      if (dongle) dongle.dongle_id = newId
    }
    toast.success('Receiver registered', newId ? `Dongle #${newId}` : 'VID/PID saved')
    await refreshSession()
    if (newId) {
      candidate.dongle_id = newId
      const dongle = dongles.value.find((item) => item.key === candidate.dongle_key)
      if (dongle) dongle.dongle_id = newId
    }
  } catch {
    // handled by the axios interceptor
  } finally {
    ui.registering = false
  }
}

async function saveMatch(candidate, key) {
  const ui = candidateUi[key]
  if (!ui || !ui.mouseId || !candidate.dongle_id) return
  ui.saving = true
  ui.existingMatch = null
  const status = ui.status === 'matched' ? 'matched' : 'suspected'
  try {
    await axios.post('/matches', {
      mouse_id: ui.mouseId,
      dongle_id: candidate.dongle_id,
      status,
      notes: ui.notes?.trim() || candidate.reason || null,
    })
    const mouse = mice.value.find((item) => item.id === ui.mouseId)
    toast.success(
      status === 'matched' ? 'Match confirmed' : 'Suspected match saved',
      `${deviceName(mouse, 'Mouse')} · Dongle #${candidate.dongle_id}`
    )
    await refreshSession()
  } catch (requestError) {
    if (requestError.response?.status === 422 && requestError.response.data?.existing_match) {
      ui.existingMatch = requestError.response.data.existing_match
      toast.warning('Duplicate active match', requestError.response.data.error || 'This device already has an active match.')
    }
  } finally {
    ui.saving = false
  }
}

async function finishSession() {
  if (!session.value?.id) return
  finishing.value = true
  try {
    const { data } = await axios.post(`/matching-sessions/${session.value.id}/complete`)
    if (data?.session) session.value = data.session
    else session.value = { ...session.value, status: 'completed' }
    toast.success('Session finished', 'The Dongle Lab session has been completed.')
    await refreshSession()
  } catch {
    // handled by the axios interceptor
  } finally {
    finishing.value = false
  }
}

async function deleteSession() {
  const accepted = await confirm({
    title: 'Delete this Dongle Lab session?',
    message: 'The session and its observations will be removed. Matches you already saved are not deleted.',
    confirmText: 'Delete session',
    variant: 'danger',
  })
  if (!accepted || !session.value?.id) return
  deleting.value = true
  try {
    await axios.delete(`/matching-sessions/${session.value.id}`)
    session.value = null
    dongles.value = []
    latestObservation.value = null
    observations.value = []
    toast.success('Session deleted')
  } catch {
    // handled by the axios interceptor
  } finally {
    deleting.value = false
  }
}

function viewExistingMatch(match) {
  if (match?.id) router.push(`/matches?highlight=${match.id}`)
}

onMounted(() => {
  loadMice()
  loadLatestSession()
})
</script>
