<template>
    <Link
        :href="href"
        :class="[
            active
                ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600'
                : 'text-gray-600 dark:text-gray-300 hover:text-blue-600',
            'px-3 py-2 text-sm font-medium transition-colors'
        ]"
    >
        <slot />
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    active: Boolean,
});
</script>
