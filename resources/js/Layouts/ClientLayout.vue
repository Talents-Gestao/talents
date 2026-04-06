<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <nav class="border-b border-talents-200 bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex items-center gap-8">
                        <Link :href="route('client.dashboard')" class="flex shrink-0 items-center">
                            <ApplicationLogo class="h-10" />
                        </Link>
                        <div class="hidden space-x-6 sm:flex">
                            <NavLink
                                :href="route('client.dashboard')"
                                :active="route().current('client.dashboard')"
                            >
                                Painel
                            </NavLink>
                            <NavLink
                                :href="route('client.surveys.index')"
                                :active="route().current('client.surveys.*')"
                            >
                                Pesquisas NR1
                            </NavLink>
                            <NavLink
                                :href="route('client.departments.index')"
                                :active="route().current('client.departments.*')"
                            >
                                Setores
                            </NavLink>
                            <NavLink
                                :href="route('client.positions.index')"
                                :active="route().current('client.positions.*')"
                            >
                                Cargos
                            </NavLink>
                            <NavLink
                                :href="route('client.complaints.index')"
                                :active="route().current('client.complaints.*')"
                            >
                                Denúncias
                            </NavLink>
                            <NavLink :href="route('client.training.index')" :active="route().current('client.training.*')">
                                Capacitação
                                <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-900">Em breve</span>
                            </NavLink>
                            <NavLink
                                v-if="$page.props.auth.user?.role === 'company_admin'"
                                :href="route('client.rhid.compliance.index')"
                                :active="route().current('client.rhid.*')"
                            >
                                RHID / Ponto
                            </NavLink>
                        </div>
                    </div>
                    <div class="hidden sm:flex sm:items-center">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-talents-900"
                                >
                                    {{ $page.props.auth.user.name }}
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
                </div>
            </div>
            <div v-if="showingNavigationDropdown" class="sm:hidden border-t px-4 pb-4">
                <ResponsiveNavLink :href="route('client.dashboard')">Painel</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('client.surveys.index')">Pesquisas</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('client.departments.index')">Setores</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('client.positions.index')">Cargos</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('client.complaints.index')">Denúncias</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('client.training.index')">Capacitação (em breve)</ResponsiveNavLink>
                <ResponsiveNavLink
                    v-if="$page.props.auth.user?.role === 'company_admin'"
                    :href="route('client.rhid.compliance.index')"
                >
                    RHID / Ponto
                </ResponsiveNavLink>
            </div>
        </nav>

        <header v-if="$slots.header" class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <slot />
            </div>
        </main>
    </div>
</template>
