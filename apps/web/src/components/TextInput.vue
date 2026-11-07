<script setup lang="ts">
const model = defineModel<string>({ required: true });

defineProps<{
  id: string;
  type?: 'text' | 'email' | 'password' | 'search' | 'date';
  invalid?: boolean;
}>();
</script>

<template>
  <input
    :id="id"
    v-model="model"
    :type="type ?? 'text'"
    :aria-invalid="invalid"
    :aria-describedby="invalid ? `${id}-error` : undefined"
    class="block w-full rounded-lg border bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm transition-colors duration-150 placeholder:text-zinc-400 focus:ring-4 focus:outline-none dark:bg-zinc-900 dark:text-zinc-100"
    :class="
      invalid
        ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20'
        : 'border-zinc-300 focus:border-orange-500 focus:ring-orange-500/20 dark:border-zinc-700'
    "
  />
</template>
