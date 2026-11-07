<script setup lang="ts">
import { computed, ref } from 'vue';
import { isAxiosError } from 'axios';
import { importSalesData } from '@/api/imports';
import type { ImportedRowCounts, SalesDataFiles } from '@/api/imports';
import AppButton from '@/components/AppButton.vue';
import SalesDataFilesPicker from '@/components/SalesDataFilesPicker.vue';

type FileField = keyof SalesDataFiles;

const SLOTS: { field: FileField; label: string; fileName: string }[] = [
  { field: 'pizza_types', label: 'Pizza types', fileName: 'pizza_types.csv' },
  { field: 'pizzas', label: 'Pizzas', fileName: 'pizzas.csv' },
  { field: 'orders', label: 'Orders', fileName: 'orders.csv' },
  { field: 'order_details', label: 'Order details', fileName: 'order_details.csv' }
];

const COUNT_LABELS: Record<keyof ImportedRowCounts, string> = {
  pizza_types: 'Pizza types',
  ingredients: 'Ingredients',
  pizzas: 'Pizzas',
  orders: 'Orders',
  order_items: 'Order lines'
};

const files = ref<Record<FileField, File | null>>({
  pizza_types: null,
  pizzas: null,
  orders: null,
  order_details: null
});
const errors = ref<Partial<Record<FileField, string[]>>>({});
const message = ref('');
const importedRowCounts = ref<ImportedRowCounts | null>(null);
const isImporting = ref(false);

const hasEveryFile = computed(() => SLOTS.every(({ field }) => files.value[field] !== null));

async function submit(): Promise<void> {
  if (!hasEveryFile.value) {
    return;
  }

  isImporting.value = true;
  errors.value = {};
  message.value = '';
  importedRowCounts.value = null;

  try {
    importedRowCounts.value = await importSalesData(files.value as SalesDataFiles);
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 422) {
      errors.value = error.response.data.errors;
    } else {
      message.value = 'The import failed. Try again.';
    }
  } finally {
    isImporting.value = false;
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight">Import sales data</h1>
    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
      Choose or drop the dataset's four CSV files. They are imported together; a file the server
      cannot read stops the import, and nothing is saved.
    </p>

    <form
      class="mt-6 space-y-5 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
      @submit.prevent="submit"
    >
      <SalesDataFilesPicker v-model="files" :slots="SLOTS" :errors="errors" />

      <Transition name="reveal">
        <p
          v-if="message"
          role="alert"
          class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400"
        >
          {{ message }}
        </p>
      </Transition>

      <AppButton type="submit" :busy="isImporting" :disabled="!hasEveryFile">
        {{ isImporting ? 'Importing…' : 'Import' }}
      </AppButton>
    </form>

    <Transition name="reveal">
      <section
        v-if="importedRowCounts"
        class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-500/30 dark:bg-emerald-500/10"
        aria-live="polite"
      >
        <h2 class="font-medium text-emerald-800 dark:text-emerald-300">Imported</h2>
        <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
          <div v-for="(label, key) in COUNT_LABELS" :key="key">
            <dt class="text-emerald-700/80 dark:text-emerald-300/70">{{ label }}</dt>
            <dd class="font-semibold text-emerald-900 dark:text-emerald-200">
              {{ importedRowCounts[key].toLocaleString() }}
            </dd>
          </div>
        </dl>
      </section>
    </Transition>
  </div>
</template>
