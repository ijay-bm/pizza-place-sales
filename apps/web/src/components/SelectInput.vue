<script setup lang="ts">
const model = defineModel<string>({ required: true });

defineProps<{
  id: string;
  options: { value: string; label: string }[];
  placeholder: string;
}>();
</script>

<template>
  <!-- The browser's own arrow ignores padding and sits on the border, so it is hidden and drawn here instead. -->
  <div class="relative">
    <select
      :id="id"
      v-model="model"
      class="block w-full appearance-none rounded-lg border border-zinc-300 bg-white py-2 pr-10 pl-3 text-sm text-zinc-900 shadow-sm transition-colors duration-150 focus:border-orange-500 focus:ring-4 focus:ring-orange-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
    >
      <option value="">{{ placeholder }}</option>
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
    <svg
      class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      aria-hidden="true"
    >
      <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
    </svg>
  </div>
</template>
