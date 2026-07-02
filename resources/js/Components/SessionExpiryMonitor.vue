<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useSessionExpiry } from '@/composables/useSessionExpiry';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const isAuthenticated = computed(() => Boolean(page.props?.auth?.user));

const { warningVisible, minutesRemaining, dismissWarning } =
    useSessionExpiry();
</script>

<template>
    <Modal
        v-if="isAuthenticated"
        :show="warningVisible"
        max-width="md"
        @close="dismissWarning"
    >
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900">
                Sua sessão vai expirar
            </h2>
            <p class="mt-3 text-sm text-gray-600">
                Por inatividade, sua sessão expirará em aproximadamente
                {{ minutesRemaining }}
                {{ minutesRemaining === 1 ? 'minuto' : 'minutos' }}.
                Salve seu trabalho para não perder alterações pendentes.
            </p>
            <div class="mt-6 flex justify-end">
                <PrimaryButton type="button" @click="dismissWarning">
                    Entendi
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
