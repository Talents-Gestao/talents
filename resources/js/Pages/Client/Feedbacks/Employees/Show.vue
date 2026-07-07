<script setup>
import FeedbackSessionCard from '@/Components/Feedback/FeedbackSessionCard.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, Link } from '@inertiajs/vue3';
import { BriefcaseIcon, BuildingOfficeIcon, PhoneIcon, UserIcon } from '@heroicons/vue/24/outline';

defineProps({
    employee: Object,
    sessions: Array,
});
</script>

<template>
    <Head :title="employee.name" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader
                :back-href="feedbackRoute('employees.index')"
                back-label="Colaboradores"
                :title="employee.name"
                :subtitle="employee.email"
            >
                <template #trailing>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="feedbackRoute('sessions.create')">
                            <PrimaryButton type="button">Novo feedback</PrimaryButton>
                        </Link>
                        <Link :href="feedbackRoute('employees.edit', employee.id)">
                            <SecondaryButton type="button">Editar</SecondaryButton>
                        </Link>
                    </div>
                </template>
            </FormPageHeader>
        </template>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-1">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Perfil</h3>
                <dl class="mt-4 space-y-4 text-sm">
                    <div class="flex items-start gap-3">
                        <BriefcaseIcon class="mt-0.5 h-4 w-4 shrink-0 text-talents-600" />
                        <div>
                            <dt class="text-xs text-slate-500">Cargo</dt>
                            <dd class="font-medium text-slate-800">{{ employee.position?.name ?? '—' }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <BuildingOfficeIcon class="mt-0.5 h-4 w-4 shrink-0 text-talents-600" />
                        <div>
                            <dt class="text-xs text-slate-500">Setor</dt>
                            <dd class="font-medium text-slate-800">{{ employee.department?.name ?? '—' }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <UserIcon class="mt-0.5 h-4 w-4 shrink-0 text-talents-600" />
                        <div>
                            <dt class="text-xs text-slate-500">Líder</dt>
                            <dd class="font-medium text-slate-800">{{ employee.leader?.name ?? '—' }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <PhoneIcon class="mt-0.5 h-4 w-4 shrink-0 text-talents-600" />
                        <div>
                            <dt class="text-xs text-slate-500">Telefone</dt>
                            <dd class="font-medium text-slate-800">{{ employee.phone ?? '—' }}</dd>
                        </div>
                    </div>
                </dl>
                <p v-if="employee.notes" class="mt-5 rounded-xl bg-slate-50 p-3 text-sm leading-relaxed text-slate-600">
                    {{ employee.notes }}
                </p>
            </div>

            <div class="lg:col-span-2">
                <h3 class="mb-4 text-sm font-semibold text-talents-900">Histórico de feedbacks</h3>
                <div v-if="sessions.length" class="grid gap-4 sm:grid-cols-2">
                    <FeedbackSessionCard v-for="s in sessions" :key="s.id" :session="s" :show-employee="false" />
                </div>
                <div
                    v-else
                    class="rounded-2xl border border-dashed border-talents-200 bg-talents-50/40 px-6 py-10 text-center"
                >
                    <p class="text-sm font-medium text-talents-900">Nenhum feedback ainda</p>
                    <p class="mt-1 text-sm text-slate-600">Abra o primeiro alinhamento com este colaborador.</p>
                    <Link
                        :href="feedbackRoute('sessions.create')"
                        class="mt-4 inline-flex rounded-xl bg-talents-700 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-800"
                    >
                        Criar feedback
                    </Link>
                </div>
            </div>
        </div>
    </FeedbacksLayout>
</template>
