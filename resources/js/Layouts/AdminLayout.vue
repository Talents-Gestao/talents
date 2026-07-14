<script setup>
import SidebarBrandMark from '@/Components/SidebarBrandMark.vue';
import SidebarLayout from '@/Components/SidebarLayout.vue';
import SidebarNavGroup from '@/Components/SidebarNavGroup.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import SidebarUserCard from '@/Components/SidebarUserCard.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    AcademicCapIcon,
    ArrowRightOnRectangleIcon,
    BanknotesIcon,
    BuildingOffice2Icon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    Cog6ToothIcon,
    HomeIcon,
    MegaphoneIcon,
    MicrophoneIcon,
    PresentationChartLineIcon,
    RocketLaunchIcon,
    SunIcon,
    UserGroupIcon,
    UserPlusIcon,
    ViewColumnsIcon,
} from '@heroicons/vue/24/outline';

const { canAdmin } = useAdminPermissions();
const page = usePage();
const adminHomeUrl = computed(
    () => page.props.auth?.user?.admin_home_url ?? route('admin.dashboard'),
);

const canCommercialSettings = computed(
    () => canAdmin('comercial') || page.props.auth?.user?.can_commercial_settings,
);

const showComercial = computed(
    () => canAdmin('comercial') || canCommercialSettings.value || canAdmin('plans'),
);

const comercialFallbackHref = computed(() => {
    if (canAdmin('comercial')) {
        return route('admin.comercial.propostas.index');
    }
    if (canCommercialSettings.value) {
        return route('admin.comercial.settings.edit', { tab: 'produtos' });
    }
    if (canAdmin('plans')) {
        return route('admin.plans.index');
    }
    return route('admin.dashboard');
});

const comercialActive = computed(
    () =>
        route().current('admin.comercial.*') ||
        route().current('admin.plans.*'),
);

const showClientes = computed(
    () => canAdmin('companies') || canAdmin('landing_interest'),
);

const comingSoonModule = computed(() => {
    const raw = String(page.url ?? '');
    const path = raw.split('?')[0] ?? '';
    const match = path.match(/\/admin\/em-breve\/([^/]+)/);
    return match?.[1] ?? null;
});

/** Href estável (não depende do Ziggy ter a rota no bundle/HTML em cache). */
const comingSoonHref = (module) => `/admin/em-breve/${encodeURIComponent(module)}`;

const isComingSoon = (...modules) => modules.includes(comingSoonModule.value);

const clientesFallbackHref = computed(() => {
    if (canAdmin('companies')) {
        return route('admin.companies.index');
    }
    if (canAdmin('landing_interest')) {
        return route('admin.landing-interest.index');
    }
    return route('admin.dashboard');
});

const clientesActive = computed(
    () =>
        route().current('admin.companies.*') ||
        route().current('admin.landing-interest.*') ||
        isComingSoon('diagnostico-empresarial', 'contratos-fechados'),
);

const showRecursosHumanos = computed(
    () => canAdmin('rhid') || canAdmin('companies'),
);

const recursosHumanosFallbackHref = computed(() => {
    if (canAdmin('rhid')) {
        return route('admin.rhid.index');
    }
    if (canAdmin('companies')) {
        return comingSoonHref('cadastro-colaboradores');
    }
    return route('admin.dashboard');
});

const recursosHumanosActive = computed(
    () =>
        route().current('admin.rhid.*') ||
        isComingSoon(
            'ponto',
            'cadastro-colaboradores',
            'regulamento-interno',
            'controle-uniformes',
            'destaques-mes',
        ),
);

const showMetamorfose = computed(() => canAdmin('methodology'));

const metamorfoseActive = computed(
    () =>
        route().current('admin.metodologia.*') ||
        route().current('admin.methodology-templates.*'),
);

const showContratacao = computed(() => canAdmin('solides') || canAdmin('entrevistas'));

const contratacaoFallbackHref = computed(() => {
    if (canAdmin('solides')) {
        return route('admin.solides.curriculos.index');
    }
    if (canAdmin('entrevistas')) {
        return route('admin.entrevistas.index');
    }
    return route('admin.dashboard');
});

const contratacaoActive = computed(
    () =>
        route().current('admin.solides.*') ||
        route().current('admin.entrevistas.*') ||
        isComingSoon('profiler', 'timeline'),
);

const showReunioes = computed(() => canAdmin('entrevistas'));

const showVozDoTime = computed(
    () => canAdmin('survey_templates') || canAdmin('desligamento') || canAdmin('denuncias'),
);

const vozDoTimeActive = computed(
    () =>
        route().current('admin.survey-templates.*') ||
        route().current('admin.desligamento.*') ||
        route().current('admin.complaints.*'),
);

const showFinanceiro = computed(() => canAdmin('financeiro'));

const financeiroActive = computed(
    () =>
        route().current('admin.financeiro.*') ||
        isComingSoon('contas-bancarias', 'contas-a-pagar', 'contas-a-receber', 'formas-pagamento'),
);
const showConfiguracao = computed(
    () => canAdmin('settings') || canAdmin('equipe') || canAdmin('empresa_talents'),
);

const configuracaoFallbackHref = computed(() => {
    if (canAdmin('settings')) {
        return route('admin.settings.edit');
    }
    if (canAdmin('equipe')) {
        return route('admin.users.index');
    }
    if (canAdmin('empresa_talents')) {
        return route('admin.empresa-talents.edit');
    }
    return route('admin.dashboard');
});

const configuracaoActive = computed(
    () =>
        route().current('admin.settings.edit') ||
        route().current('admin.ai-settings.edit') ||
        route().current('admin.users.*') ||
        route().current('admin.empresa-talents.*'),
);

const comercialSettingsProdutosHref = computed(() =>
    route('admin.comercial.settings.edit', { tab: 'produtos' }),
);

const comercialSettingsContratosHref = computed(() =>
    route('admin.comercial.settings.edit', { tab: 'contratos' }),
);

const isComercialSettingsTab = (tab) => {
    if (!route().current('admin.comercial.settings.*')) {
        return false;
    }
    const raw = String(page.url ?? '');
    const query = raw.includes('?') ? raw.slice(raw.indexOf('?') + 1) : '';
    const current = new URLSearchParams(query).get('tab') || 'produtos';

    return current === tab;
};
</script>

<template>
    <SidebarLayout
        top-bar-title="Administração"
        :top-bar-show-search="false"
        :top-bar-show-actions="true"
        :top-bar-show-files="false"
    >
        <template #logo="{ collapsed }">
            <SidebarBrandMark
                :href="adminHomeUrl"
                :collapsed="collapsed"
                isolated-icon
                icon-src="/images/logo-icon.png"
            />
        </template>

        <template #navigation="{ collapsed, compact }">
            <SidebarNavItem
                v-if="canAdmin('dashboard')"
                :href="route('admin.dashboard')"
                :active="route().current('admin.dashboard')"
                :icon="HomeIcon"
                label="Home"
                :collapsed="collapsed"
                :compact="compact"
            />

            <SidebarNavGroup
                v-if="showComercial"
                label="Comercial"
                :icon="PresentationChartLineIcon"
                :collapsed="collapsed"
                :compact="compact"
                :active="comercialActive"
                :fallback-href="comercialFallbackHref"
            >
                <SidebarNavItem
                    v-if="canCommercialSettings"
                    :href="comercialSettingsProdutosHref"
                    :active="isComercialSettingsTab('produtos')"
                    label="Tabela de valores"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canAdmin('plans')"
                    :href="route('admin.plans.index')"
                    :active="route().current('admin.plans.*')"
                    label="Planos"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canAdmin('comercial')"
                    :href="route('admin.comercial.propostas.index')"
                    :active="route().current('admin.comercial.propostas.*')"
                    label="Proposta"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canCommercialSettings"
                    :href="comercialSettingsContratosHref"
                    :active="isComercialSettingsTab('contratos')"
                    label="Contrato"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
            </SidebarNavGroup>

            <SidebarNavGroup
                v-if="showClientes"
                label="Clientes"
                :icon="BuildingOffice2Icon"
                :collapsed="collapsed"
                :compact="compact"
                :active="clientesActive"
                :fallback-href="clientesFallbackHref"
            >
                <SidebarNavItem
                    v-if="canAdmin('landing_interest')"
                    :href="route('admin.landing-interest.index')"
                    :active="route().current('admin.landing-interest.*')"
                    label="Leads"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canAdmin('companies')"
                    :href="route('admin.companies.index')"
                    :active="route().current('admin.companies.*')"
                    label="Clientes"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canAdmin('companies')"
                    :href="comingSoonHref('diagnostico-empresarial')"
                    :active="isComingSoon('diagnostico-empresarial')"
                    label="Diagnóstico empresarial"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
                <SidebarNavItem
                    v-if="canAdmin('companies')"
                    :href="comingSoonHref('contratos-fechados')"
                    :active="isComingSoon('contratos-fechados')"
                    label="Contratos fechados"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
            </SidebarNavGroup>

            <SidebarNavGroup
                v-if="showRecursosHumanos"
                label="Recursos Humanos"
                :icon="UserGroupIcon"
                :collapsed="collapsed"
                :compact="compact"
                :active="recursosHumanosActive"
                :fallback-href="recursosHumanosFallbackHref"
            >
                <SidebarNavItem
                    v-if="canAdmin('rhid')"
                    :href="route('admin.rhid.index')"
                    :active="route().current('admin.rhid.*')"
                    label="RHID"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canAdmin('rhid')"
                    :href="comingSoonHref('ponto')"
                    :active="isComingSoon('ponto')"
                    label="Ponto"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
                <SidebarNavItem
                    v-if="canAdmin('companies')"
                    :href="comingSoonHref('cadastro-colaboradores')"
                    :active="isComingSoon('cadastro-colaboradores')"
                    label="Cadastro de colaboradores"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
                <SidebarNavItem
                    v-if="canAdmin('companies')"
                    :href="comingSoonHref('regulamento-interno')"
                    :active="isComingSoon('regulamento-interno')"
                    label="Regulamento interno"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
                <SidebarNavItem
                    v-if="canAdmin('companies')"
                    :href="comingSoonHref('controle-uniformes')"
                    :active="isComingSoon('controle-uniformes')"
                    label="Controle de uniformes"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
                <SidebarNavItem
                    v-if="canAdmin('companies')"
                    :href="comingSoonHref('destaques-mes')"
                    :active="isComingSoon('destaques-mes')"
                    label="Destaques do mês"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
            </SidebarNavGroup>

            <SidebarNavGroup
                v-if="showMetamorfose"
                label="Metamorfose"
                :icon="RocketLaunchIcon"
                :collapsed="collapsed"
                :compact="compact"
                :active="metamorfoseActive"
                :fallback-href="route('admin.metodologia.index')"
            >
                <SidebarNavItem
                    :href="route('admin.metodologia.index')"
                    :active="
                        route().current('admin.metodologia.*') &&
                        !route().current('admin.methodology-templates.*')
                    "
                    label="Metamorfose"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    :href="route('admin.methodology-templates.index')"
                    :active="route().current('admin.methodology-templates.*')"
                    label="Modelos"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
            </SidebarNavGroup>

            <SidebarNavGroup
                v-if="showContratacao"
                label="Contratação"
                :icon="UserPlusIcon"
                :collapsed="collapsed"
                :compact="compact"
                :active="contratacaoActive"
                :fallback-href="contratacaoFallbackHref"
            >
                <SidebarNavItem
                    v-if="canAdmin('solides')"
                    :href="route('admin.solides.curriculos.index')"
                    :active="route().current('admin.solides.*')"
                    label="Banco de talentos"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canAdmin('solides')"
                    :href="comingSoonHref('profiler')"
                    :active="isComingSoon('profiler')"
                    label="Profiler"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
                <SidebarNavItem
                    v-if="canAdmin('solides')"
                    :href="comingSoonHref('timeline')"
                    :active="isComingSoon('timeline')"
                    label="Timeline"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
                <SidebarNavItem
                    v-if="canAdmin('entrevistas')"
                    :href="route('admin.entrevistas.index')"
                    :active="
                        route().current('admin.entrevistas.*') &&
                        !route().current('admin.entrevistas.roteiros.*')
                    "
                    label="Entrevistas IA"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canAdmin('entrevistas')"
                    :href="route('admin.entrevistas.roteiros.index')"
                    :active="route().current('admin.entrevistas.roteiros.*')"
                    label="Roteiros"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
            </SidebarNavGroup>

            <SidebarNavItem
                v-if="showReunioes"
                :href="comingSoonHref('reunioes')"
                :active="isComingSoon('reunioes')"
                :icon="MicrophoneIcon"
                label="Reuniões"
                :collapsed="collapsed"
                :compact="compact"
                badge="Em breve"
            />

            <SidebarNavItem
                v-if="canAdmin('feedbacks')"
                :href="route('admin.feedbacks.index')"
                :active="route().current('admin.feedbacks.*')"
                :icon="ChatBubbleLeftRightIcon"
                label="Feedbacks"
                :collapsed="collapsed"
                :compact="compact"
            />

            <SidebarNavItem
                v-if="canAdmin('ferias')"
                :href="route('admin.ferias.index')"
                :active="route().current('admin.ferias.*')"
                :icon="SunIcon"
                label="Férias"
                :collapsed="collapsed"
                :compact="compact"
            />

            <SidebarNavGroup
                v-if="showVozDoTime"
                label="Voz do Time"
                :icon="MegaphoneIcon"
                :collapsed="collapsed"
                :compact="compact"
                :active="vozDoTimeActive"
                :fallback-href="route('admin.survey-templates.index')"
            >
                <SidebarNavItem
                    v-if="canAdmin('survey_templates') || canAdmin('desligamento')"
                    :href="route('admin.survey-templates.index')"
                    :active="
                        route().current('admin.survey-templates.*') ||
                        route().current('admin.desligamento.*')
                    "
                    label="Pesquisas"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canAdmin('denuncias')"
                    :href="route('admin.complaints.index')"
                    :active="route().current('admin.complaints.*')"
                    label="Canal de denúncias"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
            </SidebarNavGroup>

            <SidebarNavItem
                v-if="canAdmin('strategic_calendar')"
                :href="route('admin.strategic-calendar.index')"
                :active="route().current('admin.strategic-calendar.*')"
                :icon="CalendarDaysIcon"
                label="Calendário"
                :collapsed="collapsed"
                :compact="compact"
            />

            <SidebarNavItem
                v-if="canAdmin('tarefas')"
                :href="route('admin.tarefas.quadros.index')"
                :active="route().current('admin.tarefas.*')"
                :icon="ViewColumnsIcon"
                label="Tarefas"
                :collapsed="collapsed"
                :compact="compact"
            />

            <SidebarNavItem
                v-if="canAdmin('training')"
                :href="route('admin.training.index')"
                :active="route().current('admin.training.*')"
                :icon="AcademicCapIcon"
                label="Capacitação"
                :collapsed="collapsed"
                :compact="compact"
                badge="Em breve"
            />

            <SidebarNavGroup
                v-if="showFinanceiro"
                label="Financeiro"
                :icon="BanknotesIcon"
                :collapsed="collapsed"
                :compact="compact"
                :active="financeiroActive"
                :fallback-href="route('admin.financeiro.dashboard')"
            >
                <SidebarNavItem
                    :href="route('admin.financeiro.dashboard')"
                    :active="route().current('admin.financeiro.dashboard')"
                    label="Resumo"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    :href="route('admin.financeiro.vendas.index')"
                    :active="route().current('admin.financeiro.vendas.*')"
                    label="Vendas"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    :href="route('admin.financeiro.comissoes.index')"
                    :active="route().current('admin.financeiro.comissoes.*')"
                    label="Comissões"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    :href="comingSoonHref('contas-bancarias')"
                    :active="isComingSoon('contas-bancarias')"
                    label="Contas bancárias"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
                <SidebarNavItem
                    :href="comingSoonHref('contas-a-pagar')"
                    :active="isComingSoon('contas-a-pagar')"
                    label="Contas a pagar"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
                <SidebarNavItem
                    :href="comingSoonHref('contas-a-receber')"
                    :active="isComingSoon('contas-a-receber')"
                    label="Contas a receber"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
                <SidebarNavItem
                    :href="comingSoonHref('formas-pagamento')"
                    :active="isComingSoon('formas-pagamento')"
                    label="Formas de pagamento"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                    badge="Em breve"
                />
            </SidebarNavGroup>
            <SidebarNavGroup
                v-if="showConfiguracao"
                label="Configuração"
                :icon="Cog6ToothIcon"
                :collapsed="collapsed"
                :compact="compact"
                :active="configuracaoActive"
                :fallback-href="configuracaoFallbackHref"
            >
                <SidebarNavItem
                    v-if="canAdmin('settings')"
                    :href="route('admin.settings.edit')"
                    :active="
                        route().current('admin.settings.edit') ||
                        route().current('admin.ai-settings.edit')
                    "
                    label="Geral"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canAdmin('equipe')"
                    :href="route('admin.users.index')"
                    :active="route().current('admin.users.*')"
                    label="Equipe"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarNavItem
                    v-if="canAdmin('empresa_talents')"
                    :href="route('admin.empresa-talents.edit')"
                    :active="route().current('admin.empresa-talents.*')"
                    label="Empresa Talents"
                    variant="nested"
                    :collapsed="collapsed"
                    :compact="compact"
                />
            </SidebarNavGroup>
        </template>

        <template #user="{ collapsed, compact }">
            <div class="flex flex-col gap-0.5">
                <SidebarNavItem
                    :href="route('logout')"
                    method="post"
                    as="button"
                    :icon="ArrowRightOnRectangleIcon"
                    label="Sair"
                    :collapsed="collapsed"
                    :compact="compact"
                />
                <SidebarUserCard
                    :href="route('profile.edit')"
                    :active="route().current('profile.*')"
                    :label="$page.props.auth.user.name"
                    :collapsed="collapsed"
                    :compact="compact"
                />
            </div>
        </template>

        <template v-if="$slots.header" #header>
            <slot name="header" />
        </template>

        <template v-if="$slots.aside" #aside>
            <slot name="aside" />
        </template>

        <slot />
    </SidebarLayout>
</template>
