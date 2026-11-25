<template>
  <div class="flex flex-col flex-1 min-w-0 overflow-hidden" style="background:#f1f5f9">

    <!-- Toolbar -->
    <div class="bg-white border-b border-gray-200 shrink-0 shadow-sm">
      <div class="flex items-center gap-3 px-5 pt-3 pb-2 flex-wrap">
        <div class="mr-2">
          <h3 class="text-base font-black text-gray-900 leading-tight">Select a Table</h3>
          <p class="text-[11px] text-gray-400">
            Tap a table to start or continue its order
            <span v-if="updatedAt"> · updated {{ updatedAt.toLocaleTimeString('en-LK', { hour: '2-digit', minute: '2-digit' }) }}</span>
          </p>
        </div>

        <!-- Status filters with live counts -->
        <div class="flex items-center gap-1.5 flex-wrap">
          <button type="button" @click="statusFilter = 'all'"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-colors"
            :class="statusFilter === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
            All <span class="opacity-70">{{ counts.all }}</span>
          </button>
          <button v-for="s in STATUS_FILTERS" :key="s" type="button" @click="statusFilter = statusFilter === s ? 'all' : s"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-colors"
            :class="statusFilter === s ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
            <span class="w-2 h-2 rounded-full" :class="STATUS_META[s].dot"></span>
            {{ STATUS_META[s].label }} <span class="opacity-70">{{ counts[s] }}</span>
          </button>
        </div>

        <!-- Money on open bills -->
        <div v-if="openSummary.bills" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-orange-500 text-white shadow-sm">
          <ShoppingBagIcon class="w-4 h-4" />
          <span class="text-xs font-bold">{{ openSummary.bills }} open · LKR {{ lkr(openSummary.total) }}</span>
        </div>

        <div class="ml-auto flex items-center gap-2">
          <div class="relative">
            <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            <input v-model="search" type="text" placeholder="Find table…"
              class="w-40 pl-8 pr-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:border-amber-500" />
          </div>
          <button type="button" @click="load()" :disabled="loading"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-600 transition-colors disabled:opacity-40">
            <ArrowPathIcon class="w-4 h-4" :class="loading ? 'animate-spin' : ''" /> Refresh
          </button>
        </div>
      </div>

      <!-- Section tabs -->
      <div v-if="sections.length" class="flex gap-1.5 px-5 pb-2.5 overflow-x-auto">
        <button v-for="sec in ['All', ...sections]" :key="sec" type="button" @click="section = sec"
          class="px-3.5 py-1 rounded-lg text-xs font-bold whitespace-nowrap transition-colors"
          :class="section === sec ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100'">
          {{ sec }}
        </button>
      </div>
    </div>

    <!-- Tables -->
    <div class="flex-1 overflow-y-auto p-5">
      <div v-if="loading && !tables.length" class="flex items-center justify-center h-48 text-gray-400">
        <ArrowPathIcon class="w-6 h-6 animate-spin mr-2" /> Loading tables…
      </div>

      <div v-else-if="error" class="max-w-md mx-auto mt-12 text-center text-sm text-red-600 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
        {{ error }}
      </div>

      <div v-else-if="!tables.length" class="flex flex-col items-center justify-center h-64 text-gray-400 gap-3">
        <Squares2X2Icon class="w-14 h-14 opacity-30" />
        <p class="text-sm">No tables found. Add tables from the Tables page.</p>
      </div>

      <div v-else-if="!filtered.length" class="flex flex-col items-center justify-center h-48 text-gray-400 gap-2">
        <MagnifyingGlassIcon class="w-10 h-10 opacity-30" />
        <p class="text-sm">No tables match this filter.</p>
      </div>

      <div v-else class="space-y-6">
        <section v-for="g in groups" :key="g.name">
          <h4 v-if="g.name || groups.length > 1" class="flex items-center gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">
            {{ g.name || 'Other tables' }}
            <span class="flex-1 h-px bg-gray-200"></span>
          </h4>

          <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(190px,1fr))">
            <button
              v-for="t in g.tables" :key="t.id" type="button"
              @click="state(t) !== 'maintenance' && $emit('select', String(t.table_number))"
              :disabled="state(t) === 'maintenance'"
              class="group flex flex-col text-left bg-white rounded-2xl border-2 overflow-hidden shadow-md transition-all duration-150 hover:-translate-y-1 active:scale-[0.97]"
              :class="STATUS_META[state(t)].card"
            >
              <!-- Solid status header -->
              <div class="flex items-center justify-between gap-2 px-4 py-3 text-white" :class="STATUS_META[state(t)].head">
                <div class="flex items-center gap-2.5 min-w-0">
                  <span class="shrink-0 w-9 h-9 rounded-full bg-white/25 flex items-center justify-center">
                    <TableCellsIcon class="w-5 h-5" />
                  </span>
                  <p class="text-lg font-black leading-tight truncate">{{ t.table_number }}</p>
                </div>
                <span class="shrink-0 text-[10px] font-extrabold uppercase tracking-wide bg-white/25 px-2 py-0.5 rounded-full">
                  {{ STATUS_META[state(t)].label }}
                </span>
              </div>

              <div class="px-4 py-3 flex-1 flex flex-col justify-center min-h-[5.5rem]">
                <!-- Seats -->
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                  <span class="flex -space-x-1">
                    <span v-for="n in Math.min(t.capacity, 6)" :key="n"
                      class="w-4 h-4 rounded-full border-2 border-white" :class="STATUS_META[state(t)].dot"></span>
                  </span>
                  <span class="font-semibold">{{ t.capacity }} seats</span>
                </div>

                <!-- Open bill -->
                <template v-if="t.active_sale">
                  <p class="text-xl font-black text-gray-900 leading-tight mt-2">LKR {{ lkr(t.active_sale.total) }}</p>
                  <div class="flex items-center gap-x-3 gap-y-1 flex-wrap text-[11px] font-semibold mt-1.5">
                    <span class="inline-flex items-center gap-1 text-gray-500">
                      <ShoppingBagIcon class="w-3.5 h-3.5" />{{ t.active_sale.item_count }} item{{ t.active_sale.item_count === 1 ? '' : 's' }}
                    </span>
                    <span v-if="openFor(t)" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md"
                      :class="isOverdue(t) ? 'bg-red-500 text-white animate-pulse' : 'bg-gray-100 text-gray-600'">
                      <ClockIcon class="w-3.5 h-3.5" />{{ openFor(t) }}
                    </span>
                    <span v-if="t.active_sale.waiter?.name" class="inline-flex items-center gap-1 text-gray-500 truncate">
                      <UserIcon class="w-3.5 h-3.5" />{{ t.active_sale.waiter.name }}
                    </span>
                  </div>
                </template>
                <p v-else-if="state(t) === 'occupied'" class="text-[11px] text-gray-400 mt-2">No open bill — tap to start one</p>
                <p v-else-if="state(t) === 'available'"
                  class="mt-2 inline-flex items-center justify-center gap-1 self-start px-3 py-1.5 rounded-lg bg-green-500 text-white text-xs font-bold group-hover:bg-green-600 transition-colors">
                  <PlusIcon class="w-4 h-4" /> Start order
                </p>
              </div>
            </button>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  ArrowPathIcon, ClockIcon, MagnifyingGlassIcon, PlusIcon, ShoppingBagIcon,
  Squares2X2Icon, TableCellsIcon, UserIcon,
} from '@heroicons/vue/24/outline'
import { useDineInFloor, tableState as state, STATUS_META, STATUS_FILTERS } from '@/composables/useDineInFloor'
import { lkr } from '@/composables/useDineInOrder'

defineEmits(['select'])

const {
  tables, loading, error, section, statusFilter, search, updatedAt,
  sections, counts, filtered, groups, openSummary, load, openFor, isOverdue,
} = useDineInFloor()
</script>
