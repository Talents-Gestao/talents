import { usePage } from '@inertiajs/vue3';

/**
 * Permissões partilhadas pelo HandleInertiaRequests (auth.permissions).
 * SuperAdmin: { '*': true }. CompanyAdmin / CompanyUser: { modulo: ['view', ...] }.
 */
export function usePermissions() {
    const page = usePage();

    /**
     * @param {string} module
     * @param {string} action
     */
    const can = (module, action) => {
        const p = page.props.auth?.user?.permissions;
        if (!p) {
            return false;
        }
        if (p['*'] === true) {
            return true;
        }
        const actions = p[module];
        if (!Array.isArray(actions)) {
            return false;
        }
        return actions.includes(action);
    };

    return { can };
}
