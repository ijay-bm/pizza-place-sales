<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { isAxiosError } from 'axios';
import { fetchOrder } from '@/api/orders';
import type { Order } from '@/api/orders';
import { capitalize, formatDateAndTime, formatMoney } from '@/formatting';
import AppButton from '@/components/AppButton.vue';
import AppLoader from '@/components/AppLoader.vue';

const route = useRoute();

const order = ref<Order | null>(null);
const isLoading = ref(false);
const loadError = ref('');

async function loadOrder(orderId: number): Promise<void> {
  isLoading.value = true;
  loadError.value = '';

  try {
    order.value = await fetchOrder(orderId);
  } catch (error) {
    loadError.value =
      isAxiosError(error) && error.response?.status === 404
        ? `There is no order #${orderId}.`
        : 'The order could not be loaded.';
  } finally {
    isLoading.value = false;
  }
}

// A link from one order to another reuses this screen, so the order is loaded again whenever the address's id changes.
watch(
  () => Number(route.params.orderId),
  (orderId) => {
    if (route.name === 'orders.show') {
      loadOrder(orderId);
    }
  },
  { immediate: true }
);
</script>

<template>
  <div class="max-w-3xl">
    <RouterLink
      :to="{ name: 'orders.index' }"
      class="text-sm text-zinc-500 transition-colors duration-150 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
    >
      ← Orders
    </RouterLink>

    <div v-if="isLoading && !order" class="flex justify-center py-12 text-zinc-500">
      <AppLoader label="Loading the order" />
    </div>

    <div v-else-if="loadError" class="mt-6 flex flex-col items-start gap-3 text-sm">
      <p class="text-red-600 dark:text-red-400">{{ loadError }}</p>
      <AppButton variant="secondary" @click="loadOrder(Number(route.params.orderId))">
        Try again
      </AppButton>
    </div>

    <template v-else-if="order">
      <h1 class="mt-3 text-2xl font-semibold tracking-tight">Order #{{ order.id }}</h1>
      <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
        {{ formatDateAndTime(order.ordered_at) }} · {{ order.pizza_count }}
        {{ order.pizza_count === 1 ? 'pizza' : 'pizzas' }}
      </p>

      <div
        class="mt-6 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
      >
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
          <thead class="bg-zinc-50 dark:bg-zinc-800/50">
            <tr class="text-zinc-600 dark:text-zinc-300">
              <th scope="col" class="px-4 py-3 text-left font-medium">Pizza</th>
              <th scope="col" class="px-4 py-3 text-left font-medium">Category</th>
              <th scope="col" class="px-4 py-3 text-left font-medium">Size</th>
              <th scope="col" class="px-4 py-3 text-right font-medium">Price</th>
              <th scope="col" class="px-4 py-3 text-right font-medium">Quantity</th>
              <th scope="col" class="px-4 py-3 text-right font-medium">Amount</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
            <tr v-for="orderItem in order.order_items ?? []" :key="orderItem.id">
              <td class="px-4 py-3 font-medium">{{ orderItem.pizza?.pizza_type?.name }}</td>
              <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                {{ capitalize(orderItem.pizza?.pizza_type?.category ?? '') }}
              </td>
              <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                {{ orderItem.pizza?.size }}
              </td>
              <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-300">
                {{ formatMoney(orderItem.pizza?.price ?? '0') }}
              </td>
              <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-300">
                {{ orderItem.quantity }}
              </td>
              <td class="px-4 py-3 text-right">{{ formatMoney(orderItem.line_total ?? '0') }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t border-zinc-200 dark:border-zinc-800">
              <td colspan="5" class="px-4 py-3 text-right font-medium">Total</td>
              <td class="px-4 py-3 text-right font-semibold">{{ formatMoney(order.total) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </template>
  </div>
</template>
