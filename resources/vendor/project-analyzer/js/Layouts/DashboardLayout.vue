<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <nav class="bg-white dark:bg-gray-800 shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center space-x-8">
                        <Link href="/analyzer" class="text-xl font-bold text-blue-600 dark:text-blue-400">
                            Project Analyzer
                        </Link>
                        <div class="hidden md:flex space-x-4">
                            <NavLink href="/analyzer" :active="isActive('/analyzer')">Overview</NavLink>
                            <NavLink href="/analyzer/components" :active="isActive('/analyzer/components')">Components</NavLink>
                            <NavLink href="/analyzer/graphs" :active="isActive('/analyzer/graphs')">Graphs</NavLink>
                            <NavLink href="/analyzer/tests" :active="isActive('/analyzer/tests')">Tests</NavLink>
                            <NavLink href="/analyzer/metrics" :active="isActive('/analyzer/metrics')">Metrics</NavLink>
                            <NavLink href="/analyzer/recommendations" :active="isActive('/analyzer/recommendations')">Recommendations</NavLink>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button @click="toggleTheme" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                            <Sun v-if="isDark" class="w-5 h-5 text-yellow-400" />
                            <Moon v-else class="w-5 h-5 text-gray-600" />
                        </button>
                    </div>
                </div>
            </div>
        </nav>
        <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Sun, Moon } from 'lucide-vue-next';
import NavLink from '../Components/NavLink.vue';

const isDark = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});

function toggleTheme() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
}

function isActive(path) {
    return window.location.pathname === path || window.location.pathname.startsWith(path + '/');
}
</script>
