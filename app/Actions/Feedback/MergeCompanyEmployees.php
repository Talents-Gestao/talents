<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Models\CompanyEmployee;
use App\Models\FeedbackAuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Une dois cadastros de colaborador da MESMA empresa: o principal fica (com a sua identidade) e o absorvido
 * passa seus vínculos para ele (feedbacks, Férias, Desligamento, Destaques do mês e Evolução) e fica inativo,
 * marcado como unido. Só o vínculo muda: o conteúdo das sessões (nome registrado, respostas, assinaturas) não é
 * tocado, nem a data de atualização. Tudo numa transação, com trilha em feedback_audit_logs. Repetir a união já
 * feita não altera nada (idempotente).
 */
class MergeCompanyEmployees
{
    /** Tabelas com vínculo ao colaborador (coluna company_employee_id). */
    public const LINKED_TABLES = [
        'feedback_sessions' => 'Feedbacks',
        'employee_leaves' => 'Férias',
        'exit_interviews' => 'Desligamentos',
        'company_monthly_highlights' => 'Destaques do mês',
        'feedback_evolution_reports' => 'Evoluções geradas',
    ];

    public const STALE_MESSAGE = 'Os dados dos cadastros mudaram desde a pré-visualização. Confira de novo antes de unir.';

    /**
     * O que será movido e os avisos (mesmo período, líderes diferentes). Não altera nada.
     *
     * @return array<string, mixed>
     */
    public function preview(CompanyEmployee $primary, CompanyEmployee $absorbed): array
    {
        $this->assertMergeable($primary, $absorbed);

        $counts = [];
        foreach (array_keys(self::LINKED_TABLES) as $table) {
            $counts[$table] = DB::table($table)->where('company_employee_id', $absorbed->id)
                ->when(in_array($table, ['feedback_sessions', 'feedback_evolution_reports'], true), fn ($q) => $q->whereNull('deleted_at'))
                ->count();
        }

        $sessionIds = DB::table('feedback_sessions')->where('company_employee_id', $absorbed->id)->whereNull('deleted_at')->pluck('id');
        $recordings = $sessionIds->isEmpty() ? 0 : DB::table('feedback_recordings')->whereIn('feedback_session_id', $sessionIds)->whereNull('deleted_at')->count();
        $reports = $sessionIds->isEmpty() ? 0 : DB::table('feedback_recording_reports')->whereIn('feedback_session_id', $sessionIds)->whereNull('deleted_at')->count();
        $signatures = $sessionIds->isEmpty() ? 0 : DB::table('feedback_session_signatures')->whereIn('feedback_session_id', $sessionIds)->count();
        $signed = $sessionIds->isEmpty() ? 0 : DB::table('feedback_session_signatures')->whereIn('feedback_session_id', $sessionIds)->whereNotNull('signed_at')->count();

        return [
            'counts' => $counts,
            'recordings' => $recordings,
            'reports' => $reports,
            'signatures' => $signatures,
            'signed_signatures' => $signed,
            'conflicts' => $this->conflicts($primary, $absorbed),
        ];
    }

    /**
     * @param  array<string, int>  $expectedCounts  contagens confirmadas na pré-visualização
     * @return array{already_merged: bool, moved: array<string, int>}
     */
    public function execute(CompanyEmployee $primary, CompanyEmployee $absorbed, User $actor, array $expectedCounts): array
    {
        return DB::transaction(function () use ($primary, $absorbed, $actor, $expectedCounts) {
            /** @var CompanyEmployee $p */
            $p = CompanyEmployee::query()->whereKey($primary->id)->lockForUpdate()->firstOrFail();
            /** @var CompanyEmployee $a */
            $a = CompanyEmployee::query()->whereKey($absorbed->id)->lockForUpdate()->firstOrFail();

            // Idempotente: a mesma união já feita não muda nada.
            if ((int) $a->merged_into_employee_id === $p->id) {
                return ['already_merged' => true, 'moved' => []];
            }

            $this->assertMergeable($p, $a);

            $current = $this->preview($p, $a)['counts'];
            foreach ($current as $table => $count) {
                if ((int) ($expectedCounts[$table] ?? -1) !== $count) {
                    throw ValidationException::withMessages(['confirmed' => self::STALE_MESSAGE]);
                }
            }

            $sessionIds = DB::table('feedback_sessions')->where('company_employee_id', $a->id)->pluck('id')->all();
            $moved = [];
            foreach (array_keys(self::LINKED_TABLES) as $table) {
                // Só o vínculo: sem tocar em updated_at nem em qualquer outra coluna.
                $moved[$table] = DB::table($table)->where('company_employee_id', $a->id)->update(['company_employee_id' => $p->id]);
            }

            $now = Carbon::now();
            $a->forceFill([
                'is_active' => false,
                'merged_into_employee_id' => $p->id,
                'merged_at' => $now,
                'merged_by_user_id' => $actor->id,
            ])->save();

            FeedbackAuditLog::query()->create([
                'company_id' => $p->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_EMPLOYEES_MERGED,
                'subject_type' => FeedbackAuditLog::SUBJECT_EMPLOYEE,
                'subject_id' => $p->id,
                'subject_label' => $p->name,
                'sessions_count' => $moved['feedback_sessions'] ?? 0,
                'details' => [
                    'primary_employee_id' => $p->id,
                    'absorbed_employee_id' => $a->id,
                    'absorbed_name' => $a->name,
                    'absorbed_email' => $a->email,
                    'moved' => $moved,
                    'feedback_session_ids' => $sessionIds,
                    'merged_at' => $now->toIso8601String(),
                ],
            ]);

            return ['already_merged' => false, 'moved' => $moved];
        });
    }

    /** @return list<array{type: string, message: string}> */
    private function conflicts(CompanyEmployee $primary, CompanyEmployee $absorbed): array
    {
        $months = fn (CompanyEmployee $e) => DB::table('feedback_sessions')
            ->where('company_employee_id', $e->id)
            ->whereNull('deleted_at')
            ->whereNotNull('scheduled_at')
            ->get(['id', 'title', 'scheduled_at'])
            ->groupBy(fn ($s) => Carbon::parse($s->scheduled_at)->timezone('America/Sao_Paulo')->format('Y-m'));

        $a = $months($primary);
        $b = $months($absorbed);
        $conflicts = [];
        foreach ($b as $month => $sessions) {
            if ($a->has($month)) {
                $label = Carbon::createFromFormat('Y-m', $month)->locale('pt_BR')->translatedFormat('F/Y');
                $conflicts[] = [
                    'type' => 'same_period',
                    'message' => "Os dois cadastros têm feedback em {$label}. Depois da união, as duas sessões ficam na mesma linha do tempo; confira se não são o mesmo feedback registrado duas vezes.",
                ];
            }
        }

        if ($primary->leader_user_id && $absorbed->leader_user_id && $primary->leader_user_id !== $absorbed->leader_user_id) {
            $conflicts[] = [
                'type' => 'different_leader',
                'message' => 'Os cadastros têm líderes diferentes. Fica o líder do cadastro principal; cada feedback mantém o líder com que foi feito.',
            ];
        }

        if (filled($primary->email) && filled($absorbed->email) && mb_strtolower((string) $primary->email) !== mb_strtolower((string) $absorbed->email)) {
            $conflicts[] = [
                'type' => 'different_email',
                'message' => 'Os cadastros têm e-mails diferentes. Fica o e-mail do cadastro principal.',
            ];
        }

        return $conflicts;
    }

    private function assertMergeable(CompanyEmployee $primary, CompanyEmployee $absorbed): void
    {
        if ($primary->company_id !== $absorbed->company_id) {
            abort(403, 'Só é possível unir cadastros da mesma empresa.');
        }
        if ($primary->id === $absorbed->id) {
            throw ValidationException::withMessages(['absorbed' => 'Escolha dois cadastros diferentes.']);
        }
        if ($primary->merged_into_employee_id !== null) {
            throw ValidationException::withMessages(['primary' => 'O cadastro principal já foi unido a outro. Escolha o cadastro que ficou.']);
        }
        if ($absorbed->merged_into_employee_id !== null) {
            throw ValidationException::withMessages(['absorbed' => 'Este cadastro já foi unido a outro.']);
        }
    }
}
