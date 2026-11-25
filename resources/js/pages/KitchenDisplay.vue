<template>
  <div class="flex flex-col" style="min-height: calc(100vh - 60px); background:#111827">

    <!-- Header -->
    <div class="flex items-center gap-3 px-4 py-2.5 border-b border-gray-700 shrink-0 flex-wrap" style="background:#1f2937">
      <h2 class="text-sm font-bold text-white">Kitchen Display</h2>

      <!-- Station tabs -->
      <div class="flex gap-1.5 flex-wrap ml-2">
        <button
          v-for="s in stationOptions" :key="s.value"
          @click="activeStation = s.value"
          class="px-3 py-1 rounded-full text-xs font-semibold transition-colors"
          :class="activeStation === s.value ? 'bg-amber-500 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
        >{{ s.label }}</button>
      </div>

      <!-- Status filter -->
      <div class="flex gap-1.5 ml-2">
        <button
          v-for="s in statusOptions" :key="s.value"
          @click="toggleStatus(s.value)"
          class="px-2.5 py-1 rounded-full text-xs font-semibold transition-colors"
          :class="activeStatuses.includes(s.value) ? s.activeClass : 'bg-gray-700 text-gray-400'"
        >{{ s.label }}</button>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <span class="text-xs text-gray-400">Auto-refresh every 30s</span>
        <button @click="load" :disabled="loading" class="px-3 py-1 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-200 text-xs font-semibold disabled:opacity-40">
          <ArrowPathIcon :class="loading ? 'animate-spin' : ''" class="w-4 h-4 inline" /> Refresh
        </button>
      </div>
    </div>

    <!-- KOT grid -->
    <div class="flex-1 overflow-y-auto p-4">
      <div v-if="loading && !kots.length" class="flex items-center justify-center py-20 text-gray-500">
        <ArrowPathIcon class="w-6 h-6 animate-spin mr-2" /> Loading orders…
      </div>
      <div v-else-if="!kots.length" class="flex flex-col items-center justify-center py-20 text-gray-500 gap-3">
        <CheckCircleIcon class="w-14 h-14 opacity-30" />
        <p class="text-base font-semibold">All clear — no pending orders</p>
      </div>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div
          v-for="kot in kots"
          :key="kot.id"
          class="flex flex-col rounded-2xl overflow-hidden border transition-all"
          :class="kot.priority
            ? 'border-red-500 shadow-lg shadow-red-900/40'
            : 'border-gray-700'"
        >
          <!-- KOT header -->
          <div
            class="px-4 py-3 flex items-center justify-between"
            :class="statusHeaderClass(kot.status)"
          >
            <div>
              <div class="flex items-center gap-2">
                <span class="text-xs font-black text-white font-mono">{{ kot.kot_number }}</span>
                <span v-if="kot.priority" class="px-1.5 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-black">🔥 RUSH</span>
              </div>
              <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                <span v-if="kot.sale?.table_number" class="text-xs text-white/80">Table {{ kot.sale.table_number }}</span>
                <span class="text-xs text-white/60 capitalize">{{ kot.station }}</span>
                <span class="text-[10px] text-white/50">{{ timeAgo(kot.created_at) }}</span>
              </div>
            </div>
            <div class="flex flex-col items-end gap-1">
              <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full" :class="statusBadgeClass(kot.status)">
                {{ kot.status }}
              </span>
            </div>
          </div>

          <!-- Items -->
          <div class="flex-1 bg-gray-800 px-4 py-3 space-y-2 min-h-[80px]">
            <div
              v-for="item in kot.items"
              :key="item.id"
              class="flex items-start justify-between gap-2"
              :class="item.voided_at ? 'opacity-30 line-through' : ''"
            >
              <div class="flex items-baseline gap-2">
                <span class="text-lg font-black text-amber-400 leading-none w-7 shrink-0">×{{ item.quantity }}</span>
                <span class="text-sm font-semibold text-white leading-tight">{{ item.product?.name }}</span>
              </div>
              <div v-if="!item.voided_at && canVoid" class="shrink-0">
                <button
                  @click="startVoid(kot, item)"
                  class="text-[10px] px-1.5 py-0.5 rounded bg-red-800/60 text-red-300 hover:bg-red-700 transition-colors"
                >void</button>
              </div>
            </div>
            <div v-if="kot.notes" class="mt-2 text-xs text-yellow-300 italic bg-yellow-900/30 rounded px-2 py-1">
              Note: {{ kot.notes }}
            </div>
          </div>

          <!-- Action row -->
          <div class="bg-gray-900 px-3 py-2 flex gap-2">
            <button
              v-if="kot.status !== 'served'"
              @click="advance(kot)"
              :disabled="advancing === kot.id"
              class="flex-1 py-2 rounded-lg text-xs font-bold text-white transition-colors disabled:opacity-40"
              :class="nextActionClass(kot.status)"
            >
              <ArrowPathIcon v-if="advancing === kot.id" class="w-3 h-3 inline animate-spin" />
              {{ nextActionLabel(kot.status) }}
            </button>
            <span v-else class="flex-1 text-center py-2 text-xs text-green-400 font-bold">✓ Served</span>
          </div>
        </div>
      </div>

      <!-- Load more -->
      <div v-if="meta && meta.current_page < meta.last_page" class="flex justify-center mt-6">
        <button @click="loadMore" :disabled="loadingMore" class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40">
          <ArrowPathIcon v-if="loadingMore" class="w-4 h-4 inline animate-spin" /> Load more
        </button>
      </div>
    </div>

    <!-- Void reason modal -->
    <div v-if="voidModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div class="w-full max-w-sm bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-700 flex items-center justify-between">
          <h3 class="text-sm font-bold text-white">Void Item</h3>
          <button @click="voidModal.show = false" class="text-gray-400 hover:text-white text-xl">✕</button>
        </div>
        <div class="p-5 space-y-3">
          <p class="text-sm text-gray-300">Item: <strong class="text-white">{{ voidModal.item?.product?.name }}</strong> ×{{ voidModal.item?.quantity }}</p>
          <div>
            <label class="text-xs text-gray-400 mb-1 block">Void reason *</label>
            <textarea v-model="voidModal.reason" rows="3" class="w-full rounded-lg bg-gray-700 border border-gray-600 text-white text-sm px-3 py-2 focus:outline-none focus:border-amber-500" placeholder="Enter reason…" />
          </div>
          <p v-if="voidModal.error" class="text-xs text-red-400">{{ voidModal.error }}</p>
        </div>
        <div class="px-5 pb-5 flex gap-3">
          <button @click="voidModal.show = false" class="flex-1 py-2 rounded-lg text-sm font-semibold bg-gray-700 text-gray-300 hover:bg-gray-600">Cancel</button>
          <button @click="confirmVoid" :disabled="!voidModal.reason.trim() || voidModal.saving"
            class="flex-1 py-2 rounded-lg text-sm font-semibold bg-red-600 hover:bg-red-700 disabled:opacity-40 text-white">
            {{ voidModal.saving ? 'Voiding…' : 'Confirm Void' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { ArrowPathIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import axios from 'axios'

const auth    = useAuthStore()
const kots    = ref([])
const meta    = ref(null)
const loading = ref(false)
const loadingMore = ref(false)
const advancing   = ref(null)

const activeStation  = ref('all')
const activeStatuses = ref(['new', 'preparing', 'ready'])

const stationOptions = [
  { value: 'all',     label: 'All' },
  { value: 'kitchen', label: 'Kitchen' },
  { value: 'bar',     label: 'Bar' },
  { value: 'dessert', label: 'Dessert' },
  { value: 'cold',    label: 'Cold' },
]

const statusOptions = [
  { value: 'new',       label: 'New',       activeClass: 'bg-blue-600 text-white' },
  { value: 'preparing', label: 'Preparing', activeClass: 'bg-yellow-500 text-white' },
  { value: 'ready',     label: 'Ready',     activeClass: 'bg-green-600 text-white' },
  { value: 'served',    label: 'Served',    activeClass: 'bg-gray-500 text-white' },
]

const canVoid = computed(() => {
  const r = auth.user?.role
  return r === 'admin' || r === 'owner' || r === 'manager'
})

function toggleStatus(val) {
  if (activeStatuses.value.includes(val)) {
    if (activeStatuses.value.length > 1) activeStatuses.value = activeStatuses.value.filter(s => s !== val)
  } else {
    activeStatuses.value = [...activeStatuses.value, val]
  }
  load()
}

async function load() {
  loading.value = true
  try {
    const params = { per_page: 40 }
    if (activeStation.value !== 'all') params.station = activeStation.value
    if (activeStatuses.value.length === 1) params.status = activeStatuses.value[0]
    const { data } = await axios.get('/api/kitchen/orders', { params })
    kots.value = (data.data || []).filter(k => activeStatuses.value.includes(k.status))
    meta.value = data.meta
  } finally { loading.value = false }
}

async function loadMore() {
  if (!meta.value || meta.value.current_page >= meta.value.last_page) return
  loadingMore.value = true
  try {
    const params = { per_page: 40, page: meta.value.current_page + 1 }
    if (activeStation.value !== 'all') params.station = activeStation.value
    const { data } = await axios.get('/api/kitchen/orders', { params })
    kots.value = [...kots.value, ...(data.data || []).filter(k => activeStatuses.value.includes(k.status))]
    meta.value = data.meta
  } finally { loadingMore.value = false }
}

const STATUS_FLOW = { new: 'preparing', preparing: 'ready', ready: 'served' }

async function advance(kot) {
  const next = STATUS_FLOW[kot.status]
  if (!next) return
  advancing.value = kot.id
  try {
    await axios.patch(`/api/kitchen/orders/${kot.id}/status`, { status: next })
    kot.status = next
    if (next === 'served' && !activeStatuses.value.includes('served')) {
      kots.value = kots.value.filter(k => k.id !== kot.id)
    }
  } finally { advancing.value = null }
}

function nextActionLabel(status) {
  return { new: 'Start Preparing', preparing: 'Mark Ready', ready: 'Mark Served' }[status] ?? ''
}

function nextActionClass(status) {
  return { new: 'bg-blue-600 hover:bg-blue-700', preparing: 'bg-yellow-500 hover:bg-yellow-600', ready: 'bg-green-600 hover:bg-green-700' }[status] ?? 'bg-gray-600'
}

function statusHeaderClass(status) {
  return { new: 'bg-blue-700', preparing: 'bg-yellow-600', ready: 'bg-green-700', served: 'bg-gray-600' }[status] ?? 'bg-gray-600'
}

function statusBadgeClass(status) {
  return { new: 'bg-blue-500/30 text-blue-200', preparing: 'bg-yellow-500/30 text-yellow-200', ready: 'bg-green-500/30 text-green-200', served: 'bg-gray-500/30 text-gray-300' }[status] ?? ''
}

function timeAgo(ts) {
  const diff = Math.floor((Date.now() - new Date(ts)) / 1000)
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  return `${Math.floor(diff / 3600)}h ago`
}

// Void modal
const voidModal = ref({ show: false, kot: null, item: null, reason: '', saving: false, error: '' })

function startVoid(kot, item) {
  voidModal.value = { show: true, kot, item, reason: '', saving: false, error: '' }
}

async function confirmVoid() {
  if (!voidModal.value.reason.trim()) return
  voidModal.value.saving = true; voidModal.value.error = ''
  try {
    await axios.delete(`/api/kitchen/orders/${voidModal.value.kot.id}/items/${voidModal.value.item.id}`, {
      data: { void_reason: voidModal.value.reason }
    })
    voidModal.value.item.voided_at = new Date().toISOString()
    voidModal.value.show = false
  } catch (e) {
    voidModal.value.error = e.response?.data?.message ?? 'Could not void item'
  } finally { voidModal.value.saving = false }
}

let timer = null
onMounted(() => {
  load()
  timer = setInterval(load, 30000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>
