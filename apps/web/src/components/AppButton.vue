<script setup lang="ts">
import AppLoader from './AppLoader.vue';

/**
 * The one button. `busy` shows the loader before the label and takes the click, for an action in flight.
 */
withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary';
    type?: 'button' | 'submit';
    busy?: boolean;
    disabled?: boolean;
  }>(),
  { variant: 'primary', type: 'button', busy: false, disabled: false }
);

const VARIANT_CLASS = {
  primary:
    'bg-orange-600 text-white shadow-sm hover:bg-orange-500 focus-visible:ring-orange-500/40 dark:bg-orange-500 dark:hover:bg-orange-400',
  secondary:
    'border border-zinc-300 bg-white text-zinc-700 shadow-sm hover:bg-zinc-50 focus-visible:ring-zinc-400/40 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800'
} as const;
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || busy"
    :aria-busy="busy"
    class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors duration-150 focus-visible:ring-4 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
    :class="VARIANT_CLASS[variant]"
  >
    <AppLoader v-if="busy" />
    <slot />
  </button>
</template>
