<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    token: String,
    companyName: String,
    topics: { type: Object, default: () => ({}) },
    areas: { type: Object, default: () => ({}) },
    responsePreferences: { type: Object, default: () => ({}) },
});

const form = useForm({
    topic: '',
    area: '',
    message: '',
    response_preference: '',
    reporter_name: '',
    contact: '',
});

const wantsReply = computed(() => form.response_preference === 'sim');

const submit = () => {
    form.post(route('sugestao.store', props.token));
};
</script>

<template>
    <Head title="Canal de sugestões e dúvidas" />

    <div class="app-shell min-h-screen text-slate-900">
        <header class="border-b border-white/40 bg-white/80 px-4 py-4 shadow-sm backdrop-blur-md">
            <div class="mx-auto max-w-2xl">
                <p class="text-xs uppercase tracking-widest text-talents-600">Canal anônimo</p>
                <h1 class="text-lg font-semibold text-slate-900">Canal de sugestões e dúvidas</h1>
                <p class="text-sm text-slate-600">{{ companyName }}</p>
            </div>
        </header>

        <div class="mx-auto max-w-2xl px-4 py-8">
            <div class="surface-card mb-6 space-y-3 p-6 text-sm leading-relaxed text-slate-600 sm:p-8">
                <p>
                    Este é um canal <span class="font-medium text-slate-800">anônimo</span> para sugestões, dúvidas ou apontamentos que possam contribuir com o ambiente de trabalho.
                </p>
                <p>
                    Sua participação é muito importante para continuarmos melhorando nossos processos e nosso clima organizacional.
                </p>
                <p>Caso queira, você também pode usar este espaço para relatar situações que mereçam atenção.</p>
                <p class="text-xs text-slate-500">
                    Não coletamos e-mail ou identificação, salvo o nome e o contato que você informar se quiser receber retorno.
                </p>
            </div>

            <form class="surface-card space-y-6 p-6 sm:p-8" @submit.prevent="submit">
                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">
                        Sobre o que você gostaria de falar?
                        <span class="text-red-600" aria-hidden="true">*</span>
                    </legend>
                    <div class="mt-3 space-y-2">
                        <label
                            v-for="(label, key) in topics"
                            :key="key"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-800 transition hover:border-talents-200 hover:bg-talents-50/40"
                            :class="form.topic === key ? 'border-talents-300 bg-talents-50/70' : ''"
                        >
                            <input
                                v-model="form.topic"
                                type="radio"
                                name="topic"
                                class="border-slate-300 text-talents-600 focus:ring-talents-500"
                                :value="key"
                                required
                            />
                            {{ label }}
                        </label>
                    </div>
                    <p v-if="form.errors.topic" class="mt-2 text-sm text-red-600">{{ form.errors.topic }}</p>
                </fieldset>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="suggestion-area">
                        Este assunto envolve qual área?
                    </label>
                    <select
                        id="suggestion-area"
                        v-model="form.area"
                        class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Selecione</option>
                        <option v-for="(label, key) in areas" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="form.errors.area" class="mt-1 text-sm text-red-600">{{ form.errors.area }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="suggestion-message">
                        Escreva sua mensagem
                        <span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <p class="mt-1 text-xs text-slate-500">Espaço livre para você escrever sua sugestão, dúvida ou relato.</p>
                    <textarea
                        id="suggestion-message"
                        v-model="form.message"
                        required
                        rows="8"
                        minlength="10"
                        class="mt-2 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p>
                </div>

                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">
                        Você gostaria de receber retorno?
                        <span class="text-red-600" aria-hidden="true">*</span>
                    </legend>
                    <div class="mt-3 space-y-2">
                        <label
                            v-for="(label, key) in responsePreferences"
                            :key="key"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-800 transition hover:border-talents-200 hover:bg-talents-50/40"
                            :class="form.response_preference === key ? 'border-talents-300 bg-talents-50/70' : ''"
                        >
                            <input
                                v-model="form.response_preference"
                                type="radio"
                                name="response_preference"
                                class="border-slate-300 text-talents-600 focus:ring-talents-500"
                                :value="key"
                                required
                            />
                            {{ label }}
                        </label>
                    </div>
                    <p v-if="form.errors.response_preference" class="mt-2 text-sm text-red-600">
                        {{ form.errors.response_preference }}
                    </p>
                </fieldset>

                <div v-if="wantsReply" class="space-y-4 rounded-xl border border-talents-200 bg-talents-50/40 p-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="suggestion-name">
                            Nome
                            <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="suggestion-name"
                            v-model="form.reporter_name"
                            type="text"
                            maxlength="255"
                            required
                            class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            placeholder="Seu nome"
                        />
                        <p v-if="form.errors.reporter_name" class="mt-1 text-sm text-red-600">{{ form.errors.reporter_name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="suggestion-contact">
                            E-mail, telefone ou outro contato
                            <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="suggestion-contact"
                            v-model="form.contact"
                            type="text"
                            maxlength="255"
                            required
                            class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            placeholder="E-mail, telefone ou outro contato"
                        />
                        <p v-if="form.errors.contact" class="mt-1 text-sm text-red-600">{{ form.errors.contact }}</p>
                    </div>
                </div>

                <p class="text-xs text-slate-500">Obrigado por contribuir. Sua mensagem fica disponível só para quem tem acesso a este canal no painel.</p>

                <button
                    type="submit"
                    class="w-full rounded-full bg-talents-700 py-3 text-sm font-semibold text-white shadow-md hover:bg-talents-800 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Enviar
                </button>
            </form>
        </div>
    </div>
</template>
