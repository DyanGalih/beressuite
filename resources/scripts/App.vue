<template>
  <PwaNotifier />

  <router-view />

  <BaseDialog />
</template>

<script setup>
import PwaNotifier from '@/scripts/components/pwa/PwaNotifier.vue'
</script>

