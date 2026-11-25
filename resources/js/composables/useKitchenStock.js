import { ref, computed, reactive, onMounted } from 'vue'
import axios from 'axios'

export const UNITS = ['kg', 'g', 'L', 'ml', 'pcs', 'pack']

export function fmt(val, digits = 3) {
  return Number(val || 0).toLocaleString('en-LK', { maximumFractionDigits: digits })
}
export function money(val) {
  return Number(val || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function errorMessage(e, fallback = 'Something went wrong.') {
  return Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
    || e.response?.data?.message
    || fallback
}

// ── Ingredients: stock list, add/edit, receive stock, count ─────────────
export function useIngredients() {
  const items   = ref([])
  const loading = ref(false)
  const error   = ref('')
  const search  = ref('')
  const onlyLow = ref(false)

  const form = reactive({ show: false, id: null, name: '', unit: 'kg', stock_quantity: 0, min_stock_level: 0, purchase_price: 0, saving: false, error: '' })
  const stock = reactive({ show: false, item: null, mode: 'add', quantity: '', notes: '', saving: false, error: '' })

  const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    return items.value.filter(i =>
      (!q || i.name.toLowerCase().includes(q)) && (!onlyLow.value || i.low)
    )
  })
  const lowCount = computed(() => items.value.filter(i => i.low).length)

  async function load() {
    loading.value = true; error.value = ''
    try {
      items.value = (await axios.get('/api/ingredients')).data
    } catch (e) {
      error.value = errorMessage(e, 'Could not load ingredients.')
    } finally { loading.value = false }
  }

  function openForm(item = null) {
    Object.assign(form, {
      show: true, error: '', saving: false,
      id: item?.id ?? null, name: item?.name ?? '', unit: item?.unit ?? 'kg',
      stock_quantity: 0, min_stock_level: item?.min_stock_level ?? 0, purchase_price: item?.purchase_price ?? 0,
    })
  }

  async function saveForm() {
    form.saving = true; form.error = ''
    try {
      const body = { name: form.name, unit: form.unit, min_stock_level: Number(form.min_stock_level) || 0, purchase_price: Number(form.purchase_price) || 0 }
      if (form.id) await axios.put(`/api/ingredients/${form.id}`, body)
      else await axios.post('/api/ingredients', { ...body, stock_quantity: Number(form.stock_quantity) || 0 })
      form.show = false
      await load()
    } catch (e) {
      form.error = errorMessage(e)
    } finally { form.saving = false }
  }

  function openStock(item, mode) {
    Object.assign(stock, { show: true, item, mode, quantity: mode === 'set' ? item.stock_quantity : '', notes: '', error: '', saving: false })
  }

  async function saveStock() {
    stock.saving = true; stock.error = ''
    try {
      await axios.post(`/api/ingredients/${stock.item.id}/stock`, {
        mode: stock.mode, quantity: Number(stock.quantity), notes: stock.notes || null,
      })
      stock.show = false
      await load()
    } catch (e) {
      stock.error = errorMessage(e)
    } finally { stock.saving = false }
  }

  async function remove(item) {
    if (!confirm(`Delete ingredient "${item.name}"?`)) return
    try {
      await axios.delete(`/api/ingredients/${item.id}`)
      await load()
    } catch (e) {
      error.value = errorMessage(e)
    }
  }

  onMounted(load)

  return { items, loading, error, search, onlyLow, filtered, lowCount, form, stock, load, openForm, saveForm, openStock, saveStock, remove }
}

// ── Recipes: which ingredients a dish uses per serving ──────────────────
export function useRecipes() {
  const dishes      = ref([])
  const ingredients = ref([])
  const loading     = ref(false)
  const error       = ref('')
  const search      = ref('')
  const filter      = ref('all') // all | missing | set

  const editor = reactive({ show: false, dish: null, lines: [], saving: false, error: '' })

  const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    return dishes.value.filter(d =>
      (!q || d.name.toLowerCase().includes(q)) &&
      (filter.value === 'all' || (filter.value === 'missing' ? !d.lines.length : d.lines.length > 0))
    )
  })
  const counts = computed(() => ({
    all: dishes.value.length,
    set: dishes.value.filter(d => d.lines.length).length,
    missing: dishes.value.filter(d => !d.lines.length).length,
  }))

  async function load() {
    loading.value = true; error.value = ''
    try {
      const [d, i] = await Promise.all([axios.get('/api/recipes'), axios.get('/api/ingredients')])
      dishes.value = d.data
      ingredients.value = i.data
    } catch (e) {
      error.value = errorMessage(e, 'Could not load recipes.')
    } finally { loading.value = false }
  }

  function openEditor(dish) {
    Object.assign(editor, {
      show: true, dish, error: '', saving: false,
      lines: dish.lines.map(l => ({ ingredient_id: l.ingredient_id, quantity: l.quantity })),
    })
  }

  const ingredientById = id => ingredients.value.find(i => i.id === Number(id))
  const unusedIngredients = computed(() =>
    ingredients.value.filter(i => !editor.lines.some(l => Number(l.ingredient_id) === i.id))
  )

  function addLine(ingredientId = '') { editor.lines.push({ ingredient_id: ingredientId, quantity: '' }) }
  function removeLine(idx) { editor.lines.splice(idx, 1) }

  const editorCost = computed(() => editor.lines.reduce((s, l) =>
    s + (Number(l.quantity) || 0) * (ingredientById(l.ingredient_id)?.purchase_price || 0), 0))

  async function saveEditor() {
    editor.saving = true; editor.error = ''
    try {
      const lines = editor.lines
        .filter(l => l.ingredient_id && Number(l.quantity) > 0)
        .map(l => ({ ingredient_id: Number(l.ingredient_id), quantity: Number(l.quantity) }))
      await axios.put(`/api/recipes/${editor.dish.id}`, { lines })
      editor.show = false
      await load()
    } catch (e) {
      editor.error = errorMessage(e)
    } finally { editor.saving = false }
  }

  onMounted(load)

  return {
    dishes, ingredients, loading, error, search, filter, filtered, counts, editor,
    load, openEditor, ingredientById, unusedIngredients, addLine, removeLine, editorCost, saveEditor,
  }
}

// ── Usage report: bought / used / wasted over a date range ──────────────
export function useUsage() {
  const iso = d => d.toISOString().slice(0, 10)
  const today = new Date()
  const weekAgo = new Date(Date.now() - 6 * 86400000)

  const from    = ref(iso(weekAgo))
  const to      = ref(iso(today))
  const data    = ref({ rows: [], by_dish: [], totals: { cost_used: 0, low_count: 0 }, days: 0 })
  const loading = ref(false)
  const error   = ref('')

  async function load() {
    loading.value = true; error.value = ''
    try {
      data.value = (await axios.get('/api/reports/ingredient-usage', { params: { from: from.value, to: to.value } })).data
    } catch (e) {
      error.value = errorMessage(e, 'Could not load the report.')
    } finally { loading.value = false }
  }

  function preset(kind) {
    const now = new Date()
    if (kind === 'today') from.value = iso(now)
    if (kind === 'week')  from.value = iso(new Date(Date.now() - 6 * 86400000))
    if (kind === 'month') from.value = iso(new Date(now.getFullYear(), now.getMonth(), 1))
    to.value = iso(now)
    load()
  }

  onMounted(load)

  return { from, to, data, loading, error, load, preset }
}
