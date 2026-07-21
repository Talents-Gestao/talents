<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { isDesligamentoAdminContext } from '@/composables/useDesligamentoRoutes';
import { computed } from 'vue';

const layout = computed(() => (isDesligamentoAdminContext() ? AdminLayout : ClientLayout));
</script>

<template>
    <component :is="layout">
        <template v-if="$slots.header" #header>
            <slot name="header" />
        </template>
        <slot />
    </component>
</template>
