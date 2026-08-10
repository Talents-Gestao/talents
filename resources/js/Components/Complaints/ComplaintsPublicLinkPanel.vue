<script setup>
import { ClipboardDocumentIcon, LinkIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    url: {
        type: String,
        required: true,
    },
    /**
     * panel — Dashboard (dentro de dashboard-panel-compact)
     * card — Voz do Time (surface-card)
     * compact — Admin detalhe da empresa
     */
    variant: {
        type: String,
        default: 'card',
        validator: (value) => ['panel', 'card', 'compact'].includes(value),
    },
});

const copied = ref(false);

const copyLink = async () => {
    if (!props.url) {
        return;
    }

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(props.url);
        } else {
            const input = document.createElement('textarea');
            input.value = props.url;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed';
            input.style.left = '-9999px';
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // silencioso — o URL continua visível para copiar manualmente
    }
};
</script>

<template>
    <div
        :class="{
            'mt-3': variant === 'panel',
            'surface-card mt-6 p-5': variant === 'card',
            'mt-5 rounded-2xl border border-talents-200/70 bg-talents-50/40 px-4 py-4': variant === 'compact',
        }"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 flex-1 items-start gap-3">
                <div
                    class="hidden shrink-0 rounded-xl p-2.5 ring-1 sm:flex"
                    :class="variant === 'compact'
                        ? 'bg-white text-talents-700 ring-talents-200/80'
                        : 'bg-violet-50 text-violet-700 ring-violet-100'"
                    aria-hidden="true"
                >
                    <LinkIcon class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900">
                        Link público para colaboradores
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">
                        Os colaboradores acedem <span class="font-medium text-slate-800">sem login</span>,
                        pelo link abaixo — não é pelo painel da empresa. Partilhe este URL (e-mail, intranet ou QR).
                    </p>
                    <p class="mt-2 break-all rounded-lg bg-white px-3 py-2 font-mono text-xs text-slate-700 ring-1 ring-slate-200/80">
                        {{ url }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        Lei 14.457/2022 — canal de denúncias. Acompanhar denúncias no painel continua a exigir permissão.
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 flex-col gap-2 sm:items-stretch">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-talents-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 focus:outline-none focus:ring-2 focus:ring-talents-500/40"
                    @click="copyLink"
                >
                    <ClipboardDocumentIcon class="h-4 w-4" aria-hidden="true" />
                    {{ copied ? 'Copiado!' : 'Copiar link' }}
                </button>
                <a
                    :href="url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-talents-200 hover:bg-talents-50 hover:text-talents-800"
                >
                    Abrir formulário
                </a>
            </div>
        </div>
    </div>
</template>
