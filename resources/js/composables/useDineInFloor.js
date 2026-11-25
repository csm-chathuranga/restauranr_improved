import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

const REFRESH_MS = 30000
const OVERDUE_MINUTES = 90

export const STATUS_META = {
  available:   { label: 'Available',   dot: 'bg-green-500',  head: 'bg-green-500',  card: 'border-green-500 hover:shadow-xl hover:shadow-green-200' },
  occupied:    { label: 'Occupied',    dot: 'bg-orange-500', head: 'bg-orange-500', card: 'border-orange-500 hover:shadow-xl hover:shadow-orange-200' },
  reserved:    { label: 'Reserved',    dot: 'bg-purple-500', head: 'bg-purple-500', card: 'border-purple-500 hover:shadow-xl hover:shadow-purple-200' },
  maintenance: { label: 'Maintenance', dot: 'bg-gray-400',   head: 'bg-gray-400',   card: 'border-gray-300 opacity-50 cursor-not-allowed' },
}

export const STATUS_FILTERS = ['available', 'occupied', 'reserved']

export function tableState(table) {
  if (table.status === 'maintenance') return 'maintenance'
  if (table.active_sale || table.status === 'occupied') return 'occupied'
  if (table.status === 'reserved') return 'reserved'
  return 'available'
}

/** Data and filtering for the dine-in table selection screen. */
export function useDineInFloor() {
  const tables        = ref([])
  const loading       = ref(false)
  const error         = ref('')
  const section       = ref('All')
  const statusFilter  = ref('all')
  const search        = ref('')
  const now           = ref(Date.now())
  const updatedAt     = ref(null)

  let refreshTimer = null

  async function load({ silent = false } = {}) {
    if (!silent) loading.value = true
    error.value = ''
    try {
      const { data } = await axios.get('/api/tables/floor-plan')
      tables.value = Object.values(data).flat()
      updatedAt.value = new Date()
    } catch (e) {
      if (!silent) error.value = e.response?.data?.message ?? 'Could not load tables.'
    } finally {
      loading.value = false
    }
  }

  const sections = computed(() =>
    [...new Set(tables.value.map(t => t.section).filter(Boolean))]
  )

  const counts = computed(() => {
    const scoped = section.value === 'All' ? tables.value : tables.value.filter(t => t.section === section.value)
    const c = { all: scoped.length, available: 0, occupied: 0, reserved: 0 }
    for (const t of scoped) {
      const s = tableState(t)
      if (c[s] !== undefined) c[s]++
    }
    return c
  })

  const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    return tables.value.filter(t =>
      (section.value === 'All' || t.section === section.value) &&
      (statusFilter.value === 'all' || tableState(t) === statusFilter.value) &&
      (!q || String(t.table_number).toLowerCase().includes(q))
    )
  })

  // Grouped by section so large venues scan easily; a single unnamed group shows no heading
  const groups = computed(() => {
    const map = new Map()
    for (const t of filtered.value) {
      const key = t.section || ''
      if (!map.has(key)) map.set(key, [])
      map.get(key).push(t)
    }
    return [...map.entries()].map(([name, list]) => ({ name, tables: list }))
  })

  function openMinutes(table) {
    const created = table.active_sale?.created_at
    if (!created) return 0
    return Math.max(0, Math.floor((now.value - new Date(created).getTime()) / 60000))
  }

  function openFor(table) {
    if (!table.active_sale?.created_at) return ''
    const mins = openMinutes(table)
    if (mins < 1) return 'just now'
    if (mins < 60) return `${mins}m`
    return `${Math.floor(mins / 60)}h ${mins % 60}m`
  }

  const isOverdue = table => openMinutes(table) >= OVERDUE_MINUTES

  // Money currently on open (unpaid) bills
  const openSummary = computed(() => {
    const open = tables.value.filter(t => t.active_sale)
    return { bills: open.length, total: open.reduce((s, t) => s + Number(t.active_sale.total || 0), 0) }
  })

  onMounted(() => {
    load()
    refreshTimer = setInterval(() => {
      now.value = Date.now()
      load({ silent: true })
    }, REFRESH_MS)
  })
  onBeforeUnmount(() => clearInterval(refreshTimer))

  return {
    tables, loading, error, section, statusFilter, search, updatedAt,
    sections, counts, filtered, groups, openSummary, load, openFor, isOverdue,
  }
}
