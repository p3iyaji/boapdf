<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ label }}</div>
        <div class="mt-2 text-3xl font-bold" :class="colorClass">{{ value }}</div>
        <div v-if="subtitle" class="mt-1 text-xs text-gray-400">{{ subtitle }}</div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: String,
    value: [String, Number],
    subtitle: String,
    variant: { type: String, default: 'default' },
});

const colorClass = computed(() => {
    const variants = {
        default: 'text-blue-600 dark:text-blue-400',
        success: 'text-green-600 dark:text-green-400',
        warning: 'text-yellow-600 dark:text-yellow-400',
        danger: 'text-red-600 dark:text-red-400',
    };
    return variants[props.variant] || variants.default;
});
</script>
