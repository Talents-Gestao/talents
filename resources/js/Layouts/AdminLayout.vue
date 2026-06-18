<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SidebarLayout from '@/Components/SidebarLayout.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { UserCircleIcon } from '@heroicons/vue/24/outline';

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
            <Link
                :href="adminHomeUrl"
                class="flex min-w-0 max-w-full items-center justify-center lg:justify-start"
            >
                <ApplicationLogo
                    :class="
                        collapsed
                            ? '!h-7 !max-h-7 !max-w-[2.75rem] w-auto object-contain object-center'
                            : '!h-8 !max-h-8 !max-w-[10.5rem] w-auto object-contain object-left'
                    "
                />
            </Link>
        </template>

        <template #navigation="{ collapsed }">
            <SidebarNavItem
                v-if="canAdmin('dashboard')"
                :href="route('admin.dashboard')"
                :active="route().current('admin.dashboard')"
                label="Home"
                variant="minimal"
                :collapsed="collapsed"
            />

            <SidebarNavItem
                v-if="showComercial"
                :href="comercialHref"
                :active="comercialActive"
                label="Comercial"
                variant="minimal"
                :collapsed="collapsed"
            />

            <SidebarNavItem
                v-if="canAdmin('companies')"
                :href="route('admin.companies.index')"
                :active="route().current('admin.companies.*')"
                label="Clientes"
                variant="minimal"
                :collapsed="collapsed"
            />

            <SidebarNavItem
                v-if="canAdmin('methodology')"
                :href="route('admin.metodologia.index')"
                :active="
                    route().current('admin.metodologia.*') &&
                    !route().current('admin.methodology-templates.*')
                "
                label="Metamorfose"
                variant="minimal"
                :collapsed="collapsed"
            />

            <SidebarNavItem
                v-if="showContratacao"
                :href="contratacaoHref"
                :active="contratacaoActive"
                label="Contratação de Talentos"
                variant="minimal"
                :collapsed="collapsed"
            />

            <SidebarNavItem
                v-if="canAdmin('methodology')"
                :href="route('admin.methodology-templates.index')"
                :active="route().current('admin.methodology-templates.*')"
                label="Feedbacks"
                variant="minimal"
                :collapsed="collapsed"
            />

            <SidebarNavItem
                v-if="canAdmin('survey_templates')"
                :href="route('admin.survey-templates.index')"
                :active="route().current('admin.survey-templates.*')"
                label="Voz do Time"
                variant="minimal"
                :collapsed="collapsed"
            />

            <SidebarNavItem
                v-if="canAdmin('strategic_calendar')"
                :href="route('admin.strategic-calendar.index')"
                :active="route().current('admin.strategic-calendar.*')"
                label="Calendário"
                variant="minimal"
                :collapsed="collapsed"
            />

            <SidebarNavItem
                v-if="canAdmin('tarefas')"
                :href="route('admin.tarefas.quadros.index')"
                :active="route().current('admin.tarefas.*')"
                label="Tarefas"
                variant="minimal"
                :collapsed="collapsed"
            />

            <SidebarNavItem
                v-if="canAdmin('training')"
                :href="route('admin.training.index')"
                :active="route().current('admin.training.*')"
                label="Capacitação"
                variant="minimal"
                :collapsed="collapsed"
                badge="Em breve"
            />

            <SidebarNavItem
                v-if="showConfiguracao"
                :href="configuracaoHref"
                :active="configuracaoActive"
                label="Configuração"
                variant="minimal"
                :collapsed="collapsed"
            />
        </template>

        <template #user="{ collapsed }">
            <div class="px-0.5">
                <Dropdown align="right" width="48" open-upward>
                    <template #trigger>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-2 py-2 text-left text-sm text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-talents-500/30"
                            :class="collapsed ? 'justify-center' : ''"
                        >
                            <UserCircleIcon class="h-8 w-8 shrink-0 text-talents-600" />
                            <span v-if="!collapsed" class="min-w-0 flex-1 truncate font-medium">
                                {{ $page.props.auth.user.name }}
                            </span>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">Perfil</DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">
                            Sair
                        </DropdownLink>
                    </template>
                </Dropdown>
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
