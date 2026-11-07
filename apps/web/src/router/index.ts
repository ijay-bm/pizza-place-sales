import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppLayout from '@/layouts/AppLayout.vue';

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean;
    guestOnly?: boolean;
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true }
    },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue')
        },
        {
          path: 'orders',
          name: 'orders.index',
          component: () => import('@/views/OrdersView.vue')
        },
        {
          path: 'orders/:orderId(\\d+)',
          name: 'orders.show',
          component: () => import('@/views/OrderView.vue')
        },
        {
          path: 'import',
          name: 'imports.create',
          component: () => import('@/views/ImportView.vue')
        }
      ]
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'dashboard' }
    }
  ]
});

let hasFetchedUser = false;

router.beforeEach(async (to) => {
  const authStore = useAuthStore();

  // The session cookie is HttpOnly, so only the server can say whether it is still valid; ask once per page load.
  if (!hasFetchedUser) {
    await authStore.fetchUser();
    hasFetchedUser = true;
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    return { name: 'dashboard' };
  }
});

export default router;
