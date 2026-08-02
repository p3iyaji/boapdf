<template>
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Test Coverage</h1>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <ScoreCard label="Overall Coverage" :value="(coverage.overall ?? 0) + '%'" :variant="coverageVariant" />
            <ScoreCard label="Total Tests" :value="testData.total_tests ?? 0" />
            <ScoreCard label="Missing Tests" :value="coverage.missing_count ?? 0" variant="warning" />
            <ScoreCard label="Controller Coverage" :value="(coverage.by_type?.controllers ?? 0) + '%'" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Coverage by Type</h2>
                <canvas ref="chartCanvas"></canvas>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Missing Tests</h2>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    <div v-for="(item, i) in coverage.missing_tests ?? []" :key="i" class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-0.5 text-xs bg-yellow-100 text-yellow-800 rounded">{{ item.type }}</span>
                            <span class="font-mono text-sm text-gray-900 dark:text-white">{{ item.class }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ item.suggestion }}</p>
                    </div>
                    <div v-if="!(coverage.missing_tests ?? []).length" class="text-gray-500 text-center py-8">All components have tests!</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js';
import ScoreCard from '../../Components/ScoreCard.vue';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
    testData: Object,
    coverage: Object,
});

const chartCanvas = ref(null);

const coverageVariant = computed(() => {
    const val = props.coverage?.overall ?? 0;
    if (val >= 80) return 'success';
    if (val >= 50) return 'warning';
    return 'danger';
});

onMounted(() => {
    if (!chartCanvas.value) return;
    const byType = props.coverage?.by_type ?? {};

    new Chart(chartCanvas.value, {
        type: 'bar',
        data: {
            labels: Object.keys(byType).map(k => k.charAt(0).toUpperCase() + k.slice(1)),
            datasets: [{
                label: 'Coverage %',
                data: Object.values(byType),
                backgroundColor: ['#2563eb', '#16a34a', '#d97706'],
            }],
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, max: 100 } },
        },
    });
});
</script>
