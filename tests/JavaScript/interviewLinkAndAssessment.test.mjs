import test from 'node:test';
import assert from 'node:assert/strict';
import {
    NO_APPLICATION,
    applicationLabel,
    confirmationPayload,
    confirmationText,
    isTextSource,
} from '../../resources/js/utils/interviewLink.js';
import { criteriaMissingEvidence, emptyCriterion, ratingClass } from '../../resources/js/utils/hiringAssessment.js';

const candidate = {
    id: 7,
    name: 'Ana Souza',
    applications: [{ id: 12, vacancy_title: 'Analista de RH', company_name: 'Empresa X' }],
};

test('confirmationPayload devolve candidato e vaga mostrados, ou «none» sem vaga', () => {
    assert.deepEqual(confirmationPayload(candidate, 12), { confirm_candidate_id: '7', confirm_application_id: '12' });
    assert.deepEqual(confirmationPayload(candidate, ''), { confirm_candidate_id: '7', confirm_application_id: NO_APPLICATION });
    assert.deepEqual(confirmationPayload(null, ''), { confirm_candidate_id: '', confirm_application_id: NO_APPLICATION });
});

test('confirmationText cita o candidato e a vaga por extenso', () => {
    assert.equal(confirmationText(candidate, '12'), 'Confirmo que esta entrevista é de Ana Souza, para a vaga Analista de RH · Empresa X.');
    assert.equal(confirmationText(candidate, ''), 'Confirmo que esta entrevista é de Ana Souza, sem vaga vinculada.');
    assert.equal(confirmationText(null, ''), '');
});

test('applicationLabel e isTextSource', () => {
    assert.equal(applicationLabel({ vacancy_title: 'Vaga A', company_name: null }), 'Vaga A');
    const sources = [
        { value: 'audio_whisper', is_text: false },
        { value: 'plaud', is_text: true },
    ];
    assert.equal(isTextSource(sources, 'plaud'), true);
    assert.equal(isTextSource(sources, 'audio_whisper'), false);
    assert.equal(isTextSource(sources, 'outro'), false);
});

test('critério novo começa sem evidência e avaliação sem evidência registrada é apontada', () => {
    assert.equal(emptyCriterion().avaliacao, 'sem_evidencia');
    assert.deepEqual(
        criteriaMissingEvidence([
            { criterio: 'Comunicação', evidencia: '', avaliacao: 'atende' },
            { criterio: 'Liderança', evidencia: 'Relatou equipe de 5', avaliacao: 'parcial' },
            { criterio: 'Inglês', evidencia: '', avaliacao: 'sem_evidencia' },
            { criterio: '', evidencia: '', avaliacao: 'atende' },
        ]),
        ['Comunicação'],
    );
    assert.match(ratingClass('nao_atende'), /red/);
});
