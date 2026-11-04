import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    FILTERS,
    campaignsLabel,
    countLabel,
    filterCompanies,
    highlightParts,
    initials,
    monogramColors,
    normalize,
    splitRecent,
} from '../../resources/js/Components/Nr1Shell/nr1CompanyPicker.js';

const company = (id, name, flags = {}) => ({ id, name, flags: { active: false, review: false, published: false, legacy: false, v11: false, ...flags } });
const list = [
    company(1, 'Zeta Logística', { v11: true, published: true }),
    company(2, 'Água Viva Indústria', { legacy: true, active: true }),
    company(3, 'Beta Serviços', { v11: true, review: true }),
    company(4, 'Ômega Comércio', { legacy: true }),
];

describe('filterCompanies', () => {
    it('searches the name without accents or case', () => {
        assert.deepEqual(filterCompanies(list, { term: 'agua' }).map((c) => c.id), [2]);
        assert.deepEqual(filterCompanies(list, { term: 'OMEGA' }).map((c) => c.id), [4]);
        assert.equal(filterCompanies(list, { term: '  ' }).length, 4);
    });

    it('applies the quick filters by campaign flags', () => {
        const by = (key) => filterCompanies(list, { filter: key }).map((c) => c.id);
        assert.deepEqual(FILTERS.map((f) => f.label), ['Todas', 'Com campanha ativa', 'Em revisão', 'Publicadas', 'Modelo anterior', 'V11']);
        assert.deepEqual(by('todas'), [1, 2, 3, 4]);
        assert.deepEqual(by('ativa'), [2]);
        assert.deepEqual(by('revisao'), [3]);
        assert.deepEqual(by('publicadas'), [1]);
        assert.deepEqual(by('legado'), [2, 4]);
        assert.deepEqual(by('v11'), [1, 3]);
        assert.deepEqual(filterCompanies(list, { term: 'beta', filter: 'legado' }), []);
    });
});

describe('splitRecent', () => {
    it('puts the recently opened companies first, in opening order, then the rest alphabetically', () => {
        const { recent, rest } = splitRecent(list, [3, 1, 99]);
        assert.deepEqual(recent.map((c) => c.id), [3, 1]);
        assert.deepEqual(rest.map((c) => c.name), ['Água Viva Indústria', 'Ômega Comércio']);
        assert.deepEqual(splitRecent(list, []).rest.map((c) => c.id), [2, 3, 4, 1]);
    });
});

describe('highlightParts', () => {
    it('marks the matched stretch keeping the original accents', () => {
        assert.deepEqual(highlightParts('Água Viva Indústria', 'agua'), [
            { text: 'Água', match: true },
            { text: ' Viva Indústria', match: false },
        ]);
        assert.deepEqual(highlightParts('Indústria Industrial', 'indus'), [
            { text: 'Indús', match: true },
            { text: 'tria ', match: false },
            { text: 'Indus', match: true },
            { text: 'trial', match: false },
        ]);
        assert.deepEqual(highlightParts('Beta', ''), [{ text: 'Beta', match: false }]);
        assert.equal(highlightParts('Beta', 'xyz').every((part) => !part.match), true);
    });
});

describe('labels and monogram', () => {
    it('pluralizes counters in Portuguese', () => {
        assert.equal(countLabel(1), '1 empresa');
        assert.equal(countLabel(0), '0 empresas');
        assert.equal(campaignsLabel(1), '1 campanha NR-1');
        assert.equal(campaignsLabel(3), '3 campanhas NR-1');
        assert.equal(normalize(' Ação '), 'acao');
    });

    it('uses the company brand color with readable text, or the default purple', () => {
        assert.equal(initials('Companhia do Livro (demo NR-1)'), 'CD');
        assert.equal(initials('T'), 'T');
        assert.deepEqual(monogramColors(null), { background: 'rgb(99 42 126)', color: '#ffffff' });
        assert.equal(monogramColors('#ffe066').color, '#0f172a');
        assert.equal(monogramColors('#1e6b5c').color, '#ffffff');
    });
});
