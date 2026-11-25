<script setup lang="ts">
withDefaults(
  defineProps<{
    size?: number | string;
    showText?: boolean;
    subtext?: string;
  }>(),
  {
    size: 32,
    showText: false,
    subtext: 'Woldia University',
  }
);
</script>

<template>
  <div class="flex items-center gap-2.5 shrink-0 select-none">
    <!-- Clean WDU Library SVG Logo -->
    <svg
      :width="size"
      :height="size"
      viewBox="0 0 512 512"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="shrink-0 drop-shadow-xs"
    >
      <defs>
        <linearGradient id="wduGradPrimary" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#1e3a8a"/>
          <stop offset="50%" stop-color="#1d4ed8"/>
          <stop offset="100%" stop-color="#2563eb"/>
        </linearGradient>
        <linearGradient id="wduGradGold" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#fbbf24"/>
          <stop offset="100%" stop-color="#d97706"/>
        </linearGradient>
      </defs>

      <!-- Background Squircle -->
      <rect x="24" y="24" width="464" height="464" rx="104" fill="url(#wduGradPrimary)" stroke="url(#wduGradGold)" stroke-width="8"/>
      <rect x="36" y="36" width="440" height="440" rx="92" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2"/>

      <!-- Knowledge Radiance -->
      <g transform="translate(256, 140)">
        <circle cx="0" cy="0" r="16" fill="url(#wduGradGold)"/>
        <path d="M0 -30 L0 30 M-30 0 L30 0 M-21 -21 L21 21 M-21 21 L21 -21" stroke="#fcd34d" stroke-width="4" stroke-linecap="round"/>
        <circle cx="0" cy="0" r="6" fill="#ffffff"/>
      </g>

      <!-- Open Book -->
      <g transform="translate(256, 260)">
        <path d="M0 -55 C-60 -75 -120 -50 -130 -45 L-130 55 C-120 50 -60 25 0 45 Z" fill="#ffffff" stroke="#cbd5e1" stroke-width="3"/>
        <line x1="-110" y1="-20" x2="-25" y2="-10" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>
        <line x1="-110" y1="2" x2="-25" y2="12" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>
        <line x1="-110" y1="24" x2="-45" y2="34" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>

        <path d="M0 -55 C60 -75 120 -50 130 -45 L130 55 C120 50 60 25 0 45 Z" fill="#f8fafc" stroke="#cbd5e1" stroke-width="3"/>
        <line x1="25" y1="-10" x2="110" y2="-20" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>
        <line x1="25" y1="12" x2="110" y2="2" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>
        <line x1="25" y1="34" x2="90" y2="24" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>

        <line x1="0" y1="-56" x2="0" y2="47" stroke="url(#wduGradGold)" stroke-width="6" stroke-linecap="round"/>
      </g>

      <!-- WDU Emblem Ribbon -->
      <g transform="translate(256, 360)">
        <rect x="-85" y="0" width="170" height="34" rx="8" fill="url(#wduGradGold)" stroke="#ffffff" stroke-width="2"/>
        <text x="0" y="24" text-anchor="middle" font-family="system-ui, -apple-system, sans-serif" font-weight="900" font-size="20" fill="#0f172a" letter-spacing="2">WDU</text>
      </g>
    </svg>

    <!-- Optional Label -->
    <div v-if="showText" class="flex flex-col min-w-0">
      <span class="font-bold text-xs tracking-tight text-main-text leading-tight truncate">
        Woldia University Library
      </span>
      <span class="text-[8.5px] text-brand-blue dark:text-brand-yellow font-bold tracking-wider uppercase mt-0.5 truncate">
        {{ subtext }}
      </span>
    </div>
  </div>
</template>
