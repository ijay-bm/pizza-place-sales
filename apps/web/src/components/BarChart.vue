<script setup lang="ts">
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';
import type { ChartData, ChartOptions } from 'chart.js';
import { useDark, usePreferredReducedMotion } from '@vueuse/core';

Chart.register(BarElement, CategoryScale, LinearScale, Tooltip);
Chart.defaults.font.family = "'Inter Variable', ui-sans-serif, system-ui, sans-serif";

/**
 * One series of bars in the accent color, with a value tooltip on hover; the title above it names the series, so
 * it draws no legend.
 */
const props = withDefaults(
  defineProps<{
    labels: string[];
    values: number[];
    seriesLabel: string;
    horizontal?: boolean;
    formatValue?: (value: number) => string;
  }>(),
  { horizontal: false, formatValue: (value: number) => value.toLocaleString() }
);

const isDark = useDark();
const reducedMotion = usePreferredReducedMotion();

const data = computed<ChartData<'bar'>>(() => ({
  labels: props.labels,
  datasets: [
    {
      label: props.seriesLabel,
      data: props.values,
      // orange-600 on the light surface and orange-500 on the dark one, each at least 3:1 against it.
      backgroundColor: isDark.value ? '#f97316' : '#ea580c',
      borderRadius: 4,
      borderSkipped: 'start',
      maxBarThickness: 28
    }
  ]
}));

const options = computed<ChartOptions<'bar'>>(() => {
  const ink = isDark.value ? '#a1a1aa' : '#71717a';
  const gridLine = isDark.value ? '#27272a' : '#f4f4f5';
  const valueAxis = {
    beginAtZero: true,
    grid: { color: gridLine },
    border: { display: false },
    ticks: { color: ink, callback: (value: string | number) => props.formatValue(Number(value)) }
  };
  const categoryAxis = {
    grid: { display: false },
    border: { display: false },
    ticks: { color: ink }
  };

  return {
    indexAxis: props.horizontal ? 'y' : 'x',
    responsive: true,
    maintainAspectRatio: false,
    animation: reducedMotion.value === 'reduce' ? false : { duration: 250 },
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (context) => `${props.seriesLabel}: ${props.formatValue(Number(context.raw))}`
        }
      }
    },
    scales: props.horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis }
  };
});
</script>

<template>
  <div class="h-64">
    <Bar :data="data" :options="options" :aria-label="seriesLabel" role="img" />
  </div>
</template>
