<template>
  <div class="space-y-4">
    <!-- Range -->
    <div class="flex items-end gap-3 flex-wrap">
      <div>
        <label class="form-label">From</label>
        <input v-model="from" type="date" class="form-input" />
      </div>
      <div>
        <label class="form-label">To</label>
        <input v-model="to" type="date" class="form-input" />
      </div>
      <button type="button" @click="load" class="btn-primary">Apply</button>
      <div class="flex gap-1.5 ml-auto">
        <button v-for="p in [['today','Today'],['week','7 days'],['month','This month']]" :key="p[0]" type="button" @click="preset(p[0])"
          class="px-3 py-2 rounded-lg bg-gray-100 text-xs font-bold text-gray-600 hover:bg-gray-200">{{ p[1] }}</button>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ error }}</p>

    <!-- Summary -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="rounded-2xl bg-amber-500 text-white px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase opacity-90">Ingredient cost used</p>
        <p class="text-2xl font-black">LKR {{ money(data.totals.cost_used) }}</p>
      </div>
      <div class="rounded-2xl px-5 py-4 shadow-sm text-white" :class="data.totals.low_count ? 'bg-red-500' : 'bg-green-500'">
        <p class="text-xs font-semibold uppercase opacity-90">Low stock items</p>
        <p class="text-2xl font-black">{{ data.totals.low_count }}</p>
      </div>
      <div class="rounded-2xl bg-slate-800 text-white px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase opacity-90">Period</p>
        <p class="text-2xl font-black">{{ data.days }} day{{ data.days === 1 ? '' : 's' }}</p>
      </div>
    </div>

    <!-- Ingredient usage -->
    <div class="card overflow-hidden p-0">
      <div class="px-4 py-3 border-b font-bold text-gray-800">Ingredient usage</div>
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-800 text-white text-xs uppercase">
            <th class="table-th text-left">Ingredient</th>
            <th class="table-th text-right">In stock</th>
            <th class="table-th text-right">Purchased</th>
            <th class="table-th text-right">Used in sales</th>
            <th class="table-th text-right">Wasted</th>
            <th class="table-th text-right">Avg / day</th>
            <th class="table-th text-right">Days left</th>
            <th class="table-th text-right">Cost used</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="8" class="table-td text-center text-gray-400 py-8">Loading…</td></tr>
          <tr v-else-if="!data.rows.length"><td colspan="8" class="table-td text-center text-gray-400 py-8">No ingredients yet.</td></tr>
          <tr v-for="r in data.rows" :key="r.id" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="table-td font-semibold text-gray-800">{{ r.name }}</td>
            <td class="table-td text-right">
              <span class="inline-block px-2 py-0.5 rounded-md text-xs font-bold" :class="r.low ? 'bg-red-500 text-white' : 'bg-green-100 text-green-700'">
                {{ fmt(r.stock) }} {{ r.unit }}
              </span>
            </td>
            <td class="table-td text-right text-gray-600">{{ fmt(r.purchased) }} {{ r.unit }}</td>
            <td class="table-td text-right font-bold text-amber-600">{{ fmt(r.used) }} {{ r.unit }}</td>
            <td class="table-td text-right text-gray-600">{{ fmt(r.wasted) }} {{ r.unit }}</td>
            <td class="table-td text-right text-gray-600">{{ fmt(r.avg_per_day) }}</td>
            <td class="table-td text-right font-semibold" :class="r.days_left !== null && r.days_left < 3 ? 'text-red-600' : 'text-gray-700'">
              {{ r.days_left === null ? '—' : r.days_left }}
            </td>
            <td class="table-td text-right text-gray-600">LKR {{ money(r.cost_used) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Usage by dish -->
    <div class="card overflow-hidden p-0">
      <div class="px-4 py-3 border-b font-bold text-gray-800">What each dish used</div>
      <div v-if="!data.by_dish.length" class="text-center text-gray-400 py-8 text-sm">No recipe dishes sold in this period.</div>
      <div v-else class="divide-y divide-gray-100">
        <div v-for="d in data.by_dish" :key="d.dish" class="px-4 py-3 flex items-start justify-between gap-4 flex-wrap">
          <div>
            <p class="font-semibold text-gray-800">{{ d.dish }}</p>
            <p class="text-xs text-gray-400">{{ fmt(d.sold) }} sold</p>
          </div>
          <div class="flex flex-wrap gap-1.5 justify-end">
            <span v-for="i in d.ingredients" :key="i.name" class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold">
              {{ i.name }}: {{ fmt(i.used) }} {{ i.unit }}
            </span>
          </div>
        </div>
      </div>
      <p class="px-4 py-2 text-[11px] text-gray-400 border-t">Dish figures use the current recipes; the table above comes from the stock ledger.</p>
    </div>
  </div>
</template>

<script setup>
import { useUsage, fmt, money } from '@/composables/useKitchenStock'

const { from, to, data, loading, error, load, preset } = useUsage()
</script>
