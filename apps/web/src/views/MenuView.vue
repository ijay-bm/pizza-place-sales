<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { usePreferredReducedMotion } from '@vueuse/core';
import { fetchPizzaTypes } from '@/api/orders';
import type { PizzaType } from '@/api/orders';
import { capitalize, formatMoney, shortPizzaName } from '@/utils/formatting';
import AppButton from '@/components/AppButton.vue';
import AppLoader from '@/components/AppLoader.vue';
import FormField from '@/components/FormField.vue';
import TextInput from '@/components/TextInput.vue';

const route = useRoute();
const reducedMotion = usePreferredReducedMotion();

/** The pizza type and size an order line linked to, outlined and scrolled to once the menu loads. */
const highlightedPizzaType = computed(() =>
  typeof route.query.pizza_type === 'string' ? route.query.pizza_type : ''
);
const highlightedSize = computed(() =>
  typeof route.query.size === 'string' ? route.query.size : ''
);

const pizzaTypes = ref<PizzaType[]>([]);
const isLoading = ref(false);
const loadFailed = ref(false);
const search = ref('');

async function loadPizzaTypes(): Promise<void> {
  isLoading.value = true;
  loadFailed.value = false;

  try {
    pizzaTypes.value = await fetchPizzaTypes();
  } catch {
    loadFailed.value = true;
  } finally {
    isLoading.value = false;
  }
}

onMounted(async () => {
  await loadPizzaTypes();

  if (highlightedPizzaType.value) {
    await nextTick();
    document.getElementById(`pizza-type-${highlightedPizzaType.value}`)?.scrollIntoView({
      block: 'center',
      behavior: reducedMotion.value === 'reduce' ? 'auto' : 'smooth'
    });
  }
});

/** Every pizza type is loaded, so the search narrows them here, by name or ingredient, as the user types. */
const matchingPizzaTypes = computed(() => {
  const term = search.value.trim().toLowerCase();

  if (term === '') {
    return pizzaTypes.value;
  }

  return pizzaTypes.value.filter(
    ({ name, ingredients }) =>
      name.toLowerCase().includes(term) ||
      (ingredients ?? []).some((ingredient) => ingredient.toLowerCase().includes(term))
  );
});

const categories = computed(() =>
  [...new Set(matchingPizzaTypes.value.map(({ category }) => category))].sort().map((category) => ({
    category,
    pizzaTypes: matchingPizzaTypes.value.filter((pizzaType) => pizzaType.category === category)
  }))
);
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Menu</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
          <template v-if="pizzaTypes.length">
            {{ matchingPizzaTypes.length }} of {{ pizzaTypes.length }} pizza types, with their
            sizes, prices and ingredients
          </template>
          <template v-else>&nbsp;</template>
        </p>
      </div>
      <div class="w-full sm:w-80">
        <FormField label="Search" input-id="menu-search">
          <TextInput
            id="menu-search"
            v-model="search"
            type="search"
            placeholder="Pizza type or ingredient"
          />
        </FormField>
      </div>
    </div>

    <div v-if="isLoading" class="flex justify-center py-24 text-zinc-500">
      <AppLoader label="Loading the menu" />
    </div>

    <div v-else-if="loadFailed" class="mt-8 flex flex-col items-start gap-3 text-sm">
      <p class="text-red-600 dark:text-red-400">The menu could not be loaded.</p>
      <AppButton variant="secondary" @click="loadPizzaTypes">Try again</AppButton>
    </div>

    <p
      v-else-if="categories.length === 0"
      class="py-12 text-center text-sm text-zinc-500 dark:text-zinc-400"
    >
      No pizza type's name or ingredients contain "{{ search.trim() }}".
    </p>

    <div v-else class="mt-6 space-y-8">
      <section v-for="{ category, pizzaTypes: pizzaTypesInCategory } in categories" :key="category">
        <h2 class="text-sm font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
          {{ capitalize(category) }}
        </h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="pizzaType in pizzaTypesInCategory"
            :id="`pizza-type-${pizzaType.code}`"
            :key="pizzaType.code"
            class="flex flex-col rounded-2xl border bg-white p-5 shadow-sm transition-shadow duration-150 dark:bg-zinc-900"
            :class="
              pizzaType.code === highlightedPizzaType
                ? 'border-orange-500 ring-4 ring-orange-500/20'
                : 'border-zinc-200 dark:border-zinc-800'
            "
          >
            <h3 class="font-medium">{{ shortPizzaName(pizzaType.name) }}</h3>
            <ul class="mt-3 flex flex-wrap gap-2 text-xs">
              <li
                v-for="pizza in pizzaType.pizzas ?? []"
                :key="pizza.code"
                class="rounded-full px-2.5 py-1"
                :class="
                  pizzaType.code === highlightedPizzaType && pizza.size === highlightedSize
                    ? 'bg-orange-100 text-orange-800 dark:bg-orange-500/20 dark:text-orange-300'
                    : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'
                "
              >
                <span class="font-semibold">{{ pizza.size }}</span>
                {{ formatMoney(pizza.price) }}
              </li>
            </ul>
            <p class="mt-3 flex-1 text-sm text-zinc-600 dark:text-zinc-400">
              {{ (pizzaType.ingredients ?? []).join(', ') }}
            </p>
            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm font-medium">
              <RouterLink
                :to="{ name: 'orders.index', query: { pizza_type: pizzaType.code } }"
                class="link"
              >
                Orders with this pizza type →
              </RouterLink>
              <RouterLink
                v-if="pizzaType.code === highlightedPizzaType && highlightedSize"
                :to="{
                  name: 'orders.index',
                  query: { pizza_type: pizzaType.code, size: highlightedSize }
                }"
                class="link"
              >
                In size {{ highlightedSize }} →
              </RouterLink>
            </div>
          </article>
        </div>
      </section>
    </div>
  </div>
</template>
