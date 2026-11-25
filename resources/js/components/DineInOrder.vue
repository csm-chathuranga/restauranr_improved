<template>
  <div class="flex flex-1 overflow-hidden min-w-0" style="background:#f1f5f9">

    <!-- ── LEFT: Menu ── -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

      <!-- Table bar + search -->
      <div class="flex items-center gap-3 px-4 py-2.5 bg-white border-b border-gray-200 shrink-0 shadow-sm">
        <button @click="$emit('back')" type="button"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors shrink-0">
          <ArrowLeftIcon class="w-4 h-4" /> Tables
        </button>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-50 border border-amber-300 text-amber-700 font-bold text-sm shrink-0">
          🍽️ {{ tableLabel }}
        </div>
        <div class="relative flex-1 max-w-md">
          <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input v-model="search" type="text" placeholder="Search menu…"
            class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:border-amber-500" />
        </div>
      </div>

      <!-- Category pills -->
      <div class="flex gap-2 px-4 py-2 bg-white border-b border-gray-200 overflow-x-auto shrink-0">
        <button v-for="cat in categories" :key="cat" @click="activeCategory = cat" type="button"
          class="px-4 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-colors"
          :class="activeCategory === cat ? 'bg-amber-500 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
          {{ cat }}
        </button>
      </div>

      <!-- Menu grid -->
      <div class="flex-1 overflow-y-auto p-4">
        <div v-if="loadingProducts || loadingOrder" class="flex items-center justify-center h-40 text-gray-400">
          <ArrowPathIcon class="w-6 h-6 animate-spin mr-2" /> Loading menu…
        </div>
        <div v-else-if="!menu.length" class="flex items-center justify-center h-40 text-gray-400 text-sm">
          No items found.
        </div>
        <div v-else class="grid gap-3" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
          <button
            v-for="p in menu" :key="p.id" type="button"
            @click="addProduct(p)"
            :disabled="isOutOfStock(p)"
            class="relative flex flex-col text-left rounded-2xl overflow-hidden bg-white border-2 shadow-sm transition-all select-none"
            :class="isOutOfStock(p)
              ? 'border-gray-100 opacity-40 cursor-not-allowed'
              : qtyOf(p.id) > 0
                ? 'border-amber-400 shadow-md shadow-amber-100'
                : 'border-gray-200 hover:border-amber-300 hover:shadow-md active:scale-95'"
          >
            <span v-if="qtyOf(p.id) > 0"
              class="absolute top-2 right-2 z-10 min-w-[1.5rem] h-6 px-1.5 rounded-full bg-amber-500 text-white text-xs font-bold flex items-center justify-center shadow">
              {{ qtyOf(p.id) }}
            </span>
            <div class="w-full aspect-[16/10] bg-gray-100 overflow-hidden">
              <img v-if="p.image" :src="p.image" :alt="p.name" class="w-full h-full object-cover" />
              <div v-else class="w-full h-full flex items-center justify-center text-3xl"
                style="background:linear-gradient(135deg,#fff7ed,#fed7aa)">🍴</div>
            </div>
            <div class="p-2.5">
              <p class="text-sm font-semibold text-gray-800 leading-tight line-clamp-2">{{ p.name }}</p>
              <p class="text-sm font-bold text-amber-600 mt-1">LKR {{ lkr(p.selling_price) }}</p>
              <span v-if="canMake(p) !== null"
                class="inline-block mt-1.5 px-2 py-0.5 rounded-md text-[10px] font-extrabold text-white"
                :class="canMake(p) === 0 ? 'bg-red-500' : canMake(p) <= 10 ? 'bg-amber-500' : 'bg-green-500'">
                {{ canMake(p) === 0 ? 'No ingredients' : `${canMake(p)} left` }}
              </span>
            </div>
          </button>
        </div>
      </div>
    </div>

    <!-- ── RIGHT: Table order ── -->
    <div class="flex flex-col w-[36rem] max-w-[48%] shrink-0 bg-white border-l border-gray-200 overflow-hidden">

      <!-- Header -->
      <div class="px-4 py-3 border-b border-gray-200 shrink-0" style="background:linear-gradient(135deg,#fffbeb,#fef3c7)">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide">Dine-in order</p>
            <h3 class="text-lg font-black text-gray-900 leading-tight">{{ tableLabel }}</h3>
          </div>
          <span class="text-[11px] font-bold px-2.5 py-1 rounded-full"
            :class="saleId ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700'">
            {{ saleId ? (invoiceNumber || 'Open order') : 'New order' }}
          </span>
        </div>

        <div class="flex items-center gap-2 mt-3 flex-wrap">
          <!-- Seats: table capacity and how many are free -->
          <div v-if="capacity" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-bold text-white"
            :class="overCapacity ? 'bg-red-500' : 'bg-amber-500'">
            <UsersIcon class="w-4 h-4" />
            <span>{{ capacity }} seats</span>
            <span class="opacity-80">·</span>
            <span>{{ overCapacity ? `${guests - capacity} over` : `${seatsLeft} free` }}</span>
          </div>
          <div class="flex items-center gap-1 bg-white rounded-lg border border-amber-200 px-1">
            <button @click="guests = Math.max(1, guests - 1)" type="button" class="w-6 h-7 text-gray-500 hover:text-amber-600 font-bold">−</button>
            <span class="text-xs font-bold text-gray-700 w-14 text-center">{{ guests }} guest{{ guests === 1 ? '' : 's' }}</span>
            <button @click="guests++" type="button" class="w-6 h-7 text-gray-500 hover:text-amber-600 font-bold">+</button>
          </div>
          <select v-if="waiters.length" v-model="waiterId" :disabled="isWaiterUser"
            class="flex-1 min-w-[8rem] px-2 py-1.5 rounded-lg border border-amber-200 bg-white text-xs font-medium text-gray-700 focus:outline-none focus:border-amber-500 disabled:bg-amber-50 disabled:text-amber-800">
            <option value="">Assign waiter…</option>
            <option v-for="w in waiters" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
      </div>

      <!-- Items -->
      <div class="flex-1 overflow-y-auto px-3 py-2 space-y-2">
        <div v-if="!items.length" class="flex flex-col items-center justify-center h-full text-gray-300 gap-2">
          <span class="text-5xl">🍽️</span>
          <p class="text-sm font-medium">Tap menu items to add them to this table</p>
        </div>

        <div v-for="(item, idx) in items" :key="item.product_id"
          class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="text-sm font-semibold text-gray-800 leading-tight">{{ item.name }}</p>
              <p class="text-xs text-gray-400">LKR {{ lkr(item.price) }}<span v-if="deposit(item) > 0"> · +{{ lkr(deposit(item)) }} deposit</span></p>
            </div>
            <button @click="removeItem(idx)" type="button" title="Remove"
              class="shrink-0 p-1.5 rounded-lg text-white bg-red-500 hover:bg-red-600 shadow-sm transition-colors">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
          <div class="flex items-center justify-between mt-2">
            <div class="flex items-center gap-1 bg-white rounded-lg border border-gray-200">
              <button @click="changeQty(item, -1)" type="button" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-red-500">
                <MinusIcon class="w-4 h-4" />
              </button>
              <span class="w-8 text-center text-sm font-bold text-gray-800">{{ item.quantity }}</span>
              <button @click="changeQty(item, 1)" type="button" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-green-600">
                <PlusIcon class="w-4 h-4" />
              </button>
            </div>
            <div class="flex items-center gap-2">
              <button @click="item.showNote = !item.showNote" type="button"
                class="text-[11px] font-semibold px-2 py-1 rounded-md border transition-colors"
                :class="item.note ? 'bg-amber-100 border-amber-300 text-amber-700' : 'border-gray-200 text-gray-400 hover:text-amber-600'">
                📝 Note
              </button>
              <span class="text-sm font-bold text-gray-900 w-24 text-right">{{ lkr(lineTotal(item)) }}</span>
            </div>
          </div>
          <p v-if="itemWarning(item)" class="mt-2 inline-flex items-center gap-1 px-2 py-1 rounded-md bg-red-500 text-white text-[11px] font-bold">
            <ExclamationTriangleIcon class="w-3.5 h-3.5" /> {{ itemWarning(item) }} — check ingredient stock
          </p>
          <div v-if="item.showNote" class="mt-2 space-y-1.5">
            <div class="flex flex-wrap gap-1">
              <button v-for="n in quickNotes" :key="n" type="button" @click="toggleNote(item, n)"
                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border transition-colors"
                :class="hasNote(item, n) ? 'bg-amber-500 border-amber-500 text-white' : 'bg-white border-gray-200 text-gray-500 hover:border-amber-300'">
                {{ n }}
              </button>
            </div>
            <input v-model="item.note" type="text" placeholder="Special instructions…"
              class="w-full px-2 py-1 rounded-md border border-gray-200 text-xs focus:outline-none focus:border-amber-500" />
          </div>
        </div>
      </div>

      <!-- Totals + actions -->
      <div class="shrink-0 border-t-2 border-amber-200 bg-white px-4 py-3 space-y-2">
        <div class="grid grid-cols-3 gap-2 text-[11px] text-gray-500 font-semibold">
          <label class="flex flex-col gap-0.5">Service %
            <input v-model.number="servicePct" type="number" min="0" max="100" @wheel.prevent
              class="px-2 py-1 rounded-md border border-gray-200 text-xs text-gray-800 focus:outline-none focus:border-amber-500" />
          </label>
          <label class="flex flex-col gap-0.5">Tax
            <select v-model="taxId" class="px-1 py-1 rounded-md border border-gray-200 text-xs text-gray-800 focus:outline-none focus:border-amber-500">
              <option value="">None</option>
              <option v-for="t in taxes" :key="t.id" :value="t.id">{{ t.name }} ({{ t.rate }}%)</option>
            </select>
          </label>
          <label class="flex flex-col gap-0.5">Discount
            <input v-model.number="discount" type="number" min="0" @wheel.prevent
              class="px-2 py-1 rounded-md border border-gray-200 text-xs text-gray-800 focus:outline-none focus:border-amber-500" />
          </label>
        </div>

        <div class="text-xs text-gray-500 space-y-0.5">
          <div class="flex justify-between"><span>Subtotal</span><span>LKR {{ lkr(subtotal) }}</span></div>
          <div v-if="serviceCharge > 0" class="flex justify-between"><span>Service charge</span><span>+ {{ lkr(serviceCharge) }}</span></div>
          <div v-if="taxAmount > 0" class="flex justify-between"><span>Tax</span><span>+ {{ lkr(taxAmount) }}</span></div>
          <div v-if="discountAmount > 0" class="flex justify-between text-green-600"><span>Discount</span><span>− {{ lkr(discountAmount) }}</span></div>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm font-bold text-gray-700">TOTAL</span>
          <span class="text-2xl font-black text-amber-600">LKR {{ lkr(total) }}</span>
        </div>

        <p v-if="error" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ error }}</p>

        <div class="grid grid-cols-3 gap-2">
          <button @click="holdOrder" :disabled="!items.length || saving" type="button"
            class="py-3 rounded-xl border-2 border-amber-400 text-amber-700 font-bold text-sm hover:bg-amber-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
            {{ saving ? 'Saving…' : '💾 Hold' }}
          </button>
          <button @click="printBill" :disabled="!items.length || saving" type="button"
            class="py-3 rounded-xl border-2 border-blue-400 text-blue-700 font-bold text-sm hover:bg-blue-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
            🖨️ Print Bill
          </button>
          <button @click="openPayment" :disabled="!items.length || saving" type="button"
            class="py-3 rounded-xl bg-amber-500 text-white font-bold text-sm hover:bg-amber-600 shadow-md shadow-amber-200 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
            💳 Pay
          </button>
        </div>
      </div>
    </div>

    <!-- ── Payment modal ── -->
    <div v-if="showPayment" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="showPayment = false">
      <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <div>
            <h3 class="text-base font-bold text-gray-900">Pay {{ tableLabel }}</h3>
            <p class="text-xs text-gray-400">Total LKR {{ lkr(total) }}</p>
          </div>
          <button @click="showPayment = false" type="button" class="w-8 h-8 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg text-xl leading-none">✕</button>
        </div>
        <div class="p-5 space-y-4">
          <div class="grid grid-cols-2 gap-2">
            <button v-for="m in ['cash', 'card']" :key="m" type="button" @click="selectMethod(m)"
              class="py-2.5 rounded-xl border-2 font-bold text-sm capitalize transition-all"
              :class="paymentMethod === m ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
              {{ m === 'cash' ? '💵 Cash' : '💳 Card' }}
            </button>
          </div>

          <div v-if="paymentMethod === 'cash'" class="space-y-2">
            <label class="text-xs font-semibold text-gray-500">Amount received</label>
            <input v-model.number="amountPaid" type="number" min="0" @wheel.prevent ref="amountRef"
              class="w-full px-3 py-2.5 rounded-xl border-2 border-gray-200 text-lg font-bold focus:outline-none focus:border-amber-500" />
            <div class="flex gap-1.5 flex-wrap">
              <button v-for="d in quickAmounts" :key="d" type="button" @click="amountPaid = d"
                class="px-3 py-1 rounded-lg bg-gray-100 text-xs font-semibold text-gray-600 hover:bg-amber-100 hover:text-amber-700">
                {{ lkr(d) }}
              </button>
            </div>
            <div class="flex justify-between text-sm font-bold" :class="changeDue > 0 ? 'text-green-600' : 'text-gray-400'">
              <span>Change</span><span>LKR {{ lkr(changeDue) }}</span>
            </div>
          </div>

          <div v-else class="space-y-2">
            <label class="text-xs font-semibold text-gray-500">Card reference (optional)</label>
            <input v-model="cardReference" type="text"
              class="w-full px-3 py-2.5 rounded-xl border-2 border-gray-200 text-sm focus:outline-none focus:border-amber-500" />
          </div>

          <div>
            <label class="text-xs font-semibold text-gray-500">Customer (optional)</label>
            <select v-model="customerId"
              class="w-full mt-1 px-3 py-2 rounded-xl border-2 border-gray-200 text-sm focus:outline-none focus:border-amber-500">
              <option value="">Walk-in customer</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <p v-if="error" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ error }}</p>

          <button @click="confirmPayment" :disabled="saving" type="button"
            class="w-full py-3 rounded-xl bg-green-500 text-white font-bold hover:bg-green-600 transition-colors disabled:opacity-50">
            {{ saving ? 'Processing…' : 'Confirm Payment' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  ArrowLeftIcon, ArrowPathIcon, ExclamationTriangleIcon, MagnifyingGlassIcon,
  MinusIcon, PlusIcon, TrashIcon, UsersIcon,
} from '@heroicons/vue/24/outline'
import { useDineInOrder } from '@/composables/useDineInOrder'

const props = defineProps({
  tableNumber:     { type: String, required: true },
  capacity:        { type: Number, default: 0 },
  products:        { type: Array, default: () => [] },
  customers:       { type: Array, default: () => [] },
  taxes:           { type: Array, default: () => [] },
  draftBills:      { type: Array, default: () => [] },
  loadingProducts: { type: Boolean, default: false },
})
const emit = defineEmits(['back', 'saved', 'printed', 'paid'])

const {
  items, saleId, invoiceNumber, loadingOrder, saving, error,
  guests, waiterId, waiters, isWaiterUser, customerId, tableLabel, seatsLeft, overCapacity,
  servicePct, taxId, discount, search, activeCategory,
  showPayment, paymentMethod, amountPaid, cardReference, amountRef,
  categories, menu, subtotal, serviceCharge, taxAmount, discountAmount, total, changeDue, quickAmounts,
  quickNotes, lkr, isOutOfStock, qtyOf, deposit, lineTotal, hasNote, canMake, itemWarning, warningCount,
  addProduct, changeQty, removeItem, toggleNote,
  holdOrder, printBill, openPayment, selectMethod, confirmPayment,
} = useDineInOrder(props, emit)
</script>
