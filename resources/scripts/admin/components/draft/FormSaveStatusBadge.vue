<template>
  <div
    v-if="status && status !== 'clean'"
    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium transition-all duration-300"
    :class="badgeClasses"
  >
    <span
      v-if="status === 'unsaved'"
      class="h-2 w-2 mr-1.5 rounded-full bg-amber-400 animate-pulse"
      aria-hidden="true"
    />
    <BaseIcon
      v-else-if="status === 'draft_saved'"
      name="CloudUploadIcon"
      class="h-3.5 w-3.5 mr-1.5 opacity-80"
    />
    <BaseIcon
      v-else-if="status === 'saved_to_server'"
      name="CheckCircleIcon"
      class="h-3.5 w-3.5 mr-1.5 text-emerald-500"
    />
    <span>{{ statusText }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  status: {
    type: String,
    default: 'clean', // 'clean' | 'unsaved' | 'draft_saved' | 'saved_to_server'
  },
  lastSavedAt: {
    type: String,
    default: null,
  },
})

const { t } = useI18n()

const formattedTime = computed(() => {
  if (!props.lastSavedAt) return ''
  try {
    const d = new Date(props.lastSavedAt)
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  } catch {
    return ''
  }
})

const statusText = computed(() => {
  switch (props.status) {
    case 'unsaved':
      return t('general.unsaved_changes')
    case 'draft_saved':
      return t('general.draft_saved_at', { time: formattedTime.value })
    case 'saved_to_server':
      return t('general.saved_to_server')
    default:
      return ''
  }
})

const badgeClasses = computed(() => {
  switch (props.status) {
    case 'unsaved':
      return 'bg-amber-100 text-amber-800 border border-amber-200'
    case 'draft_saved':
      return 'bg-gray-100 text-gray-700 border border-gray-200'
    case 'saved_to_server':
      return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
    default:
      return ''
  }
})
</script>
