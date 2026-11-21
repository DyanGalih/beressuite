<template>
  <ApiTokenModal
    ref="tokenModal"
    :available-abilities="apiTokenStore.availableAbilities"
    @created="loadTokens"
  />

  <BaseSettingCard
    :title="$t('settings.api_tokens.title')"
    :description="$t('settings.api_tokens.description')"
  >
    <template #action>
      <BaseButton variant="primary-outline" @click="openCreateModal">
        <template #left="slotProps">
          <BaseIcon name="PlusIcon" :class="slotProps.class" />
        </template>
        {{ $t('settings.api_tokens.create_token') }}
      </BaseButton>
    </template>

    <div class="mt-14 overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead>
          <tr class="text-left text-gray-500">
            <th class="px-4 py-3 font-medium">
              {{ $t('settings.api_tokens.name') }}
            </th>
            <th class="px-4 py-3 font-medium">
              {{ $t('settings.api_tokens.abilities') }}
            </th>
            <th class="px-4 py-3 font-medium">
              {{ $t('settings.api_tokens.last_used') }}
            </th>
            <th class="px-4 py-3 font-medium">
              {{ $t('settings.api_tokens.expires') }}
            </th>
            <th class="px-4 py-3 font-medium">
              {{ $t('settings.api_tokens.status') }}
            </th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="isLoading">
            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
              {{ $t('settings.api_tokens.loading') }}
            </td>
          </tr>
          <tr v-else-if="!apiTokenStore.apiTokens.length">
            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
              {{ $t('settings.api_tokens.empty') }}
            </td>
          </tr>
          <tr v-for="token in apiTokenStore.apiTokens" v-else :key="token.id">
            <td class="px-4 py-3 font-medium text-gray-900">
              {{ token.name }}
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <BaseBadge
                  v-for="ability in token.abilities"
                  :key="ability"
                  bg-color="#E0E7FF"
                  color="#3730A3"
                >
                  {{ abilityLabel(ability) }}
                </BaseBadge>
              </div>
            </td>
            <td class="px-4 py-3 text-gray-700">
              {{
                token.last_used_at
                  ? formatDate(token.last_used_at)
                  : $t('settings.api_tokens.never_used')
              }}
            </td>
            <td class="px-4 py-3 text-gray-700">
              {{
                token.expires_at
                  ? formatDate(token.expires_at)
                  : $t('settings.api_tokens.never_expires')
              }}
            </td>
            <td class="px-4 py-3">
              <BaseBadge
                :bg-color="token.is_expired ? '#FEE2E2' : '#D1FAE5'"
                :color="token.is_expired ? '#991B1B' : '#065F46'"
              >
                {{
                  token.is_expired
                    ? $t('settings.api_tokens.expired')
                    : $t('settings.api_tokens.active')
                }}
              </BaseBadge>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <BaseButton
                class="mr-2"
                size="sm"
                variant="primary-outline"
                @click="rotateToken(token)"
              >
                {{ $t('settings.api_tokens.rotate') }}
              </BaseButton>
              <BaseButton size="sm" variant="danger" @click="revokeToken(token)">
                {{ $t('settings.api_tokens.revoke') }}
              </BaseButton>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </BaseSettingCard>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiTokenStore } from '@/scripts/admin/stores/api-token'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useNotificationStore } from '@/scripts/stores/notification'
import ApiTokenModal from '@/scripts/admin/components/modal-components/ApiTokenModal.vue'

const { t } = useI18n()
const apiTokenStore = useApiTokenStore()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()

const tokenModal = ref(null)
const isLoading = ref(false)

onMounted(() => {
  loadTokens()
})

async function loadTokens() {
  isLoading.value = true
  try {
    await apiTokenStore.fetchApiTokens()
  } catch (err) {
    // handled by store (handleError)
  } finally {
    isLoading.value = false
  }
}

function abilityLabel(ability) {
  return apiTokenStore.availableAbilities[ability] || ability
}

function formatDate(value) {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}

function openCreateModal() {
  tokenModal.value && tokenModal.value.openCreate()
}

async function rotateToken(token) {
  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('settings.api_tokens.confirm_rotate', { name: token.name }),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  })

  if (!confirmed) return

  try {
    const response = await apiTokenStore.rotateApiToken(token.id)
    await loadTokens()
    // Pass the one-time secret directly to the modal; never store it here.
    tokenModal.value &&
      tokenModal.value.openReveal(response.data.plain_text_token)
  } catch (err) {
    // handled by store (handleError)
  }
}

async function revokeToken(token) {
  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('settings.api_tokens.confirm_revoke', { name: token.name }),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  })

  if (!confirmed) return

  try {
    await apiTokenStore.revokeApiToken(token.id)
    notificationStore.showNotification({
      type: 'success',
      message: t('settings.api_tokens.revoked_message'),
    })
    await loadTokens()
  } catch (err) {
    // handled by store (handleError)
  }
}
</script>
