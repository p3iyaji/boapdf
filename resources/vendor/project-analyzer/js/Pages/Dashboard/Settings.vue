<template>
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Settings & Configuration</h1>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Analysis Paths</h2>
            <div class="flex flex-wrap gap-2">
                <span v-for="path in config.analysis?.paths ?? []" :key="path" class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-sm">
                    {{ path }}
                </span>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Enabled Analyzers</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div v-for="(enabled, name) in config.analyzers ?? {}" :key="name" class="flex items-center space-x-2">
                    <span :class="enabled ? 'bg-green-500' : 'bg-gray-300'" class="w-3 h-3 rounded-full"></span>
                    <span class="text-sm capitalize text-gray-700 dark:text-gray-300">{{ name }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Export</h2>
            <div class="flex space-x-3">
                <button v-for="format in config.export?.formats ?? []" :key="format" @click="exportFormat(format)"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm capitalize">
                    Export {{ format }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

defineProps({
    config: Object,
});

function exportFormat(format) {
    router.post('/analyzer/export', { format });
}
</script>
