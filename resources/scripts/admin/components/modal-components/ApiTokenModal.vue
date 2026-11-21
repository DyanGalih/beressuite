<template>
  <BaseModal :show="show" @close="closeModal">
    <template #header>
      <div class="flex justify-between w-full">
        {{
          plainTextToken
            ? $t('settings.api_tokens.token_created_title')
            : $t('settings.api_tokens.create_token')
        }}
        <BaseIcon
          name="XIcon"
          class="h-6 w-6 text-gray-500 cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <!-- One-time plaintext reveal -->
    <div v-if="plainTextToken">
      <div class="p-4 sm:p-6 space-y-4">
        <p class="text-sm text-yellow-700 bg-yellow-50 rounded p-3">
          {{ $t('settings.api_tokens.copy_warning') }}
        </p>
        <div class="flex items-center gap-2">
          <input
            :value="plainTextToken"
            type="text"
            readonly
            class="w-full font-mono text-sm border border-gray-300 rounded px-3 py-2 bg-gray-50"
            @focus="$event.target.select()"
          />
          <BaseButton variant="primary-outline" type="button" @click="copyToken">
            <template #left="slotProps">
              <BaseIcon name="ClipboardIcon" :class="slotProps.class" />
            </template>
            {{ $t('settings.api_tokens.copy') }}
          </BaseButton>
        </div>
      </div>
      <div
        class="z-0 flex justify-end p-4 border-t border-solid border-modal-bg"
      >
        <BaseButton variant="primary" type="button" @click="closeModal">
          {{ $t('settings.api_tokens.done') }}
        </BaseButton>
      </div>
    </div>

    <!-- Create form -->
    <form v-else action="" @submit.prevent="submitToken">
      <div class="p-4 sm:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('settings.api_tokens.name')"
            :error="nameError"
            required
          >
            <BaseInput
              v-model="form.name"
              :invalid="!!nameError"
              type="text"
              maxlength="255"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.api_tokens.abilities')"
            :error="abilitiesError"
            required
          >
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
              <BaseCheckbox
                v-for="(label, key) in availableAbilities"
                :key="key"
                v-model="form.abilities"
                :value="key"
                :label="label"
              />
            </div>
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.api_tokens.expiration')" required>
            <select
              v-model="form.expiration"
              class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
            >
              <option
                v-for="preset in expirationPresets"
                :key="preset.value"
                :value="preset.value"
              >
                {{ preset.label }}
              </option>
            </select>
            <p
              v-if="form.expiration === 'never'"
              class="mt-2 text-sm text-red-700 bg-red-50 rounded p-3"
            >
              {{ $t('settings.api_tokens.never_expires_warning') }}
            </p>
          </BaseInputGroup>
        </BaseInputGrid>
      </div>
      <div
        class="z-0 flex justify-end p-4 border-t border-solid border-modal-bg"
      >
        <BaseButton
          class="mr-3 text-sm"
          variant="primary-outline"
          type="button"
          @click="closeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          variant="primary"
          type="submit"
        >
          {{ $t('settings.api_tokens.create') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { computed, onUnmounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiTokenStore } from '@/scripts/admin/stores/api-token'
import { useNotificationStore } from '@/scripts/stores/notification'

defineProps({
  availableAbilities: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['created'])

const { t } = useI18n()
const apiTokenStore = useApiTokenStore()
const notificationStore = useNotificationStore()

const show = ref(false)
const isSaving = ref(false)
const submitted = ref(false)
// Transient, component-local one-time secret. Never persisted.
const plainTextToken = ref('')

const form = reactive({
  name: '',
  abilities: [],
  expiration: '90d',
})

const expirationPresets = computed(() => [
  { value: '30d', label: t('settings.api_tokens.expires_30d') },
  { value: '90d', label: t('settings.api_tokens.expires_90d') },
  { value: '1y', label: t('settings.api_tokens.expires_1y') },
  { value: 'never', label: t('settings.api_tokens.expires_never') },
])

const nameError = computed(() => {
  if (!submitted.value) return ''
  return form.name.trim() ? '' : t('validation.required')
})

const abilitiesError = computed(() => {
  if (!submitted.value) return ''
  return form.abilities.length
    ? ''
    : t('settings.api_tokens.abilities_required')
})

function computeExpiresAt(preset) {
  const date = new Date()
  switch (preset) {
    case '30d':
      date.setDate(date.getDate() + 30)
      break
    case '90d':
      date.setDate(date.getDate() + 90)
      break
    case '1y':
      date.setFullYear(date.getFullYear() + 1)
      break
    default:
      return null
  }
  return date.toISOString()
}

function resetForm() {
  form.name = ''
  form.abilities = []
  form.expiration = '90d'
  submitted.value = false
}

function clearToken() {
  plainTextToken.value = ''
}

function openCreate() {
  clearToken()
  resetForm()
  show.value = true
}

function openReveal(token) {
  plainTextToken.value = token || ''
  show.value = !!plainTextToken.value
}

function closeModal() {
  show.value = false
  clearToken()
  resetForm()
}

async function submitToken() {
  submitted.value = true
  if (nameError.value || abilitiesError.value) {
    return
  }

  isSaving.value = true
  try {
    const response = await apiTokenStore.createApiToken({
      name: form.name.trim(),
      abilities: [...form.abilities],
      expires_at: computeExpiresAt(form.expiration),
    })
    plainTextToken.value = response.data.plain_text_token || ''
    notificationStore.showNotification({
      type: 'success',
      message: t('settings.api_tokens.created_message'),
    })
    emit('created')
  } catch (err) {
    // handled by store (handleError)
  } finally {
    isSaving.value = false
  }
}

async function copyToken() {
  try {
    await navigator.clipboard.writeText(plainTextToken.value)
    notificationStore.showNotification({
      type: 'success',
      message: t('settings.api_tokens.copied'),
    })
  } catch (err) {
    notificationStore.showNotification({
      type: 'error',
      message: t('settings.api_tokens.copy_failed'),
    })
  }
}

onUnmounted(() => {
  clearToken()
})

defineExpose({ openCreate, openReveal })
</script>
