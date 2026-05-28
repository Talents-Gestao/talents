<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SidebarLayout from '@/Components/SidebarLayout.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    AcademicCapIcon,
    BanknotesIcon,
    BeakerIcon,
    BuildingOffice2Icon,
    IdentificationIcon,
    CalendarDaysIcon,
    ViewColumnsIcon,
    DocumentDuplicateIcon,
    DocumentTextIcon,
    Cog6ToothIcon,
    CreditCardIcon,
    EnvelopeOpenIcon,
    HomeIcon,
    ClockIcon,
    UserCircleIcon,
    UsersIcon,
    MicrophoneIcon,
} from '@heroicons/vue/24/outline';

const { canAdmin } = useAdminPermissions();
const page = usePage();
const adminHomeUrl = computed(
    () => page.props.auth?.user?.admin_home_url ?? route('admin.dashboard'),
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
                :icon="HomeIcon"
                label="Painel"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('landing_interest')"
                :href="route('admin.landing-interest.index')"
                :active="route().current('admin.landing-interest.*')"
                :icon="EnvelopeOpenIcon"
                label="Interessados"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('companies')"
                :href="route('admin.companies.index')"
                :active="route().current('admin.companies.*')"
                :icon="BuildingOffice2Icon"
                label="Empresas"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('rhid')"
                :href="route('admin.rhid.index')"
                :active="route().current('admin.rhid.*')"
                :icon="ClockIcon"
                label="RHID"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('plans')"
                :href="route('admin.plans.index')"
                :active="route().current('admin.plans.*')"
                :icon="CreditCardIcon"
                label="Planos"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('survey_templates')"
                :href="route('admin.survey-templates.index')"
                :active="route().current('admin.survey-templates.*')"
                :icon="DocumentDuplicateIcon"
                label="Mapeamentos"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('methodology')"
                :href="route('admin.metodologia.index')"
                :active="
                    route().current('admin.metodologia.*') ||
                    route().current('admin.methodology-templates.*')
                "
                :icon="BeakerIcon"
                label="Direcionamento Estratégico"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('strategic_calendar')"
                :href="route('admin.strategic-calendar.index')"
                :active="route().current('admin.strategic-calendar.*')"
                :icon="CalendarDaysIcon"
                label="Calendário estratégico"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('tarefas')"
                :href="route('admin.tarefas.quadros.index')"
                :active="route().current('admin.tarefas.*')"
                :icon="ViewColumnsIcon"
                label="Tarefas"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('comercial')"
                :href="route('admin.comercial.dashboard')"
                :active="
                    route().current('admin.comercial.*') &&
                    !route().current('admin.comercial.settings.*')
                "
                :icon="BanknotesIcon"
                label="Comercial"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="$page.props.auth?.user?.can_commercial_settings"
                :href="route('admin.comercial.settings.edit')"
                :active="route().current('admin.comercial.settings.*')"
                :icon="DocumentTextIcon"
                label="Valores e contratos"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('empresa_talents')"
                :href="route('admin.empresa-talents.edit')"
                :active="route().current('admin.empresa-talents.*')"
                :icon="IdentificationIcon"
                label="Empresa Talents"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('solides')"
                :href="route('admin.solides.curriculos.index')"
                :active="route().current('admin.solides.*')"
                :icon="DocumentTextIcon"
                label="Sólides — Currículos"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('entrevistas')"
                :href="route('admin.entrevistas.index')"
                :active="route().current('admin.entrevistas.*')"
                :icon="MicrophoneIcon"
                label="Entrevistas (IA)"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('equipe')"
                :href="route('admin.users.index')"
                :active="route().current('admin.users.*')"
                :icon="UsersIcon"
                label="Equipe"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('settings')"
                :href="route('admin.settings.edit')"
                :active="
                    route().current('admin.settings.edit') ||
                    route().current('admin.ai-settings.edit')
                "
                :icon="Cog6ToothIcon"
                label="Configurações"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="canAdmin('training')"
                :href="route('admin.training.index')"
                :active="route().current('admin.training.*')"
                :icon="AcademicCapIcon"
                label="Capacitação"
                :collapsed="collapsed"
                badge="Em breve"
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
