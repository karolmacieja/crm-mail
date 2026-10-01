<template>
  <!-- "Zaległe | Dziś | 7 dni" switch from the mockup. -->
  <div class="flex rounded-lg bg-gray-100 p-1 text-sm font-medium" role="tablist">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      type="button"
      role="tab"
      class="flex-1 rounded py-1.5 transition"
      :class="modelValue === tab.key ? 'border border-gray-200 bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
      :aria-selected="modelValue === tab.key"
      @click="$emit('update:modelValue', tab.key)"
    >
      {{ tab.label }}
      <span v-if="tab.count !== undefined" class="ml-1" :class="countClass(tab)">{{ tab.count }}</span>
    </button>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: String, required: true },
  /** [{ key, label, count?, tone?: 'danger' | 'primary' }] */
  tabs: { type: Array, required: true },
})
defineEmits(['update:modelValue'])

function countClass(tab) {
  if (!tab.count) return 'text-gray-400'
  if (tab.tone === 'danger') return 'text-red-500'
  if (tab.tone === 'primary' || props.modelValue === tab.key) return 'text-primary'
  return 'text-gray-400'
}
</script>
