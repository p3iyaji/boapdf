<template>
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Metrics & Analysis</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <ScoreCard label="Avg Complexity" :value="complexity.average_complexity ?? 0" />
            <ScoreCard label="Total Methods" :value="complexity.total_methods ?? 0" />
            <ScoreCard label="High Complexity" :value="(complexity.high_complexity_classes ?? []).length" variant="warning" />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Largest Classes</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-2 text-gray-500">Class</th>
                        <th class="text-right py-2 text-gray-500">Methods</th>
                        <th class="text-right py-2 text-gray-500">Complexity</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="cls in complexity.largest_classes ?? []" :key="cls.class" class="border-b border-gray-100 dark:border-gray-700">
                        <td class="py-2 font-mono text-blue-600 dark:text-blue-400">{{ cls.class }}</td>
                        <td class="py-2 text-right">{{ cls.method_count }}</td>
                        <td class="py-2 text-right font-semibold">{{ cls.complexity }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Graph Summary</h2>
            <div class="grid grid-cols-3 gap-4">
                <ScoreCard label="Nodes" :value="metrics.graph_summary?.nodes ?? 0" />
                <ScoreCard label="Edges" :value="metrics.graph_summary?.edges ?? 0" />
                <ScoreCard label="Circular Dependencies" :value="metrics.graph_summary?.circular_dependencies ?? 0" variant="danger" />
            </div>
        </div>
    </div>
</template>

<script setup>
import ScoreCard from '../../Components/ScoreCard.vue';

defineProps({
    metrics: Object,
    complexity: Object,
});
</script>
