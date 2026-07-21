<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { isFeriasAdminContext } from '@/composables/useFeriasRoutes';
import { computed } from 'vue';

const layout = computed(() => (isFeriasAdminContext() ? AdminLayout : ClientLayout));
</script>

<template>
    <component :is="layout">
        <template v-if="$slots.header" #header>
            <slot name="header" />
        </template>
        <slot />
    </component>
</template>
