<script setup lang="ts">
import { useDark, useToggle } from '@vueuse/core';

const isDark = useDark();
const toggleDark = useToggle(isDark);
</script>

<template>
  <button
    type="button"
    class="rounded-lg p-2 text-zinc-500 transition-colors duration-150 hover:bg-zinc-100 hover:text-zinc-900 focus-visible:ring-4 focus-visible:ring-zinc-400/40 focus-visible:outline-none dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
    :aria-label="isDark ? 'Use the light theme' : 'Use the dark theme'"
    @click="toggleDark()"
  >
    <svg
      v-if="isDark"
      class="size-5"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.75"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="4" />
      <path
        stroke-linecap="round"
        d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"
      />
    </svg>
    <svg
      v-else
      class="size-5"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.75"
      aria-hidden="true"
    >
      <path stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
    </svg>
  </button>
</template>
