<script setup>
import ComplaintsPublicLinkPanel from '@/Components/Complaints/ComplaintsPublicLinkPanel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import { collectiveBargainingMonthLabel } from '@/utils/collectiveBargainingMonths';
import { formatCnpj } from '@/utils/formatCnpj';
import { ChevronRightIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    section: { type: String, default: null },
    company: { type: Object, required: true },
    nr1ShellUrls: { type: Object, default: () => ({}) },
    planIncludesMetodologia: { type: Boolean, default: false },
    complaintsPublicUrl: { type: String, default: null },
    templates: { type: Array, default: () => [] },
    methodologyTemplates: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'delete-survey', 'attach-template', 'detach-template', 'attach-methodology', 'detach-methodology']);

const { canAdmin } = useAdminPermissions();

const titles = {
    dados: 'Dados',
    assinaturas: 'Assinaturas',
    direcionamento: 'Direcionamento Estratégico',
    mapeamentos: 'Mapeamentos disponíveis',
    pesquisas: 'Pesquisas e parecer',
    mapa_proposito: 'Mapa de Propósito',
    usuarios: 'Usuários',
};

const title = computed(() => titles[props.section] || 'Atalho');

const collectiveBargainingMonth = computed(() =>
    collectiveBargainingMonthLabel(props.company.collective_bargaining_month),
);

const subscriptionStatusClass = (status) => {
    const value = String(status || '').toLowerCase();
    if (value === 'active') {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-200/80';
    }
    if (value === 'cancelled' || value === 'canceled') {
        return 'bg-slate-100 text-slate-600 ring-slate-200/80';
    }
    return 'bg-amber-50 text-amber-800 ring-amber-200/80';
};

const subscriptionStatusLabel = (status) => {
    const value = String(status || '').toLowerCase();
    if (value === 'active') {
        return 'Ativa';
    }
    if (value === 'cancelled' || value === 'canceled') {
        return 'Cancelada';
    }
    if (value === 'pending') {
        return 'Pendente';
    }
    if (value === 'expired') {
        return 'Expirada';
    }
    return status || '—';
};

const roleLabel = (role) => {
    const map = {
        company_admin: 'Administrador da empresa',
        company_user: 'Usuário da empresa',
        super_admin: 'Administrador Talents',
    };
    return map[String(role || '').toLowerCase()] ?? role ?? '—';
};

const isLinked = (collection, id) => (collection || []).some((item) => item.id === id);

const destinations = computed(() => {
    const companyId = props.company.id;
    const items = [];

    if (props.section === 'dados' && canAdmin('companies', 'edit')) {
        items.push({
            key: 'edit',
            label: 'Editar empresa',
            hint: 'Cadastro, plano, portal e módulos',
            href: route('admin.companies.edit', companyId),
        });
    }

    if (props.section === 'assinaturas') {
        if (canAdmin('companies', 'edit')) {
            items.push({
                key: 'edit-plan',
                label: 'Alterar plano da empresa',
                hint: 'A assinatura é definida na ficha',
                href: route('admin.companies.edit', companyId),
            });
        }
        if (canAdmin('plans')) {
            items.push({
                key: 'plans',
                label: 'Catálogo de planos',
                hint: 'Planos disponíveis para vincular',
                href: route('admin.plans.index'),
            });
        }
    }

    if (props.section === 'direcionamento') {
        if (canAdmin('plans')) {
            items.push({
                key: 'plans',
                label: 'Planos',
                hint: 'O acesso ao Direcionamento vem da assinatura ativa',
                href: route('admin.plans.index'),
            });
        }
        if (canAdmin('methodology')) {
            items.push({
                key: 'templates',
                label: 'Templates de satisfação',
                hint: 'Cadastro dos formulários da etapa 02',
                href: route('admin.methodology-templates.index'),
            });
        }
    }

    if (props.section === 'mapeamentos' && canAdmin('survey_templates')) {
        items.push({
            key: 'catalog',
            label: 'Catálogo de templates NR-1',
            hint: 'Criar e editar os mapeamentos',
            href: route('admin.survey-templates.index'),
        });
    }

    if (props.section === 'pesquisas') {
        for (const survey of props.company.surveys || []) {
            const href = props.nr1ShellUrls?.[survey.id];
            if (!href) {
                continue;
            }
            items.push({
                key: `survey-${survey.id}`,
                label: survey.title,
                hint: 'Abrir painel NR-1',
                href,
                surveyId: survey.id,
            });
        }
    }

    if (props.section === 'mapa_proposito' && canAdmin('companies')) {
        items.push({
            key: 'dashboard',
            label: 'Dashboard de resultados',
            hint: 'Perguntas anônimas e respostas por setor',
            href: route('admin.mapa-proposito.index'),
        });
    }

    if (props.section === 'usuarios' && canAdmin('companies')) {
        items.push({
            key: 'manage',
            label: 'Gerenciar usuários',
            hint: 'Lista, convite e permissões',
            href: route('admin.companies.users.index', companyId),
        });
        if (canAdmin('companies', 'create')) {
            items.push({
                key: 'create',
                label: 'Novo usuário',
                hint: 'Convidar alguém para esta empresa',
                href: route('admin.companies.users.create', companyId),
            });
        }
        for (const user of props.company.users || []) {
            items.push({
                key: `user-${user.id}`,
                label: user.name,
                hint: user.email || roleLabel(user.role),
                href: route('admin.companies.users.edit', [companyId, user.id]),
            });
        }
    }

    return items;
});

const surveysWithoutPanel = computed(() => {
    if (props.section !== 'pesquisas') {
        return [];
    }
    return (props.company.surveys || []).filter((survey) => !props.nr1ShellUrls?.[survey.id]);
});
</script>

<template>
    <Modal :show="show" max-width="3xl" @close="emit('close')">
        <div class="max-h-[min(80vh,40rem)] overflow-y-auto p-6">
            <h2 class="text-lg font-semibold text-slate-900">{{ title }}</h2>
            <p class="mt-1 text-sm text-slate-500">Escolha para onde deseja ir.</p>

            <ul v-if="destinations.length" class="mt-4 space-y-2">
                <li v-for="item in destinations" :key="item.key" class="flex items-stretch gap-2">
                    <Link
                        :href="item.href"
                        class="group flex min-w-0 flex-1 items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-talents-300 hover:bg-talents-50/40"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-900">{{ item.label }}</span>
                            <span class="mt-0.5 block truncate text-xs text-slate-500 group-hover:text-talents-800">
                                {{ item.hint }}
                            </span>
                        </span>
                        <ChevronRightIcon class="h-4 w-4 shrink-0 text-slate-400 group-hover:text-talents-700" aria-hidden="true" />
                    </Link>
                    <button
                        v-if="section === 'pesquisas' && item.surveyId && canAdmin('companies', 'delete')"
                        type="button"
                        class="shrink-0 rounded-2xl px-3 text-xs font-semibold text-red-700 ring-1 ring-red-200 transition hover:bg-red-50"
                        @click="emit('delete-survey', (company.surveys || []).find((survey) => survey.id === item.surveyId))"
                    >
                        Excluir
                    </button>
                </li>
            </ul>
            <p
                v-else-if="section === 'pesquisas' && !(company.surveys || []).length"
                class="mt-4 text-sm text-slate-500"
            >
                Nenhuma pesquisa cadastrada para esta empresa.
            </p>
            <p v-else-if="section === 'usuarios' && !(company.users || []).length" class="mt-4 text-sm text-slate-500">
                Nenhum usuário vinculado.
            </p>

            <ul v-if="surveysWithoutPanel.length" class="mt-3 space-y-2">
                <li
                    v-for="survey in surveysWithoutPanel"
                    :key="`unavailable-${survey.id}`"
                    class="flex items-center justify-between gap-3 rounded-2xl border border-dashed border-slate-200 px-4 py-3"
                >
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-slate-800">{{ survey.title }}</span>
                        <span class="mt-0.5 block text-xs text-slate-500">Painel NR-1 ainda não disponível</span>
                    </span>
                    <button
                        v-if="canAdmin('companies', 'delete')"
                        type="button"
                        class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200 transition hover:bg-red-50"
                        @click="emit('delete-survey', survey)"
                    >
                        Excluir
                    </button>
                </li>
            </ul>

            <div v-if="section === 'dados'" class="mt-6 border-t border-slate-100 pt-5">
                <h3 class="text-xs font-medium uppercase tracking-wide text-slate-400">Informações cadastrais</h3>
                <dl class="mt-3 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">E-mail</dt>
                        <dd class="mt-1 text-slate-800">{{ company.contact_email || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Instagram</dt>
                        <dd class="mt-1 text-slate-800">{{ company.instagram || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Portal de entrada</dt>
                        <dd class="mt-1 text-slate-800">
                            <template v-if="company.portal_slug">
                                {{ company.portal_enabled ? 'Ativo' : 'Inativo' }}
                                — /entrar/{{ company.portal_slug }}
                                <span v-if="company.brand_name"> ({{ company.brand_name }})</span>
                            </template>
                            <template v-else>—</template>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Razão social</dt>
                        <dd class="mt-1 text-slate-800">{{ company.legal_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">CNPJ</dt>
                        <dd class="mt-1 text-slate-800">{{ formatCnpj(company.cnpj) || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Segmento</dt>
                        <dd class="mt-1 text-slate-800">{{ company.segment || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Ramo de atividade</dt>
                        <dd class="mt-1 text-slate-800">{{ company.activity_branch || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Mês do dissídio</dt>
                        <dd class="mt-1 text-slate-800">{{ collectiveBargainingMonth || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Logradouro</dt>
                        <dd class="mt-1 text-slate-800">{{ company.address_street || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Bairro</dt>
                        <dd class="mt-1 text-slate-800">{{ company.address_neighborhood || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Município</dt>
                        <dd class="mt-1 text-slate-800">{{ company.address_city || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">UF</dt>
                        <dd class="mt-1 text-slate-800">{{ company.address_state || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">CEP</dt>
                        <dd class="mt-1 text-slate-800">{{ company.address_zip || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Regime tributário</dt>
                        <dd class="mt-1 text-slate-800">{{ company.tax_regime || '—' }}</dd>
                    </div>
                </dl>
                <div v-if="complaintsPublicUrl" class="mt-4">
                    <ComplaintsPublicLinkPanel :url="complaintsPublicUrl" variant="compact" />
                </div>
            </div>

            <div v-else-if="section === 'assinaturas'" class="mt-6 border-t border-slate-100 pt-5">
                <h3 class="text-xs font-medium uppercase tracking-wide text-slate-400">Planos vinculados</h3>
                <ul v-if="company.subscriptions?.length" class="mt-3 divide-y divide-slate-100">
                    <li
                        v-for="sub in company.subscriptions"
                        :key="sub.id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span class="text-sm font-medium text-slate-800">{{ sub.plan?.name || 'Plano' }}</span>
                        <span
                            class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                            :class="subscriptionStatusClass(sub.status)"
                        >
                            {{ subscriptionStatusLabel(sub.status) }}
                        </span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Nenhuma assinatura vinculada.</p>
            </div>

            <div v-else-if="section === 'direcionamento'" class="mt-6 border-t border-slate-100 pt-5">
                <p
                    class="rounded-2xl px-4 py-3 text-sm font-medium"
                    :class="planIncludesMetodologia ? 'bg-emerald-50/90 text-emerald-900' : 'bg-amber-50/90 text-amber-900'"
                >
                    {{
                        planIncludesMetodologia
                            ? 'Plano ativo inclui Direcionamento Estratégico.'
                            : 'A assinatura ativa não inclui Direcionamento Estratégico — ajuste a assinatura da empresa.'
                    }}
                </p>
                <h3 class="mt-5 text-xs font-medium uppercase tracking-wide text-slate-400">
                    Templates de satisfação desta empresa
                </h3>
                <ul v-if="methodologyTemplates.length" class="mt-3 divide-y divide-slate-100">
                    <li
                        v-for="template in methodologyTemplates"
                        :key="`m-${template.id}`"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span class="text-sm text-slate-800">{{ template.title }}</span>
                        <button
                            v-if="!isLinked(company.methodology_form_templates, template.id)"
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold text-talents-700 ring-1 ring-talents-200/80 transition hover:bg-talents-50"
                            @click="emit('attach-methodology', template.id)"
                        >
                            Vincular
                        </button>
                        <button
                            v-else
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200/80 transition hover:bg-red-50"
                            @click="emit('detach-methodology', template.id)"
                        >
                            Remover
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">
                    Nenhum template cadastrado. Crie em Admin → Direcionamento Estratégico → Templates.
                </p>
            </div>

            <div v-else-if="section === 'mapeamentos'" class="mt-6 border-t border-slate-100 pt-5">
                <h3 class="text-xs font-medium uppercase tracking-wide text-slate-400">Templates liberados para esta empresa</h3>
                <ul v-if="templates.length" class="mt-3 divide-y divide-slate-100">
                    <li
                        v-for="template in templates"
                        :key="template.id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span class="text-sm text-slate-800">{{ template.title }}</span>
                        <button
                            v-if="!isLinked(company.survey_templates, template.id)"
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold text-talents-700 ring-1 ring-talents-200/80 transition hover:bg-talents-50"
                            @click="emit('attach-template', template.id)"
                        >
                            Vincular
                        </button>
                        <button
                            v-else
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200/80 transition hover:bg-red-50"
                            @click="emit('detach-template', template.id)"
                        >
                            Remover
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Nenhum mapeamento disponível no catálogo.</p>
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton type="button" @click="emit('close')">Fechar</SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
