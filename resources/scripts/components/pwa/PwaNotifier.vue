<template>
  <div>
    <!-- Offline status banner -->
    <transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform -translate-y-full opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform -translate-y-full opacity-0"
    >
      <div
        v-if="isOffline"
        class="fixed top-0 inset-x-0 z-50 bg-amber-500 text-white px-4 py-2 text-sm font-medium shadow-md flex items-center justify-between"
        role="alert"
      >
        <div class="flex items-center space-x-2">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 4.243a9 9 0 01-2.829-6.364m2.829 6.364L3 21m0-18l18 18M9.879 9.879a3 3 0 00-.879 2.121" />
          </svg>
          <span>You are currently offline. Working in offline preview mode. Reconnect before saving changes.</span>
        </div>
        <button
          class="ml-4 px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-xs font-semibold rounded uppercase tracking-wider focus:outline-none"
          @click="checkConnection"
        >
          Check
        </button>
      </div>
    </transition>

    <!-- Service worker update prompt toast -->
    <transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform translate-y-8 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform translate-y-8 opacity-0"
    >
      <div
        v-if="needRefresh"
        class="fixed bottom-5 right-5 z-50 max-w-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl p-4 flex flex-col space-y-3"
        role="alert"
      >
        <div class="flex items-start justify-between">
          <div class="flex items-center space-x-2">
            <div class="w-2.5 h-2.5 rounded-full bg-indigo-500 animate-pulse"></div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
              Update Available
            </h4>
          </div>
          <button
            class="text-gray-400 hover:text-gray-500 focus:outline-none text-xs"
            @click="closePrompt"
          >
            ✕
          </button>
        </div>
        <p class="text-xs text-gray-600 dark:text-gray-300">
          A new version of the application is available. Refresh now to apply the latest updates.
        </p>
        <div class="flex justify-end space-x-2 pt-1">
          <button
            class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition"
            @click="closePrompt"
          >
            Later
          </button>
          <button
            class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded shadow-sm transition"
            @click="updateApp"
          >
            Refresh & Update
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const isOffline = ref(!navigator.onLine)
const needRefresh = ref(false)
let updateSWHandler = null

const handleOnline = () => {
  isOffline.value = false
}

const handleOffline = () => {
  isOffline.value = true
}

const checkConnection = () => {
  isOffline.value = !navigator.onLine
}

const closePrompt = () => {
  needRefresh.value = false
}

const updateApp = async () => {
  if (typeof updateSWHandler === 'function') {
    await updateSWHandler(true)
  } else {
    window.location.reload()
  }
}

onMounted(async () => {
  window.addEventListener('online', handleOnline)
  window.addEventListener('offline', handleOffline)

  // Register service worker lifecycle
  if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
    try {
      const { registerSW } = await import('virtual:pwa-register')
      updateSWHandler = registerSW({
        immediate: true,
        onNeedRefresh() {
          needRefresh.value = true
        },
        onOfflineReady() {
          // Application ready to work offline
        },
      })
    } catch {
      // In development or environments without virtual:pwa-register
    }
  }
})

onUnmounted(() => {
  window.removeEventListener('online', handleOnline)
  window.removeEventListener('offline', handleOffline)
})
</script>
