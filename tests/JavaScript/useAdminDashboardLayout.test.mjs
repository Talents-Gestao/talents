import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    ADMIN_DASHBOARD_DEFAULT_SECTION_ORDER,
    ADMIN_DASHBOARD_DEFAULT_SECTIONS,
    adminDashboardLayoutStorageKey,
    mergeAdminDashboardLayout,
} from '../../resources/js/composables/useAdminDashboardLayout.js';

describe('mergeAdminDashboardLayout (v2/v3)', () => {
    it('devolve defaults quando stored é null', () => {
        const layout = mergeAdminDashboardLayout(null);

        assert.equal(layout.version, 3);
        assert.deepEqual(layout.sectionOrder, [...ADMIN_DASHBOARD_DEFAULT_SECTION_ORDER]);
        assert.deepEqual(layout.sections.operation, [...ADMIN_DASHBOARD_DEFAULT_SECTIONS.operation]);
        assert.deepEqual(layout.sections.kpis, [...ADMIN_DASHBOARD_DEFAULT_SECTIONS.kpis]);
        assert.deepEqual(layout.sections.insights, [...ADMIN_DASHBOARD_DEFAULT_SECTIONS.insights]);
    });

    it('mantém ordem de secções e widgets; acrescenta ids em falta (v3)', () => {
        const layout = mergeAdminDashboardLayout({
            version: 3,
            sectionOrder: ['insights', 'operation'],
            sections: {
                operation: ['calendar_today', 'finance'],
                kpis: ['mrr', 'active_clients'],
                insights: ['funnel'],
            },
        });

        assert.deepEqual(layout.sectionOrder, ['insights', 'operation', 'kpis']);
        assert.deepEqual(layout.sections.operation, ['calendar_today', 'finance', 'tasks_today']);
        assert.equal(layout.sections.kpis[0], 'mrr');
        assert.equal(layout.sections.kpis[1], 'active_clients');
        assert.ok(layout.sections.kpis.includes('methodology'));
        assert.deepEqual(layout.sections.insights, ['funnel', 'leads_source', 'monthly_goal']);
    });

    it('migra v2: nova ordem de secções, mantém ordem dos widgets', () => {
        const layout = mergeAdminDashboardLayout({
            version: 2,
            sectionOrder: ['operation', 'kpis', 'insights'],
            sections: {
                operation: ['calendar_today', 'finance'],
                kpis: ['mrr', 'active_clients'],
                insights: ['funnel'],
            },
        });

        assert.equal(layout.version, 3);
        assert.deepEqual(layout.sectionOrder, [...ADMIN_DASHBOARD_DEFAULT_SECTION_ORDER]);
        assert.deepEqual(layout.sections.operation, ['calendar_today', 'finance', 'tasks_today']);
        assert.deepEqual(layout.sections.insights, ['funnel', 'leads_source', 'monthly_goal']);
    });

    it('migra payload flat v1 (indicators + kpi_*) para v3', () => {
        const layout = mergeAdminDashboardLayout({
            operation: ['tasks_today', 'finance'],
            indicators: ['kpi_mrr', 'kpi_clients'],
            insights: ['monthly_goal', 'funnel'],
        });

        assert.equal(layout.version, 3);
        assert.deepEqual(layout.sectionOrder, [...ADMIN_DASHBOARD_DEFAULT_SECTION_ORDER]);
        assert.deepEqual(layout.sections.operation, ['tasks_today', 'finance', 'calendar_today']);
        assert.equal(layout.sections.kpis[0], 'mrr');
        assert.equal(layout.sections.kpis[1], 'active_clients');
        assert.deepEqual(layout.sections.insights, ['monthly_goal', 'funnel', 'leads_source']);
    });

    it('ignora ids desconhecidos e valores inválidos', () => {
        const layout = mergeAdminDashboardLayout({
            version: 3,
            sectionOrder: ['kpis', 'ghost', 'operation'],
            sections: {
                operation: ['finance', 'unknown_widget', 42, null],
                kpis: 'não-array',
                insights: ['leads_source', 'ghost'],
            },
        });

        assert.deepEqual(layout.sectionOrder, ['kpis', 'operation', 'insights']);
        assert.deepEqual(layout.sections.operation, ['finance', 'tasks_today', 'calendar_today']);
        assert.deepEqual(layout.sections.kpis, [...ADMIN_DASHBOARD_DEFAULT_SECTIONS.kpis]);
        assert.deepEqual(layout.sections.insights, ['leads_source', 'funnel', 'monthly_goal']);
    });

    it('mantém widget movido para outra secção e não o duplica na origem', () => {
        const layout = mergeAdminDashboardLayout({
            version: 3,
            sectionOrder: ['insights', 'operation', 'kpis'],
            sections: {
                operation: ['tasks_today', 'calendar_today'],
                kpis: ['mrr', 'active_clients'],
                insights: ['finance', 'funnel', 'leads_source', 'monthly_goal'],
            },
        });

        assert.ok(layout.sections.insights.includes('finance'));
        assert.ok(!layout.sections.operation.includes('finance'));
        assert.deepEqual(layout.sections.operation, ['tasks_today', 'calendar_today']);
        assert.ok(layout.sections.kpis.includes('methodology'));
        const all = [
            ...layout.sections.operation,
            ...layout.sections.kpis,
            ...layout.sections.insights,
        ];
        assert.equal(all.filter((id) => id === 'finance').length, 1);
    });
});

describe('adminDashboardLayoutStorageKey', () => {
    it('usa guest e versão 3 por omissão', () => {
        assert.equal(adminDashboardLayoutStorageKey(null), 'talents.adminDashboard.layout.v3.guest');
        assert.equal(adminDashboardLayoutStorageKey(undefined), 'talents.adminDashboard.layout.v3.guest');
    });

    it('inclui o userId e permite chave legada v1/v2', () => {
        assert.equal(adminDashboardLayoutStorageKey(7), 'talents.adminDashboard.layout.v3.7');
        assert.equal(adminDashboardLayoutStorageKey(7, 2), 'talents.adminDashboard.layout.v2.7');
        assert.equal(adminDashboardLayoutStorageKey(7, 1), 'talents.adminDashboard.layout.v1.7');
    });
});
