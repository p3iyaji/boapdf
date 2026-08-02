<template>
    <div>
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Test Generator</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Generate starter test stubs for uncovered controllers, models, and services.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <select
                    v-model="framework"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                >
                    <option value="pest">Pest</option>
                    <option value="phpunit">PHPUnit</option>
                </select>

                <button
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isGenerating || generatedTests.length === 0"
                    @click="generateTests"
                >
                    {{ isGenerating ? 'Generating...' : 'Generate Test Stubs' }}
                </button>
            </div>
        </div>

        <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Missing Tests</div>
                <div class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ missingTests.length }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Generated Preview Files</div>
                <div class="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">{{ generatedTests.length }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Framework</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ frameworkLabel }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Coverage</div>
                <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ coverage.overall ?? 0 }}%</div>
            </div>
        </div>

        <div v-if="feedback" class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
            {{ feedback }}
        </div>

        <div v-if="generatedTests.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
            No missing tests detected. Your current analysis does not need generated test stubs.
        </div>

        <div v-else class="space-y-6">
            <div
                v-for="generatedTest in generatedTests"
                :key="generatedTest.relative_path"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex flex-col gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-700 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-mono text-sm text-blue-600 dark:text-blue-400">{{ generatedTest.relative_path }}</div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ generatedTest.class }} · {{ generatedTest.suite }} · {{ generatedTest.type }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full px-2 py-1 text-xs font-medium"
                            :class="generatedTest.exists ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'"
                        >
                            {{ generatedTest.exists ? 'Exists' : 'New file' }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-6 px-6 py-5 lg:grid-cols-[1fr,1.2fr]">
                    <div>
                        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Suggested Cases</h2>
                        <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                            <li v-for="(item, index) in generatedTest.suggested_cases" :key="index" class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/50">
                                {{ item }}
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Generated Stub</h2>
                        <pre class="max-h-[28rem] overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ generatedTest.contents }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    coverage: { type: Object, required: true },
    missingTests: { type: Array, required: true },
    generatedTests: { type: Array, required: true },
    config: { type: Object, required: true },
});

const framework = ref(props.config?.framework ?? 'pest');
const isGenerating = ref(false);
const feedback = ref('');

const frameworkLabel = computed(() => framework.value === 'phpunit' ? 'PHPUnit' : 'Pest');

async function generateTests() {
    isGenerating.value = true;
    feedback.value = '';

    try {
        const response = await fetch('/analyzer/test-generation/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                framework: framework.value,
                force: false,
            }),
        });

        const result = await response.json();
        feedback.value = `Generated ${result.written_count ?? 0} test file(s); skipped ${result.skipped_count ?? 0}.`;
    } catch (error) {
        feedback.value = 'Failed to generate test stubs from the dashboard.';
    } finally {
        isGenerating.value = false;
    }
}
</script>
