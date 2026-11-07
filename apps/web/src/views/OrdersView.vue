<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { LocationQueryRaw } from 'vue-router';
import { refDebounced } from '@vueuse/core';
import { isCancel } from 'axios';
import { fetchOrders, fetchPizzaTypes } from '@/api/orders';
import type { Order, OrderFilters, Page, PizzaType } from '@/api/orders';
import { capitalize, formatDateAndTime, formatMoney, shortPizzaName } from '@/formatting';
import AppButton from '@/components/AppButton.vue';
import AppLoader from '@/components/AppLoader.vue';
import FormField from '@/components/FormField.vue';
import SelectInput from '@/components/SelectInput.vue';
import TextInput from '@/components/TextInput.vue';

const DEFAULT_SORT = '-ordered_at';

const SORTABLE_COLUMNS = [
  { column: 'id', label: 'Order' },
  { column: 'ordered_at', label: 'Ordered' },
  { column: 'total', label: 'Total' }
] as const;

const route = useRoute();
const router = useRouter();

/** The list's state as the address holds it, so a search can be linked to and survives a reload. */
const listQuery = computed(() => {
  const read = (key: string): string =>
    typeof route.query[key] === 'string' ? (route.query[key] as string) : '';

  return {
    search: read('search'),
    orderedFrom: read('from'),
    orderedTo: read('to'),
    category: read('category'),
    size: read('size'),
    pizzaType: read('pizza_type'),
    sort: read('sort') || DEFAULT_SORT,
    page: Math.max(1, Number(read('page')) || 1)
  };
});

const page = ref<Page<Order> | null>(null);
const pizzaTypes = ref<PizzaType[]>([]);
const isLoading = ref(false);
const loadFailed = ref(false);
let inFlight: AbortController | null = null;

const categoryOptions = computed(() =>
  [...new Set(pizzaTypes.value.map(({ category }) => category))]
    .sort()
    .map((category) => ({ value: category, label: capitalize(category) }))
);
// Each type's pizzas come cheapest first, which is smallest first, so the first sighting of each size keeps S to XXL.
const sizeOptions = computed(() =>
  [
    ...new Set(pizzaTypes.value.flatMap(({ pizzas }) => (pizzas ?? []).map(({ size }) => size)))
  ].map((size) => ({
    value: size,
    label: size
  }))
);
const pizzaTypeOptions = computed(() =>
  pizzaTypes.value.map(({ code, name }) => ({ value: code, label: shortPizzaName(name) }))
);

const hasFilters = computed(() =>
  Boolean(
    listQuery.value.search ||
    listQuery.value.orderedFrom ||
    listQuery.value.orderedTo ||
    listQuery.value.category ||
    listQuery.value.size ||
    listQuery.value.pizzaType
  )
);

/** Writes changes into the address; an empty value is dropped, and any change but the page starts at page one. */
function updateQuery(changes: Record<string, string | number | undefined>): void {
  const query: LocationQueryRaw = { ...route.query, page: undefined, ...changes };

  for (const key of Object.keys(query)) {
    if (query[key] === '' || query[key] === undefined || (key === 'page' && query[key] === 1)) {
      delete query[key];
    }
  }

  router.replace({ query });
}

const searchInput = ref(listQuery.value.search);
const debouncedSearch = refDebounced(searchInput, 300);

// The search waits until typing stops for 300 ms, then becomes part of the address.
watch(debouncedSearch, (search) => {
  if (search.trim() !== listQuery.value.search) {
    updateQuery({ search: search.trim() });
  }
});

// The address changed from outside the search box, by Back or a link: the box shows what is being searched.
watch(
  () => listQuery.value.search,
  (search) => {
    if (search !== debouncedSearch.value.trim()) {
      searchInput.value = search;
    }
  }
);

/** Loads the page the query asks for; a request still in flight is cancelled, so a slower older answer never replaces it. */
async function loadPage(query: typeof listQuery.value): Promise<void> {
  inFlight?.abort();
  const controller = new AbortController();
  inFlight = controller;
  isLoading.value = true;
  loadFailed.value = false;

  const filters: OrderFilters = {
    search: query.search,
    ordered_from: query.orderedFrom,
    ordered_to: query.orderedTo,
    category: query.category,
    size: query.size,
    pizza_type: query.pizzaType
  };

  try {
    page.value = await fetchOrders(filters, query.sort, query.page, controller.signal);
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

// The address is the list's state, so any change to it loads that page. Leaving for another screen changes the
// address too, and loads nothing.
watch(
  listQuery,
  (query) => {
    if (route.name === 'orders.index') {
      loadPage(query);
    }
  },
  { immediate: true }
);

onMounted(async () => {
  pizzaTypes.value = await fetchPizzaTypes();
});

function sortBy(column: string): void {
  updateQuery({ sort: listQuery.value.sort === `-${column}` ? column : `-${column}` });
}

function sortDirection(column: string): 'ascending' | 'descending' | 'none' {
  if (listQuery.value.sort === column) {
    return 'ascending';
  }

  return listQuery.value.sort === `-${column}` ? 'descending' : 'none';
}

function clearFilters(): void {
  searchInput.value = '';
  router.replace({
    query: listQuery.value.sort === DEFAULT_SORT ? {} : { sort: listQuery.value.sort }
  });
}

function pizzasOf(order: Order): string {
  const names = (order.order_items ?? []).map(({ pizza, quantity }) => {
    const name = `${shortPizzaName(pizza?.pizza_type?.name ?? '')} ${pizza?.size ?? ''}`;

    return quantity > 1 ? `${name} ×${quantity}` : name;
  });

  return names.length > 3
    ? `${names.slice(0, 3).join(', ')} +${names.length - 3} more`
    : names.join(', ');
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Orders</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
          <template v-if="page">{{ page.meta.total.toLocaleString() }} orders</template>
          <template v-else>&nbsp;</template>
        </p>
      </div>
    </div>

    <div
      class="mt-6 grid gap-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6 dark:border-zinc-800 dark:bg-zinc-900"
    >
      <div class="sm:col-span-2">
        <FormField label="Search" input-id="search">
          <TextInput
            id="search"
            v-model="searchInput"
            type="search"
            placeholder="Order number, pizza or ingredient"
          />
        </FormField>
      </div>
      <FormField label="From" input-id="ordered-from">
        <TextInput
          id="ordered-from"
          :model-value="listQuery.orderedFrom"
          type="date"
          @update:model-value="updateQuery({ from: $event })"
        />
      </FormField>
      <FormField label="To" input-id="ordered-to">
        <TextInput
          id="ordered-to"
          :model-value="listQuery.orderedTo"
          type="date"
          @update:model-value="updateQuery({ to: $event })"
        />
      </FormField>
      <FormField label="Category" input-id="category">
        <SelectInput
          id="category"
          :model-value="listQuery.category"
          :options="categoryOptions"
          placeholder="All categories"
          @update:model-value="updateQuery({ category: $event })"
        />
      </FormField>
      <FormField label="Size" input-id="size">
        <SelectInput
          id="size"
          :model-value="listQuery.size"
          :options="sizeOptions"
          placeholder="All sizes"
          @update:model-value="updateQuery({ size: $event })"
        />
      </FormField>
      <div class="sm:col-span-2 lg:col-span-5">
        <FormField label="Pizza" input-id="pizza-type">
          <SelectInput
            id="pizza-type"
            :model-value="listQuery.pizzaType"
            :options="pizzaTypeOptions"
            placeholder="All pizzas"
            @update:model-value="updateQuery({ pizza_type: $event })"
          />
        </FormField>
      </div>
      <div class="flex items-end">
        <AppButton variant="secondary" class="w-full" :disabled="!hasFilters" @click="clearFilters">
          Clear filters
        </AppButton>
      </div>
    </div>

    <div
      class="mt-6 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
    >
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
          <thead class="bg-zinc-50 dark:bg-zinc-800/50">
            <tr>
              <th
                v-for="{ column, label } in SORTABLE_COLUMNS"
                :key="column"
                scope="col"
                :aria-sort="sortDirection(column)"
                class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300"
                :class="{ 'text-right': column === 'total' }"
              >
                <button
                  type="button"
                  class="flex w-full items-center gap-2 rounded transition-colors duration-150 hover:text-zinc-900 focus-visible:ring-4 focus-visible:ring-zinc-400/40 focus-visible:outline-none dark:hover:text-zinc-100"
                  :class="column === 'total' ? 'justify-end' : 'justify-between'"
                  @click="sortBy(column)"
                >
                  {{ label }}
                  <!-- A fixed width, so the label does not move when the column's sort changes. -->
                  <span aria-hidden="true" class="w-3 text-right text-xs">
                    {{
                      sortDirection(column) === 'ascending'
                        ? '▲'
                        : sortDirection(column) === 'descending'
                          ? '▼'
                          : ''
                    }}
                  </span>
                </button>
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300"
              >
                Pizzas
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-300"
              >
                Count
              </th>
            </tr>
          </thead>
          <tbody
            class="divide-y divide-zinc-100 transition-opacity duration-150 dark:divide-zinc-800"
            :class="{ 'opacity-50': isLoading && page }"
          >
            <tr
              v-for="order in page?.data ?? []"
              :key="order.id"
              class="cursor-pointer transition-colors duration-150 hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
              @click="router.push({ name: 'orders.show', params: { orderId: order.id } })"
            >
              <td class="px-4 py-3 font-medium">
                <RouterLink
                  :to="{ name: 'orders.show', params: { orderId: order.id } }"
                  class="hover:text-orange-600 dark:hover:text-orange-400"
                  @click.stop
                >
                  #{{ order.id }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-zinc-600 dark:text-zinc-300">
                {{ formatDateAndTime(order.ordered_at) }}
              </td>
              <td class="px-4 py-3 text-right font-medium">{{ formatMoney(order.total) }}</td>
              <td class="max-w-md truncate px-4 py-3 text-zinc-600 dark:text-zinc-300">
                {{ pizzasOf(order) }}
              </td>
              <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-300">
                {{ order.pizza_count }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!page && isLoading" class="flex justify-center py-12 text-zinc-500">
        <AppLoader label="Loading orders" />
      </div>
      <div v-else-if="loadFailed" class="flex flex-col items-center gap-3 py-12 text-sm">
        <p class="text-red-600 dark:text-red-400">The orders could not be loaded.</p>
        <AppButton variant="secondary" @click="loadPage(listQuery)">Try again</AppButton>
      </div>
      <p
        v-else-if="page && page.data.length === 0"
        class="py-12 text-center text-sm text-zinc-500 dark:text-zinc-400"
      >
        No orders match these filters.
      </p>

      <div
        v-if="page && page.meta.total > 0"
        class="flex items-center justify-between border-t border-zinc-200 px-4 py-3 text-sm dark:border-zinc-800"
      >
        <span class="text-zinc-500 dark:text-zinc-400">
          {{ page.meta.from?.toLocaleString() }}–{{ page.meta.to?.toLocaleString() }} of
          {{ page.meta.total.toLocaleString() }}
        </span>
        <div class="flex items-center gap-2">
          <AppButton
            variant="secondary"
            :disabled="page.meta.current_page <= 1"
            @click="updateQuery({ page: page.meta.current_page - 1 })"
          >
            Previous
          </AppButton>
          <span class="text-zinc-500 dark:text-zinc-400">
            Page {{ page.meta.current_page }} of {{ page.meta.last_page.toLocaleString() }}
          </span>
          <AppButton
            variant="secondary"
            :disabled="page.meta.current_page >= page.meta.last_page"
            @click="updateQuery({ page: page.meta.current_page + 1 })"
          >
            Next
          </AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
