import './assets/main.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { isAxiosError } from 'axios';

import App from './App.vue';
import router from './router';
import { http } from './api/http';
import { useAuthStore } from './stores/auth';

const app = createApp(App);

app.use(createPinia());
app.use(router);

// A 401 on a signed-in screen means the session ended on the server, so the user is sent back to log in.
http.interceptors.response.use(undefined, (error) => {
  if (
    isAxiosError(error) &&
    error.response?.status === 401 &&
    router.currentRoute.value.meta.requiresAuth
  ) {
    useAuthStore().user = null;
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
  }

  return Promise.reject(error);
});

app.mount('#app');
