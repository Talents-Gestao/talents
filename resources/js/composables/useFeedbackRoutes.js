export function feedbackRoutePrefix() {
    const current = route().current() ?? '';

    return current.startsWith('admin.feedbacks') ? 'admin.feedbacks' : 'client.feedbacks';
}

export function feedbackRoute(name, ...params) {
    return route(`${feedbackRoutePrefix()}.${name}`, ...params);
}

export function isFeedbackAdminContext() {
    return feedbackRoutePrefix() === 'admin.feedbacks';
}
