<template>
  <div
    v-if="hasDraft && draftInfo"
    class="mb-6 rounded-md border border-amber-200 bg-amber-50 p-4 shadow-sm"
  >
    <div
      class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
    >
      <div class="flex items-start">
        <div class="shrink-0 mt-0.5">
          <BaseIcon
            name="ExclamationIcon"
            class="h-5 w-5 text-amber-500"
            aria-hidden="true"
          />
        </div>
        <div class="ml-3">
          <h4 class="text-sm font-semibold text-amber-900">
            {{ $t('general.draft_found_title') }}
          </h4>
          <p class="mt-1 text-sm text-amber-700">
            {{
              $t('general.draft_found_desc', {
                time: draftInfo.formattedTime || draftInfo.savedAt,
              })
            }}
          </p>
          <p
            v-if="draftInfo.isStale"
            class="mt-1 text-xs font-medium text-red-600"
          >
            {{ $t('general.draft_stale_warning') }}
          </p>
        </div>
      </div>
      <div class="flex items-center space-x-2 shrink-0 sm:ml-4">
        <BaseButton
          size="sm"
          variant="primary"
          type="button"
          @click="$emit('restore')"
        >
          {{ $t('general.restore_draft') }}
        </BaseButton>
        <BaseButton
          size="sm"
          variant="white"
          type="button"
          @click="$emit('discard')"
        >
          {{ $t('general.discard_draft') }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  hasDraft: {
    type: Boolean,
    default: false,
  },
  draftInfo: {
    type: Object,
    default: null,
  },
})

defineEmits(['restore', 'discard'])
</script>
