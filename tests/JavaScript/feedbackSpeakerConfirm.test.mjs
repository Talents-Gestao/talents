import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
    chooseRole,
    confirmSentence,
    displayText,
    initialRoles,
    invertRoles,
    missingRolesText,
    personLabel,
    speakerDisplay,
    swapAnnouncement,
    useQuickConfirm,
} from '../../resources/js/utils/feedbackSpeakerConfirm.js';

const people = { leader: 'Rafael Mendes (fictício)', employee: 'Bruno Siqueira (fictício)' };
const speakers = [{ label: 'Speaker 1' }, { label: 'Speaker 2' }];

describe('Confirmar falantes', () => {
    it('«Falante N» na tela e «Nome — papel» sem parênteses duplos', () => {
        assert.equal(speakerDisplay('Speaker 2'), 'Falante 2');
        assert.equal(speakerDisplay('Marcos'), 'Marcos');
        assert.equal(displayText('Speaker 1 chama Bruno pelo nome.'), 'Falante 1 chama Bruno pelo nome.');
        assert.equal(personLabel('leader', people), 'Rafael Mendes (fictício) — líder');
        assert.equal(personLabel('employee', people), 'Bruno Siqueira (fictício) — colaborador');
        assert.equal(personLabel('leader', {}), 'Líder');
        assert.equal(personLabel('other', people), 'Outra pessoa');
        assert.ok(!personLabel('leader', people).includes(') ('), 'sem parênteses duplos');
    });

    it('frase de confirmação com os nomes, ou só o papel sem nome', () => {
        const roles = { 'Speaker 1': 'leader', 'Speaker 2': 'employee' };
        assert.equal(
            confirmSentence(speakers, roles, { leader: 'Rafael Mendes', employee: 'Bruno Siqueira' }),
            'Identificamos Falante 1 como Rafael Mendes, líder, e Falante 2 como Bruno Siqueira, colaborador.',
        );
        assert.equal(confirmSentence(speakers, roles, {}), 'Identificamos Falante 1 como líder e Falante 2 como colaborador.');
    });

    it('escolha exclusiva: usar a pessoa de outro falante troca os dois; «Outra pessoa» pode repetir', () => {
        const start = { 'Speaker 1': 'leader', 'Speaker 2': 'employee' };

        const swapped = chooseRole(start, 'Speaker 2', 'leader');
        assert.deepEqual(swapped.roles, { 'Speaker 1': 'employee', 'Speaker 2': 'leader' });
        assert.equal(swapped.swappedWith, 'Speaker 1');
        assert.equal(swapAnnouncement(swapped.swappedWith, swapped.roles, people), 'Troca automática: Falante 1 agora é Bruno Siqueira (fictício) — colaborador.');

        const others = chooseRole(chooseRole(start, 'Speaker 1', 'other').roles, 'Speaker 2', 'other');
        assert.deepEqual(others.roles, { 'Speaker 1': 'other', 'Speaker 2': 'other' });
        assert.equal(others.swappedWith, null);

        // 3 falantes: o terceiro vira líder; o antigo líder fica com o papel que o terceiro tinha.
        const three = chooseRole({ A: 'leader', B: 'employee', C: 'other' }, 'C', 'leader');
        assert.deepEqual(three.roles, { A: 'other', B: 'employee', C: 'leader' });
        assert.equal(three.swappedWith, 'A');
    });

    it('inverter troca líder e colaborador e mantém outra pessoa', () => {
        assert.deepEqual(invertRoles({ A: 'leader', B: 'employee', C: 'other' }), { A: 'employee', B: 'leader', C: 'other' });
    });

    it('papel inicial: confirmado, depois a sugestão, depois a ordem', () => {
        assert.deepEqual(initialRoles(speakers, { map: { 'Speaker 1': 'employee', 'Speaker 2': 'leader' } }), { 'Speaker 1': 'employee', 'Speaker 2': 'leader' });
        assert.deepEqual(initialRoles([{ label: 'A' }, { label: 'B' }, { label: 'C' }], null), { A: 'leader', B: 'employee', C: 'other' });
    });

    it('confirmação rápida só com 2 falantes, confiança alta e sem ajuste; aviso de papel faltando', () => {
        const ai = (n, confidence) => ({ speakers: Array.from({ length: n }, (_, i) => ({ label: `S${i}` })), speaker_suggestion: { confidence } });
        assert.equal(useQuickConfirm(ai(2, 'high'), false), true);
        assert.equal(useQuickConfirm(ai(2, 'high'), true), false);
        assert.equal(useQuickConfirm(ai(2, 'low'), false), false);
        assert.equal(useQuickConfirm(ai(3, 'high'), false), false);
        assert.equal(missingRolesText({ A: 'leader', B: 'other' }), 'Falta indicar um colaborador.');
        assert.equal(missingRolesText({ A: 'leader', B: 'employee' }), '');
    });
});
