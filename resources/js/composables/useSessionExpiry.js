import { redirectToLoginExpired } from '@/utils/sessionExpiry';
import { router, usePage } from '@inertiajs/vue3';
import { nextTick, onUnmounted, ref, watch } from 'vue';

/**
 * Agenda aviso e expiração com base em `page.props.sessionExpiry` (atualizado a cada visita Inertia).
 *
 * @param {(minutesRemaining: number) => void} onWarning
 */
export function useSessionExpiry(onWarning) {
    const page = usePage();
    const warningVisible = ref(false);
    const minutesRemaining = ref(5);

    /** @type {ReturnType<typeof setTimeout> | null} */
    let warningTimer = null;
    /** @type {ReturnType<typeof setTimeout> | null} */
    let expiryTimer = null;
    /** @type {number | null} */
    let scheduledForExpiresAt = null;

    function clearTimers() {
        if (warningTimer !== null) {
            clearTimeout(warningTimer);
            warningTimer = null;
        }
        if (expiryTimer !== null) {
            clearTimeout(expiryTimer);
            expiryTimer = null;
        }
    }

    function schedule(session) {
        if (!session?.expires_at) {
            if (import.meta.env.DEV) {
                console.warn(
                    '[SessionExpiry] sessionExpiry ausente em page.props — aviso de expiração não agendado.',
                );
            }

            return;
        }

        const expiresAt = Number(session.expires_at);

        if (Number.isNaN(expiresAt)) {
            return;
        }

        if (
            scheduledForExpiresAt === expiresAt &&
            warningTimer !== null &&
            expiryTimer !== null
        ) {
            return;
        }

        clearTimers();
        warningVisible.value = false;
        scheduledForExpiresAt = expiresAt;

        const now = Date.now();
        const warningMinutes = session.warning_minutes ?? 5;
        const warningMs = warningMinutes * 60 * 1000;
        const warningAt = expiresAt - warningMs;

        if (import.meta.env.DEV) {
            const warnInSec = Math.max(0, Math.round((warningAt - now) / 1000));
            const expireInSec = Math.max(0, Math.round((expiresAt - now) / 1000));
            console.info(
                `[SessionExpiry] agendado — aviso em ${warnInSec}s, expiração em ${expireInSec}s (lifetime ${session.lifetime_minutes} min, aviso ${warningMinutes} min antes).`,
            );
        }

        if (expiresAt <= now) {
            redirectToLoginExpired();
            return;
        }

        if (warningAt <= now) {
            minutesRemaining.value = Math.max(
                1,
                Math.ceil((expiresAt - now) / 60000),
            );
            warningVisible.value = true;
            onWarning?.(minutesRemaining.value);
        } else {
            warningTimer = setTimeout(() => {
                minutesRemaining.value = Math.max(
                    1,
                    Math.ceil((expiresAt - Date.now()) / 60000),
                );
                warningVisible.value = true;
                onWarning?.(minutesRemaining.value);
            }, warningAt - now);
        }

        expiryTimer = setTimeout(redirectToLoginExpired, expiresAt - now);
    }

    function dismissWarning() {
        warningVisible.value = false;
    }

    watch(
        () => page.props?.sessionExpiry,
        (sessionExpiry) => schedule(sessionExpiry),
        { immediate: true, deep: true },
    );

    const removeFinishListener = router.on('finish', () => {
        schedule(page.props?.sessionExpiry);
    });

    onUnmounted(() => {
        clearTimers();
        removeFinishListener();
        scheduledForExpiresAt = null;
    });

    return {
        warningVisible,
        minutesRemaining,
        dismissWarning,
    };
}
