<script setup>
import SignaturePad from '@/Components/Feedback/SignaturePad.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircleIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    signature: Object,
    token: String,
    session: Object,
});

const page = usePage();
const success = computed(() => page.props.flash?.success);

const form = useForm({
    signature_data: '',
    declaration_accepted: false,
});

const signed = computed(() => !!props.signature.signed_at);

const submit = () => {
    form.post(route('feedback.sign.store', props.token));
};
</script>

<template>
    <Head title="Assinar feedback" />

    <GuestLayout>
        <div class="mx-auto max-w-2xl py-8 sm:py-12">
            <div class="text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-talents-100 text-talents-700">
                    <DocumentTextIcon class="h-6 w-6" />
                </div>
                <h1 class="mt-4 font-serif text-2xl font-bold tracking-tight text-talents-900 sm:text-3xl">
                    Assinatura de feedback
                </h1>
                <p class="mt-2 text-sm text-slate-600">{{ session.title }}</p>
            </div>

            <div
                v-if="success"
                class="mt-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"
            >
                <CheckCircleIcon class="h-5 w-5 shrink-0 text-emerald-600" />
                {{ success }}
            </div>

            <div class="mt-8 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-gradient-to-r from-talents-50/80 to-white px-6 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Participantes</p>
                </div>
                <dl class="grid gap-4 p-6 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">Colaborador</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ session.employee?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Líder</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ session.leader?.name }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-slate-500">Signatário</dt>
                        <dd class="mt-1 font-medium text-slate-800">
                            {{ signature.signer_name }}
                            <span class="font-normal text-slate-500">({{ signature.role_label }})</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <div v-if="!signed" class="mt-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <p class="text-sm font-medium text-talents-900">Declaração</p>
                </div>
                <div class="space-y-5 p-6">
                    <p class="text-sm leading-relaxed text-slate-700">
                        Declaro que participei deste alinhamento, compreendi os pontos discutidos e estou ciente das
                        expectativas estabelecidas para o próximo período.
                    </p>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-4 text-sm text-slate-700">
                        <input
                            v-model="form.declaration_accepted"
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300 text-talents-700 focus:ring-talents-500"
                        />
                        Li e concordo com a declaração acima
                    </label>

                    <div>
                        <p class="text-sm font-medium text-slate-800">Assinatura digital</p>
                        <p class="mt-1 text-xs text-slate-500">Desenhe sua assinatura no campo abaixo</p>
                        <div class="mt-3">
                            <SignaturePad v-model="form.signature_data" />
                            <p v-if="form.errors.signature_data" class="mt-2 text-sm text-red-600">
                                {{ form.errors.signature_data }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="w-full rounded-xl bg-talents-700 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing || !form.declaration_accepted || !form.signature_data"
                        @click="submit"
                    >
                        Confirmar assinatura
                    </button>
                </div>
            </div>

            <div
                v-else
                class="mt-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-gradient-to-b from-emerald-50 to-white p-6 text-sm text-emerald-900"
            >
                <CheckCircleIcon class="h-6 w-6 shrink-0 text-emerald-600" />
                <div>
                    <p class="font-semibold">Documento assinado com sucesso</p>
                    <p class="mt-1 text-emerald-800/90">
                        Em {{ new Date(signature.signed_at).toLocaleString('pt-BR') }}.
                    </p>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
