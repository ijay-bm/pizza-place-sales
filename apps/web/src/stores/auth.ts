import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { isAxiosError } from 'axios';
import * as authApi from '@/api/auth';
import type { Credentials, User } from '@/api/auth';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);
  const isAuthenticated = computed(() => user.value !== null);

  /**
   * Asks the server who is signed in; a 401 means nobody is, anything else is an error and is thrown.
   */
  async function fetchUser(): Promise<void> {
    try {
      user.value = await authApi.fetchUser();
    } catch (error) {
      if (isAxiosError(error) && error.response?.status === 401) {
        user.value = null;

        return;
      }

      throw error;
    }
  }

  async function login(credentials: Credentials): Promise<void> {
    await authApi.fetchCsrfCookie();
    user.value = await authApi.login(credentials);
  }

  async function logout(): Promise<void> {
    await authApi.logout();
    user.value = null;
  }

  return { user, isAuthenticated, fetchUser, login, logout };
});
