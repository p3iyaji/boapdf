<template>
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Project Health Overview</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <ScoreCard label="Health" :value="metrics.overall ?? 'N/A'" variant="success" />
            <ScoreCard label="Testability" :value="metrics.testability ?? 'N/A'" />
            <ScoreCard label="Code Quality" :value="metrics.code_quality ?? 'N/A'" />
            <ScoreCard label="Architecture" :value="metrics.architecture ?? 'N/A'" />
            <ScoreCard label="Security" :value="metrics.security ?? 'N/A'" :variant="(metrics.security ?? 100) < 70 ? 'danger' : 'default'" />
            <ScoreCard label="Maintainability" :value="metrics.maintainability ?? 'N/A'" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Component Statistics</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div v-for="(value, key) in stats" :key="key" class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400 capitalize">{{ formatKey(key) }}</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ value }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Recommendations</h2>
                <div v-if="recommendations.length === 0" class="text-gray-500">No recommendations</div>
                <ul v-else class="space-y-3">
                    <li v-for="(rec, i) in recommendations" :key="i" class="flex items-start space-x-3">
                        <span :class="priorityClass(rec.priority)" class="px-2 py-0.5 text-xs rounded-full font-medium">{{ rec.priority }}</span>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ rec.title }}</div>
                            <div class="text-sm text-gray-500">{{ rec.description }}</div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <p class="text-sm text-gray-400">Generated: {{ generatedAt }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import ScoreCard from '../../Components/ScoreCard.vue';

const props = defineProps({
    metrics: Object,
    recommendations: Array,
    generatedAt: String,
});

const stats = computed(() => props.metrics?.statistics ?? {});

function formatKey(key) {
    return key.replace(/_/g, ' ');
}

function priorityClass(priority) {
    const classes = {
        high: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        low: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    };
    return classes[priority] ?? classes.low;
}
</script>
