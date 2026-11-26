<template>
  <div>
    <PageHeader eyebrow="Tools" title="USB Detective" subtitle="Scan connected hardware, decode identifiers, research an unknown receiver and find official software.">
      <template #actions>
        <AppButton :icon="ScanLineIcon" :loading="scanning" @click="runScan(true)">Scan USB devices</AppButton>
        <AppButton variant="secondary" :icon="RefreshCwIcon" :loading="loadingScans" @click="loadScans">Refresh history</AppButton>
        <AppButton variant="secondary" :icon="ScanSearchIcon" :loading="quickScanning" @click="runScan(false)">Quick scan</AppButton>
      </template>
    </PageHeader>

    <!-- Scan summary -->
    <section class="card mb-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Last scan</p>
          <p class="mt-0.5 text-sm font-semibold text-slate-800">{{ formatDateTime(lastScan?.scanned_at || lastScan?.detected_at) }}</p>
        </div>
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Devices found</p>
          <p class="mt-0.5 text-sm font-semibold text-slate-800">{{ devices.length }}</p>
        </div>
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Scan history</p>
          <p class="mt-0.5 text-sm font-semibold text-slate-800">{{ scans.length }} saved</p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <AppButton size="sm" variant="secondary" :icon="RefreshCwIcon" :loading="lookupLoading" :disabled="!analyzer.vid" @click="lookupUsbIds">Decode VID/PID</AppButton>
        <AppButton size="sm" variant="ghost" :icon="DatabaseIcon" :loading="refreshingIds" @click="refreshUsbIds">Update ID database</AppButton>
      </div>
    </section>

    <div v-if="scanError" class="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
      <TriangleAlertIcon :size="18" class="mt-0.5 shrink-0 text-amber-600" />
      <div>
        <p class="text-sm font-semibold text-amber-800">Scan unavailable</p>
        <p class="mt-0.5 text-xs leading-relaxed text-amber-700">{{ scanError }}</p>
      </div>
    </div>

    <div v-if="linkedDongle" class="mb-6 flex flex-col gap-3 rounded-xl border border-brand-200 bg-brand-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600"><UsbIcon :size="18" /></div>
        <div>
          <p class="text-sm font-bold text-brand-800">Investigating registered dongle</p>
          <p class="text-xs text-brand-700">{{ deviceName(linkedDongle, 'Unknown receiver') }} · {{ linkedDongle.dongle_id }} · {{ linkedDongle.vid || '----' }}:{{ linkedDongle.pid || '----' }}</p>
        </div>
      </div>
      <AppButton size="sm" variant="secondary" @click="router.push(`/dongles/${linkedDongle.id}`)">View profile</AppButton>
    </div>

    <!-- Live Pairing -->
    <section class="card mb-6 overflow-hidden">
      <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><LinkIcon :size="18" /></div>
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-sm font-bold text-slate-900">Live Pairing</h2>
              <StatusBadge status="active" label="Live" />
            </div>
            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500">
              Plug in the dongle, switch the mouse on, then detect. Windows reports the devices actually connected through that dongle — nothing is invented or guessed.
            </p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <AppButton :icon="LinkIcon" :loading="liveScanning" @click="runLiveMatch">Detect connected mice</AppButton>
          <template v-if="liveResult">
            <span class="text-[11px] text-slate-400">Detected {{ formatDateTime(liveResult.scanned_at) }}</span>
            <AppButton size="sm" variant="secondary" :icon="RefreshCwIcon" :loading="liveScanning" @click="runLiveMatch">Refresh</AppButton>
          </template>
        </div>
      </div>

      <LoadingState v-if="liveScanning" :rows="3" label="Detecting devices connected through each receiver" />

      <template v-else-if="liveError">
        <div v-if="liveResult?.message" class="flex items-start gap-2 border-b border-slate-100 bg-slate-50/70 px-5 py-3 text-xs leading-relaxed text-slate-600">
          <InfoIcon :size="15" class="mt-0.5 shrink-0 text-slate-400" />
          <span>{{ liveResult.message }}</span>
        </div>
        <div class="mx-5 mt-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
          <TriangleAlertIcon :size="18" class="mt-0.5 shrink-0 text-amber-600" />
          <div>
            <p class="text-sm font-semibold text-amber-800">Detection unavailable</p>
            <p class="mt-0.5 text-xs leading-relaxed text-amber-700">{{ liveError }}</p>
          </div>
        </div>
        <EmptyState
          :icon="TriangleAlertIcon"
          title="Live detection unavailable"
          :description="`${liveError} Switch the mouse on, make sure it is paired, and try another USB port.`"
        />
      </template>

      <template v-else-if="liveResult">
        <div v-if="liveResult.message" class="flex items-start gap-2 border-b border-slate-100 bg-slate-50/70 px-5 py-3 text-xs leading-relaxed text-slate-600">
          <InfoIcon :size="15" class="mt-0.5 shrink-0 text-slate-400" />
          <span>{{ liveResult.message }}</span>
        </div>

        <div v-if="!liveHasResults" class="p-2">
          <EmptyState
            :icon="RadarIcon"
            title="No connected mouse detected"
            description="Windows did not report any mouse connected through a plugged-in dongle. Switch the mouse on, make sure it is paired with the dongle, and try another USB port."
          />
        </div>

        <div v-else class="space-y-4 p-5">
          <div v-for="dongle in liveDongles" :key="dongle._key" class="rounded-2xl border border-slate-200 bg-slate-50/40 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 ring-1 ring-slate-200"><UsbIcon :size="18" /></div>
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-lg bg-slate-800 px-2 py-1 font-mono text-[11px] font-semibold text-white">{{ dongle.vid || '----' }}:{{ dongle.pid || '----' }}</span>
                    <p class="text-sm font-bold text-slate-800">{{ dongle.name || 'Unknown receiver' }}</p>
                  </div>
                  <p v-if="dongle.dongle_id" class="mt-1 text-[11px] text-slate-500">Registered dongle #{{ dongle.dongle_id }}</p>
                  <form v-else class="mt-3 flex flex-wrap items-end gap-2" @submit.prevent="registerLiveDongle(dongle)">
                    <div>
                      <label class="label" :for="`live-dongle-${dongle._key}`">Receiver name (optional)</label>
                      <input :id="`live-dongle-${dongle._key}`" v-model="liveUi.registerDongle[dongle._key].model" class="input" placeholder="e.g. Logitech Unifying Receiver" />
                    </div>
                    <AppButton type="submit" size="sm" :icon="PlusIcon" :loading="liveRegisterBusy === dongle._key" :disabled="Boolean(liveRegisterBusy)">Register receiver</AppButton>
                    <p class="w-full text-[11px] text-slate-400">VID {{ dongle.vid || '----' }} and PID {{ dongle.pid || '----' }} are saved automatically. Brand/model can be edited later.</p>
                  </form>
                </div>
              </div>
              <span class="rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200">{{ dongle._connectedDevices.length }} device(s)</span>
            </div>

            <div class="mt-4">
              <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Devices connected through this receiver</p>
              <div v-if="!dongle._connectedDevices.length" class="rounded-xl border border-dashed border-slate-200 p-3 text-xs text-slate-400">No devices were reported through this receiver.</div>
              <div v-else class="space-y-2">
                <LiveDeviceRow v-for="(device, deviceIndex) in dongle._connectedDevices" :key="device.instance_id || deviceIndex" :device="device">
                  <template #badge>
                    <span v-if="isSuggestedDevice(dongle, deviceIndex)" class="rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-bold text-brand-700">Suggestion source</span>
                  </template>
                </LiveDeviceRow>
              </div>
            </div>

            <div class="mt-4 border-t border-slate-200 pt-4">
              <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Mouse suggestions</p>
              <p v-if="!dongle._suggestions.length" class="rounded-xl border border-dashed border-slate-200 p-3 text-xs text-slate-400">No registered mouse could be suggested from the devices reported through this receiver.</p>
              <div v-else class="space-y-3">
                <div v-for="suggestion in dongle._suggestions" :key="suggestion._key" class="rounded-xl border border-slate-200 bg-white p-4">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="suggestion.mouse_id ? 'bg-brand-50 text-brand-600' : 'bg-slate-100 text-slate-400'">
                          <MouseIcon :size="16" />
                        </div>
                        <p class="text-sm font-bold text-slate-800">{{ deviceName(suggestion, 'Unregistered device') }}</p>
                        <span v-if="suggestion.mouse_id" class="font-mono text-[11px] text-slate-400">#{{ suggestion.mouse_id }}</span>
                        <StatusBadge :status="suggestion.confidence || 'unknown'" />
                      </div>
                      <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ suggestion.reason || 'No reason was reported for this suggestion.' }}</p>
                      <p v-if="suggestion._sourceDevice?.name" class="mt-1.5 text-[11px] text-slate-500">Based on device: <span class="font-semibold text-slate-600">{{ suggestion._sourceDevice.name }}</span></p>
                      <p v-if="needsManualVerification(suggestion.confidence)" class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600">
                        <HelpCircleIcon :size="13" /> Need manual verification
                      </p>
                    </div>
                    <button
                      type="button"
                      class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1.5 text-[11px] font-semibold text-brand-700 transition hover:bg-brand-50"
                      :aria-expanded="Boolean(liveUi.evidenceOpen[suggestion._key])"
                      @click="liveUi.evidenceOpen[suggestion._key] = !liveUi.evidenceOpen[suggestion._key]"
                    >
                      Evidence ({{ safeJson(suggestion.evidence).length }})
                      <ChevronDownIcon :size="14" :class="liveUi.evidenceOpen[suggestion._key] ? 'rotate-180' : ''" class="transition-transform" />
                    </button>
                  </div>

                  <div v-if="liveUi.evidenceOpen[suggestion._key]" class="mt-3 space-y-2 rounded-lg bg-slate-50 p-3">
                    <div v-for="(item, itemIndex) in safeJson(suggestion.evidence)" :key="itemIndex" class="rounded-lg border border-slate-100 bg-white p-2.5">
                      <p class="text-[11px] font-bold text-brand-700">{{ item.type || 'Evidence' }}</p>
                      <p class="mt-0.5 text-xs text-slate-700">{{ item.detail || 'No detail reported.' }}</p>
                      <p v-if="item.source" class="mt-1 text-[11px] text-slate-400">Source: {{ item.source }}</p>
                    </div>
                    <p v-if="!safeJson(suggestion.evidence).length" class="text-xs text-slate-400">No evidence was reported for this suggestion.</p>
                  </div>

                  <div v-if="liveUi.existingMatch[suggestion._key]" class="mt-3 flex flex-col gap-2 rounded-lg border border-rose-200 bg-rose-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                      <p class="text-xs font-bold text-rose-800">An active match already exists</p>
                      <p class="mt-0.5 text-[11px] leading-relaxed text-rose-700">
                        {{ deviceName(liveUi.existingMatch[suggestion._key].mouse, 'A mouse') }} is already linked to {{ deviceName(liveUi.existingMatch[suggestion._key].dongle, 'a dongle') }} ({{ liveUi.existingMatch[suggestion._key].status }}).
                      </p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                      <AppButton size="xs" variant="ghost" @click="dismissLiveConflict(suggestion._key)">Dismiss</AppButton>
                      <AppButton size="xs" variant="secondary" @click="viewExistingMatch(liveUi.existingMatch[suggestion._key])">View match</AppButton>
                    </div>
                  </div>

                  <div v-if="suggestion.mouse_id && dongle.dongle_id" class="mt-4 border-t border-slate-100 pt-4">
                    <Field :id="`live-note-${suggestion._key}`" label="Notes" hint="Prefilled with the reason for this suggestion — edit before saving.">
                      <textarea :id="`live-note-${suggestion._key}`" v-model="liveUi.notes[suggestion._key]" rows="2" class="input resize-y" placeholder="Why this match was suggested"></textarea>
                    </Field>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                      <AppButton
                        size="sm"
                        variant="secondary"
                        :icon="HelpCircleIcon"
                        :loading="liveSaveBusy === saveBusyKey(suggestion._key, 'suspected')"
                        :disabled="Boolean(liveSaveBusy)"
                        @click="saveLiveMatch(dongle, suggestion, suggestion._key, 'suspected')"
                      >Save as Suspected</AppButton>
                      <AppButton
                        size="sm"
                        :icon="BadgeCheckIcon"
                        :loading="liveSaveBusy === saveBusyKey(suggestion._key, 'matched')"
                        :disabled="Boolean(liveSaveBusy)"
                        @click="saveLiveMatch(dongle, suggestion, suggestion._key, 'matched')"
                      >Confirm Match</AppButton>
                    </div>
                  </div>

                  <div v-else-if="suggestion.mouse_id" class="mt-4 border-t border-slate-100 pt-4 text-[11px] leading-relaxed text-slate-500">
                    This receiver is not registered yet — register the dongle before saving a match.
                  </div>

                  <form v-else class="mt-4 space-y-3 border-t border-slate-100 pt-4" @submit.prevent="registerLiveMouse(suggestion._key)">
                    <p class="text-[11px] font-semibold text-slate-600">Not registered yet — add brand and model, then re-run detection to match it.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                      <Field :id="`live-brand-${suggestion._key}`" label="Brand" required>
                        <input :id="`live-brand-${suggestion._key}`" v-model="liveUi.register[suggestion._key].brand" class="input" placeholder="e.g. Logitech" required />
                      </Field>
                      <Field :id="`live-model-${suggestion._key}`" label="Model" required>
                        <input :id="`live-model-${suggestion._key}`" v-model="liveUi.register[suggestion._key].model" class="input" placeholder="e.g. M185" required />
                      </Field>
                    </div>
                    <AppButton type="submit" size="sm" :icon="PlusIcon" :loading="liveRegisterBusy === suggestion._key" :disabled="Boolean(liveRegisterBusy)">Register mouse</AppButton>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <div v-if="liveUnattributed.length" class="rounded-2xl border border-slate-200 p-4">
            <button
              type="button"
              class="flex w-full items-center gap-2 text-left"
              :aria-expanded="liveUnattributedOpen"
              @click="liveUnattributedOpen = !liveUnattributedOpen"
            >
              <ChevronDownIcon :size="16" :class="liveUnattributedOpen ? 'rotate-180' : ''" class="shrink-0 text-slate-400 transition-transform" />
              <span class="text-xs font-bold text-slate-700">Devices not tied to a receiver ({{ liveUnattributed.length }})</span>
            </button>
            <p class="mt-1.5 text-[11px] leading-relaxed text-slate-500">Windows reported these devices but did not tie them to a receiver, so they cannot be attributed to a dongle.</p>
            <div v-if="liveUnattributedOpen" class="mt-3 space-y-2">
              <LiveDeviceRow v-for="(device, index) in liveUnattributed" :key="device.instance_id || index" :device="device" />
            </div>
          </div>
        </div>
      </template>

      <div v-else class="p-2">
        <EmptyState :icon="LinkIcon" title="Nothing detected yet" description="Plug in a dongle and switch the mouse on, then click Detect connected mice." />
      </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
      <!-- Device list -->
      <section class="card flex flex-col overflow-hidden xl:col-span-1">
        <div class="border-b border-slate-100 px-5 py-4">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-sm font-bold text-slate-900">Detected devices</h2>
              <p class="text-[11px] text-slate-400">From the most recent scan — nothing is invented</p>
            </div>
            <span class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">{{ filteredDevices.length }}</span>
          </div>
          <div class="mt-3"><SearchInput v-model="deviceSearch" placeholder="Search devices or VID/PID..." /></div>
          <div class="mt-2 flex gap-1 rounded-xl bg-slate-100 p-1">
            <button
              v-for="filter in [{ key: 'all', label: 'All devices' }, { key: 'receivers', label: 'Receivers only' }]"
              :key="filter.key"
              type="button"
              class="flex-1 rounded-lg px-3 py-1.5 text-[11px] font-semibold transition"
              :class="deviceFilter === filter.key ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
              @click="deviceFilter = filter.key"
            >
              {{ filter.label }}
            </button>
          </div>
        </div>

        <div class="min-h-64 flex-1 space-y-2 overflow-y-auto p-4" style="max-height: 620px">
          <div v-if="scanning || quickScanning" class="space-y-2">
            <div v-for="n in 5" :key="n" class="skeleton h-20 w-full rounded-xl"></div>
          </div>
          <div v-else-if="!devices.length" class="py-8">
            <EmptyState :icon="UsbIcon" title="No scan results yet" description="Click Scan USB devices to enumerate hardware currently connected to this Windows machine." />
          </div>
          <div v-else-if="!filteredDevices.length" class="py-8">
            <EmptyState :icon="SearchIcon" title="No matching devices" description="Try a different search term or show all devices." />
          </div>
          <button
            v-for="(device, index) in filteredDevices"
            v-else
            :key="device.device_instance_id || device.instance_id || index"
            type="button"
            class="w-full rounded-xl border p-3 text-left transition"
            :class="selectedDevice === device
              ? 'border-brand-300 bg-brand-50/70 shadow-sm ring-2 ring-brand-500/10'
              : 'border-slate-200 bg-white hover:border-brand-200 hover:bg-slate-50'"
            @click="selectDevice(device)"
          >
            <div class="flex items-start gap-3">
              <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="device.is_receiver ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400'">
                <UsbIcon :size="17" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-bold text-slate-800">{{ device.name || 'Unknown device' }}</p>
                <p class="mt-0.5 font-mono text-[11px] text-slate-500">{{ device.vid || '----' }}:{{ device.pid || '----' }}</p>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                  <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ titleCase(device.category) }}</span>
                  <span v-if="device.status" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ device.status }}</span>
                  <span v-if="device.is_receiver" class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">Receiver candidate</span>
                </div>
              </div>
            </div>
          </button>
        </div>

        <div v-if="scans.length" class="border-t border-slate-100 p-4">
          <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Recent saved scans</p>
          <div class="space-y-1">
            <button
              v-for="scan in scans.slice(0, 5)"
              :key="scan.id"
              type="button"
              class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-left text-[11px] transition hover:bg-slate-50"
              :class="lastScan?.id === scan.id ? 'text-brand-700' : 'text-slate-500'"
              @click="showScan(scan)"
            >
              <span class="font-mono">{{ scan.scan_id }}</span>
              <span>{{ scan.device_count }} devices · {{ formatDate(scan.scanned_at) }}</span>
            </button>
          </div>
        </div>
      </section>

      <!-- Analyzer -->
      <section class="card overflow-hidden xl:col-span-2">
        <div class="border-b border-slate-100 px-6 py-4">
          <h2 class="text-sm font-bold text-slate-900">Identifier analyzer</h2>
          <p class="mt-0.5 text-xs text-slate-500">Detected hardware IDs are prefilled when you select a device. Correct them if needed.</p>
        </div>
        <div class="p-6">
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Field label="VID" id="analyzer-vid" required hint="4 hex characters">
              <input id="analyzer-vid" v-model="analyzer.vid" class="input font-mono uppercase" maxlength="4" placeholder="046D" />
            </Field>
            <Field label="PID" id="analyzer-pid" hint="4 hex characters">
              <input id="analyzer-pid" v-model="analyzer.pid" class="input font-mono uppercase" maxlength="4" placeholder="C52B" />
            </Field>
            <Field label="Manufacturer" id="analyzer-manufacturer">
              <input id="analyzer-manufacturer" v-model="analyzer.manufacturer" class="input" placeholder="From label or scan" />
            </Field>
            <Field label="Model / marking" id="analyzer-model">
              <input id="analyzer-model" v-model="analyzer.model" class="input" placeholder="e.g. M185 / C52B" />
            </Field>
          </div>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <Field label="Hardware ID" id="analyzer-hardware">
              <input id="analyzer-hardware" v-model="analyzer.hardware_id" class="input font-mono text-xs" placeholder="USB\\VID_046D&PID_C52B" />
            </Field>
            <Field label="Serial number" id="analyzer-serial">
              <input id="analyzer-serial" v-model="analyzer.serial_number" class="input font-mono text-xs" placeholder="If reported by hardware" />
            </Field>
          </div>

          <div v-if="selectedDevice" class="mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 p-3">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Hardware IDs</span>
            <code class="min-w-0 flex-1 truncate font-mono text-[11px] text-slate-600">{{ hardwareIdsText || 'Not reported by this device' }}</code>
            <AppButton size="xs" variant="secondary" :icon="CopyIcon" @click="copyHardwareIds">Copy</AppButton>
          </div>

          <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-5">
            <AppButton :icon="SparklesIcon" :loading="analyzing" @click="analyze">Identify</AppButton>
            <AppButton variant="secondary" :icon="GlobeIcon" :loading="researching" @click="identifyOnline">Identify Online</AppButton>
            <AppButton variant="secondary" :icon="DownloadIcon" :loading="softwareLoading" @click="loadSoftware">Find software</AppButton>
            <AppButton variant="secondary" :icon="PlusIcon" @click="openRegister">Register Device</AppButton>
          </div>

          <div v-if="usbLookup" class="mt-5 rounded-xl border border-slate-200 p-4">
            <div class="flex items-center justify-between">
              <p class="text-xs font-bold text-slate-800">Local VID/PID lookup</p>
              <StatusBadge :status="usbLookup.vendor_name ? 'matched' : 'unknown'" :label="usbLookup.vendor_name ? 'Found locally' : 'Not in local database'" />
            </div>
            <dl class="mt-3 grid gap-3 text-xs sm:grid-cols-2">
              <div><dt class="text-slate-400">Vendor</dt><dd class="mt-0.5 font-semibold text-slate-700">{{ usbLookup.vendor_name || 'Unknown' }}</dd></div>
              <div><dt class="text-slate-400">Product</dt><dd class="mt-0.5 font-semibold text-slate-700">{{ usbLookup.product_name || 'Unknown' }}</dd></div>
            </dl>
            <p v-if="usbLookup.message" class="mt-2 text-[11px] text-slate-500">{{ usbLookup.message }}</p>
          </div>
        </div>
      </section>
    </div>

    <!-- Selected device profile -->
    <section class="card mt-6 overflow-hidden">
      <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="text-sm font-bold text-slate-900">Selected device profile</h2>
        <p class="mt-0.5 text-xs text-slate-500">Choose a device from the scan list to inspect its reported metadata.</p>
      </div>
      <div v-if="!selectedDevice" class="p-6">
        <EmptyState :icon="RadarIcon" title="No device selected" description="Select a scanned device to see its identity, driver information and analysis." />
      </div>
      <div v-else class="p-6">
        <div class="mb-6 flex flex-wrap items-center gap-2">
          <p class="text-base font-bold text-slate-900">{{ selectedDevice.name || 'Unknown device' }}</p>
          <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-[11px] text-slate-600">{{ selectedDevice.vid || '----' }}:{{ selectedDevice.pid || '----' }}</span>
          <StatusBadge v-if="deviceStatus" :status="deviceStatus === 'OK' ? 'matched' : 'unknown'" :label="deviceStatus" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
          <div>
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Device identity</h3>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <div v-for="item in identityFields" :key="item.label" class="min-w-0">
                <dt class="text-[11px] text-slate-400">{{ item.label }}</dt>
                <dd class="mt-0.5 break-all text-xs font-medium text-slate-700">{{ item.value || 'Not reported' }}</dd>
              </div>
            </dl>
          </div>
          <div>
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Wireless information</h3>
            <div v-if="analysis" class="grid grid-cols-2 gap-3">
              <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-[11px] text-slate-400">Technology</p>
                <p class="mt-0.5 text-xs font-semibold text-slate-700">{{ analysis.wireless_technology || 'Unknown' }}</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-[11px] text-slate-400">Frequency</p>
                <p class="mt-0.5 text-xs font-semibold text-slate-700">{{ analysis.frequency || 'Unknown' }}</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-[11px] text-slate-400">Receiver family</p>
                <p class="mt-0.5 text-xs font-semibold text-slate-700">{{ analysis.receiver_family || 'Unknown' }}</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-[11px] text-slate-400">Pairing utility</p>
                <p class="mt-0.5 text-xs font-semibold text-slate-700">{{ analysis.pairing_utility || 'Unknown' }}</p>
              </div>
              <div class="col-span-2 flex items-center gap-2">
                <span class="text-[11px] text-slate-400">Confidence</span>
                <StatusBadge :status="analysis.confidence || 'unknown'" />
              </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-slate-200 p-4 text-xs text-slate-400">
              Run <span class="font-semibold text-slate-600">Identify</span> to see evidence-based wireless information.
            </div>
          </div>
        </div>

        <!-- Analysis result -->
        <div v-if="analysis" class="mt-6 border-t border-slate-100 pt-6">
          <div class="grid gap-6 lg:grid-cols-2">
            <div>
              <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Possible compatible devices</h3>
              <p class="mb-3 text-[11px] leading-relaxed text-amber-700">Leads only — a shared brand or frequency is not proof. Physical testing is still required.</p>
              <ul v-if="compatibleMice.length" class="space-y-2">
                <li v-for="(item, index) in compatibleMice" :key="index" class="rounded-xl border border-slate-100 p-3">
                  <p class="text-xs font-semibold text-slate-800">{{ typeof item === 'string' ? item : [item.brand, item.model].filter(Boolean).join(' ') || 'Compatible device' }}</p>
                  <p v-if="typeof item === 'object' && item.reason" class="mt-0.5 text-[11px] text-slate-500">{{ item.reason }}</p>
                  <a v-if="typeof item === 'object' && item.source_url" :href="item.source_url" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-brand-600 hover:underline">Source <ExternalLinkIcon :size="11" /></a>
                </li>
              </ul>
              <p v-else class="text-xs text-slate-400">No compatibility leads were found.</p>
            </div>
            <div>
              <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Evidence &amp; remaining unknowns</h3>
              <ul v-if="evidence.length" class="space-y-2">
                <li v-for="(item, index) in evidence" :key="index" class="rounded-xl border border-slate-100 p-3">
                  <p class="text-[11px] font-semibold text-brand-700">{{ typeof item === 'string' ? 'Evidence' : item.type || 'Evidence' }}</p>
                  <p class="mt-0.5 text-xs text-slate-700">{{ typeof item === 'string' ? item : item.detail }}</p>
                  <p v-if="typeof item === 'object' && item.source" class="mt-1 text-[11px] text-slate-400">Source: {{ item.source }}</p>
                </li>
              </ul>
              <p v-else class="text-xs text-slate-400">No evidence was reported.</p>
              <div v-if="unknowns.length" class="mt-3 rounded-xl bg-amber-50 p-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Remaining unknowns</p>
                <ul class="mt-2 space-y-1">
                  <li v-for="(item, index) in unknowns" :key="index" class="flex items-start gap-1.5 text-xs text-amber-800">
                    <HelpCircleIcon :size="13" class="mt-0.5 shrink-0" />
                    <span>{{ typeof item === 'string' ? item : item.detail || JSON.stringify(item) }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Research sources -->
    <section class="card mt-6 overflow-hidden">
      <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-sm font-bold text-slate-900">Research sources</h2>
          <p class="mt-0.5 text-xs text-slate-500">Real links only — no results are fabricated.</p>
        </div>
        <AppButton size="sm" variant="secondary" :icon="GlobeIcon" :loading="researching" @click="identifyOnline">Identify Online</AppButton>
      </div>
      <div v-if="research && research.available === false" class="flex items-start gap-3 border-b border-amber-200 bg-amber-50 p-4">
        <InfoIcon :size="17" class="mt-0.5 shrink-0 text-amber-600" />
        <p class="text-xs leading-relaxed text-amber-800">{{ research.message || 'Online search is not configured. Official links below are constructed directly from the identifiers.' }}</p>
      </div>
      <div v-if="research && research.message && research.available !== false" class="border-b border-slate-100 bg-slate-50 px-6 py-3 text-xs text-slate-500">{{ research.message }}</div>
      <div v-if="!researchSources.length" class="p-6">
        <EmptyState :icon="BookOpenIcon" title="No sources yet" description="Run Identify Online to gather real research links for this device." />
      </div>
      <div v-else class="divide-y divide-slate-100">
        <a
          v-for="(source, index) in researchSources"
          :key="`${source.url}-${index}`"
          :href="source.url"
          target="_blank"
          rel="noopener"
          class="block px-6 py-4 transition hover:bg-slate-50"
        >
          <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm font-semibold text-slate-800">{{ source.title || source.name || source.url }}</p>
            <StatusBadge v-if="source.official" status="matched" label="Official" />
            <span v-else class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">{{ source.source || source.category || 'Third-party' }}</span>
          </div>
          <p v-if="source.snippet || source.description" class="mt-1 text-xs leading-relaxed text-slate-500">{{ source.snippet || source.description }}</p>
          <p class="mt-1 truncate font-mono text-[11px] text-brand-600">{{ source.url }}</p>
        </a>
      </div>
    </section>

    <!-- Software & downloads -->
    <section class="card mt-6 overflow-hidden">
      <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="text-sm font-bold text-slate-900">Software &amp; downloads</h2>
        <p class="mt-0.5 text-xs text-slate-500">URLs and source type are shown before anything is downloaded. Executables require extra confirmation and are never run.</p>
      </div>

      <div v-if="!softwareItems.length" class="p-6">
        <EmptyState :icon="DownloadIcon" title="No software found yet" description="Run Identify or Find software to discover official drivers and pairing utilities.">
          <template #action><AppButton :icon="DownloadIcon" @click="loadSoftware">Find software</AppButton></template>
        </EmptyState>
      </div>
      <div v-else class="divide-y divide-slate-100">
        <div v-for="(item, index) in softwareItems" :key="`${item.url}-${index}`" class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-sm font-semibold text-slate-800">{{ item.name || item.title || 'Software' }}</p>
              <StatusBadge :status="item.official ? 'matched' : 'unknown'" :label="item.official ? 'Official' : item.type || item.source || 'Third-party'" />
            </div>
            <p v-if="item.description || item.snippet" class="mt-1 text-xs text-slate-500">{{ item.description || item.snippet }}</p>
            <p class="mt-1 truncate font-mono text-[11px] text-brand-600">{{ item.url }}</p>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <a :href="item.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-brand-600" aria-label="Open source URL">
              <ExternalLinkIcon :size="16" />
            </a>
            <AppButton size="sm" variant="secondary" :icon="DownloadIcon" @click="startDownload(item)">Download</AppButton>
          </div>
        </div>
      </div>

      <div class="border-t border-slate-100">
        <div class="flex items-center justify-between px-6 py-4">
          <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Download history</h3>
          <AppButton size="xs" variant="ghost" :icon="RefreshCwIcon" @click="loadDownloads">Refresh</AppButton>
        </div>
        <div v-if="!downloads.length" class="px-6 pb-6">
          <EmptyState :icon="DownloadIcon" title="No downloads recorded" description="Files you download through the app will appear here with a verification hash." />
        </div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="download in downloads" :key="download.id" class="flex flex-col gap-3 px-6 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
              <p class="truncate text-xs font-semibold text-slate-800">{{ download.filename }}</p>
              <p class="truncate font-mono text-[11px] text-slate-400">{{ download.source_url }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
              <AppButton size="xs" variant="ghost" :icon="ShieldCheckIcon" :loading="busyDownload === download.id" @click="verifyDownload(download)">Verify</AppButton>
              <AppButton size="xs" variant="ghost" :icon="FolderOpenIcon" :loading="busyDownload === download.id" @click="openDownload(download)">Open location</AppButton>
              <AppButton size="xs" variant="ghost" :icon="Trash2Icon" @click="removeDownload(download)">Delete</AppButton>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Register device modal -->
    <Modal v-model="registerOpen" title="Register device" subtitle="Review the detected values and correct anything that looks wrong before saving." size="lg" @close="resetRegister">
      <form id="register-form" class="space-y-4" @submit.prevent="saveDevice">
        <div v-if="analysis?.probable_manufacturer || analysis?.probable_model" class="flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50/60 p-3">
          <SparklesIcon :size="17" class="mt-0.5 shrink-0 text-brand-600" />
          <p class="text-xs leading-relaxed text-brand-800">
            Analysis suggests <span class="font-bold">{{ analysis.probable_manufacturer || 'unknown manufacturer' }}</span>
            <span v-if="analysis.probable_model"> / {{ analysis.probable_model }}</span> with {{ statusLabel(analysis.confidence || 'unknown').toLowerCase() }}.
            Confirm from the physical label before saving.
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <Field label="Brand" id="register-brand"><input id="register-brand" v-model="registerForm.brand" class="input" placeholder="Optional for unknown receivers" /></Field>
          <Field label="Model" id="register-model"><input id="register-model" v-model="registerForm.model" class="input" /></Field>
          <Field label="Color" id="register-color"><input id="register-color" v-model="registerForm.color" class="input" /></Field>
          <Field label="Receiver type" id="register-type"><input id="register-type" v-model="registerForm.receiver_type" class="input" placeholder="e.g. USB nano receiver" /></Field>
          <Field label="VID" id="register-vid"><input id="register-vid" v-model="registerForm.vid" class="input font-mono uppercase" maxlength="4" /></Field>
          <Field label="PID" id="register-pid"><input id="register-pid" v-model="registerForm.pid" class="input font-mono uppercase" maxlength="4" /></Field>
          <Field label="Hardware ID" id="register-hardware"><input id="register-hardware" v-model="registerForm.hardware_id" class="input font-mono text-xs" /></Field>
          <Field label="Serial number" id="register-serial"><input id="register-serial" v-model="registerForm.serial_number" class="input font-mono text-xs" /></Field>
        </div>
        <Field label="Physical description" id="register-description"><textarea id="register-description" v-model="registerForm.physical_description" rows="2" class="input resize-y"></textarea></Field>
        <Field label="Notes" id="register-notes"><textarea id="register-notes" v-model="registerForm.notes" rows="2" class="input resize-y"></textarea></Field>

        <div class="rounded-xl border border-slate-200 p-4">
          <PhotoUpload v-model="registerPhoto" label="Photo of the receiver / label" />
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <AppButton type="button" size="sm" variant="secondary" :icon="ScanTextIcon" :loading="ocrBusy" :disabled="!registerPhoto" @click="runOcr">Read text from photo (OCR)</AppButton>
            <span class="text-[11px] text-slate-400">Optional — works only if an OCR service is configured.</span>
          </div>
          <div v-if="ocrResult && ocrResult.available === false" class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
            {{ ocrResult.message || 'OCR is not configured on this server. Enter any visible markings manually.' }}
          </div>
          <div v-else-if="ocrResult" class="mt-3 rounded-lg bg-emerald-50 p-3">
            <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Extracted text — verify before trusting</p>
            <pre class="mt-1 max-h-32 overflow-auto whitespace-pre-wrap font-mono text-[11px] text-emerald-900">{{ ocrResult.extracted_text || 'No text extracted.' }}</pre>
          </div>
        </div>

        <label v-if="analysis" class="flex cursor-pointer items-center gap-2 text-xs text-slate-600">
          <input v-model="saveIdentification" type="checkbox" class="h-4 w-4 rounded accent-brand-600" />
          Save this analysis as an identification record for the new dongle.
        </label>
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="registerOpen = false">Cancel</AppButton>
        <AppButton type="submit" form="register-form" :icon="SaveIcon" :loading="savingDevice">Save dongle</AppButton>
      </template>
    </Modal>

    <!-- Download confirmation -->
    <Modal v-model="downloadOpen" title="Confirm download" :subtitle="downloadCandidate?.url" size="md" @close="resetDownload">
      <div v-if="downloadCandidate" class="space-y-4">
        <div class="rounded-xl bg-slate-50 p-4">
          <p class="text-sm font-bold text-slate-800">{{ downloadCandidate.name || downloadCandidate.title || 'Software' }}</p>
          <p class="mt-1 break-all font-mono text-[11px] text-brand-700">{{ downloadCandidate.url }}</p>
          <p class="mt-2 text-xs text-slate-500">Source: {{ downloadCandidate.official ? 'Official vendor source' : downloadCandidate.source || downloadCandidate.type || 'Third-party / unknown' }}</p>
        </div>
        <div v-if="isExecutable(downloadCandidate.url)" class="rounded-xl border border-rose-200 bg-rose-50 p-4">
          <div class="flex items-start gap-3">
            <TriangleAlertIcon :size="18" class="mt-0.5 shrink-0 text-rose-600" />
            <div>
              <p class="text-sm font-bold text-rose-800">This is an executable file</p>
              <p class="mt-1 text-xs leading-relaxed text-rose-700">The app will download and hash the file but will never run it or change security settings. Only continue if you trust the source.</p>
            </div>
          </div>
          <label class="mt-3 flex cursor-pointer items-start gap-2 text-xs font-semibold text-rose-800">
            <input v-model="execConfirmed" type="checkbox" class="mt-0.5 h-4 w-4 accent-rose-600" />
            I understand this downloads an executable file and I will run it myself only if I trust it.
          </label>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="downloadOpen = false">Cancel</AppButton>
        <AppButton :icon="DownloadIcon" :loading="downloading" :disabled="isExecutable(downloadCandidate?.url) && !execConfirmed" @click="confirmDownload">Download</AppButton>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import {
  ScanLineIcon,
  RefreshCwIcon,
  ScanSearchIcon,
  TriangleAlertIcon,
  UsbIcon,
  SearchIcon,
  InfoIcon,
  CopyIcon,
  SparklesIcon,
  GlobeIcon,
  DownloadIcon,
  PlusIcon,
  DatabaseIcon,
  RadarIcon,
  HelpCircleIcon,
  BookOpenIcon,
  ExternalLinkIcon,
  ScanTextIcon,
  SaveIcon,
  ShieldCheckIcon,
  FolderOpenIcon,
  Trash2Icon,
  LinkIcon,
  ChevronDownIcon,
  BadgeCheckIcon,
  MouseIcon,
} from 'lucide-vue-next'
import PageHeader from '../components/PageHeader.vue'
import AppButton from '../components/AppButton.vue'
import SearchInput from '../components/SearchInput.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import Modal from '../components/Modal.vue'
import Field from '../components/Field.vue'
import PhotoUpload from '../components/PhotoUpload.vue'
import LiveDeviceRow from '../components/LiveDeviceRow.vue'
import {
  deviceName,
  formatDate,
  formatDateTime,
  safeJson,
  statusLabel,
  titleCase,
} from '../composables/useFormat'
import { useConfirm } from '../composables/useConfirm'
import { useToast } from '../composables/useToast'

const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const scans = ref([])
const devices = ref([])
const lastScan = ref(null)
const scanError = ref('')
const scanning = ref(false)
const quickScanning = ref(false)
const loadingScans = ref(false)
const deviceSearch = ref('')
const deviceFilter = ref('all')
const selectedDevice = ref(null)

const analyzer = ref({ vid: '', pid: '', hardware_id: '', manufacturer: '', model: '', serial_number: '', notes: '' })
const usbLookup = ref(null)
const lookupLoading = ref(false)
const refreshingIds = ref(false)
const analysis = ref(null)
const research = ref(null)
const softwareResults = ref([])
const analyzing = ref(false)
const researching = ref(false)
const softwareLoading = ref(false)
const linkedDongle = ref(null)

const liveResult = ref(null)
const liveError = ref('')
const liveScanning = ref(false)
const liveSaveBusy = ref('')
const liveRegisterBusy = ref('')
const liveUnattributedOpen = ref(false)
const liveUi = reactive({ notes: {}, evidenceOpen: {}, register: {}, registerDongle: {}, existingMatch: {} })

const registerOpen = ref(false)
const savingDevice = ref(false)
const ocrBusy = ref(false)
const ocrResult = ref(null)
const saveIdentification = ref(true)
const emptyRegister = () => ({
  brand: '', model: '', color: '', receiver_type: '', vid: '', pid: '',
  hardware_id: '', serial_number: '', physical_description: '', notes: '',
})
const registerForm = ref(emptyRegister())
const registerPhoto = ref(null)

const downloads = ref([])
const busyDownload = ref(null)
const downloadOpen = ref(false)
const downloadCandidate = ref(null)
const execConfirmed = ref(false)
const downloading = ref(false)

const filteredDevices = computed(() => {
  const term = deviceSearch.value.trim().toLowerCase()
  return devices.value.filter((device) => {
    if (deviceFilter.value === 'receivers' && !device.is_receiver) return false
    if (!term) return true
    const haystack = [
      device.name, device.manufacturer, device.description, device.vid, device.pid,
      device.category, device.serial_number, device.driver_name,
      ...safeJson(device.hardware_ids),
    ].filter(Boolean).join(' ').toLowerCase()
    return haystack.includes(term)
  })
})

const hardwareIdsText = computed(() => {
  if (!selectedDevice.value) return ''
  const ids = safeJson(selectedDevice.value.hardware_ids)
  if (ids.length) return ids.join(' · ')
  return selectedDevice.value.device_instance_id || selectedDevice.value.instance_id || ''
})

const identityFields = computed(() => [
  { label: 'Manufacturer', value: selectedDevice.value?.manufacturer },
  { label: 'Model / name', value: selectedDevice.value?.name },
  { label: 'Description', value: selectedDevice.value?.description },
  { label: 'VID', value: selectedDevice.value?.vid },
  { label: 'PID', value: selectedDevice.value?.pid },
  { label: 'Serial number', value: selectedDevice.value?.serial_number },
  { label: 'Hardware IDs', value: hardwareIdsText.value },
  { label: 'Device instance', value: selectedDevice.value?.device_instance_id || selectedDevice.value?.instance_id },
  { label: 'USB class', value: selectedDevice.value?.usb_class || selectedDevice.value?.class },
  { label: 'Driver name', value: selectedDevice.value?.driver_name },
  { label: 'Driver version', value: selectedDevice.value?.driver_version },
  { label: 'Driver provider', value: selectedDevice.value?.driver_provider },
  { label: 'Location', value: selectedDevice.value?.location },
])

const deviceStatus = computed(() => selectedDevice.value?.status || '')
const evidence = computed(() => safeJson(analysis.value?.evidence))
const unknowns = computed(() => safeJson(analysis.value?.unknowns))
const compatibleMice = computed(() => safeJson(analysis.value?.compatible_mice))

const researchSources = computed(() => {
  const sources = []
  const fromResearch = safeJson(research.value?.results)
  const fromAnalysis = safeJson(analysis.value?.research_sources)
  for (const item of [...fromResearch, ...fromAnalysis]) {
    if (item && typeof item === 'object' && item.url) {
      sources.push(item)
    }
  }
  const seen = new Set()
  return sources.filter((item) => {
    if (seen.has(item.url)) return false
    seen.add(item.url)
    return true
  })
})

const softwareItems = computed(() => {
  const items = []
  for (const item of safeJson(analysis.value?.recommended_software)) {
    if (item && typeof item === 'object' && item.url) items.push(item)
  }
  for (const item of softwareResults.value) {
    if (item && typeof item === 'object' && item.url) items.push(item)
  }
  const seen = new Set()
  return items.filter((item) => {
    if (seen.has(item.url)) return false
    seen.add(item.url)
    return true
  })
})

const liveDongles = computed(() => {
  const dongles = Array.isArray(liveResult.value?.dongles) ? liveResult.value.dongles : []
  return dongles.map((dongle, dongleIndex) => {
    const connectedDevices = Array.isArray(dongle.connected_devices) ? dongle.connected_devices : []
    const suggestions = Array.isArray(dongle.suggestions) ? dongle.suggestions : []
    const dongleKey = `dongle-${dongleIndex}`
    return {
      ...dongle,
      _key: dongleKey,
      _connectedDevices: connectedDevices,
      _suggestions: suggestions.map((suggestion, suggestionIndex) => ({
        ...suggestion,
        _key: `${dongleKey}-suggestion-${suggestionIndex}`,
        _sourceDevice: connectedDevices[suggestion.device_index] || null,
      })),
    }
  })
})

const liveUnattributed = computed(() => Array.isArray(liveResult.value?.unattributed_devices) ? liveResult.value.unattributed_devices : [])
const liveHasResults = computed(() => liveDongles.value.length > 0 || liveUnattributed.value.length > 0)

watch(() => route.query.dongle, (value) => {
  if (value) loadLinkedDongle(value)
}, { immediate: true })

async function loadScans() {
  loadingScans.value = true
  try {
    const { data } = await axios.get('/usb-scans', { params: { per_page: 10 } })
    scans.value = Array.isArray(data.data) ? data.data : []
    if (scans.value.length) {
      showScan(scans.value[0], false)
    } else {
      devices.value = []
      lastScan.value = null
    }
  } catch {
    // handled by interceptor
  } finally {
    loadingScans.value = false
  }
}

function showScan(scan, clearSelection = true) {
  lastScan.value = scan
  devices.value = safeJson(scan.devices)
  scanError.value = ''
  if (clearSelection) {
    selectedDevice.value = null
    analysis.value = null
    research.value = null
    softwareResults.value = []
    usbLookup.value = null
  }
}

async function runScan(save) {
  if (save) scanning.value = true
  else quickScanning.value = true
  scanError.value = ''
  try {
    const { data } = await axios.post(save ? '/usb-scans' : '/usb-scans/scan', save ? {} : { save: false })
    if (data.error) {
      scanError.value = data.error
      toast.warning('Scan reported a problem', data.error)
      return
    }
    const found = Array.isArray(data.devices) ? data.devices : safeJson(data.scan?.devices)
    devices.value = found
    if (!save) {
      lastScan.value = { scanned_at: data.scanned_at, device_count: data.count ?? found.length }
    }
    selectedDevice.value = null
    analysis.value = null
    research.value = null
    softwareResults.value = []
    toast.success('Scan complete', `${found.length} device(s) found.`)
    if (save) await loadScans()
  } catch (requestError) {
    const message = requestError.response?.data?.error || requestError.response?.data?.message
    if (message) scanError.value = String(message)
    // interceptor also surfaces the error
  } finally {
    scanning.value = false
    quickScanning.value = false
  }
}

function selectDevice(device) {
  selectedDevice.value = device
  analyzer.value = {
    vid: device.vid || '',
    pid: device.pid || '',
    hardware_id: safeJson(device.hardware_ids).join('; ') || device.device_instance_id || device.instance_id || '',
    manufacturer: device.manufacturer || '',
    model: device.name || '',
    serial_number: device.serial_number || '',
    notes: device.description || '',
  }
  analysis.value = null
  research.value = null
  softwareResults.value = []
  usbLookup.value = null
}

async function copyHardwareIds() {
  try {
    await navigator.clipboard.writeText(hardwareIdsText.value || analyzer.value.hardware_id)
    toast.success('Copied', 'Hardware IDs copied to clipboard.')
  } catch {
    toast.warning('Clipboard unavailable', 'Select the hardware IDs and copy them manually.')
  }
}

async function lookupUsbIds() {
  if (!analyzer.value.vid) return
  lookupLoading.value = true
  try {
    const { data } = await axios.get('/usb-ids/lookup', { params: { vid: analyzer.value.vid, pid: analyzer.value.pid || undefined } })
    usbLookup.value = data
  } catch {
    // handled by interceptor
  } finally {
    lookupLoading.value = false
  }
}

async function refreshUsbIds() {
  refreshingIds.value = true
  try {
    const { data } = await axios.post('/usb-ids/refresh')
    toast.success('ID database updated', `${data.imported ?? 0} rows imported.`)
  } catch {
    // handled by interceptor
  } finally {
    refreshingIds.value = false
  }
}

async function analyze() {
  if (!analyzer.value.vid || analyzer.value.vid.length !== 4) {
    toast.warning('VID required', 'Enter a 4-character VID before analyzing.')
    return
  }
  analyzing.value = true
  try {
    const { data } = await axios.post('/identifications/analyze', {
      vid: analyzer.value.vid,
      pid: analyzer.value.pid || null,
      hardware_id: analyzer.value.hardware_id || null,
      dongle_id: linkedDongle.value?.id || null,
      manufacturer: analyzer.value.manufacturer || null,
      model: analyzer.value.model || null,
      serial_number: analyzer.value.serial_number || null,
      notes: analyzer.value.notes || null,
    })
    if (data.error) {
      toast.warning('Analysis unavailable', data.error)
      return
    }
    analysis.value = data
    toast.success('Analysis complete', `Confidence: ${statusLabel(data.confidence || 'unknown').toLowerCase()}.`)
  } catch {
    // handled by interceptor
  } finally {
    analyzing.value = false
  }
}

async function identifyOnline() {
  researching.value = true
  try {
    const queries = safeJson(analysis.value?.search_queries || analysis.value?.suggested_queries)
    if (!queries.length) {
      const vid = analyzer.value.vid
      const pid = analyzer.value.pid
      if (vid && pid) queries.push(`USB VID ${vid} PID ${pid}`)
      if (analyzer.value.manufacturer) queries.push(`${analyzer.value.manufacturer} wireless receiver`)
    }
    const { data } = await axios.post('/research/search', {
      queries,
      vid: analyzer.value.vid || undefined,
      pid: analyzer.value.pid || undefined,
      manufacturer: analyzer.value.manufacturer || analysis.value?.probable_manufacturer || undefined,
      model: analyzer.value.model || undefined,
    })
    research.value = data
    if (data.available === false) {
      toast.info('Online search unavailable', data.message || 'Official direct links are shown instead.')
    } else {
      toast.success('Research complete', `${safeJson(data.results).length} source(s) found.`)
    }
  } catch {
    // handled by interceptor
  } finally {
    researching.value = false
  }
}

async function loadSoftware() {
  softwareLoading.value = true
  try {
    const { data } = await axios.get('/research/software', {
      params: {
        manufacturer: analyzer.value.manufacturer || analysis.value?.probable_manufacturer || undefined,
        model: analyzer.value.model || undefined,
        vid: analyzer.value.vid || undefined,
        pid: analyzer.value.pid || undefined,
      },
    })
    if (data && data.available === false && data.message) {
      toast.info('Software search unavailable', data.message)
    }
    softwareResults.value = Array.isArray(data?.results) ? data.results : []
  } catch {
    // handled by interceptor
  } finally {
    softwareLoading.value = false
  }
}

async function loadLinkedDongle(id) {
  try {
    const { data } = await axios.get(`/dongles/${id}`)
    linkedDongle.value = data
    analyzer.value = {
      ...analyzer.value,
      vid: data.vid || analyzer.value.vid,
      pid: data.pid || analyzer.value.pid,
      hardware_id: data.hardware_id || analyzer.value.hardware_id,
      manufacturer: data.brand || analyzer.value.manufacturer,
      model: data.model || analyzer.value.model,
      serial_number: data.serial_number || analyzer.value.serial_number,
    }
  } catch {
    // handled by interceptor
  }
}

function openRegister() {
  ocrResult.value = null
  registerPhoto.value = null
  saveIdentification.value = Boolean(analysis.value)
  registerForm.value = {
    brand: analysis.value?.probable_manufacturer || analyzer.value.manufacturer || linkedDongle.value?.brand || '',
    model: analysis.value?.probable_model || analyzer.value.model || linkedDongle.value?.model || '',
    color: '',
    receiver_type: analysis.value?.device_category || '',
    vid: analyzer.value.vid || '',
    pid: analyzer.value.pid || '',
    hardware_id: analyzer.value.hardware_id || '',
    serial_number: analyzer.value.serial_number || '',
    physical_description: analyzer.value.notes || '',
    notes: '',
  }
  registerOpen.value = true
}

function resetRegister() {
  registerForm.value = emptyRegister()
  registerPhoto.value = null
  ocrResult.value = null
}

async function runOcr() {
  if (!registerPhoto.value) return
  ocrBusy.value = true
  try {
    const payload = new FormData()
    payload.append('photo', registerPhoto.value)
    payload.append('photo_type', 'dongle_label')
    if (linkedDongle.value) payload.append('dongle_id', linkedDongle.value.id)
    const { data } = await axios.post('/identifications/ocr', payload)
    ocrResult.value = data
    if (data.available === false) {
      toast.info('OCR unavailable', data.message || 'Enter visible markings manually.')
      return
    }
    const fields = data.fields || {}
    if (fields.vid) registerForm.value.vid = String(fields.vid).toUpperCase()
    if (fields.pid) registerForm.value.pid = String(fields.pid).toUpperCase()
    if (fields.model && !registerForm.value.model) registerForm.value.model = fields.model
    if (fields.serial && !registerForm.value.serial_number) registerForm.value.serial_number = fields.serial
    toast.success('Text extracted', 'Review the values before saving — OCR can be wrong.')
  } catch {
    // handled by interceptor
  } finally {
    ocrBusy.value = false
  }
}

async function saveDevice() {
  savingDevice.value = true
  try {
    const payload = new FormData()
    const fields = ['brand', 'model', 'color', 'receiver_type', 'vid', 'pid', 'hardware_id', 'serial_number', 'physical_description', 'notes']
    for (const field of fields) payload.append(field, registerForm.value[field] ?? '')
    if (registerPhoto.value) payload.append('photo', registerPhoto.value)
    const { data: dongle } = await axios.post('/dongles', payload)
    linkedDongle.value = dongle
    toast.success('Dongle registered', deviceName(dongle, 'Unknown receiver'))

    if (analysis.value && saveIdentification.value) {
      try {
        await axios.post('/identifications', {
          dongle_id: dongle.id,
          vid: analysis.value.vid || registerForm.value.vid || null,
          pid: analysis.value.pid || registerForm.value.pid || null,
          hardware_id: registerForm.value.hardware_id || null,
          probable_manufacturer: analysis.value.probable_manufacturer || null,
          probable_model: analysis.value.probable_model || null,
          device_category: analysis.value.device_category || null,
          wireless_technology: analysis.value.wireless_technology || null,
          frequency: analysis.value.frequency || null,
          receiver_family: analysis.value.receiver_family || null,
          compatible_mice: safeJson(analysis.value.compatible_mice),
          pairing_utility: analysis.value.pairing_utility || null,
          official_support_url: analysis.value.support_url || null,
          evidence: safeJson(analysis.value.evidence),
          confidence: analysis.value.confidence || 'unknown',
          research_sources: researchSources.value,
          notes: registerForm.value.notes || null,
        })
        toast.success('Identification saved', 'The analysis was attached to the new dongle.')
      } catch {
        toast.warning('Identification not saved', 'The dongle was registered, but the analysis record could not be stored.')
      }
    }

    registerOpen.value = false
    resetRegister()
  } catch {
    // handled by interceptor
  } finally {
    savingDevice.value = false
  }
}

function isExecutable(url) {
  if (!url) return false
  return /\.(exe|msi|msu|bat|cmd|scr|com)(\?|$)/i.test(url)
}

function startDownload(item) {
  downloadCandidate.value = item
  execConfirmed.value = false
  downloadOpen.value = true
}

function resetDownload() {
  downloadCandidate.value = null
  execConfirmed.value = false
}

async function confirmDownload() {
  const item = downloadCandidate.value
  if (!item?.url) return
  downloading.value = true
  try {
    await axios.post('/downloads/fetch', {
      url: item.url,
      filename: item.name || item.title || deriveFilename(item.url),
      source_url: item.url,
      source_type: item.official ? 'official' : 'third-party',
      manufacturer: analyzer.value.manufacturer || analysis.value?.probable_manufacturer || null,
      model: analyzer.value.model || analysis.value?.probable_model || null,
      software_type: item.type || null,
      dongle_id: linkedDongle.value?.id || null,
      confirmed: true,
    })
    toast.success('Download recorded', item.name || item.title || 'Software')
    downloadOpen.value = false
    resetDownload()
    await loadDownloads()
  } catch {
    // handled by interceptor
  } finally {
    downloading.value = false
  }
}

function deriveFilename(url) {
  try {
    const pathname = new URL(url).pathname
    return decodeURIComponent(pathname.split('/').pop()) || 'download'
  } catch {
    return 'download'
  }
}

async function loadDownloads() {
  try {
    const { data } = await axios.get('/downloads', { params: { per_page: 10 } })
    downloads.value = Array.isArray(data.data) ? data.data : []
  } catch {
    // handled by interceptor
  }
}

async function verifyDownload(download) {
  busyDownload.value = download.id
  try {
    const { data } = await axios.get(`/downloads/${download.id}/verify`)
    if (data.verified) toast.success('Hash verified', 'Stored SHA-256 matches the file.')
    else toast.warning('Verification failed', data.error || 'Stored and computed hashes differ.')
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
    if (data.opened) toast.success('Folder opened', data.path || 'Location opened in Explorer.')
    else toast.warning('Could not open folder', data.error || 'No path available.')
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

function needsManualVerification(confidence) {
  return !confidence || confidence === 'low' || confidence === 'unknown'
}

function isSuggestedDevice(dongle, deviceIndex) {
  return dongle._suggestions.some((suggestion) => suggestion.device_index === deviceIndex)
}

function saveBusyKey(suggestionKey, status) {
  return `${suggestionKey}:${status}`
}

function initializeLiveUi() {
  liveUi.evidenceOpen = {}
  liveUi.existingMatch = {}
  liveUi.notes = {}
  liveUi.register = {}
  liveUi.registerDongle = {}
  for (const dongle of liveDongles.value) {
    liveUi.registerDongle[dongle._key] = { model: '' }
    for (const suggestion of dongle._suggestions) {
      liveUi.evidenceOpen[suggestion._key] = false
      liveUi.notes[suggestion._key] = suggestion.reason || ''
      liveUi.register[suggestion._key] = {
        brand: suggestion.brand || '',
        model: suggestion.model || '',
      }
    }
  }
}

async function runLiveMatch() {
  liveScanning.value = true
  liveError.value = ''
  try {
    const { data } = await axios.post('/usb-scans/live-match')
    liveResult.value = data && typeof data === 'object' ? data : null
    if (data?.error) liveError.value = String(data.error)
    initializeLiveUi()
  } catch (requestError) {
    liveError.value = requestError.response?.data?.error || requestError.response?.data?.message || 'Live detection could not be completed.'
    liveResult.value = null
  } finally {
    liveScanning.value = false
  }
}

async function saveLiveMatch(dongle, suggestion, suggestionKey, status) {
  if (!suggestion.mouse_id || !dongle.dongle_id) return
  const accepted = await confirm(status === 'matched'
    ? {
      title: 'Confirm this match?',
      message: `Only continue if you physically tested ${deviceName(suggestion, 'this mouse')} with receiver #${dongle.dongle_id} and it worked.`,
      confirmText: 'Confirm match',
    }
    : {
      title: 'Save as suspected?',
      message: `This records a possible match between ${deviceName(suggestion, 'this mouse')} and receiver #${dongle.dongle_id}. A physical test is still required.`,
      confirmText: 'Save suspected',
    })
  if (!accepted) return

  liveSaveBusy.value = saveBusyKey(suggestionKey, status)
  try {
    await axios.post('/matches', {
      mouse_id: suggestion.mouse_id,
      dongle_id: dongle.dongle_id,
      status,
      notes: liveUi.notes[suggestionKey]?.trim() || suggestion.reason || null,
    })
    toast.success(status === 'matched' ? 'Match confirmed' : 'Suspected match saved', `${deviceName(suggestion, 'Mouse')} · receiver #${dongle.dongle_id}`)
    await runLiveMatch()
  } catch (requestError) {
    if (requestError.response?.status === 422 && requestError.response.data?.existing_match) {
      liveUi.existingMatch[suggestionKey] = requestError.response.data.existing_match
      toast.warning('Duplicate active match', requestError.response.data.error || 'This device already has an active match.')
    }
  } finally {
    liveSaveBusy.value = ''
  }
}

function dismissLiveConflict(suggestionKey) {
  delete liveUi.existingMatch[suggestionKey]
}

function viewExistingMatch(match) {
  if (match?.id) router.push(`/matches?highlight=${match.id}`)
}

async function registerLiveDongle(dongle) {
  const model = (liveUi.registerDongle[dongle._key]?.model || '').trim()
  liveRegisterBusy.value = dongle._key
  try {
    const { data } = await axios.post('/dongles', {
      vid: dongle.vid || null,
      pid: dongle.pid || null,
      model: model || null,
      notes: 'Registered from Live Pairing detection. Edit brand/model if known.',
    })
    toast.success('Receiver registered', data?.dongle_id || deviceName(data, 'Receiver'))
    await runLiveMatch()
  } catch {
    // handled by interceptor
  } finally {
    liveRegisterBusy.value = ''
  }
}

async function registerLiveMouse(suggestionKey) {
  const form = liveUi.register[suggestionKey]
  const brand = (form?.brand || '').trim()
  const model = (form?.model || '').trim()
  if (!brand || !model) {
    toast.warning('Brand and model required', 'Enter both a brand and a model before registering.')
    return
  }
  liveRegisterBusy.value = suggestionKey
  try {
    const { data } = await axios.post('/mice', { brand, model })
    toast.success('Mouse registered', deviceName(data, 'Mouse'))
    await runLiveMatch()
  } catch {
    // handled by interceptor
  } finally {
    liveRegisterBusy.value = ''
  }
}

onMounted(() => {
  loadScans()
  loadDownloads()
})
</script>
