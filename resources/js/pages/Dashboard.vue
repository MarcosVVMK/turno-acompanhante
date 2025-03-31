<script setup lang="ts">
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import Welcome from "@/pages/Welcome.vue";
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                Dashboard
            </h2>

        </template>
        <Welcome>

        </Welcome>
    </AuthenticatedLayout>
</template>
