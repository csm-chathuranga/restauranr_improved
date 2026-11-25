<template>
  <nav v-if="lastPage >= 1" class="flex items-center gap-1" aria-label="Pagination">
    <button type="button" @click="go(page - 1)" :disabled="page <= 1"
      class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
      Prev
    </button>

    <template v-for="(p, i) in pages" :key="i">
      <span v-if="p === '…'" class="px-1.5 text-xs text-gray-400 select-none">…</span>
      <button v-else type="button" @click="go(p)"
        class="min-w-[2rem] h-8 px-2 rounded-lg text-xs font-bold transition-colors"
        :class="p === page ? 'bg-amber-500 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
        :aria-current="p === page ? 'page' : undefined">
        {{ p }}
      </button>
    </template>

    <button type="button" @click="go(page + 1)" :disabled="page >= lastPage"
      class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-800 text-white hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
      Next
    </button>
  </nav>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  page:     { type: Number, required: true },
  lastPage: { type: Number, default: 1 },
})
const emit = defineEmits(['change'])

// 1 … 4 5 [6] 7 8 … 20 — always shows first, last and the pages around the current one
const pages = computed(() => {
  const last = props.lastPage || 1
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)

  const set = new Set([1, last, props.page - 1, props.page, props.page + 1])
  if (props.page <= 3) [2, 3, 4].forEach(n => set.add(n))
  if (props.page >= last - 2) [last - 3, last - 2, last - 1].forEach(n => set.add(n))

  const sorted = [...set].filter(n => n >= 1 && n <= last).sort((a, b) => a - b)
  const out = []
  sorted.forEach((n, i) => {
    if (i && n - sorted[i - 1] > 1) out.push('…')
    out.push(n)
  })
  return out
})

function go(p) {
  const target = Math.min(Math.max(1, p), props.lastPage || 1)
  if (target !== props.page) emit('change', target)
}
</script>
