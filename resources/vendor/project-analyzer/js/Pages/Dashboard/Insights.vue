<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Insights</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Prioritized intelligence across security, enhancement opportunities, and runtime cost hotspots.
            </p>
        </div>

        <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Security Insights</div>
                <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{{ summary.security ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Enhancement Insights</div>
                <div class="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">{{ summary.enhancement ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Cost Hotspots</div>
                <div class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ summary.cost ?? 0 }}</div>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr,1fr]">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Security Overview</h2>
                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-lg bg-red-50 p-4 text-center dark:bg-red-950">
                        <div class="text-xs uppercase tracking-wide text-red-600 dark:text-red-300">High</div>
                        <div class="mt-2 text-2xl font-bold text-red-700 dark:text-red-200">{{ security.high_severity ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-yellow-50 p-4 text-center dark:bg-yellow-950">
                        <div class="text-xs uppercase tracking-wide text-yellow-700 dark:text-yellow-300">Medium</div>
                        <div class="mt-2 text-2xl font-bold text-yellow-700 dark:text-yellow-200">{{ security.medium_severity ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-green-50 p-4 text-center dark:bg-green-950">
                        <div class="text-xs uppercase tracking-wide text-green-700 dark:text-green-300">Low</div>
                        <div class="mt-2 text-2xl font-bold text-green-700 dark:text-green-200">{{ security.low_severity ?? 0 }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Runtime Cost Score</h2>
                <div class="text-4xl font-bold text-gray-900 dark:text-white">{{ cost.estimated_score ?? 0 }}</div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Aggregate static score across query, network, and memory-intensive patterns.
                </p>
            </div>
        </div>

        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Prioritized Insights</h2>
            <select v-model="filterCategory" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                <option value="">All Categories</option>
                <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
            </select>
        </div>

        <div class="space-y-4">
            <div
                v-for="(item, index) in filteredRecommendations"
                :key="`${item.category}-${index}`"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="mb-2 flex items-center gap-2">
                    <span class="rounded-full px-2 py-1 text-xs font-medium uppercase" :class="priorityClass(item.priority)">
                        {{ item.priority }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                        {{ item.category }}
                    </span>
                    <span v-if="item.estimated_cost" class="rounded-full bg-yellow-100 px-2 py-1 text-xs text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                        cost: {{ item.estimated_cost }}
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ item.title }}</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ item.description }}</p>
                <p v-if="item.impact" class="mt-2 text-sm text-gray-500 dark:text-gray-400">Impact: {{ item.impact }}</p>
                <p v-if="item.class" class="mt-2 font-mono text-xs text-blue-600 dark:text-blue-400">{{ item.class }}</p>
                <p v-if="item.file" class="mt-2 font-mono text-xs text-blue-600 dark:text-blue-400">{{ item.file }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    recommendations: { type: Array, required: true },
    security: { type: Object, required: true },
    cost: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const filterCategory = ref('');

const categories = computed(() => [...new Set(props.recommendations.map(item => item.category))]);

const filteredRecommendations = computed(() => {
    if (!filterCategory.value) {
        return props.recommendations;
    }

    return props.recommendations.filter(item => item.category === filterCategory.value);
});

function priorityClass(priority) {
    return {
        high: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        low: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    }[priority] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
}
</script>
