<script setup lang="ts">
defineProps<{
  label: string;
  inputId: string;
  error?: string | null;
}>();
</script>

<template>
  <div class="space-y-1.5">
    <label :for="inputId" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
      {{ label }}
    </label>
    <slot />
    <Transition name="reveal">
      <p
        v-if="error"
        :id="`${inputId}-error`"
        role="alert"
        class="text-sm text-red-600 dark:text-red-400"
      >
        {{ error }}
      </p>
    </Transition>
  </div>
</template>
