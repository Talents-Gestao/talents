import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    DEFAULT_BRAND_HEX,
    brandThemeStyle,
    buildBrandTheme,
    contrastRatio,
    parseHex,
    relativeLuminance,
} from '../../resources/js/utils/brandTheme.js';

const WHITE = [255, 255, 255];

const toRgb = (channelString) => channelString.split(' ').map(Number);

describe('parseHex', () => {
    it('aceita #rrggbb, rrggbb e #rgb', () => {
        assert.deepEqual(parseHex('#632a7e'), [99, 42, 126]);
        assert.deepEqual(parseHex('632A7E'), [99, 42, 126]);
        assert.deepEqual(parseHex('#fff'), [255, 255, 255]);
    });

    it('rejeita valores inválidos', () => {
        for (const bad of ['', '#12', '#ggg', 'purple', null, undefined, 42]) {
            assert.equal(parseHex(bad), null);
        }
    });
});

describe('contrastRatio', () => {
    it('bate com valores de referência WCAG', () => {
        assert.equal(Math.round(contrastRatio([0, 0, 0], WHITE)), 21);
        assert.equal(contrastRatio(WHITE, WHITE), 1);
        // roxo Talents (talents-600) sobre branco: 9,71 no diagnóstico
        assert.ok(Math.abs(contrastRatio(parseHex('#632a7e'), WHITE) - 9.71) < 0.05);
        assert.ok(relativeLuminance(WHITE) > relativeLuminance([0, 0, 0]));
    });
});

describe('buildBrandTheme', () => {
    it('retorna null para cor inválida ou vazia (mantém o tema padrão)', () => {
        assert.equal(buildBrandTheme(''), null);
        assert.equal(buildBrandTheme('abc123x'), null);
        assert.equal(brandThemeStyle(null), undefined);
    });

    it('mantém a cor da marca quando já tem contraste suficiente', () => {
        const theme = buildBrandTheme(DEFAULT_BRAND_HEX);

        assert.equal(theme.adjusted, false);
        assert.deepEqual(theme.primary, [99, 42, 126]);
        assert.deepEqual(theme.onPrimary, WHITE);
    });

    it('garante AA em cores claras, médias e saturadas escolhidas pela empresa', () => {
        const samples = [
            '#ffffff', '#f5e6c8', '#e8b84a', '#ffff00', '#7fdbff', '#b388d9', '#ff851b',
            '#2ecc40', '#0074d9', '#ff4136', '#808080', '#777777', '#000000', '#111111',
        ];

        for (const hex of samples) {
            const theme = buildBrandTheme(hex);
            const primary = toRgb(theme.vars['--primary']);
            const onPrimary = toRgb(theme.vars['--on-primary']);
            const text = toRgb(theme.vars['--primary-text']);
            const ring = toRgb(theme.vars['--primary-ring']);
            const hover = toRgb(theme.vars['--primary-hover']);

            assert.ok(contrastRatio(primary, onPrimary) >= 4.5, `${hex}: texto sobre a primária`);
            assert.ok(contrastRatio(hover, onPrimary) >= 4.5, `${hex}: texto sobre o hover`);
            assert.ok(contrastRatio(text, WHITE) >= 4.5, `${hex}: link sobre branco`);
            assert.ok(contrastRatio(ring, WHITE) >= 3, `${hex}: anel de foco`);
        }
    });

    it('usa texto escuro sobre primária clara e marca como ajustada só se mudou', () => {
        const yellow = buildBrandTheme('#ffdc00');

        assert.deepEqual(yellow.onPrimary, [15, 23, 42]);
        assert.equal(yellow.adjusted, false);

        const mid = buildBrandTheme('#777777');

        assert.equal(mid.adjusted, true);
    });

    it('emite todas as variáveis esperadas como canais "R G B"', () => {
        const vars = brandThemeStyle('#0074d9');

        assert.deepEqual(Object.keys(vars).sort(), [
            '--on-primary',
            '--primary',
            '--primary-border',
            '--primary-hover',
            '--primary-ring',
            '--primary-soft',
            '--primary-text',
        ]);

        for (const value of Object.values(vars)) {
            assert.match(value, /^\d{1,3} \d{1,3} \d{1,3}$/);
        }
    });
});
