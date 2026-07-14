export function complaintRoutePrefix() {
    const current = route().current() ?? '';

    return current.startsWith('admin.complaints') ? 'admin.complaints' : 'client.complaints';
}

export function complaintRoute(name, ...params) {
    return route(`${complaintRoutePrefix()}.${name}`, ...params);
}

export function isComplaintAdminContext() {
    return complaintRoutePrefix() === 'admin.complaints';
}
