<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SidebarLayout from '@/Components/SidebarLayout.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import { Link } from '@inertiajs/vue3';
import {
    AcademicCapIcon,
    BeakerIcon,
    BuildingOffice2Icon,
    CalendarDaysIcon,
    DocumentDuplicateIcon,
    DocumentTextIcon,
    Cog6ToothIcon,
    CreditCardIcon,
    EnvelopeOpenIcon,
    HomeIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline';
</script>

<template>
    <SidebarLayout top-bar-title="Administração">
        <template #logo="{ collapsed }">
            <Link
                :href="route('admin.dashboard')"
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
                :href="route('admin.dashboard')"
                :active="route().current('admin.dashboard')"
                :icon="HomeIcon"
                label="Painel"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('admin.landing-interest.index')"
                :active="route().current('admin.landing-interest.*')"
                :icon="EnvelopeOpenIcon"
                label="Interessados"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('admin.companies.index')"
                :active="route().current('admin.companies.*')"
                :icon="BuildingOffice2Icon"
                label="Empresas"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('admin.plans.index')"
                :active="route().current('admin.plans.*')"
                :icon="CreditCardIcon"
                label="Planos"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('admin.survey-templates.index')"
                :active="route().current('admin.survey-templates.*')"
                :icon="DocumentDuplicateIcon"
                label="Templates NR1"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('admin.metodologia.index')"
                :active="
                    route().current('admin.metodologia.*') ||
                    route().current('admin.methodology-templates.*')
                "
                :icon="BeakerIcon"
                label="Metodologia"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('admin.strategic-calendar.index')"
                :active="route().current('admin.strategic-calendar.*')"
                :icon="CalendarDaysIcon"
                label="Calendário estratégico"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('admin.solides.curriculos.index')"
                :active="route().current('admin.solides.*')"
                :icon="DocumentTextIcon"
                label="Sólides — Currículos"
                :collapsed="collapsed"
            />
            <SidebarNavItem
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
