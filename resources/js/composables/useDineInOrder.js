import { ref, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

export const QUICK_NOTES = ['No Ice', 'Less Sugar', 'Extra Spicy', 'No Onion', 'Well Done', 'Medium', 'No Garnish']

const round2 = v => Math.round(v * 100) / 100

export function lkr(val) {
  return Number(val || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function errorMessage(e) {
  return e.response?.data?.message
    ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
    ?? 'An error occurred. Please try again.'
}

/**
 * State and actions for the dine-in table order screen.
 * `props` are the DineInOrder component props; `emit` is its emit function.
 */
export function useDineInOrder(props, emit) {
  const auth = useAuthStore()
  const isWaiterUser = auth.user?.role === 'waiter'

  // ── State ───────────────────────────────────────────
  const items         = ref([])
  const saleId        = ref(null)
  const invoiceNumber = ref('')
  const loadingOrder  = ref(false)
  const saving        = ref(false)
  const error         = ref('')

  const guests     = ref(1)
  const waiterId   = ref('')
  const waiters    = ref([])
  const customerId = ref('')
  const orderNotes = ref('')
  const servicePct = ref(0)
  const taxId      = ref('')
  const discount   = ref(0)

  const search         = ref('')
  const activeCategory = ref('All')

  const showPayment   = ref(false)
  const paymentMethod = ref('cash')
  const amountPaid    = ref('')
  const cardReference = ref('')
  const amountRef     = ref(null)

  // Table names are free text, so avoid "Table Table 1" when the name already says "Table"
  const tableLabel = computed(() =>
    /^table\b/i.test(props.tableNumber) ? props.tableNumber : `Table ${props.tableNumber}`
  )
  const seatsLeft    = computed(() => Math.max(0, (props.capacity || 0) - guests.value))
  const overCapacity = computed(() => props.capacity > 0 && guests.value > props.capacity)

  // ── Menu ────────────────────────────────────────────
  const categories = computed(() => {
    const set = new Set()
    for (const p of props.products) if (p.category?.name) set.add(p.category.name)
    return ['All', ...set]
  })

  const menu = computed(() => {
    let list = props.products
    if (activeCategory.value !== 'All') list = list.filter(p => p.category?.name === activeCategory.value)
    const q = search.value.trim().toLowerCase()
    if (q) list = list.filter(p => `${p.name || ''} ${p.sku || ''} ${p.brand || ''}`.toLowerCase().includes(q))
    return list
  })

  function isStockTracked(p) {
    return String(p?.product_type ?? '').toLowerCase() !== 'food'
  }
  function isOutOfStock(p) {
    return isStockTracked(p) && Number(p.stock_quantity) < 1
  }
  function qtyOf(productId) {
    return items.value.find(i => i.product_id == productId)?.quantity || 0
  }

  // Servings the ingredient stock allows; null when the dish has no recipe
  function canMake(p) {
    return p?.can_make ?? null
  }
  // Warn (never block) when the order needs more than the ingredients cover
  function itemWarning(item) {
    const c = canMake(item.product)
    if (c === null || Number(item.quantity) <= c) return ''
    return c === 0 ? 'Out of ingredients' : `Only ${c} can be made`
  }
  const warningCount = computed(() => items.value.filter(i => itemWarning(i)).length)

  // ── Order items ─────────────────────────────────────
  function addProduct(p) {
    if (isOutOfStock(p)) return
    const existing = items.value.find(i => i.product_id == p.id && !i.serving_ml && !i.open_bottle_id)
    if (existing) { existing.quantity++; return }
    items.value.push({
      product_id: p.id, product: p, name: p.name, price: Number(p.selling_price || 0),
      quantity: 1, note: '', showNote: false, serving_ml: 0, open_bottle_id: null,
    })
  }

  function changeQty(item, delta) {
    const next = Math.round((Number(item.quantity) + delta) * 1000) / 1000
    if (next <= 0) { items.value.splice(items.value.indexOf(item), 1); return }
    item.quantity = next
  }

  function removeItem(idx) { items.value.splice(idx, 1) }

  function hasNote(item, n) {
    return item.note.split(',').map(s => s.trim()).includes(n)
  }
  function toggleNote(item, n) {
    const parts = item.note.split(',').map(s => s.trim()).filter(Boolean)
    const i = parts.indexOf(n)
    if (i >= 0) parts.splice(i, 1); else parts.push(n)
    item.note = parts.join(', ')
  }

  function deposit(item) {
    return item.product?.bottle_deposit_required
      ? Number(item.product.bottle_deposit_amount || 0) * Number(item.quantity || 0)
      : 0
  }
  function lineTotal(item) {
    return Number(item.price || 0) * Number(item.quantity || 0) + deposit(item)
  }

  // ── Totals ──────────────────────────────────────────
  const subtotal       = computed(() => items.value.reduce((s, i) => s + lineTotal(i), 0))
  const serviceCharge  = computed(() => round2(subtotal.value * (Number(servicePct.value) || 0) / 100))
  const taxRate        = computed(() => Number(props.taxes.find(t => t.id == taxId.value)?.rate || 0))
  const taxAmount      = computed(() => round2(subtotal.value * taxRate.value / 100))
  const discountAmount = computed(() => Math.min(Number(discount.value) || 0, subtotal.value))
  const total          = computed(() => Math.max(0, subtotal.value + serviceCharge.value + taxAmount.value - discountAmount.value))
  const changeDue      = computed(() => Math.max(0, Number(amountPaid.value || 0) - total.value))

  const quickAmounts = computed(() => {
    const roundedUp = Math.ceil(total.value / 100) * 100
    const standard = [500, 1000, 2000, 5000, 10000]
    return [...new Set([roundedUp, ...standard.filter(d => d > roundedUp).slice(0, 3)])].filter(d => d > 0)
  })

  // ── Persistence ─────────────────────────────────────
  function buildPayload(status) {
    const itemNotes = items.value.filter(i => i.note).map(i => `${i.name}: ${i.note}`)
    const notes = [orderNotes.value, ...itemNotes].filter(Boolean).join('\n')
    return {
      order_type:     'dine_in',
      table_number:   props.tableNumber,
      guest_count:    guests.value || 1,
      waiter_id:      waiterId.value || null,
      customer_id:    customerId.value || null,
      discount:       discountAmount.value,
      tax:            taxAmount.value,
      tax_rate:       taxRate.value,
      service_charge: serviceCharge.value,
      notes:          notes || null,
      status,
      // Held bills are unpaid; the store endpoint still requires a payment method
      payment_method: 'cash',
      payment_status: 'pending',
      amount_paid:    0,
      items: items.value.map(i => ({
        product_id:            i.product_id,
        quantity:              Number(i.quantity),
        unit_price:            i.price,
        discount:              0,
        empty_bottle_returned: false,
        bottle_deposit_amount: Number(i.product?.bottle_deposit_amount || 0),
        serving_ml:            i.serving_ml || 0,
        open_bottle_id:        i.open_bottle_id || null,
      })),
    }
  }

  async function persist(payload) {
    const { data } = saleId.value
      ? await axios.put(`/api/sales/${saleId.value}`, payload)
      : await axios.post('/api/sales', payload)
    saleId.value = data.id ?? saleId.value
    return data
  }

  async function holdOrder() {
    if (!items.value.length) return
    saving.value = true; error.value = ''
    try {
      await persist(buildPayload('draft'))
      emit('saved')
    } catch (e) {
      error.value = errorMessage(e)
    } finally { saving.value = false }
  }

  // Save the open order, then show the bill so it can be printed before payment
  async function printBill() {
    if (!items.value.length) return
    saving.value = true; error.value = ''
    try {
      const data = await persist(buildPayload('draft'))
      emit('printed', data.id)
    } catch (e) {
      error.value = errorMessage(e)
    } finally { saving.value = false }
  }

  function openPayment() {
    error.value = ''
    paymentMethod.value = 'cash'
    amountPaid.value = ''
    showPayment.value = true
    nextTick(() => amountRef.value?.focus())
  }

  function selectMethod(m) {
    paymentMethod.value = m
    error.value = ''
    if (m === 'card') amountPaid.value = total.value
  }

  async function confirmPayment() {
    if (paymentMethod.value === 'cash' && Number(amountPaid.value || 0) < total.value) {
      error.value = `Cash received is less than the total (LKR ${lkr(total.value)}).`
      return
    }
    saving.value = true; error.value = ''
    try {
      const paid = paymentMethod.value === 'card' ? total.value : Number(amountPaid.value)
      const data = await persist({
        ...buildPayload('completed'),
        payment_method: paymentMethod.value,
        payment_status: 'paid',
        amount_paid:    paid,
        card_reference: paymentMethod.value === 'card' ? (cardReference.value || null) : null,
      })
      emit('paid', data.id)
    } catch (e) {
      error.value = errorMessage(e)
    } finally { saving.value = false }
  }

  // ── Resume an existing held order for this table ────
  async function loadExistingOrder() {
    const draft = props.draftBills.find(d => String(d.table_number) === String(props.tableNumber))
    if (!draft) return
    loadingOrder.value = true
    try {
      const { data } = await axios.get(`/api/sales/${draft.id}`)
      saleId.value        = data.id
      invoiceNumber.value = data.invoice_number || ''
      guests.value        = data.guest_count || 1
      waiterId.value      = data.waiter_id || ''
      customerId.value    = data.customer_id || ''
      orderNotes.value    = data.notes || ''
      discount.value      = Number(data.discount || 0)
      taxId.value         = props.taxes.find(t => Number(t.rate) === Number(data.tax_rate))?.id ?? ''
      items.value = (data.items || []).map(si => ({
        product_id:     si.product_id,
        product:        props.products.find(p => p.id == si.product_id) || si.product,
        name:           si.product?.name || '',
        price:          Number(si.unit_price || 0),
        quantity:       Number(si.quantity || 0),
        note: '', showNote: false,
        serving_ml:     Number(si.serving_ml || 0),
        open_bottle_id: si.open_bottle_id || null,
      }))
      servicePct.value = subtotal.value > 0
        ? round2((Number(data.service_charge || 0) / subtotal.value) * 100)
        : 0
    } catch (e) {
      error.value = errorMessage(e)
    } finally { loadingOrder.value = false }
  }

  async function loadWaiters() {
    try {
      const { data } = await axios.get('/api/waiters')
      waiters.value = data
    } catch {
      waiters.value = []
    }
    // A waiter taking the order is the assigned waiter, unless the held order already has one
    if (isWaiterUser && !waiterId.value) waiterId.value = auth.user.id
  }

  onMounted(async () => {
    await loadExistingOrder()
    await loadWaiters()
  })

  return {
    // state
    items, saleId, invoiceNumber, loadingOrder, saving, error,
    guests, waiterId, waiters, isWaiterUser, customerId, tableLabel, seatsLeft, overCapacity,
    servicePct, taxId, discount, search, activeCategory,
    showPayment, paymentMethod, amountPaid, cardReference, amountRef,
    // derived
    categories, menu, subtotal, serviceCharge, taxAmount, discountAmount, total, changeDue, quickAmounts,
    // helpers
    quickNotes: QUICK_NOTES, lkr, isOutOfStock, qtyOf, deposit, lineTotal, hasNote,
    canMake, itemWarning, warningCount,
    // actions
    addProduct, changeQty, removeItem, toggleNote,
    holdOrder, printBill, openPayment, selectMethod, confirmPayment,
  }
}
