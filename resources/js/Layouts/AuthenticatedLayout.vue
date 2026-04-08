<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SidebarLayout from '@/Components/SidebarLayout.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import { Link } from '@inertiajs/vue3';
import { HomeIcon, IdentificationIcon, UserCircleIcon } from '@heroicons/vue/24/outline';
</script>

<template>
    <SidebarLayout shell-class="min-h-screen bg-gray-100">
        <template #logo="{ collapsed }">
            <Link
                :href="route('dashboard')"
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
                :href="route('dashboard')"
                :active="route().current('dashboard')"
                :icon="HomeIcon"
                label="Dashboard"
                :collapsed="collapsed"
            />
            <SidebarNavItem
                :href="route('profile.edit')"
                :active="route().current('profile.*')"
                :icon="IdentificationIcon"
                label="Profile"
                :collapsed="collapsed"
            />
        </template>

        <template #user="{ collapsed }">
            <div class="px-0.5">
                <Dropdown align="right" width="48" open-upward>
                    <template #trigger>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-2 py-2 text-left text-sm text-gray-700 shadow-sm transition hover:bg-gray-50"
                            :class="collapsed ? 'justify-center' : ''"
                        >
                            <UserCircleIcon class="h-8 w-8 shrink-0 text-gray-600" />
                            <span v-if="!collapsed" class="min-w-0 flex-1 truncate font-medium">
                                {{ $page.props.auth.user.name }}
                            </span>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">
                            Profile
                        </DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">
                            Log Out
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
