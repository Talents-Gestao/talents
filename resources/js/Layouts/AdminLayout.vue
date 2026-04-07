<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);

const navLinkClass = (active) =>
    active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-talents-600 text-sm font-medium leading-5 text-talents-900 focus:outline-none transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-600 hover:text-talents-900 hover:border-gray-300 focus:outline-none transition duration-150 ease-in-out';
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="border-b border-gray-200 bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex items-center gap-8">
                        <Link :href="route('admin.dashboard')" class="flex shrink-0 items-center">
                            <ApplicationLogo class="h-10" />
                        </Link>
                        <div class="hidden space-x-6 sm:flex">
                            <Link :href="route('admin.dashboard')" :class="navLinkClass(route().current('admin.dashboard'))">
                                Painel
                            </Link>
                            <Link :href="route('admin.companies.index')" :class="navLinkClass(route().current('admin.companies.*'))">
                                Empresas
                            </Link>
                            <Link :href="route('admin.plans.index')" :class="navLinkClass(route().current('admin.plans.*'))">
                                Planos
                            </Link>
                            <Link
                                :href="route('admin.survey-templates.index')"
                                :class="navLinkClass(route().current('admin.survey-templates.*'))"
                            >
                                Templates NR1
                            </Link>
                            <Link
                                :href="route('admin.metodologia.index')"
                                :class="navLinkClass(route().current('admin.metodologia.*') || route().current('admin.methodology-templates.*'))"
                            >
                                Metodologia
                            </Link>
                            <Link
                                :href="route('admin.settings.edit')"
                                :class="navLinkClass(route().current('admin.settings.edit') || route().current('admin.ai-settings.edit'))"
                            >
                                Configurações
                            </Link>
                            <Link
                                :href="route('admin.training.index')"
                                :class="navLinkClass(route().current('admin.training.*'))"
                            >
                                Capacitação
                                <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-900">Em breve</span>
                            </Link>
                        </div>
                    </div>
                    <div class="hidden sm:flex sm:items-center">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-talents-900 shadow-sm"
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
                    <div class="-me-2 flex items-center sm:hidden">
                        <button
                            type="button"
                            class="text-gray-700"
                            @click="showingNavigationDropdown = !showingNavigationDropdown"
                        >
                            Menu
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="showingNavigationDropdown" class="sm:hidden border-t border-gray-200 px-4 pb-4">
                <ResponsiveNavLink :href="route('admin.dashboard')">Painel</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('admin.companies.index')">Empresas</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('admin.plans.index')">Planos</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('admin.survey-templates.index')">Templates</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('admin.metodologia.index')">Metodologia</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('admin.settings.edit')">Configurações</ResponsiveNavLink>
                <ResponsiveNavLink :href="route('admin.training.index')">Capacitação (em breve)</ResponsiveNavLink>
            </div>
        </nav>

        <header v-if="$slots.header" class="border-b border-gray-200 bg-white shadow-sm">
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
