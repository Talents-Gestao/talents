import { usePage } from '@inertiajs/vue3';

export function useAdminPermissions() {
    const page = usePage();

    /**
     * @param {string} module
     * @param {'view'|'create'|'edit'|'delete'} [action='view']
     */
    const canAdmin = (module, action = 'view') => {
        const perms = page.props.auth?.user?.admin_permissions;
        if (!perms) {
            return false;
        }
        if (perms['*'] === true) {
            return true;
        }
        const actions = perms[module];
        return Array.isArray(actions) && actions.includes(action);
    };

    /**
     * @param {string[]} modules
     * @param {'view'|'create'|'edit'|'delete'} [action='view']
     */
    const canAdminAny = (modules, action = 'view') =>
        modules.some((module) => canAdmin(module, action));

    return { canAdmin, canAdminAny };
}
