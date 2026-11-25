<template>
  <div class="flex flex-col" style="height: calc(100vh - 60px); background:#f1f5f9">

    <!-- Top bar -->
    <div class="flex items-center gap-3 px-4 py-2 bg-white border-b-2 border-amber-200 shrink-0 shadow-sm flex-wrap">
      <h2 class="text-sm font-bold text-gray-800">Floor Plan</h2>

      <!-- Section tabs -->
      <div class="flex gap-1.5 flex-wrap ml-2">
        <button
          v-for="sec in ['All', ...sections]" :key="sec"
          @click="activeSection = sec"
          class="px-3 py-1 rounded-full text-xs font-semibold transition-colors"
          :class="activeSection === sec ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
        >{{ sec }}</button>
      </div>

      <!-- Legend -->
      <div class="ml-auto flex items-center gap-3 text-xs text-gray-500">
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-green-400 inline-block"></span>Available</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-orange-400 inline-block"></span>Occupied</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-purple-400 inline-block"></span>Reserved</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-gray-300 inline-block"></span>Maintenance</span>
        <button @click="refresh" :disabled="loading" class="ml-2 px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 font-semibold transition-colors disabled:opacity-40">
          <ArrowPathIcon :class="loading ? 'animate-spin' : ''" class="w-4 h-4 inline" /> Refresh
        </button>
      </div>
    </div>

    <!-- Floor canvas -->
    <div class="flex-1 overflow-auto p-4">
      <div v-if="loading" class="flex items-center justify-center h-full text-gray-400">
        <ArrowPathIcon class="w-8 h-8 animate-spin mr-2" /> Loading floor plan…
      </div>
      <div v-else-if="!filteredTables.length" class="flex flex-col items-center justify-center h-full text-gray-400 gap-3">
        <Squares2X2Icon class="w-14 h-14 opacity-20" />
        <p class="text-sm">No tables to display. Add tables with floor plan positions from the Tables page.</p>
      </div>
      <template v-else>
        <!-- Grid-based floor plan -->
        <div
          class="relative bg-white rounded-2xl border-2 border-gray-200 shadow-inner overflow-hidden"
          :style="canvasStyle"
        >
          <!-- Grid lines (subtle) -->
          <svg class="absolute inset-0 w-full h-full pointer-events-none" style="opacity:0.04">
            <defs>
              <pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse">
                <path d="M 60 0 L 0 0 0 60" fill="none" stroke="#6b7280" stroke-width="1"/>
              </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)" />
          </svg>

          <!-- Tables -->
          <div
            v-for="table in filteredTables"
            :key="table.id"
            class="absolute flex flex-col items-center justify-center cursor-pointer select-none transition-transform hover:scale-105 hover:z-10"
            :style="tableStyle(table)"
            @click="selectTable(table)"
          >
            <!-- Shape rendering -->
            <div
              class="flex flex-col items-center justify-center w-full h-full font-bold text-white text-sm shadow-md relative"
              :class="[statusColorClass(table.status), table.shape === 'circle' ? 'rounded-full' : 'rounded-xl']"
            >
              <!-- Active sale indicator -->
              <span v-if="table.active_sale" class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-amber-400 rounded-full border-2 border-white text-[8px] flex items-center justify-center font-black text-amber-900">!</span>

              <span class="text-xs font-black leading-tight">{{ table.table_number }}</span>
              <span class="text-[10px] opacity-80">{{ table.capacity }}p</span>

              <!-- Active sale mini info -->
              <div v-if="table.active_sale" class="absolute bottom-0.5 left-0 right-0 text-center leading-tight">
                <span class="text-[8px] opacity-90">{{ lkr(table.active_sale.total) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Fallback: tables without positions shown as cards below -->
        <div v-if="tablesWithoutPos.length" class="mt-6">
          <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-2">Tables without floor positions</p>
          <div class="flex flex-wrap gap-3">
            <div
              v-for="table in tablesWithoutPos"
              :key="table.id"
              @click="selectTable(table)"
              class="cursor-pointer flex flex-col items-center justify-center w-20 h-20 rounded-xl font-bold text-white text-sm shadow-md transition-transform hover:scale-105"
              :class="statusColorClass(table.status)"
            >
              <span class="text-xs font-black">{{ table.table_number }}</span>
              <span class="text-[10px] opacity-80">{{ table.capacity }}p</span>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Table detail sidebar -->
    <transition name="slide-in">
      <div
        v-if="selected"
        class="fixed inset-y-0 right-0 w-80 bg-white shadow-2xl border-l border-gray-200 z-50 flex flex-col"
        style="top:60px"
      >
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <div>
            <h3 class="text-base font-bold text-gray-900">Table {{ selected.table_number }}</h3>
            <p class="text-xs text-gray-400 mt-0.5 capitalize">{{ selected.status }} · {{ selected.capacity }} seats</p>
          </div>
          <button @click="selected = null" class="text-gray-400 hover:text-gray-700 text-xl leading-none">✕</button>
        </div>

        <!-- Active sale info -->
        <div class="flex-1 overflow-y-auto p-5 space-y-4">
          <div v-if="selected.active_sale" class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-amber-700 uppercase tracking-wide">Active Order</span>
              <span class="text-xs font-mono text-amber-600 bg-amber-100 px-2 py-0.5 rounded">{{ selected.active_sale.invoice_number }}</span>
            </div>
            <div class="text-2xl font-black text-amber-800">LKR {{ lkr(selected.active_sale.total) }}</div>
            <div class="grid grid-cols-2 gap-1 text-xs text-gray-600">
              <span>Items: <strong>{{ selected.active_sale.items_count }}</strong></span>
              <span v-if="selected.active_sale.order_type" class="capitalize">Type: <strong>{{ selected.active_sale.order_type?.replace('_', ' ') }}</strong></span>
              <span v-if="selected.active_sale.guest_count">Guests: <strong>{{ selected.active_sale.guest_count }}</strong></span>
              <span v-if="selected.active_sale.waiter">Waiter: <strong>{{ selected.active_sale.waiter }}</strong></span>
            </div>
            <div class="flex gap-2 pt-1">
              <router-link
                :to="'/sales/new?draft=' + selected.active_sale.id"
                class="flex-1 text-center py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold transition-colors"
              >Edit Order</router-link>
              <router-link
                :to="'/sales/' + selected.active_sale.id"
                class="flex-1 text-center py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold transition-colors"
              >View Bill</router-link>
            </div>
          </div>
          <div v-else class="rounded-xl border border-green-200 bg-green-50 p-4 text-center">
            <p class="text-sm font-semibold text-green-700">Table is {{ selected.status }}</p>
            <router-link
              :to="'/sales/new'"
              class="mt-3 inline-block px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold transition-colors"
            >New Order for this Table</router-link>
          </div>

          <!-- Table metadata -->
          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-gray-500"><span>Section</span><span class="font-medium text-gray-800">{{ selected.section || '—' }}</span></div>
            <div class="flex justify-between text-gray-500"><span>Shape</span><span class="font-medium text-gray-800 capitalize">{{ selected.shape || 'rectangle' }}</span></div>
            <div class="flex justify-between text-gray-500"><span>Notes</span><span class="font-medium text-gray-800">{{ selected.notes || '—' }}</span></div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Overlay when sidebar open -->
    <div v-if="selected" class="fixed inset-0 z-40 bg-black/20" @click="selected = null" style="top:60px"></div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ArrowPathIcon, Squares2X2Icon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const floorData   = ref({})
const loading     = ref(false)
const selected    = ref(null)
const activeSection = ref('All')

const CELL = 60   // px per grid cell

async function refresh() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/tables/floor-plan')
    floorData.value = data
  } finally { loading.value = false }
}

const allTables = computed(() => {
  return Object.values(floorData.value).flat()
})

const sections = computed(() => Object.keys(floorData.value).filter(Boolean))

const filteredTables = computed(() => {
  const all = allTables.value.filter(t => t.pos_x != null && t.pos_y != null)
  if (activeSection.value === 'All') return all
  return all.filter(t => t.section === activeSection.value)
})

const tablesWithoutPos = computed(() => {
  const all = allTables.value.filter(t => t.pos_x == null || t.pos_y == null)
  if (activeSection.value === 'All') return all
  return all.filter(t => t.section === activeSection.value)
})

const canvasStyle = computed(() => {
  if (!filteredTables.value.length) return { width: '800px', height: '500px' }
  const maxX = Math.max(...filteredTables.value.map(t => (t.pos_x || 0) + (t.width || 2))) * CELL + 40
  const maxY = Math.max(...filteredTables.value.map(t => (t.pos_y || 0) + (t.height || 2))) * CELL + 40
  return { width: Math.max(maxX, 800) + 'px', height: Math.max(maxY, 400) + 'px' }
})

function tableStyle(table) {
  const x = (table.pos_x || 0) * CELL + 20
  const y = (table.pos_y || 0) * CELL + 20
  const w = (table.width || 2) * CELL - 8
  const h = (table.height || 2) * CELL - 8
  return {
    left: x + 'px',
    top: y + 'px',
    width: w + 'px',
    height: h + 'px',
  }
}

function statusColorClass(status) {
  return {
    available:   'bg-green-500 hover:bg-green-600',
    occupied:    'bg-orange-500 hover:bg-orange-600',
    reserved:    'bg-purple-500 hover:bg-purple-600',
    maintenance: 'bg-gray-400 hover:bg-gray-500',
  }[status] ?? 'bg-gray-400'
}

function selectTable(table) {
  selected.value = table
}

function lkr(val) {
  return Number(val || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

onMounted(refresh)
</script>

<style scoped>
.slide-in-enter-active, .slide-in-leave-active { transition: transform 0.2s ease; }
.slide-in-enter-from, .slide-in-leave-to { transform: translateX(100%); }
</style>
