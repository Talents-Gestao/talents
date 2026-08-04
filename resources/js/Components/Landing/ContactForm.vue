<script setup>
import LandingInterestSourceField from '@/Components/Landing/LandingInterestSourceField.vue';
import { useForm, usePage } from '@inertiajs/vue3';

defineProps({
    compact: { type: Boolean, default: false },
    showHeading: { type: Boolean, default: true },
});

const page = usePage();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    company: '',
    message: '',
    source: 'site',
});

const submitInterest = () => {
    form.post(route('landing.interest'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'email', 'phone', 'company', 'message');
            form.source = 'site';
        },
    });
};
</script>

<template>
    <div>
        <template v-if="showHeading">
            <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">
                {{ compact ? 'Fale com um especialista' : 'Quer conhecer mais a Talents?' }}
            </h2>
            <p class="mx-auto mt-3 max-w-xl text-center text-slate-600">
                Deixe seus dados (telefone/WhatsApp ajuda no retorno) e, se quiser, uma mensagem.
            </p>
        </template>

        <div
            v-if="page.props.flash?.success"
            class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm text-emerald-800"
        >
            {{ page.props.flash.success }}
        </div>
        <div
            v-if="page.props.flash?.error"
            class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-center text-sm text-red-800"
        >
            {{ page.props.flash.error }}
        </div>

        <form
            class="surface-card-soft space-y-5 p-6 sm:p-8"
            :class="showHeading ? 'mt-8' : ''"
            @submit.prevent="submitInterest"
        >
            <div>
                <label class="block text-sm font-medium text-slate-700" for="interest-name">Nome</label>
                <input
                    id="interest-name"
                    v-model="form.name"
                    type="text"
                    required
                    autocomplete="name"
                    class="field-input"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="interest-email">E-mail</label>
                <input
                    id="interest-email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    class="field-input"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="interest-phone">
                    Telefone / WhatsApp <span class="font-normal text-slate-500">(opcional)</span>
                </label>
                <input
                    id="interest-phone"
                    v-model="form.phone"
                    type="tel"
                    autocomplete="tel"
                    placeholder="DDD + número"
                    class="field-input"
                />
                <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="interest-company">
                    Empresa <span class="font-normal text-slate-500">(opcional)</span>
                </label>
                <input
                    id="interest-company"
                    v-model="form.company"
                    type="text"
                    autocomplete="organization"
                    class="field-input"
                />
                <p v-if="form.errors.company" class="mt-1 text-sm text-red-600">{{ form.errors.company }}</p>
            </div>
            <LandingInterestSourceField
                id="interest-source"
                v-model="form.source"
                :error="form.errors.source"
            />
            <div>
                <label class="block text-sm font-medium text-slate-700" for="interest-message">
                    Mensagem <span class="font-normal text-slate-500">(opcional)</span>
                </label>
                <textarea
                    id="interest-message"
                    v-model="form.message"
                    rows="4"
                    class="field-input"
                    placeholder="Conte um pouco do que você busca ou deixe em branco."
                />
                <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p>
            </div>
            <div class="pt-2">
                <button
                    type="submit"
                    class="btn-primary w-full disabled:opacity-60 sm:w-auto"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Enviando…' : 'Falar com Especialista' }}
                </button>
            </div>
        </form>
    </div>
</template>
