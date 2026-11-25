<template>
  <div class="space-y-5">
    <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1 w-fit">
      <button v-for="t in tabs" :key="t.key" type="button" @click="tab = t.key"
        class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-bold transition-all"
        :class="tab === t.key ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-800'">
        <component :is="t.icon" class="w-4 h-4" /> {{ t.label }}
      </button>
    </div>

    <IngredientsTab v-if="tab === 'ingredients'" />
    <RecipesTab v-else-if="tab === 'recipes'" />
    <UsageTab v-else />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { BeakerIcon, ChartBarIcon, ClipboardDocumentListIcon } from '@heroicons/vue/24/outline'
import IngredientsTab from '@/components/kitchen/IngredientsTab.vue'
import RecipesTab from '@/components/kitchen/RecipesTab.vue'
import UsageTab from '@/components/kitchen/UsageTab.vue'

const tabs = [
  { key: 'ingredients', label: 'Ingredients', icon: BeakerIcon },
  { key: 'recipes',     label: 'Recipes',     icon: ClipboardDocumentListIcon },
  { key: 'usage',       label: 'Usage report', icon: ChartBarIcon },
]
const tab = ref('ingredients')
</script>
