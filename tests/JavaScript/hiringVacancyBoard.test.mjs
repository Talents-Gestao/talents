import test from 'node:test';
import assert from 'node:assert/strict';
import {
    describeApplicationEvent,
    groupApplicationsByStage,
    shiftItem,
    stageDeleteBlockReason,
} from '../../resources/js/utils/hiringVacancyBoard.js';

test('agrupa candidaturas por etapa na ordem de sort_order', () => {
    const stages = [{ id: 1 }, { id: 2 }];
    const columns = groupApplicationsByStage(stages, [
        { id: 10, stage_id: 1, sort_order: 2 },
        { id: 11, stage_id: 1, sort_order: 1 },
        { id: 12, stage_id: 2, sort_order: 1 },
        { id: 13, stage_id: 99, sort_order: 1 },
    ]);
    assert.deepEqual(columns[1].map((a) => a.id), [11, 10]);
    assert.deepEqual(columns[2].map((a) => a.id), [12]);
    assert.equal(columns[99], undefined);
});

test('shiftItem move para cima e para baixo sem sair dos limites', () => {
    assert.deepEqual(shiftItem(['a', 'b', 'c'], 1, -1), ['b', 'a', 'c']);
    assert.deepEqual(shiftItem(['a', 'b', 'c'], 1, 1), ['a', 'c', 'b']);
    assert.deepEqual(shiftItem(['a', 'b', 'c'], 0, -1), ['a', 'b', 'c']);
    assert.deepEqual(shiftItem(['a', 'b', 'c'], 2, 1), ['a', 'b', 'c']);
});

test('stageDeleteBlockReason espelha as regras do servidor', () => {
    assert.match(stageDeleteBlockReason({ is_hired: false, applications_count: 0 }, 1), /pelo menos uma/);
    assert.match(stageDeleteBlockReason({ is_hired: true, applications_count: 0 }, 4), /contratado/);
    assert.match(stageDeleteBlockReason({ is_hired: false, applications_count: 2 }, 4), /Mova os candidatos/);
    assert.equal(stageDeleteBlockReason({ is_hired: false, applications_count: 0 }, 4), null);
});

test('describeApplicationEvent descreve inclusão, movimento e anotação', () => {
    assert.equal(describeApplicationEvent({ type: 'added', to_stage_name: 'Triagem' }), 'Incluído na etapa Triagem');
    assert.equal(
        describeApplicationEvent({ type: 'moved', from_stage_name: 'Triagem', to_stage_name: null }),
        'De Triagem para etapa excluída',
    );
    assert.equal(describeApplicationEvent({ type: 'note', note: 'Ligar amanhã' }), 'Ligar amanhã');
});
