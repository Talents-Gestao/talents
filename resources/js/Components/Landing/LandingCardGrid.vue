<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    columns: {
        type: String,
        default: 'md:grid-cols-2 lg:grid-cols-3',
    },
    /**
     * default — surface-card-soft (Para Empresas / Para Pessoas)
     * rich — borda talents + hover elaborado (Welcome)
     */
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'rich'].includes(value),
    },
});

const isRich = computed(() => props.variant === 'rich');

const cardClass = computed(() =>
    isRich.value
        ? 'landing-card-rich group'
        : 'surface-card-soft group p-6 transition duration-200 hover:-translate-y-0.5 hover:shadow-md',
);

const iconClass = computed(() =>
    isRich.value
        ? 'landing-card-rich__icon'
        : 'mb-4 inline-flex rounded-2xl bg-talents-50 p-3 text-talents-700 ring-1 ring-talents-100/60',
);
</script>

<template>
    <div class="grid gap-5" :class="columns">
        <article
            v-for="item in items"
            :key="item.title"
            :class="cardClass"
        >
            <div
                v-if="item.icon"
                :class="iconClass"
            >
                <component :is="item.icon" class="h-6 w-6" aria-hidden="true" />
            </div>
            <h3 class="text-lg font-semibold text-slate-900">{{ item.title }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ item.description }}</p>
        </article>
    </div>
</template>
