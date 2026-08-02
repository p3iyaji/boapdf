<template>
    <div>
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Auto Fix</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Apply supported low-risk fixes for missing tests, direct superglobal access, and missing service contracts.
                </p>
            </div>

            <button
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="isApplying || candidates.length === 0"
                @click="applyFixes"
            >
                {{ isApplying ? 'Applying...' : 'Apply Supported Fixes' }}
            </button>
        </div>

        <div v-if="feedback" class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
            {{ feedback }}
        </div>

        <div v-if="candidates.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
            No supported auto-fix candidates were detected in the current analysis.
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="candidate in candidates"
                :key="candidate.id"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="mb-2 flex items-center gap-2">
                    <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        {{ candidate.category }}
                    </span>
                    <span
                        class="rounded-full px-2 py-1 text-xs font-medium"
                        :class="candidate.supported ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'"
                    >
                        {{ candidate.supported ? 'supported' : 'manual only' }}
                    </span>
                </div>

                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ candidate.title }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ candidate.description }}</p>
                <p v-if="candidate.file" class="mt-3 font-mono text-xs text-blue-600 dark:text-blue-400">{{ candidate.file }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    candidates: {
        type: Array,
        required: true,
    },
});

const isApplying = ref(false);
const feedback = ref('');

async function applyFixes() {
    isApplying.value = true;
    feedback.value = '';

    try {
        const response = await fetch('/analyzer/auto-fix/apply', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                force: false,
            }),
        });

        const result = await response.json();
        feedback.value = `Applied ${result.applied_count ?? 0} fix(es); skipped ${result.skipped_count ?? 0}.`;
    } catch (error) {
        feedback.value = 'Failed to apply supported fixes from the dashboard.';
    } finally {
        isApplying.value = false;
    }
}
</script>
