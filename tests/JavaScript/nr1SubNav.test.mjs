import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
    accentFor,
    campaignSwitchVisit,
    isNr1Path,
    sectionAfterCampaignSwitch,
    subNavBadges,
    subNavItems,
} from '../../resources/js/Components/Nr1Shell/nr1SubNav.js';

const sections = (keys) => keys.map((key) => ({ key, label: key, icon: '', href: `/nr1/${key}` }));
const LEGACY_MENU = sections(['visao-geral', 'resultados', 'plano-de-acao', 'documentos', 'parecer', 'solucoes', 'oportunidades']);
const V11_MENU = sections(['visao-geral', 'resultados', 'plano-de-acao', 'documentos', 'solucoes', 'oportunidades']);

describe('lista da sub-sidebar por tipo de campanha', () => {
    it('shows «Parecer e publicação» right after Documentos only on the previous model', () => {
        const legacy = subNavItems(LEGACY_MENU, 'parecer').map((item) => item.key);
        assert.equal(legacy.indexOf('parecer'), legacy.indexOf('documentos') + 1);
        assert.equal(subNavItems(LEGACY_MENU, 'parecer').find((item) => item.active).key, 'parecer');
        assert.equal(subNavItems(V11_MENU, 'documentos').some((item) => item.key === 'parecer'), false);
    });

    it('never leaves an active item that does not exist after switching to a V11 campaign', () => {
        assert.equal(subNavItems(V11_MENU, 'parecer').some((item) => item.active), false);
    });

    it('gives every section a pastel accent from the panel palette', () => {
        for (const [index, item] of LEGACY_MENU.entries()) {
            assert.ok(['lilac', 'green', 'gold', 'coral'].includes(accentFor(item.key, index)));
        }
        assert.equal(accentFor('politicas', 5), 'green');
    });
});

describe('sectionAfterCampaignSwitch', () => {
    it('keeps the current section when it exists for the new campaign', () => {
        for (const section of ['visao-geral', 'resultados', 'plano-de-acao', 'documentos', 'solucoes']) {
            assert.equal(sectionAfterCampaignSwitch(section, V11_MENU, { id: 2, legacy: true }), section);
            assert.equal(sectionAfterCampaignSwitch(section, LEGACY_MENU, { id: 3, legacy: false }), section);
        }
        assert.equal(sectionAfterCampaignSwitch('parecer', LEGACY_MENU, { id: 4, legacy: true }), 'parecer');
    });

    it('falls back to Visão geral (never Campanha NR-1) when the section does not exist for the new campaign', () => {
        assert.equal(sectionAfterCampaignSwitch('parecer', LEGACY_MENU, { id: 5, legacy: false }), 'visao-geral');
        assert.equal(sectionAfterCampaignSwitch('inexistente', V11_MENU, { id: 5 }), 'visao-geral');
        assert.equal(sectionAfterCampaignSwitch('parecer', sections(['campanha', 'solucoes']), { id: 5 }), 'solucoes');
        assert.equal(sectionAfterCampaignSwitch('campanha', V11_MENU, { id: 5 }), 'visao-geral');
    });

    it('builds the visit with the menu href and the company and campaign query', () => {
        const visit = campaignSwitchVisit({ current: 'resultados', menu: V11_MENU }, { company: { id: 9 } }, { id: 7, legacy: true });
        assert.deepEqual(visit, { section: 'resultados', href: '/nr1/resultados', query: { campanha: 7, empresa: 9 } });
        const company = campaignSwitchVisit({ current: 'parecer', menu: LEGACY_MENU }, { company: null }, { id: 6, legacy: false });
        assert.deepEqual(company, { section: 'visao-geral', href: '/nr1/visao-geral', query: { campanha: 6 } });
    });
});

describe('subNavBadges', () => {
    it('only uses the status already in the header context', () => {
        assert.deepEqual(subNavBadges({ survey: { statusBadge: { label: 'Publicado', tone: 'ok' } } }, V11_MENU), { documentos: { label: 'Publicado', tone: 'ok' } });
        assert.deepEqual(subNavBadges({ survey: { statusBadge: { label: 'Em revisão', tone: 'warn' } } }, LEGACY_MENU), { parecer: { label: 'Em revisão', tone: 'warn' } });
        assert.deepEqual(subNavBadges({ survey: { statusBadge: { label: 'Encerrada', tone: 'gray' } } }, LEGACY_MENU), {});
        assert.deepEqual(subNavBadges({ survey: null }, V11_MENU), {});
        assert.deepEqual(subNavBadges({ collaborator: true, survey: { statusBadge: { label: 'Ativa', tone: 'ok' } } }, V11_MENU), {});
    });
});

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const menu = [
    { key: 'visao-geral', label: 'Visão geral', icon: '◫', href: '/nr1/visao-geral' },
    { key: 'campanha', label: 'Campanha NR-1', icon: '▶', href: '/nr1/campanha' },
    { key: 'parecer', label: 'Parecer e publicação', icon: '✎', href: '/nr1/parecer' },
];

describe('subNavItems', () => {
    it('keeps the server order and marks only the current item as active', () => {
        const items = subNavItems(menu, 'campanha');
        assert.deepEqual(items.map((item) => item.key), ['visao-geral', 'campanha', 'parecer']);
        assert.deepEqual(items.map((item) => item.active), [false, true, false]);
    });

    it('accepts a future section without layout changes, keeping the server glyph as fallback icon', () => {
        const items = subNavItems([...menu, { key: 'politicas', label: 'Políticas NR-1', icon: '§', href: '/nr1/politicas' }], 'politicas');
        assert.equal(items.at(-1).label, 'Políticas NR-1');
        assert.equal(items.at(-1).glyph, '§');
        assert.equal(items.at(-1).active, true);
    });

    it('marks nothing active when the current section is not in the menu and ignores invalid entries', () => {
        assert.ok(subNavItems(menu, 'oportunidades').every((item) => !item.active));
        assert.deepEqual(subNavItems([null, { key: 'x' }, menu[0]], 'visao-geral').map((item) => item.key), ['visao-geral']);
        assert.deepEqual(subNavItems(undefined, 'x'), []);
    });
});

describe('isNr1Path', () => {
    it('matches any /nr1 route, with or without query, and nothing else', () => {
        for (const url of ['/nr1', '/nr1/visao-geral', '/nr1/resultados?empresa=2&campanha=3', '/nr1/empresas', '/nr1/oportunidades#x']) {
            assert.equal(isNr1Path(url), true, url);
        }
        for (const url of ['/nr1x', '/admin/nr1', '/client/surveys', '', null, undefined]) {
            assert.equal(isNr1Path(url), false, String(url));
        }
    });
});

describe('moldura do painel NR-1', () => {
    it('no longer ships the old shell layout, brand, dark sidebar or footer', () => {
        assert.throws(() => read('resources/js/Layouts/Nr1Layout.vue'));
        const sources = [
            'resources/js/Components/Nr1Shell/Nr1Frame.vue',
            'resources/js/Components/Nr1Shell/Nr1SubNav.vue',
            'resources/js/Components/Nr1Shell/ShellHeader.vue',
            'resources/js/Pages/Nr1/Section.vue',
            'resources/css/nr1-shell.css',
        ].map(read).join('\n');
        for (const needle of ['n1-sidebar', 'n1-logo', 'Gestão de Pessoas', '--n1-sidebar', 'n1-menu-toggle']) {
            assert.equal(sources.includes(needle), false, needle);
        }
    });

    it('uses one labelled navigation landmark with aria-current, separate from the main menu', () => {
        const nav = read('resources/js/Components/Nr1Shell/Nr1SubNav.vue');
        assert.match(nav, /aria-label="Navegação do NR-1"/);
        assert.match(nav, /:aria-current="item\.active \? 'page' : undefined"/);
        assert.match(read('resources/js/Components/SidebarLayout.vue'), /aria-label="Menu principal"/);
    });
});
