import { ref, computed, watch, onMounted, onUnmounted, unref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { useI18n } from 'vue-i18n'
import isEqual from 'lodash/isEqual'
import cloneDeep from 'lodash/cloneDeep'
import Ls from '@/scripts/services/ls'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'

/**
 * Clear all stored form drafts from localStorage.
 * Used during logout to prevent data exposure on shared computers.
 */
export function clearAllFormDrafts() {
  try {
    if (typeof localStorage === 'undefined') return
    Object.keys(localStorage).forEach((key) => {
      if (key.startsWith('crater_draft:')) {
        localStorage.removeItem(key)
      }
    })
  } catch (_error) {
    // Storage unavailable or blocked
  }
}

/**
 * Composable for managing browser-side form draft persistence,
 * dirty tracking, navigation guards, and live save status.
 *
 * @param {Object} options
 * @param {string} options.docType - 'invoice' | 'estimate' | 'payment'
 * @param {Function} options.getFormPayload - Function returning current serializable form state
 * @param {Function} options.applyFormPayload - Function applying restored draft payload to form / store
 * @param {import('vue').Ref<boolean>|boolean} [options.isEdit=false] - Whether form is in edit mode
 * @param {import('vue').Ref<string|number|null>|string|number|null} [options.recordId=null] - Entity ID if edit mode
 * @param {import('vue').Ref<string|null>|string|null} [options.recordUpdatedAt=null] - Server updated_at timestamp
 * @param {number} [options.debounceMs=500] - Debounce period before auto-saving to storage
 */
export function useFormDraft({
  docType,
  getFormPayload,
  applyFormPayload,
  isEdit = false,
  recordId = null,
  recordUpdatedAt = null,
  debounceMs = 500,
}) {
  const { t } = useI18n()
  const companyStore = useCompanyStore()
  const userStore = useUserStore()

  const isDirty = ref(false)
  const isInitialized = ref(false)
  const isSubmitting = ref(false)
  const saveStatus = ref('clean') // 'clean' | 'unsaved' | 'draft_saved' | 'saved_to_server'
  const lastSavedAt = ref(null)

  const hasExistingDraft = ref(false)
  const existingDraftInfo = ref(null)

  let baselineSnapshot = null
  let debounceTimer = null

  // Storage key uniquely scoped by company, user, document type, and mode
  const storageKey = computed(() => {
    const companyId =
      companyStore.selectedCompany?.id ?? Ls.get('selectedCompany') ?? '1'
    const userId = userStore.currentUser?.id ?? '1'
    const editMode = unref(isEdit)
    const id = unref(recordId)
    const mode = editMode && id ? `edit_${id}` : 'create'

    return `crater_draft:${companyId}:${userId}:${docType}:${mode}`
  })

  // Format time for UI display
  const formatTime = (isoString) => {
    if (!isoString) return ''
    try {
      const date = new Date(isoString)
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    } catch {
      return ''
    }
  }

  // Load existing draft from storage
  const checkForExistingDraft = () => {
    try {
      const raw = localStorage.getItem(storageKey.value)
      if (!raw) {
        hasExistingDraft.value = false
        existingDraftInfo.value = null
        return
      }

      const parsed = JSON.parse(raw)
      if (parsed && parsed.payload && parsed.savedAt) {
        const serverUpdated = unref(recordUpdatedAt)
        const isStale = Boolean(
          serverUpdated &&
            new Date(serverUpdated).getTime() > new Date(parsed.savedAt).getTime()
        )

        hasExistingDraft.value = true
        existingDraftInfo.value = {
          savedAt: parsed.savedAt,
          formattedTime: formatTime(parsed.savedAt),
          isStale,
          payload: parsed.payload,
        }
      }
    } catch (_error) {
      hasExistingDraft.value = false
      existingDraftInfo.value = null
    }
  }

  // Save draft payload directly to localStorage
  const saveDraftToStorage = () => {
    try {
      const currentPayload = getFormPayload()
      const now = new Date().toISOString()
      const draftRecord = {
        savedAt: now,
        baseUpdatedAt: unref(recordUpdatedAt) || null,
        payload: cloneDeep(currentPayload),
      }

      localStorage.setItem(storageKey.value, JSON.stringify(draftRecord))
      lastSavedAt.value = now
      saveStatus.value = 'draft_saved'
    } catch (_error) {
      // Storage unavailable or quota exceeded
    }
  }

  // Establish initial baseline once data/settings are loaded
  const initBaseline = (customInitialData = null) => {
    const initial = customInitialData || getFormPayload()
    baselineSnapshot = cloneDeep(initial)
    isInitialized.value = true
    checkForExistingDraft()
  }

  // Trigger debounced auto-save
  const onFormModified = () => {
    if (!isInitialized.value || isSubmitting.value) return

    const currentPayload = getFormPayload()
    if (baselineSnapshot && isEqual(currentPayload, baselineSnapshot)) {
      isDirty.value = false
      saveStatus.value = 'clean'
      if (debounceTimer) clearTimeout(debounceTimer)
      return
    }

    isDirty.value = true
    saveStatus.value = 'unsaved'

    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      if (isDirty.value && !isSubmitting.value) {
        saveDraftToStorage()
      }
    }, debounceMs)
  }

  // Watch for external reactive payload changes
  watch(
    () => getFormPayload(),
    () => {
      onFormModified()
    },
    { deep: true }
  )

  // Restore draft into form and store
  const restoreDraft = () => {
    if (!existingDraftInfo.value?.payload) return

    try {
      const restored = cloneDeep(existingDraftInfo.value.payload)
      if (unref(isEdit)) {
        delete restored.id
        delete restored.updated_at
        delete restored.created_at
      }
      applyFormPayload(restored)
      hasExistingDraft.value = false
      isDirty.value = true
      lastSavedAt.value = existingDraftInfo.value.savedAt
      saveStatus.value = 'draft_saved'
    } catch (_error) {
      // Ignore restore payload parsing errors
    }
  }

  // Discard draft and clear from storage
  const discardDraft = () => {
    if (debounceTimer) {
      clearTimeout(debounceTimer)
      debounceTimer = null
    }
    try {
      localStorage.removeItem(storageKey.value)
    } catch (_error) {
      // Ignore storage removal errors
    }
    isDirty.value = false
    saveStatus.value = 'clean'
    hasExistingDraft.value = false
    existingDraftInfo.value = null
  }

  // Purge draft and mark saved to server
  const markSavedToServer = () => {
    isSubmitting.value = true
    isDirty.value = false
    saveStatus.value = 'saved_to_server'

    try {
      localStorage.removeItem(storageKey.value)
    } catch (_error) {
      // Ignore storage removal errors
    }

    hasExistingDraft.value = false
    existingDraftInfo.value = null

    // Reset status back to clean after delay
    setTimeout(() => {
      if (saveStatus.value === 'saved_to_server') {
        saveStatus.value = 'clean'
      }
      isSubmitting.value = false
    }, 2500)
  }

  // Browser-level reload / close interceptor
  const handleBeforeUnload = (e) => {
    if (isDirty.value && !isSubmitting.value) {
      e.preventDefault()
      e.returnValue = ''
      return ''
    }
  }

  // Vue Router in-app navigation interceptor
  try {
    onBeforeRouteLeave((to, from, next) => {
      if (isDirty.value && !isSubmitting.value) {
        const confirmMsg =
          t('general.unsaved_changes_leave_confirm') ||
          'You have unsaved changes. Are you sure you want to leave this page?'
        const answer = window.confirm(confirmMsg)
        if (answer) {
          next()
        } else {
          next(false)
        }
      } else {
        next()
      }
    })
  } catch (err) {
    // onBeforeRouteLeave is only available in setup context
  }

  onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload)
    checkForExistingDraft()
  })

  onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
    if (debounceTimer) clearTimeout(debounceTimer)
  })

  return {
    isDirty,
    isInitialized,
    saveStatus,
    lastSavedAt,
    hasExistingDraft,
    existingDraftInfo,
    initBaseline,
    saveDraftToStorage,
    restoreDraft,
    discardDraft,
    markSavedToServer,
    storageKey,
  }
}
