<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Recommendations</h1>
            <select v-model="filterCategory" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-sm">
                <option value="">All Categories</option>
                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
            </select>
        </div>

        <div class="space-y-4">
            <div
                v-for="(rec, i) in filteredRecommendations"
                :key="i"
                class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center space-x-2 mb-2">
                            <span :class="priorityClass(rec.priority)" class="px-2 py-0.5 text-xs rounded-full font-medium uppercase">{{ rec.priority }}</span>
                            <span class="px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 rounded text-gray-600 dark:text-gray-300">{{ rec.category }}</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ rec.title }}</h3>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">{{ rec.description }}</p>
                        <p v-if="rec.impact" class="text-sm text-gray-500 mt-2">Impact: {{ rec.impact }}</p>
                    </div>
                </div>
            </div>
            <div v-if="filteredRecommendations.length === 0" class="text-center py-12 text-gray-500">
                No recommendations found
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    recommendations: Array,
});

const filterCategory = ref('');

const categories = computed(() => [...new Set((props.recommendations ?? []).map(r => r.category))]);

const filteredRecommendations = computed(() => {
    if (!filterCategory.value) return props.recommendations ?? [];
    return (props.recommendations ?? []).filter(r => r.category === filterCategory.value);
});

function priorityClass(priority) {
    const classes = {
        high: 'bg-red-100 text-red-800',
        medium: 'bg-yellow-100 text-yellow-800',
        low: 'bg-green-100 text-green-800',
    };
    return classes[priority] ?? classes.low;
}
</script>
