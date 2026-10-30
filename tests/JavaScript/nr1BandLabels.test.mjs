import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, it } from 'node:test';

// O rótulo de faixa vem pronto do backend (Nr1BandLabels): nenhuma tela do NR-1 pode fixá-lo no front.
const files = [
    ...['Nr1Shell', 'Nr1Legacy'].flatMap((dir) =>
        readdirSync(join('resources/js/Components', dir))
            .filter((name) => /\.(vue|js)$/.test(name))
            .map((name) => join('resources/js/Components', dir, name)),
    ),
    'resources/js/Components/Nr1SurveyResultsPanel.vue',
    'resources/js/Components/Nr1RiskLevelLegend.vue',
    'resources/js/Components/Dashboard/HealthBadge.vue',
];

describe('rótulos de faixa no front', () => {
    it('não há rótulo fixo de nenhum método', () => {
        const fixed = /Menor sinalização|Maior sinalização|BAND_BY_RISK|['"`>·] ?(Leve|Moderado|Grave|Elevado)\b/;
        const offenders = files.filter((file) => fixed.test(readFileSync(file, 'utf8')));

        assert.deepEqual(offenders, []);
    });
});
