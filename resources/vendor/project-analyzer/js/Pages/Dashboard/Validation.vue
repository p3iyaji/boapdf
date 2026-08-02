<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Validation</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Environment and configuration checks for analysis, export, dashboard, and code generation features.
            </p>
        </div>

        <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Overall Status</div>
                <div class="mt-2 text-3xl font-bold capitalize" :class="statusClass(validation.status)">{{ validation.status }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Passed</div>
                <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ validation.passed ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Warnings</div>
                <div class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ validation.warnings ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Failed</div>
                <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{{ validation.failed ?? 0 }}</div>
            </div>
        </div>

        <div class="space-y-4">
            <div
                v-for="check in validation.checks ?? []"
                :key="check.name"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ check.name }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ check.message }}</p>
                    </div>

                    <span class="rounded-full px-3 py-1 text-xs font-medium uppercase" :class="badgeClass(check.status)">
                        {{ check.status }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    validation: {
        type: Object,
        required: true,
    },
});

function statusClass(status) {
    return {
        passed: 'text-green-600 dark:text-green-400',
        warning: 'text-yellow-600 dark:text-yellow-400',
        failed: 'text-red-600 dark:text-red-400',
    }[status] ?? 'text-gray-900 dark:text-white';
}

function badgeClass(status) {
    return {
        passed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }[status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
}
</script>
