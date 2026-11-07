<script setup lang="ts">
withDefaults(defineProps<{ label?: string }>(), { label: 'Loading' });
</script>

<template>
  <span role="status" class="inline-flex">
    <span
      class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent"
      aria-hidden="true"
    />
    <span class="sr-only">{{ label }}</span>
  </span>
</template>
