<script setup lang="ts">
import { ref } from 'vue';
import type { SalesDataFiles } from '@/api/imports';

type FileField = keyof SalesDataFiles;

/**
 * Takes several files at once, by the shared picker or by dropping them, and puts each in the slot whose expected file
 * name it has exactly; a file with any other name is listed and left out. Each slot also has its own picker, which
 * takes a file of any name. The name only chooses the slot: the server still checks every file's columns.
 */
const files = defineModel<Record<FileField, File | null>>({ required: true });

const props = defineProps<{
  slots: { field: FileField; label: string; fileName: string }[];
  errors: Partial<Record<FileField, string[]>>;
}>();

const input = ref<HTMLInputElement | null>(null);
const isDraggingOver = ref(false);
const unexpectedFileNames = ref<string[]>([]);

function take(fileList: FileList | null | undefined): void {
  // Assigned once at the end: the model updates only when the parent re-renders, so writing it per file would
  // spread the same stale value each time and keep only the last file.
  const nextFiles = { ...files.value };
  unexpectedFileNames.value = [];

  for (const file of Array.from(fileList ?? [])) {
    const slot = props.slots.find(({ fileName }) => fileName === file.name);

    if (slot === undefined) {
      unexpectedFileNames.value.push(file.name);
      continue;
    }

    nextFiles[slot.field] = file;
  }

  files.value = nextFiles;
}

function chooseFor(field: FileField, event: Event): void {
  const fileInput = event.target as HTMLInputElement;
  const file = fileInput.files?.[0];

  if (file !== undefined) {
    files.value = { ...files.value, [field]: file };
  }

  fileInput.value = '';
}

function choose(event: Event): void {
  const fileInput = event.target as HTMLInputElement;
  take(fileInput.files);
  // Cleared, so choosing the same file again still fires change.
  fileInput.value = '';
}

function drop(event: DragEvent): void {
  isDraggingOver.value = false;
  take(event.dataTransfer?.files);
}

function clear(field: FileField): void {
  files.value = { ...files.value, [field]: null };
}
</script>

<template>
  <div class="space-y-4">
    <div
      class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors duration-150"
      :class="
        isDraggingOver
          ? 'border-orange-500 bg-orange-50 dark:bg-orange-500/10'
          : 'border-zinc-300 dark:border-zinc-700'
      "
      @dragenter.prevent="isDraggingOver = true"
      @dragover.prevent="isDraggingOver = true"
      @dragleave.prevent="isDraggingOver = false"
      @drop.prevent="drop"
    >
      <p class="text-sm text-zinc-600 dark:text-zinc-300">Drop the CSV files here, or</p>
      <button
        type="button"
        class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 shadow-sm transition-colors duration-150 hover:bg-zinc-50 focus-visible:ring-4 focus-visible:ring-zinc-400/40 focus-visible:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
        @click="input?.click()"
      >
        Choose files
      </button>
      <p class="text-xs text-zinc-500 dark:text-zinc-400">
        Each file goes to the slot with its exact name; several can be chosen at once.
      </p>
      <input
        ref="input"
        type="file"
        accept=".csv,text/csv"
        multiple
        autocomplete="off"
        class="sr-only"
        aria-label="Choose the CSV files"
        @change="choose"
      />
    </div>

    <Transition name="reveal">
      <p
        v-if="unexpectedFileNames.length > 0"
        role="alert"
        class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
      >
        Not one of the four expected files, so left out: {{ unexpectedFileNames.join(', ') }}
      </p>
    </Transition>

    <ul
      class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800"
    >
      <li v-for="slot in slots" :key="slot.field" class="px-4 py-3">
        <div class="flex items-center justify-between gap-4">
          <div class="min-w-0">
            <p class="text-sm font-medium">{{ slot.label }}</p>
            <p v-if="files[slot.field]" class="truncate text-xs">
              <span class="text-zinc-700 dark:text-zinc-300">{{ files[slot.field]?.name }}</span>
              <span
                v-if="files[slot.field]?.name !== slot.fileName"
                class="text-amber-700 dark:text-amber-400"
              >
                · expects {{ slot.fileName }}
              </span>
            </p>
            <p v-else class="truncate text-xs text-zinc-500 dark:text-zinc-400">
              {{ slot.fileName }}
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <span
              v-if="files[slot.field]"
              class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
            >
              {{
                ((files[slot.field] as File).size / 1024).toLocaleString(undefined, {
                  maximumFractionDigits: 0
                })
              }}
              KB
            </span>
            <span v-else class="text-xs text-zinc-400 dark:text-zinc-500">Missing</span>
            <label
              class="cursor-pointer rounded-md border border-zinc-300 px-2.5 py-1 text-xs font-medium text-zinc-700 transition-colors duration-150 hover:bg-zinc-50 focus-within:ring-4 focus-within:ring-zinc-400/40 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
              {{ files[slot.field] ? 'Replace' : 'Choose' }}
              <input
                type="file"
                accept=".csv,text/csv"
                autocomplete="off"
                class="sr-only"
                :aria-label="`Choose ${slot.fileName}`"
                @change="chooseFor(slot.field, $event)"
              />
            </label>
            <button
              v-if="files[slot.field]"
              type="button"
              class="rounded-md p-1 text-zinc-400 transition-colors duration-150 hover:bg-zinc-100 hover:text-zinc-700 focus-visible:ring-4 focus-visible:ring-zinc-400/40 focus-visible:outline-none dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
              :aria-label="`Remove ${slot.fileName}`"
              @click="clear(slot.field)"
            >
              <svg
                class="size-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
              >
                <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
              </svg>
            </button>
          </div>
        </div>
        <Transition name="reveal">
          <p
            v-if="errors[slot.field]"
            role="alert"
            class="mt-1 text-sm text-red-600 dark:text-red-400"
          >
            {{ errors[slot.field]?.[0] }}
          </p>
        </Transition>
      </li>
    </ul>
  </div>
</template>
