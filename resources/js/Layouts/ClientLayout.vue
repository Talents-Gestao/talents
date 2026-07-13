<script setup>
import SidebarBrandMark from '@/Components/SidebarBrandMark.vue';
import SidebarLayout from '@/Components/SidebarLayout.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import SidebarNavSection from '@/Components/SidebarNavSection.vue';
import SidebarUserCard from '@/Components/SidebarUserCard.vue';
import { usePermissions } from '@/composables/usePermissions';
import { computed } from 'vue';

const { can } = usePermissions();

const showVozDoTime = computed(
    () => can('pesquisas', 'view') || can('denuncias', 'view') || can('desligamento', 'view'),
);

import {
    AcademicCapIcon,
    ArrowRightOnRectangleIcon,
    BriefcaseIcon,
    BuildingOfficeIcon,
    CalendarDaysIcon,
    ViewColumnsIcon,
    ClipboardDocumentListIcon,
    FingerPrintIcon,
    HomeIcon,
    MegaphoneIcon,
    ChatBubbleLeftRightIcon,
    RocketLaunchIcon,
    ShieldExclamationIcon,
    SunIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
</script>

<template>
    <SidebarLayout top-bar-title="Área do cliente">
        <template #logo="{ collapsed }">
            <SidebarBrandMark
                :href="route('client.dashboard')"
                :collapsed="collapsed"
                isolated-icon
                icon-src="/images/logo-icon.png"
            />
        </template>

        <template #navigation="{ collapsed, compact }">
            <SidebarNavItem
                :href="route('client.dashboard')"
                :active="route().current('client.dashboard')"
                :icon="HomeIcon"
                label="Painel"
                :collapsed="collapsed"
            />
            <SidebarNavSection v-if="showVozDoTime" label="Voz do Time" :collapsed="collapsed">
                <SidebarNavItem
                    :href="route('client.voz-do-time.index')"
                    :active="route().current('client.voz-do-time.*')"
                    :icon="MegaphoneIcon"
                    label="Visão geral"
                    :collapsed="collapsed"
                />
                <SidebarNavItem
                    v-if="can('pesquisas', 'view')"
                    :href="route('client.surveys.index')"
                    :active="route().current('client.surveys.*')"
                    :icon="ClipboardDocumentListIcon"
                    label="Pesquisas NR1"
                    :collapsed="collapsed"
                />
                <SidebarNavItem
                    v-if="can('denuncias', 'view')"
                    :href="route('client.complaints.index')"
                    :active="route().current('client.complaints.*')"
                    :icon="ShieldExclamationIcon"
                    label="Denúncias"
                    :collapsed="collapsed"
                />
                <SidebarNavItem
                    v-if="can('desligamento', 'view')"
                    :href="route('client.desligamento.index')"
                    :active="route().current('client.desligamento.*')"
                    :icon="ClipboardDocumentListIcon"
                    label="Desligamento"
                    :collapsed="collapsed"
                />
            </SidebarNavSection>
            <SidebarNavItem
                v-if="can('metodologia', 'view')"
                :href="route('client.metodologia.index')"
                :active="route().current('client.metodologia.*')"
                :icon="RocketLaunchIcon"
                label="Direcionamento Estratégico"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="can('feedbacks', 'view')"
                :href="route('client.feedbacks.index')"
                :active="route().current('client.feedbacks.*')"
                :icon="ChatBubbleLeftRightIcon"
                label="Feedbacks internos"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="can('ferias', 'view')"
                :href="route('client.ferias.index')"
                :active="route().current('client.ferias.*')"
                :icon="SunIcon"
                label="Férias"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="can('calendario_estrategico', 'view')"
                :href="route('client.strategic-calendar.index')"
                :active="route().current('client.strategic-calendar.*')"
                :icon="CalendarDaysIcon"
                label="Calendário estratégico"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="can('tarefas', 'view')"
                :href="route('client.tarefas.index')"
                :active="route().current('client.tarefas.*')"
                :icon="ViewColumnsIcon"
                label="Tarefas"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="can('departamentos_cargos', 'view')"
                :href="route('client.departments.index')"
                :active="route().current('client.departments.*')"
                :icon="BuildingOfficeIcon"
                label="Setores"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="can('departamentos_cargos', 'view')"
                :href="route('client.positions.index')"
                :active="route().current('client.positions.*')"
                :icon="BriefcaseIcon"
                label="Cargos"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="can('capacitacao', 'view')"
                :href="route('client.training.index')"
                :active="route().current('client.training.*')"
                :icon="AcademicCapIcon"
                label="Capacitação"
                :collapsed="collapsed"
                badge="Em breve"
            />
            <SidebarNavItem
                v-if="can('rhid', 'view')"
                :href="route('client.rhid.compliance.index')"
                :active="route().current('client.rhid.*')"
                :icon="FingerPrintIcon"
                label="RHID / Ponto"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                v-if="$page.props.auth.user?.role === 'company_admin'"
                :href="route('client.usuarios.index')"
                :active="route().current('client.usuarios.*')"
                :icon="UsersIcon"
                label="Utilizadores"
                :collapsed="collapsed"
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
