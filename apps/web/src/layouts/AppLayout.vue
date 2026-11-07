<script setup lang="ts">
import { ref } from 'vue';
import { RouterView, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppButton from '@/components/AppButton.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';

const authStore = useAuthStore();
const router = useRouter();
const isLoggingOut = ref(false);

async function logout(): Promise<void> {
  isLoggingOut.value = true;

  try {
    await authStore.logout();
    await router.push({ name: 'login' });
  } finally {
    isLoggingOut.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen">
    <header
      class="sticky top-0 z-10 border-b border-zinc-200 bg-white/80 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80"
    >
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <span class="flex items-center gap-2 font-semibold tracking-tight">
          <span class="size-2.5 rounded-full bg-orange-500" aria-hidden="true" />
          Pizza Place Sales
        </span>
        <div class="flex items-center gap-3">
          <span class="hidden text-sm text-zinc-500 sm:inline dark:text-zinc-400">
            {{ authStore.user?.name }}
          </span>
          <ThemeToggle />
          <AppButton variant="secondary" :busy="isLoggingOut" @click="logout">Log out</AppButton>
        </div>
      </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <RouterView v-slot="{ Component }">
        <Transition name="fade" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>
  </div>
</template>
