<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { computed, useSlots } from 'vue';

defineProps({
    title: { type: String, required: true },
    icon: { type: String, default: '' },
    description: { type: String, default: '' },
    defaultOpen: { type: Boolean, default: false },
    meta: { type: String, default: '' },
    collapsible: { type: Boolean, default: true },
});

const slots = useSlots();

const hasSlotContent = computed(() => (slots.default?.() ?? []).length > 0);
</script>

<template>
    <details
        v-if="collapsible"
        class="group overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm open:border-talents-200/80 open:shadow-md"
        :open="defaultOpen || undefined"
    >
        <summary
            class="flex cursor-pointer list-none items-start justify-between gap-3 bg-gradient-to-r from-talents-50/60 to-white px-5 py-4 transition hover:from-talents-50/90 marker:content-none [&::-webkit-details-marker]:hidden"
        >
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span v-if="icon" class="shrink-0 text-base leading-none" aria-hidden="true">{{ icon }}</span>
                    <h3 class="min-w-0 text-pretty font-semibold text-talents-900">{{ title }}</h3>
                    <span
                        v-if="meta"
                        class="shrink-0 whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                    >
                        {{ meta }}
                    </span>
                </div>
                <p
                    v-if="description"
                    class="mt-1 line-clamp-2 whitespace-pre-wrap text-sm text-slate-600 group-open:line-clamp-none"
                >
                    {{ description }}
                </p>
            </div>
            <ChevronDownIcon
                class="mt-0.5 h-5 w-5 shrink-0 text-talents-500 transition-transform duration-200 group-open:rotate-180"
            />
        </summary>

        <div class="border-t border-slate-100">
            <slot />
        </div>
    </details>

    <div
        v-else
        class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
    >
        <div class="bg-gradient-to-r from-talents-50/60 to-white px-5 py-4">
            <div class="flex flex-wrap items-center gap-2">
                <span v-if="icon" class="shrink-0 text-base leading-none" aria-hidden="true">{{ icon }}</span>
                <h3 class="min-w-0 text-pretty font-semibold text-talents-900">{{ title }}</h3>
            </div>
            <p v-if="description" class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">
                {{ description }}
            </p>
        </div>
        <div v-if="hasSlotContent" class="border-t border-slate-100">
            <slot />
        </div>
    </div>
</template>
