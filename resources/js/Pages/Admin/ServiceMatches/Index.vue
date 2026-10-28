<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    matches: { type: Array, default: () => [] },
    confidenceLabels: { type: Object, default: () => ({}) },
    owners: { type: Array, default: () => [] },
    requireReview: { type: Boolean, default: true },
});

const drafts = reactive(
    Object.fromEntries(
        props.matches.map((m) => [
            m.id,
            {
                service_name: m.service_name,
                eligible_services: m.eligible_services,
                consultative_text: m.consultative_text,
                confidence: m.confidence,
                active: m.active,
                commercial_owner_id: m.commercial_owner_id ?? '',
            },
        ]),
    ),
);

const save = (match) =>
    router.patch(route('admin.service-matches.update', match.id), { ...drafts[match.id], commercial_owner_id: drafts[match.id].commercial_owner_id || null }, { preserveScroll: true });

const approve = (match) => router.post(route('admin.service-matches.approve', match.id), {}, { preserveScroll: true });
</script>

<template>
    <Head title="Soluções Talents · regras" />

    <AdminLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Soluções Talents · consultoria</p>
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Regras de recomendação por dimensão</h2>
            </div>
        </template>

        <div class="space-y-4">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $page.props.flash.success }}</div>

            <p class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
                Cada regra liga uma dimensão em atenção a um serviço. Os textos iniciais vêm do protótipo aprovado.
                <span v-if="requireReview"><strong>A empresa só vê uma regra depois que a Talents revisa o texto</strong>; alterar o texto, o serviço ou a confiança exige nova revisão.</span>
                <span v-else>A revisão está desligada neste ambiente: a empresa vê todas as regras ativas.</span>
            </p>

            <section v-for="match in matches" :key="match.id" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" :aria-labelledby="`match-${match.id}`">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 :id="`match-${match.id}`" class="text-base font-semibold text-talents-900">{{ match.factor_label }}</h3>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="match.reviewed ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-100 text-amber-900'"
                    >
                        {{ match.reviewed ? `Revisado por ${match.reviewed_by ?? '—'} em ${match.reviewed_at}` : 'Aguardando revisão da Talents' }}
                    </span>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label :for="`svc-${match.id}`" class="text-xs font-semibold text-gray-600">Serviço</label>
                        <input :id="`svc-${match.id}`" v-model="drafts[match.id].service_name" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label :for="`elig-${match.id}`" class="text-xs font-semibold text-gray-600">Serviços elegíveis</label>
                        <input :id="`elig-${match.id}`" v-model="drafts[match.id].eligible_services" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :for="`txt-${match.id}`" class="text-xs font-semibold text-gray-600">Explicação consultiva</label>
                        <textarea :id="`txt-${match.id}`" v-model="drafts[match.id].consultative_text" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label :for="`conf-${match.id}`" class="text-xs font-semibold text-gray-600">Grau de confiança</label>
                        <select :id="`conf-${match.id}`" v-model="drafts[match.id].confidence" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option v-for="(label, value) in confidenceLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label :for="`owner-${match.id}`" class="text-xs font-semibold text-gray-600">Responsável comercial padrão</label>
                        <select :id="`owner-${match.id}`" v-model="drafts[match.id].commercial_owner_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">Sem responsável (assume quem pegar)</option>
                            <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm sm:col-span-2">
                        <input v-model="drafts[match.id].active" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                        <span>Regra ativa</span>
                    </label>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-800" @click="save(match)">Salvar regra</button>
                    <button v-if="!match.reviewed" type="button" class="rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white" @click="approve(match)">Revisar e liberar à empresa</button>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
