<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Architecture Graphs</h1>
            <button @click="exportGraph" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                Export Graph
            </button>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Dependency Graph</h2>
                <div ref="mermaidContainer" class="overflow-auto min-h-[400px]"></div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Database ER Diagram</h2>
                <div ref="erContainer" class="overflow-auto min-h-[300px]"></div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Graph Statistics</h2>
                <div class="grid grid-cols-3 gap-4">
                    <ScoreCard label="Nodes" :value="graphData.node_count ?? 0" />
                    <ScoreCard label="Edges" :value="graphData.edge_count ?? 0" />
                    <ScoreCard label="Circular Deps" :value="(graphData.circular_dependencies ?? []).length" variant="warning" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import mermaid from 'mermaid';
import ScoreCard from '../../Components/ScoreCard.vue';

const props = defineProps({
    dependencyGraph: String,
    erDiagram: String,
    graphData: Object,
});

const mermaidContainer = ref(null);
const erContainer = ref(null);

onMounted(async () => {
    mermaid.initialize({ startOnLoad: false, theme: document.documentElement.classList.contains('dark') ? 'dark' : 'default' });

    if (props.dependencyGraph && mermaidContainer.value) {
        const { svg } = await mermaid.render('dep-graph', props.dependencyGraph);
        mermaidContainer.value.innerHTML = svg;
    }

    if (props.erDiagram && erContainer.value) {
        const { svg } = await mermaid.render('er-diagram', props.erDiagram);
        erContainer.value.innerHTML = svg;
    }
});

function exportGraph() {
    const blob = new Blob([props.dependencyGraph], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'dependency-graph.mmd';
    a.click();
}
</script>
