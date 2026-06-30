<script setup>
import SidebarBrandMark from '@/Components/SidebarBrandMark.vue';
import SidebarLayout from '@/Components/SidebarLayout.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import SidebarUserCard from '@/Components/SidebarUserCard.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    AcademicCapIcon,
    ArrowRightOnRectangleIcon,
    BuildingOffice2Icon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    Cog6ToothIcon,
    HomeIcon,
    MegaphoneIcon,
    PresentationChartLineIcon,
    RocketLaunchIcon,
    UserPlusIcon,
    ViewColumnsIcon,
} from '@heroicons/vue/24/outline';

const { canAdmin } = useAdminPermissions();
const page = usePage();
const adminHomeUrl = computed(
    () => page.props.auth?.user?.admin_home_url ?? route('admin.dashboard'),
);

const showComercial = computed(
    () =>
        canAdmin('comercial') ||
        canAdmin('financeiro') ||
        page.props.auth?.user?.can_commercial_settings ||
        canAdmin('landing_interest') ||
        canAdmin('plans'),
);

const comercialHref = computed(() => {
    if (canAdmin('comercial')) {
        return route('admin.comercial.dashboard');
    }
    if (canAdmin('financeiro')) {
        return route('admin.financeiro.dashboard');
    }
    if (page.props.auth?.user?.can_commercial_settings) {
        return route('admin.comercial.settings.edit');
    }
    if (canAdmin('landing_interest')) {
        return route('admin.landing-interest.index');
    }
    if (canAdmin('plans')) {
        return route('admin.plans.index');
    }
    return route('admin.dashboard');
});

const comercialActive = computed(
    () =>
        (route().current('admin.comercial.*') &&
            !route().current('admin.comercial.settings.*')) ||
        route().current('admin.comercial.settings.*') ||
        route().current('admin.financeiro.*') ||
        route().current('admin.landing-interest.*') ||
        route().current('admin.plans.*'),
);

const showContratacao = computed(() => canAdmin('solides') || canAdmin('entrevistas'));

const contratacaoHref = computed(() => {
    if (canAdmin('solides')) {
        return route('admin.solides.curriculos.index');
    }
    if (canAdmin('entrevistas')) {
        return route('admin.entrevistas.index');
    }
    return route('admin.dashboard');
});

const contratacaoActive = computed(
    () => route().current('admin.solides.*') || route().current('admin.entrevistas.*'),
);

const showConfiguracao = computed(
    () => canAdmin('settings') || canAdmin('equipe') || canAdmin('empresa_talents'),
);

const configuracaoHref = computed(() => {
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
</script>

<template>
    <SidebarLayout
        top-bar-title="Administração"
        :top-bar-show-search="false"
        :top-bar-show-actions="false"
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

            <SidebarNavItem
                v-if="showComercial"
                :href="comercialHref"
                :active="comercialActive"
                :icon="PresentationChartLineIcon"
                label="Comercial"
                :collapsed="collapsed"
                :compact="compact"
            />

            <SidebarNavItem
                v-if="canAdmin('companies')"
                :href="route('admin.companies.index')"
                :active="route().current('admin.companies.*')"
                :icon="BuildingOffice2Icon"
                label="Clientes"
                :collapsed="collapsed"
                :compact="compact"
            />

            <SidebarNavItem
                v-if="canAdmin('methodology')"
                :href="route('admin.metodologia.index')"
                :active="
                    route().current('admin.metodologia.*') &&
                    !route().current('admin.methodology-templates.*')
                "
                :icon="RocketLaunchIcon"
                label="Metamorfose"
                :collapsed="collapsed"
                :compact="compact"
            />

            <SidebarNavItem
                v-if="showContratacao"
                :href="contratacaoHref"
                :active="contratacaoActive"
                :icon="UserPlusIcon"
                label="Contratação de Talentos"
                :collapsed="collapsed"
                :compact="compact"
            />

            <SidebarNavItem
                v-if="canAdmin('methodology')"
                :href="route('admin.methodology-templates.index')"
                :active="route().current('admin.methodology-templates.*')"
                :icon="ChatBubbleLeftRightIcon"
                label="Feedbacks"
                :collapsed="collapsed"
                :compact="compact"
            />

            <SidebarNavItem
                v-if="canAdmin('survey_templates')"
                :href="route('admin.survey-templates.index')"
                :active="route().current('admin.survey-templates.*')"
                :icon="MegaphoneIcon"
                label="Voz do Time"
                :collapsed="collapsed"
                :compact="compact"
            />

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

            <SidebarNavItem
                v-if="showConfiguracao"
                :href="configuracaoHref"
                :active="configuracaoActive"
                :icon="Cog6ToothIcon"
                label="Configuração"
                :collapsed="collapsed"
                :compact="compact"
            />
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
