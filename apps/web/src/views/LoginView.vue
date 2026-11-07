<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { isAxiosError } from 'axios';
import { useAuthStore } from '@/stores/auth';
import AppButton from '@/components/AppButton.vue';
import FormField from '@/components/FormField.vue';
import TextInput from '@/components/TextInput.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({ email: '', password: '' });
const errors = ref<Record<string, string[]>>({});
const message = ref('');
const isSubmitting = ref(false);

async function submit(): Promise<void> {
  isSubmitting.value = true;
  errors.value = {};
  message.value = '';

  try {
    await authStore.login(form);
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/';
    await router.push(redirect);
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 422) {
      errors.value = error.response.data.errors;
    } else if (isAxiosError(error) && error.response?.status === 429) {
      message.value = 'Too many attempts. Wait a minute and try again.';
    } else {
      message.value = 'Logging in failed. Try again.';
    }
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <div class="relative flex min-h-screen items-center justify-center px-4">
    <div class="absolute top-4 right-4">
      <ThemeToggle />
    </div>

    <div class="w-full max-w-sm">
      <div class="mb-8 flex flex-col items-center gap-3">
        <span
          class="flex size-11 items-center justify-center rounded-xl bg-orange-500 shadow-lg shadow-orange-500/30"
        >
          <span class="size-3 rounded-full bg-white" aria-hidden="true" />
        </span>
        <h1 class="text-xl font-semibold tracking-tight">Pizza Place Sales</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Log in to the sales dashboard</p>
      </div>

      <form
        class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        @submit.prevent="submit"
      >
        <Transition name="reveal">
          <p
            v-if="message"
            role="alert"
            class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400"
          >
            {{ message }}
          </p>
        </Transition>

        <FormField label="Email" input-id="email" :error="errors.email?.[0]">
          <TextInput
            id="email"
            required
            v-model="form.email"
            type="email"
            autocomplete="username"
            :invalid="Boolean(errors.email)"
          />
        </FormField>

        <FormField label="Password" input-id="password" :error="errors.password?.[0]">
          <TextInput
            id="password"
            required
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            :invalid="Boolean(errors.password)"
          />
        </FormField>

        <AppButton type="submit" class="w-full" :busy="isSubmitting">
          {{ isSubmitting ? 'Logging in…' : 'Log in' }}
        </AppButton>
      </form>
    </div>
  </div>
</template>
