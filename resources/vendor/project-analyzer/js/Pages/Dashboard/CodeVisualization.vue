<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Code Maps</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Explore component distribution, namespace concentration, dependency hotspots, and class density.
            </p>
        </div>

        <div class="mb-8 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Component Breakdown</h2>
                <canvas ref="componentCanvas"></canvas>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Route Activity</h2>
                <canvas ref="routeCanvas"></canvas>
            </div>
        </div>

        <div class="mb-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Dependency Hotspots</h2>
            <canvas ref="hotspotCanvas"></canvas>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Top Namespaces</h2>
                <div class="space-y-3">
                    <div
                        v-for="item in visualizations.namespace_breakdown ?? []"
                        :key="item.label"
                        class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-gray-900/50"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-mono text-sm text-blue-600 dark:text-blue-400">{{ item.label }}</span>
                            <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                {{ item.value }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Class Density Heatmap</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div
                        v-for="item in visualizations.class_size_heatmap ?? []"
                        :key="item.label"
                        class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                        :style="{ backgroundColor: heatColor(item.methods) }"
                    >
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">{{ item.type }}</div>
                        <div class="mt-2 font-mono text-sm text-gray-900 dark:text-white">{{ item.label }}</div>
                        <div class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ item.methods }} public methods</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
);

const props = defineProps({
    visualizations: {
        type: Object,
        required: true,
    },
});

const componentCanvas = ref(null);
const routeCanvas = ref(null);
const hotspotCanvas = ref(null);

onMounted(() => {
    renderComponentBreakdown();
    renderRouteActivity();
    renderHotspots();
});

function renderComponentBreakdown() {
    if (!componentCanvas.value) return;

    const items = props.visualizations.component_breakdown ?? [];

    new Chart(componentCanvas.value, {
        type: 'doughnut',
        data: {
            labels: items.map(item => item.label),
            datasets: [{
                data: items.map(item => item.value),
                backgroundColor: ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0891b2'],
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
            },
        },
    });
}

function renderRouteActivity() {
    if (!routeCanvas.value) return;

    const items = props.visualizations.route_activity ?? [];

    new Chart(routeCanvas.value, {
        type: 'bar',
        data: {
            labels: items.map(item => item.label),
            datasets: [{
                label: 'Routes',
                data: items.map(item => item.value),
                backgroundColor: '#0f766e',
            }],
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                },
            },
        },
    });
}

function renderHotspots() {
    if (!hotspotCanvas.value) return;

    const items = props.visualizations.dependency_hotspots ?? [];

    new Chart(hotspotCanvas.value, {
        type: 'bar',
        data: {
            labels: items.map(item => shorten(item.label)),
            datasets: [
                {
                    label: 'Incoming',
                    data: items.map(item => item.incoming),
                    backgroundColor: '#2563eb',
                },
                {
                    label: 'Outgoing',
                    data: items.map(item => item.outgoing),
                    backgroundColor: '#f59e0b',
                },
            ],
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                },
            },
        },
    });
}

function shorten(value) {
    return value.length > 32 ? `${value.slice(0, 29)}...` : value;
}

function heatColor(methodCount) {
    const intensity = Math.min(methodCount / 12, 1);
    const alpha = 0.12 + (intensity * 0.35);

    return `rgba(37, 99, 235, ${alpha})`;
}
</script>
