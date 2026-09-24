<script setup>
import { computed } from 'vue';
import ContactForm from '@/Components/Landing/ContactForm.vue';
import LandingLayout from '@/Components/Landing/LandingLayout.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const title = 'Contato — Fale com um Especialista | Talents';
const description =
    'Entre em contato com a Talents. Consultoria estratégica em gestão de pessoas para empresas e profissionais. Vamos fortalecer sua empresa através das pessoas.';

const breadcrumbJsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Home', item: typeof window !== 'undefined' ? window.location.origin : '' },
            { '@type': 'ListItem', position: 2, name: 'Contato' },
        ],
    }),
);
</script>

<template>
    <LandingLayout
        :title="title"
        :description="description"
        :can-login="canLogin"
        :can-register="canRegister"
    >
        <template #head>
            <component :is="'script'" type="application/ld+json" head-key="jsonld-breadcrumb">
                {{ breadcrumbJsonLd }}
            </component>
        </template>

        <section class="mx-auto max-w-6xl px-4 py-16 md:py-20">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-widest text-talents-600">Contato</p>
                <h1 class="mt-4 text-4xl font-bold leading-tight text-slate-900 md:text-5xl">
                    Vamos fortalecer sua empresa através das pessoas?
                </h1>
                <p class="mt-6 text-lg leading-relaxed text-slate-600">
                    Converse com um especialista Talents e descubra como nossa consultoria estratégica em gestão de
                    pessoas pode apoiar sua transformação.
                </p>
            </div>
        </section>

        <section class="pb-16">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 lg:grid-cols-5">
                <div class="surface-card-soft p-8 lg:col-span-2">
                    <h2 class="text-lg font-semibold text-slate-900">Canais de contato</h2>
                    <div class="mt-6 space-y-4 text-sm text-slate-600">
                        <div>
                            <p class="font-medium text-slate-900">E-mail</p>
                            <a href="mailto:contato@talentsgestao.com" class="text-talents-700 hover:underline">
                                contato@talentsgestao.com
                            </a>
                        </div>
                        <div>
                            <p class="font-medium text-slate-900">WhatsApp</p>
                            <a
                                href="https://wa.me/551131096837"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-talents-700 hover:underline"
                            >
                                (11) 3109-6837
                            </a>
                        </div>
                        <div>
                            <p class="font-medium text-slate-900">Localização</p>
                            <p>Várzea Paulista · SP</p>
                        </div>
                    </div>
                    <a
                        href="https://wa.me/551131096837"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn-primary mt-8 w-full sm:w-auto"
                    >
                        Falar no WhatsApp
                    </a>
                </div>

                <div id="contato" class="lg:col-span-3">
                    <ContactForm />
                </div>
            </div>
        </section>
    </LandingLayout>
</template>
