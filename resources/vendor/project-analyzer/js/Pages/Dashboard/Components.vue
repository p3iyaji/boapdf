<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Component Explorer</h1>
            <input
                v-model="searchQuery"
                type="search"
                placeholder="Search components..."
                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white w-64"
            />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Categories</h3>
                    <button
                        v-for="(items, type) in components"
                        :key="type"
                        @click="selectedType = type"
                        :class="[
                            selectedType === type ? 'bg-blue-50 dark:bg-blue-900 text-blue-600' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                            'w-full text-left px-3 py-2 rounded-lg text-sm flex justify-between'
                        ]"
                    >
                        <span class="capitalize">{{ type }}</span>
                        <span class="text-gray-400">{{ Array.isArray(items) ? items.length : 0 }}</span>
                    </button>
                </div>
            </div>

            <div class="lg:col-span-3">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div
                            v-for="item in filteredItems"
                            :key="item.fqn"
                            class="p-4 hover:bg-gray-50 dark:hover:bg-gray-750 cursor-pointer"
                            @click="selectedItem = item"
                        >
                            <div class="font-mono text-sm text-blue-600 dark:text-blue-400">{{ item.fqn }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ item.file }}</div>
                        </div>
                        <div v-if="filteredItems.length === 0" class="p-8 text-center text-gray-500">No components found</div>
                    </div>
                </div>

                <div v-if="selectedItem" class="mt-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ selectedItem.fqn }}</h3>
                    <p class="text-sm text-gray-500 mb-4">{{ selectedItem.file }}</p>
                    <div v-if="selectedItem.methods?.length">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Methods ({{ selectedItem.methods.length }})</h4>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="m in selectedItem.methods" :key="m.name" class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs font-mono">
                                {{ m.name }}()
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    components: Object,
    search: String,
});

const searchQuery = ref(props.search || '');
const selectedType = ref('models');
const selectedItem = ref(null);

const filteredItems = computed(() => {
    const items = props.components?.[selectedType.value] ?? [];
    if (!searchQuery.value) return items;
    const q = searchQuery.value.toLowerCase();
    return items.filter(i =>
        (i.fqn ?? '').toLowerCase().includes(q) ||
        (i.file ?? '').toLowerCase().includes(q)
    );
});
</script>
