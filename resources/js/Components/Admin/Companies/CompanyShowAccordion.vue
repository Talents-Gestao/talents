<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    defaultOpen: { type: Boolean, default: false },
    tone: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'danger'].includes(v),
    },
});
</script>

<template>
    <details
        class="group overflow-hidden rounded-3xl border shadow-sm open:shadow-md"
        :class="
            tone === 'danger'
                ? 'border-red-200/80 bg-red-50/40 open:border-red-300'
                : 'border-slate-200/80 bg-white open:border-talents-200/80'
        "
        :open="defaultOpen || undefined"
    >
        <summary
            class="flex cursor-pointer list-none items-start justify-between gap-3 px-5 py-4 transition marker:content-none [&::-webkit-details-marker]:hidden sm:px-6"
            :class="
                tone === 'danger'
                    ? 'hover:bg-red-50/80'
                    : 'bg-gradient-to-r from-slate-50/80 to-white hover:from-talents-50/50'
            "
        >
            <div class="flex min-w-0 flex-1 items-start gap-3">
                <div
                    v-if="$slots.icon"
                    class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl"
                    :class="tone === 'danger' ? 'bg-red-100/80 text-red-700' : 'bg-slate-100 text-slate-600'"
                    aria-hidden="true"
                >
                    <slot name="icon" />
                </div>
                <div class="min-w-0">
                    <h3
                        class="text-base font-semibold"
                        :class="tone === 'danger' ? 'text-red-900' : 'text-slate-900'"
                    >
                        {{ title }}
                    </h3>
                    <p
                        v-if="description"
                        class="mt-0.5 text-sm"
                        :class="tone === 'danger' ? 'text-red-900/70' : 'text-slate-500'"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
            <ChevronDownIcon
                class="mt-1 h-5 w-5 shrink-0 transition-transform duration-200 group-open:rotate-180"
                :class="tone === 'danger' ? 'text-red-500' : 'text-talents-500'"
            />
        </summary>
        <div
            class="border-t px-5 py-5 sm:px-6"
            :class="tone === 'danger' ? 'border-red-200/70' : 'border-slate-100'"
        >
            <slot />
        </div>
    </details>
</template>
