<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
</script>

<template>
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
      Welcome back, {{ authStore.user?.name }}.
    </p>
  </div>
</template>
