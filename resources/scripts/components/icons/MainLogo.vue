<template>
  <svg viewBox="0 0 220 48" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient
        id="beres_logo_grad"
        x1="2"
        y1="4"
        x2="42"
        y2="44"
        gradientUnits="userSpaceOnUse"
      >
        <stop :stop-color="darkColor" />
        <stop offset="1" :stop-color="lightColor" />
      </linearGradient>
    </defs>
    <!-- Emblem -->
    <rect
      x="2"
      y="4"
      width="40"
      height="40"
      rx="10"
      :fill="darkColor === 'white' ? 'white' : 'url(#beres_logo_grad)'"
    />
    <path
      d="M15 12h9l6 6v16a2 2 0 01-2 2H15a2 2 0 01-2-2V14a2 2 0 012-2z"
      :stroke="darkColor === 'white' ? 'rgba(var(--color-primary-600), 1)' : 'white'"
      stroke-width="2.2"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
    <path
      d="M19 20h8M19 25h8M19 30h5"
      :stroke="darkColor === 'white' ? 'rgba(var(--color-primary-600), 1)' : 'white'"
      stroke-width="2.2"
      stroke-linecap="round"
    />
    <!-- Wordmark -->
    <text
      x="52"
      y="33"
      font-family="'Plus Jakarta Sans', system-ui, -apple-system, sans-serif"
      font-size="27"
      font-weight="600"
      :fill="darkColor"
      letter-spacing="-0.5"
    >
      Beres<tspan font-weight="800" :fill="lightColor">Suite</tspan>
    </text>
  </svg>
</template>

<script setup>
defineProps({
  darkColor: {
    type: String,
    default: 'rgba(var(--color-primary-500), 1)',
  },
  lightColor: {
    type: String,
    default: 'rgba(var(--color-primary-400), 1)',
  },
})
</script>
