<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SidebarLayout from '@/Components/SidebarLayout.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import { Link } from '@inertiajs/vue3';
import {
    AcademicCapIcon,
    BriefcaseIcon,
    BuildingOfficeIcon,
    CalendarDaysIcon,
    ClipboardDocumentListIcon,
    FingerPrintIcon,
    HomeIcon,
    RocketLaunchIcon,
    ShieldExclamationIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline';
</script>

<template>
    <SidebarLayout shell-class="min-h-screen bg-zinc-50">
        <template #logo="{ collapsed }">
            <Link
                :href="route('client.dashboard')"
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
                :href="route('client.dashboard')"
                :active="route().current('client.dashboard')"
                :icon="HomeIcon"
                label="Painel"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('client.surveys.index')"
                :active="route().current('client.surveys.*')"
                :icon="ClipboardDocumentListIcon"
                label="Pesquisas NR1"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="$page.props.auth.user?.company?.has_methodology"
                :href="route('client.metodologia.index')"
                :active="route().current('client.metodologia.*')"
                :icon="RocketLaunchIcon"
                label="Metodologia Talents"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="$page.props.auth.user?.company?.has_strategic_calendar"
                :href="route('client.strategic-calendar.index')"
                :active="route().current('client.strategic-calendar.*')"
                :icon="CalendarDaysIcon"
                label="Calendário estratégico"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('client.departments.index')"
                :active="route().current('client.departments.*')"
                :icon="BuildingOfficeIcon"
                label="Setores"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('client.positions.index')"
                :active="route().current('client.positions.*')"
                :icon="BriefcaseIcon"
                label="Cargos"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('client.complaints.index')"
                :active="route().current('client.complaints.*')"
                :icon="ShieldExclamationIcon"
                label="Denúncias"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('client.training.index')"
                :active="route().current('client.training.*')"
                :icon="AcademicCapIcon"
                label="Capacitação"
                :collapsed="collapsed"
                badge="Em breve"
            />
            <SidebarNavItem
                v-if="$page.props.auth.user?.role === 'company_admin'"
                :href="route('client.rhid.compliance.index')"
                :active="route().current('client.rhid.*')"
                :icon="FingerPrintIcon"
                label="RHID / Ponto"
                :collapsed="collapsed"
            />
        </template>

        <template #user="{ collapsed }">
            <div class="px-0.5">
                <Dropdown align="right" width="48" open-upward>
                    <template #trigger>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm text-zinc-800 transition hover:bg-zinc-100/90 focus:outline-none focus:ring-2 focus:ring-talents-500/30"
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

        <slot />
    </SidebarLayout>
</template>
