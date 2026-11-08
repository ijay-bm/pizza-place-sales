<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { LocationQueryRaw } from 'vue-router';
import { isCancel } from 'axios';
import { fetchStatistics } from '@/api/statistics';
import type { PizzaTypeSales, Statistics } from '@/api/statistics';
import {
  capitalize,
  formatHour,
  formatMoney,
  formatMonths,
  formatWeekday,
  formatWholeMoney,
  shortPizzaName
} from '@/utils/formatting';
import { useAuthStore } from '@/stores/auth';
import AppButton from '@/components/AppButton.vue';
import AppLoader from '@/components/AppLoader.vue';
import BarChart from '@/components/BarChart.vue';
import FormField from '@/components/FormField.vue';
import TextInput from '@/components/TextInput.vue';

const SELLERS_SHOWN = 5;

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

/** The date range as the address holds it, so a view of July can be linked to and survives a reload. */
const dateRange = computed(() => ({
  orderedFrom: typeof route.query.from === 'string' ? route.query.from : '',
  orderedTo: typeof route.query.to === 'string' ? route.query.to : ''
}));

const statistics = ref<Statistics | null>(null);
const isLoading = ref(false);
const loadFailed = ref(false);
let inFlight: AbortController | null = null;

/** Loads every statistic for the range; a load still in flight is cancelled, so an older answer never wins. */
async function loadStatistics(range: typeof dateRange.value): Promise<void> {
  inFlight?.abort();
  const controller = new AbortController();
  inFlight = controller;
  isLoading.value = true;
  loadFailed.value = false;

  try {
    statistics.value = await fetchStatistics(
      { ordered_from: range.orderedFrom, ordered_to: range.orderedTo },
      controller.signal
    );
  } catch (error) {
    if (isCancel(error)) {
      return;
    }

    loadFailed.value = true;
  } finally {
    if (inFlight === controller) {
      isLoading.value = false;
    }
  }
}

// The address holds the range, so a change to it loads the statistics again; leaving for another screen loads nothing.
watch(
  dateRange,
  (range) => {
    if (route.name === 'dashboard') {
      loadStatistics(range);
    }
  },
  { immediate: true }
);

function updateRange(changes: { from?: string; to?: string }): void {
  const query: LocationQueryRaw = { ...route.query, ...changes };

  for (const key of Object.keys(query)) {
    if (!query[key]) {
      delete query[key];
    }
  }

  router.replace({ query });
}

const bestSellers = computed<PizzaTypeSales[]>(
  () => statistics.value?.pizzaTypeSales.slice(0, SELLERS_SHOWN) ?? []
);
const worstSellers = computed<PizzaTypeSales[]>(
  () => statistics.value?.pizzaTypeSales.slice(-SELLERS_SHOWN).reverse() ?? []
);

const summaryTiles = computed(() => {
  const summary = statistics.value?.summary;

  if (!summary) {
    return [];
  }

  return [
    { label: 'Revenue', value: formatMoney(summary.revenue) },
    { label: 'Orders', value: summary.orders.toLocaleString() },
    { label: 'Pizzas sold', value: summary.pizzas_sold.toLocaleString() },
    {
      label: 'Average order',
      value: formatMoney(summary.average_order_value),
      detail: `${summary.average_pizzas_per_order} pizzas per order`
    }
  ];
});
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
          Welcome back, {{ authStore.user?.name }}. Sales for the dates below, or the whole dataset.
        </p>
      </div>
      <div class="flex items-end gap-3">
        <FormField label="From" input-id="statistics-from">
          <TextInput
            id="statistics-from"
            :model-value="dateRange.orderedFrom"
            type="date"
            @update:model-value="updateRange({ from: $event })"
          />
        </FormField>
        <FormField label="To" input-id="statistics-to">
          <TextInput
            id="statistics-to"
            :model-value="dateRange.orderedTo"
            type="date"
            @update:model-value="updateRange({ to: $event })"
          />
        </FormField>
        <AppButton
          variant="secondary"
          :disabled="!dateRange.orderedFrom && !dateRange.orderedTo"
          @click="updateRange({ from: '', to: '' })"
        >
          All dates
        </AppButton>
      </div>
    </div>

    <div v-if="!statistics && isLoading" class="flex justify-center py-24 text-zinc-500">
      <AppLoader label="Loading the statistics" />
    </div>

    <div v-else-if="loadFailed" class="mt-8 flex flex-col items-start gap-3 text-sm">
      <p class="text-red-600 dark:text-red-400">The statistics could not be loaded.</p>
      <AppButton variant="secondary" @click="loadStatistics(dateRange)">Try again</AppButton>
    </div>

    <div
      v-else-if="statistics"
      class="mt-6 space-y-6 transition-opacity duration-150"
      :class="{ 'opacity-50': isLoading }"
    >
      <p
        v-if="statistics.summary.orders === 0"
        class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400"
      >
        No orders were placed in this range.
      </p>

      <template v-else>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="tile in summaryTiles"
            :key="tile.label"
            class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
          >
            <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ tile.label }}</dt>
            <dd class="mt-1 text-2xl font-semibold tracking-tight">{{ tile.value }}</dd>
            <dd v-if="tile.detail" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
              {{ tile.detail }}
            </dd>
          </div>
        </dl>

        <div class="grid gap-6 lg:grid-cols-2">
          <section
            class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
          >
            <h2 class="font-medium">Revenue by month</h2>
            <BarChart
              class="mt-4"
              series-label="Revenue"
              :labels="formatMonths(statistics.revenueByMonth.map(({ month }) => month))"
              :values="statistics.revenueByMonth.map(({ revenue }) => Number(revenue))"
              :format-value="formatWholeMoney"
            />
          </section>

          <section
            class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
          >
            <h2 class="font-medium">Revenue by category</h2>
            <BarChart
              class="mt-4"
              series-label="Revenue"
              horizontal
              :labels="statistics.categorySales.map(({ category }) => capitalize(category))"
              :values="statistics.categorySales.map(({ revenue }) => Number(revenue))"
              :format-value="formatWholeMoney"
            />
          </section>

          <section
            class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
          >
            <h2 class="font-medium">Orders by hour of the day</h2>
            <BarChart
              class="mt-4"
              series-label="Orders"
              :labels="statistics.ordersByHour.map(({ hour }) => formatHour(hour))"
              :values="statistics.ordersByHour.map(({ orders }) => orders)"
            />
          </section>

          <section
            class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
          >
            <h2 class="font-medium">Orders by day of the week</h2>
            <BarChart
              class="mt-4"
              series-label="Orders"
              :labels="statistics.ordersByWeekday.map(({ weekday }) => formatWeekday(weekday))"
              :values="statistics.ordersByWeekday.map(({ orders }) => orders)"
            />
          </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
          <section
            v-for="sellers in [
              { title: 'Best sellers', pizzaTypes: bestSellers },
              { title: 'Worst sellers', pizzaTypes: worstSellers }
            ]"
            :key="sellers.title"
            class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
          >
            <h2 class="font-medium">{{ sellers.title }}</h2>
            <table class="mt-3 w-full text-sm">
              <thead class="text-left text-zinc-500 dark:text-zinc-400">
                <tr>
                  <th scope="col" class="py-2 font-medium">Pizza type</th>
                  <th scope="col" class="py-2 font-medium">Category</th>
                  <th scope="col" class="py-2 text-right font-medium">Sold</th>
                  <th scope="col" class="py-2 text-right font-medium">Revenue</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                <tr v-for="pizzaType in sellers.pizzaTypes" :key="pizzaType.code">
                  <td class="py-2 font-medium">{{ shortPizzaName(pizzaType.name) }}</td>
                  <td class="py-2 text-zinc-600 dark:text-zinc-300">
                    {{ capitalize(pizzaType.category) }}
                  </td>
                  <td class="py-2 text-right text-zinc-600 dark:text-zinc-300">
                    {{ pizzaType.quantity.toLocaleString() }}
                  </td>
                  <td class="py-2 text-right">{{ formatMoney(pizzaType.revenue) }}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </div>
      </template>
    </div>
  </div>
</template>
