<template>
  <div class="space-y-4">
    <!-- Toolbar -->
    <div class="flex items-center gap-3 flex-wrap">
      <div class="relative">
        <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input v-model="search" type="text" placeholder="Search ingredient…" class="form-input pl-9 w-64" />
      </div>
      <button type="button" @click="onlyLow = !onlyLow"
        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold transition-colors"
        :class="onlyLow ? 'bg-red-500 text-white' : 'bg-red-50 text-red-600 hover:bg-red-100'">
        <ExclamationTriangleIcon class="w-4 h-4" /> Low stock <span>{{ lowCount }}</span>
      </button>
      <button type="button" @click="openForm()" class="btn-primary ml-auto inline-flex items-center gap-1.5">
        <PlusIcon class="w-4 h-4" /> Add Ingredient
      </button>
    </div>

    <p v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ error }}</p>

    <div class="card overflow-hidden p-0">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-800 text-white text-xs uppercase">
            <th class="table-th text-left">Ingredient</th>
            <th class="table-th text-right">In stock</th>
            <th class="table-th text-right">Min level</th>
            <th class="table-th text-right">Cost / unit</th>
            <th class="table-th text-center">Used in</th>
            <th class="table-th text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="6" class="table-td text-center text-gray-400 py-10">Loading…</td></tr>
          <tr v-else-if="!filtered.length">
            <td colspan="6" class="table-td text-center text-gray-400 py-10">
              {{ items.length ? 'No ingredients match.' : 'No ingredients yet. Add flour, butter, eggs… then build recipes.' }}
            </td>
          </tr>
          <tr v-for="i in filtered" :key="i.id" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="table-td font-semibold text-gray-800">{{ i.name }}</td>
            <td class="table-td text-right">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg font-bold text-xs"
                :class="i.low ? 'bg-red-500 text-white' : 'bg-green-100 text-green-700'">
                {{ fmt(i.stock_quantity) }} {{ i.unit }}
              </span>
            </td>
            <td class="table-td text-right text-gray-500">{{ fmt(i.min_stock_level) }} {{ i.unit }}</td>
            <td class="table-td text-right text-gray-500">LKR {{ money(i.purchase_price) }}</td>
            <td class="table-td text-center text-gray-500">{{ i.used_in }} recipe{{ i.used_in === 1 ? '' : 's' }}</td>
            <td class="table-td text-right whitespace-nowrap">
              <button type="button" @click="openStock(i, 'add')"
                class="px-2.5 py-1 rounded-lg bg-green-500 text-white text-xs font-bold hover:bg-green-600">+ Receive</button>
              <button type="button" @click="openStock(i, 'set')"
                class="ml-1 px-2.5 py-1 rounded-lg bg-blue-500 text-white text-xs font-bold hover:bg-blue-600">Count</button>
              <button type="button" @click="openForm(i)" title="Edit"
                class="ml-1 p-1.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600 align-middle"><PencilSquareIcon class="w-4 h-4" /></button>
              <button type="button" @click="remove(i)" title="Delete"
                class="ml-1 p-1.5 rounded-lg bg-red-500 text-white hover:bg-red-600 align-middle"><TrashIcon class="w-4 h-4" /></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add / edit ingredient -->
    <div v-if="form.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="form.show = false">
      <form @submit.prevent="saveForm" class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b">
          <h3 class="font-semibold">{{ form.id ? 'Edit Ingredient' : 'Add Ingredient' }}</h3>
          <button type="button" @click="form.show = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <div class="p-5 grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="form-label">Name *</label>
            <input v-model="form.name" required class="form-input" placeholder="e.g. Wheat Flour" />
          </div>
          <div>
            <label class="form-label">Unit *</label>
            <select v-model="form.unit" class="form-input">
              <option v-for="u in UNITS" :key="u" :value="u">{{ u }}</option>
            </select>
          </div>
          <div v-if="!form.id">
            <label class="form-label">Opening stock</label>
            <input v-model.number="form.stock_quantity" type="number" min="0" step="0.001" class="form-input" />
          </div>
          <div>
            <label class="form-label">Alert when below</label>
            <input v-model.number="form.min_stock_level" type="number" min="0" step="1" class="form-input" />
          </div>
          <div>
            <label class="form-label">Cost per {{ form.unit }} (LKR)</label>
            <input v-model.number="form.purchase_price" type="number" min="0" step="0.01" class="form-input" />
          </div>
          <p class="col-span-2 text-xs text-gray-400">
            Tip: use a small unit (g, ml) when recipes need small amounts, e.g. 150 g of flour per kottu.
          </p>
          <p v-if="form.error" class="col-span-2 text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ form.error }}</p>
        </div>
        <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t">
          <button type="button" @click="form.show = false" class="btn-secondary">Cancel</button>
          <button type="submit" :disabled="form.saving" class="btn-primary">{{ form.saving ? 'Saving…' : 'Save' }}</button>
        </div>
      </form>
    </div>

    <!-- Receive stock / physical count -->
    <div v-if="stock.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="stock.show = false">
      <form @submit.prevent="saveStock" class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
        <div class="px-5 py-4 border-b" :class="stock.mode === 'add' ? 'bg-green-500 text-white' : 'bg-blue-500 text-white'">
          <h3 class="font-semibold">{{ stock.mode === 'add' ? 'Receive stock' : 'Stock count' }} — {{ stock.item.name }}</h3>
          <p class="text-xs opacity-90">Currently {{ fmt(stock.item.stock_quantity) }} {{ stock.item.unit }}</p>
        </div>
        <div class="p-5 space-y-3">
          <div>
            <label class="form-label">{{ stock.mode === 'add' ? `Quantity received (${stock.item.unit})` : `Counted quantity (${stock.item.unit})` }}</label>
            <input v-model="stock.quantity" required type="number" min="0" step="0.001" class="form-input text-lg font-bold" autofocus />
            <p v-if="stock.mode === 'set'" class="text-xs text-gray-400 mt-1">Sets stock to this exact amount and records the difference.</p>
          </div>
          <div>
            <label class="form-label">Note (optional)</label>
            <input v-model="stock.notes" class="form-input" :placeholder="stock.mode === 'add' ? 'e.g. Supplier delivery' : 'e.g. Weekly count'" />
          </div>
          <p v-if="stock.error" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ stock.error }}</p>
        </div>
        <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t">
          <button type="button" @click="stock.show = false" class="btn-secondary">Cancel</button>
          <button type="submit" :disabled="stock.saving" class="btn-primary">{{ stock.saving ? 'Saving…' : 'Save' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ExclamationTriangleIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useIngredients, UNITS, fmt, money } from '@/composables/useKitchenStock'

const {
  loading, error, search, onlyLow, filtered, items, lowCount, form, stock,
  openForm, saveForm, openStock, saveStock, remove,
} = useIngredients()
</script>
