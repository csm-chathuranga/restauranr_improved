<template>
  <div class="space-y-4">
    <div class="flex items-center gap-3 flex-wrap">
      <div class="relative">
        <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input v-model="search" type="text" placeholder="Search dish…" class="form-input pl-9 w-64" />
      </div>
      <div class="flex gap-1.5">
        <button v-for="f in [['all','All'],['set','With recipe'],['missing','No recipe']]" :key="f[0]" type="button" @click="filter = f[0]"
          class="px-3 py-1.5 rounded-full text-xs font-bold transition-colors"
          :class="filter === f[0] ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
          {{ f[1] }} <span class="opacity-70">{{ f[0] === 'all' ? counts.all : counts[f[0]] }}</span>
        </button>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ error }}</p>
    <p v-if="!loading && !ingredients.length" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
      Add ingredients on the Ingredients tab first, then build the recipes here.
    </p>

    <div v-if="loading" class="text-center text-gray-400 py-12">Loading…</div>
    <div v-else class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
      <div v-for="d in filtered" :key="d.id" class="bg-white rounded-2xl border-2 shadow-sm overflow-hidden flex flex-col"
        :class="d.lines.length ? 'border-gray-200' : 'border-dashed border-gray-300'">
        <div class="px-4 py-3 flex items-start justify-between gap-2" :class="d.lines.length ? 'bg-amber-500 text-white' : 'bg-gray-200 text-gray-600'">
          <div class="min-w-0">
            <p class="font-bold leading-tight truncate">{{ d.name }}</p>
            <p class="text-xs opacity-90">LKR {{ money(d.selling_price) }}</p>
          </div>
          <span v-if="d.can_make !== null" class="shrink-0 text-[11px] font-extrabold px-2 py-0.5 rounded-full"
            :class="d.can_make === 0 ? 'bg-red-600 text-white' : d.can_make <= 5 ? 'bg-white text-red-600' : 'bg-white/25 text-white'">
            {{ d.can_make === 0 ? 'Out' : `${d.can_make} left` }}
          </span>
        </div>

        <div class="px-4 py-3 flex-1 text-sm">
          <ul v-if="d.lines.length" class="space-y-1">
            <li v-for="l in d.lines" :key="l.ingredient_id" class="flex justify-between gap-2">
              <span class="text-gray-700 truncate">{{ l.name }}</span>
              <span class="font-semibold text-gray-900 shrink-0">{{ fmt(l.quantity) }} {{ l.unit }}</span>
            </li>
          </ul>
          <p v-else class="text-gray-400 text-xs">No recipe yet — stock is not reduced when this dish is sold.</p>
        </div>

        <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between gap-2">
          <div v-if="d.lines.length" class="text-xs text-gray-500">
            Cost <span class="font-bold text-gray-800">LKR {{ money(d.cost) }}</span>
            <span v-if="d.selling_price > 0" class="ml-1" :class="d.selling_price - d.cost >= 0 ? 'text-green-600' : 'text-red-600'">
              · margin {{ money(d.selling_price - d.cost) }}
            </span>
          </div>
          <span v-else></span>
          <button type="button" @click="openEditor(d)"
            class="px-3 py-1.5 rounded-lg bg-amber-500 text-white text-xs font-bold hover:bg-amber-600 inline-flex items-center gap-1">
            <PencilSquareIcon class="w-4 h-4" /> {{ d.lines.length ? 'Edit' : 'Add recipe' }}
          </button>
        </div>
      </div>
      <p v-if="!filtered.length" class="col-span-full text-center text-gray-400 py-10">No dishes found.</p>
    </div>

    <!-- Recipe editor -->
    <div v-if="editor.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="editor.show = false">
      <form @submit.prevent="saveEditor" class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-5 py-4 bg-amber-500 text-white">
          <h3 class="font-semibold">Recipe — {{ editor.dish.name }}</h3>
          <p class="text-xs opacity-90">Ingredients used to make <strong>1 serving</strong></p>
        </div>

        <div class="p-5 space-y-2 overflow-y-auto">
          <div v-for="(l, idx) in editor.lines" :key="idx" class="flex items-center gap-2">
            <select v-model="l.ingredient_id" required class="form-input flex-1">
              <option value="" disabled>Select ingredient…</option>
              <option v-if="l.ingredient_id" :value="l.ingredient_id">{{ ingredientById(l.ingredient_id)?.name }}</option>
              <option v-for="i in unusedIngredients" :key="i.id" :value="i.id">{{ i.name }}</option>
            </select>
            <input v-model="l.quantity" required type="number" min="0.0001" step="any" placeholder="Qty" class="form-input w-24" />
            <span class="w-9 text-xs font-semibold text-gray-500">{{ ingredientById(l.ingredient_id)?.unit }}</span>
            <button type="button" @click="removeLine(idx)" title="Remove"
              class="p-1.5 rounded-lg bg-red-500 text-white hover:bg-red-600"><TrashIcon class="w-4 h-4" /></button>
          </div>
          <p v-if="!editor.lines.length" class="text-sm text-gray-400 text-center py-4">No ingredients added.</p>
          <button type="button" @click="addLine()" :disabled="!unusedIngredients.length"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-800 text-white text-xs font-bold hover:bg-gray-700 disabled:opacity-40">
            <PlusIcon class="w-4 h-4" /> Add ingredient
          </button>
          <p v-if="editor.error" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ editor.error }}</p>
        </div>

        <div class="flex items-center justify-between gap-2 px-5 py-3 bg-gray-50 border-t">
          <p class="text-xs text-gray-500">Cost per serving <span class="font-bold text-gray-800">LKR {{ money(editorCost) }}</span></p>
          <div class="flex gap-2">
            <button type="button" @click="editor.show = false" class="btn-secondary">Cancel</button>
            <button type="submit" :disabled="editor.saving" class="btn-primary">{{ editor.saving ? 'Saving…' : 'Save recipe' }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useRecipes, fmt, money } from '@/composables/useKitchenStock'

const {
  ingredients, loading, error, search, filter, filtered, counts, editor,
  openEditor, ingredientById, unusedIngredients, addLine, removeLine, editorCost, saveEditor,
} = useRecipes()
</script>
