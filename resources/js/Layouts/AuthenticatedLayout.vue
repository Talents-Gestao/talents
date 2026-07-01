<script setup>
import SidebarBrandMark from '@/Components/SidebarBrandMark.vue';
import SidebarLayout from '@/Components/SidebarLayout.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import SidebarUserCard from '@/Components/SidebarUserCard.vue';
import {
    ArrowRightOnRectangleIcon,
    HomeIcon,
    IdentificationIcon,
} from '@heroicons/vue/24/outline';
</script>

<template>
    <SidebarLayout top-bar-title="Painel">
        <template #logo="{ collapsed }">
            <SidebarBrandMark
                :href="route('dashboard')"
                :collapsed="collapsed"
                isolated-icon
                icon-src="/images/logo-icon.png"
            />
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
